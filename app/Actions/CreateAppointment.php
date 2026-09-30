<?php

namespace App\Actions;

use App\Models\Appointment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreateAppointment
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function handle(array $validated): Appointment
    {
        $disk = (string) config('appointments.document_disk', 'local');
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($validated, $disk, &$storedPaths): Appointment {
                $appointment = Appointment::create([
                    ...Arr::only($validated, [
                        'client_full_name',
                        'client_contact_number',
                        'client_email',
                        'client_address',
                        'preferred_contact_method',
                        'service_type',
                        'preferred_date',
                        'preferred_time',
                        'preferred_church',
                        'additional_notes',
                    ]),
                    'client_contact_key' => preg_replace('/\D+/', '', $validated['client_contact_number']) ?? '',
                    'service_details' => $validated['service'],
                    'documents' => [],
                ]);

                $documents = [];

                foreach ($validated['documents'] ?? [] as $documentType => $files) {
                    foreach ($files as $file) {
                        if (! $file instanceof UploadedFile) {
                            continue;
                        }

                        $path = $file->store('appointments/'.$appointment->reference_number, $disk);

                        if ($path === false) {
                            throw new RuntimeException('The appointment document could not be stored.');
                        }

                        $storedPaths[] = $path;
                        $documents[$documentType][] = [
                            'label' => config("appointments.services.{$appointment->service_type}.documents.{$documentType}.label", Str::headline($documentType)),
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }

                $appointment->update(['documents' => $documents]);

                return $appointment->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storedPaths);

            throw $exception;
        }
    }
}
