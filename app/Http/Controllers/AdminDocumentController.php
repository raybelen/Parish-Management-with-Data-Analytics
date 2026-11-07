<?php

namespace App\Http\Controllers;

use App\Actions\ExpirePendingAppointment;
use App\Actions\NotifyAppointmentClient;
use App\Mail\AppointmentConfirmed;
use App\Models\Appointment;
use App\Support\OfficeAccess;
use App\Support\OfficeAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDocumentController extends Controller
{
    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('documents.manage');
        OfficeAccess::appointment($request->user(), $appointment);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($appointment->documentRequirements()))],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'version' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000'],
        ]);
        $disk = Storage::disk(config('appointments.document_disk'));
        $storedPaths = [];
        try {
            DB::transaction(function () use ($appointment, $data, $disk, &$storedPaths): void {
                $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
                abort_unless($locked->version === (int) $data['version'], 409, 'Refresh this appointment before uploading.');
                abort_unless(in_array($locked->status, ['pending', 'confirmed'], true), 409, 'Finalized documents are preserved.');
                $documents = $locked->documents ?? [];
                if (count($documents[$data['type']] ?? []) + count($data['files']) > 5) {
                    throw ValidationException::withMessages(['files' => 'A requirement may contain at most five files.']);
                }
                foreach ($data['files'] as $file) {
                    $path = $disk->putFile('appointments/'.$locked->reference_number, $file);
                    if ($path === false) {
                        throw new \RuntimeException('The appointment document could not be stored.');
                    }
                    $storedPaths[] = $path;
                    $documents[$data['type']][] = [
                        'label' => $locked->documentRequirements()[$data['type']]['label'],
                        'original_name' => $file->getClientOriginalName(), 'path' => $path,
                        'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                        'uploaded_at' => now()->toIso8601String(), 'uploaded_by' => auth()->id(),
                    ];
                }
                $verification = $locked->online_verification ?? [];
                unset($verification[$data['type']]);
                $locked->update(['documents' => $documents, 'online_verification' => $verification, 'version' => $locked->version + 1]);
                OfficeAudit::record('document.uploaded', $locked, [], ['type' => $data['type'], 'file_count' => count($storedPaths)], $data['reason']);
            });
        } catch (\Throwable $exception) {
            $disk->delete($storedPaths);
            throw $exception;
        }

        return back()->with('status', 'Documents uploaded privately. Physical copies still require office verification.');
    }

    public function show(Request $request, Appointment $appointment, string $type, int $index): StreamedResponse
    {
        Gate::authorize('documents.manage');
        OfficeAccess::appointment($request->user(), $appointment);
        $file = $appointment->documents[$type][$index] ?? null;
        abort_unless($file && str_starts_with($file['path'], 'appointments/'.$appointment->reference_number.'/') && ! str_contains($file['path'], '..'), 404);
        OfficeAudit::record('document.accessed', $appointment, [], ['type' => $type, 'index' => $index]);

        return Storage::disk(config('appointments.document_disk'))->download($file['path'], basename($file['original_name']), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function update(Request $request, Appointment $appointment, ExpirePendingAppointment $expirePendingAppointment, NotifyAppointmentClient $notifyClient): RedirectResponse|JsonResponse
    {
        Gate::authorize('documents.manage');
        OfficeAccess::appointment($request->user(), $appointment);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($appointment->documentRequirements()))],
            'decision' => ['required', Rule::in(['verified', 'declined'])],
            'version' => ['required', 'integer'],
        ], [
            'decision.required' => 'Choose Verified or Decline.',
            'decision.in' => 'Choose Verified or Decline.',
        ]);
        $result = DB::transaction(function () use ($appointment, $data, $expirePendingAppointment, $notifyClient): string {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($locked->version === (int) $data['version'], 409, 'Refresh this appointment before saving.');
            abort_unless(in_array($locked->status, ['pending', 'confirmed'], true), 409, 'Finalized requirements are preserved.');

            if ($locked->status === 'pending' && ($expirationReason = $expirePendingAppointment->handle($locked)) !== null) {
                return 'expired_'.$expirationReason;
            }

            $before = $locked->physical_documents ?? [];
            $isVerified = $data['decision'] === 'verified';
            $locked->physical_documents = array_replace($before, [$data['type'] => [
                'verified' => $isVerified,
                'user_id' => auth()->id(),
                'verified_at' => now()->toIso8601String(),
            ]]);
            $previousStatus = $locked->status;
            if (! $isVerified) {
                $locked->status = 'declined';
                $locked->outcome = 'A submitted physical document was declined by the parish office.';
            } elseif ($locked->status === 'pending' && $locked->physicalDocumentsComplete()) {
                $locked->status = 'confirmed';
                $locked->outcome = null;
            }
            $locked->version++;
            $locked->save();
            OfficeAudit::record(
                $isVerified ? 'document.verified' : 'document.declined',
                $locked,
                ['physical_documents' => $before],
                ['physical_documents' => $locked->physical_documents],
                $isVerified ? 'Physical copy verified' : 'Physical copy declined',
            );
            if (! $isVerified) {
                OfficeAudit::record('appointment.declined', $locked, ['status' => $previousStatus], ['status' => 'declined'], $locked->outcome);
                OfficeAudit::notify($locked, 'declined', 'Document declined: '.$locked->reference_number);
                $notifyClient->handle($locked, 'declined');
            } elseif ($previousStatus === 'pending' && $locked->status === 'confirmed') {
                OfficeAudit::record('appointment.confirmed', $locked, ['status' => 'pending'], ['status' => 'confirmed'], 'All required physical documents verified');
                OfficeAudit::notify($locked, 'confirmed', 'Documents verified and appointment confirmed: '.$locked->reference_number);
                if ($locked->wantsEmailNotifications()) {
                    Mail::to($locked->client_email)->queue(
                        (new AppointmentConfirmed($locked))->afterCommit(),
                    );
                }
            }

            return match (true) {
                ! $isVerified => 'declined',
                $previousStatus === 'pending' && $locked->status === 'confirmed' => 'confirmed',
                default => 'verified',
            };
        });

        $message = match ($result) {
            'declined' => 'Document declined. The reserved slot was released and will not appear on parish calendars.',
            'expired_scheduled_start' => 'The scheduled appointment time has passed. The unverified reservation expired and its slot was released.',
            'expired_document_deadline' => 'The three-day document deadline has passed. The reserved slot was released.',
            'confirmed' => 'All required physical documents are verified. The appointment is confirmed and now appears on the public parish calendar.',
            default => 'Physical document verified. Verify the remaining required documents to confirm the appointment.',
        };

        if (! $request->expectsJson()) {
            return back()->with('status', $message);
        }

        $appointment->refresh();

        return response()->json([
            'result' => $result,
            'message' => $message,
            'appointment_status' => $appointment->status,
            'status_summary' => match ($appointment->status) {
                'pending' => 'The slot is reserved for three days and visible to staff while the physical documents are verified. It is not yet on the public calendar.',
                'confirmed' => 'All required physical documents are verified. The appointment is confirmed and visible on both parish calendars.',
                'completed' => 'This service is complete. You can review its details and history below.',
                default => 'This appointment is closed. Its details and history are kept for your records.',
            },
            'version' => $appointment->version,
            'document' => [
                'type' => $data['type'],
                'verified' => (bool) ($appointment->physical_documents[$data['type']]['verified'] ?? false),
            ],
            'status_badge_html' => view('admin.appointments.partials.status-badge', ['status' => $appointment->status])->render(),
            'status_section_html' => view('admin.appointments.partials.status-section', ['appointment' => $appointment])->render(),
        ]);
    }
}
