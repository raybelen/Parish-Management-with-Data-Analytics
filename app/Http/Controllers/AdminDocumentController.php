<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Support\OfficeAccess;
use App\Support\OfficeAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('documents.manage');
        OfficeAccess::appointment($request->user(), $appointment);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($appointment->documentRequirements()))],
            'channel' => ['required', Rule::in(['physical', 'online'])], 'verified' => ['required', 'boolean'],
            'version' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($appointment, $data): void {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($locked->version === (int) $data['version'], 409, 'Refresh this appointment before saving.');
            abort_unless(in_array($locked->status, ['pending', 'confirmed'], true), 409, 'Finalized requirements are preserved.');
            if ($data['channel'] === 'online') {
                abort_unless(! empty($locked->documents[$data['type']]), 422, 'No uploaded file exists for this requirement.');
            }
            $field = $data['channel'] === 'physical' ? 'physical_documents' : 'online_verification';
            $before = $locked->$field ?? [];
            $locked->$field = array_replace($before, [$data['type'] => ['verified' => (bool) $data['verified'], 'user_id' => auth()->id(), 'verified_at' => now()->toIso8601String()]]);
            if ($locked->status === 'confirmed' && ! $locked->physicalDocumentsComplete()) {
                throw ValidationException::withMessages(['verified' => 'A confirmed appointment must keep its required physical documents verified.']);
            }
            $locked->version++;
            $locked->save();
            OfficeAudit::record('document.verified', $locked, [$field => $before], [$field => $locked->$field], $data['reason']);
        });

        return back()->with('status', 'Document verification updated.');
    }
}
