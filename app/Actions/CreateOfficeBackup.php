<?php

namespace App\Actions;

use App\Models\Appointment;
use App\Support\OfficeAudit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateOfficeBackup
{
    public const TABLES = ['users', 'service_types', 'personnel', 'parish_settings', 'appointments', 'appointment_participants', 'payments', 'service_records', 'audit_logs', 'forecast_runs', 'office_notifications', 'schedule_blocks', 'schedules'];

    public function handle(): string
    {
        $payload = DB::transaction(function (): array {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $tables = [];
            foreach (self::TABLES as $table) {
                if (Schema::hasTable($table)) {
                    $tables[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
                }
            }
            $documents = [];
            foreach (Appointment::query()->select(['id', 'reference_number', 'documents'])->lazyById(100) as $appointment) {
                foreach ($appointment->documents ?? [] as $files) {
                    foreach ($files as $file) {
                        if (! str_starts_with($file['path'], 'appointments/'.$appointment->reference_number.'/') || str_contains($file['path'], '..')) {
                            throw new \RuntimeException('An invalid document path prevented backup creation.');
                        }
                        $documents[$file['path']] = base64_encode(Storage::disk(config('appointments.document_disk'))->get($file['path']));
                    }
                }
            }

            return ['version' => 1, 'created_at' => now()->toIso8601String(), 'tables' => $tables, 'documents' => $documents];
        });
        $path = 'office-backups/'.now()->format('Ymd-His').'-'.Str::random(8).'.json.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)));
        if (Schema::hasTable('audit_logs')) {
            OfficeAudit::record('backup.created', null, [], ['filename' => basename($path), 'table_count' => count($payload['tables'])]);
        }

        return $path;
    }
}
