<?php

namespace App\Console\Commands;

use App\Actions\CreateOfficeBackup;
use App\Models\ParishSetting;
use App\Models\ServiceType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RestoreOfficeBackup extends Command
{
    protected $signature = 'office:restore {path : Encrypted backup path on private local storage}';

    protected $description = 'Restore into an empty, migrated recovery database using the original encryption key';

    public function handle(): int
    {
        if (! app()->environment('recovery') || ! $this->isEmptyRecoveryDatabase()) {
            $this->error('Restore requires APP_ENV=recovery and an empty migrated database. Existing records are never overwritten.');

            return self::FAILURE;
        }
        $path = (string) $this->argument('path');
        if (! str_starts_with($path, 'office-backups/') || str_contains($path, '..')) {
            $this->error('Choose a backup inside private office-backups storage.');

            return self::FAILURE;
        }
        try {
            $payload = json_decode(Crypt::decryptString(Storage::disk('local')->get($path)), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $this->error('Backup could not be read or decrypted. Use the original application encryption key.');

            return self::FAILURE;
        }
        if (($payload['version'] ?? null) !== 1 || ! is_array($payload['tables'] ?? null)
            || array_diff(array_keys($payload['tables']), CreateOfficeBackup::TABLES)
            || array_diff(array_diff(CreateOfficeBackup::TABLES, ['schedules']), array_keys($payload['tables']))
            || ! is_array($payload['documents'] ?? null)) {
            $this->error('Unsupported backup format.');

            return self::FAILURE;
        }
        foreach ($payload['tables'] as $rows) {
            if (! is_array($rows) || ! array_is_list($rows) || array_filter($rows, fn ($row) => ! is_array($row))) {
                $this->error('Invalid table data in backup.');

                return self::FAILURE;
            }
        }
        $disk = Storage::disk(config('appointments.document_disk'));
        foreach ($payload['documents'] as $documentPath => $contents) {
            if (! preg_match('#\Aappointments/[A-Za-z0-9_-]+/[A-Za-z0-9_.-]+\z#', $documentPath)
                || str_contains($documentPath, '..') || ! is_string($contents) || base64_decode($contents, true) === false) {
                $this->error('Invalid document in backup.');

                return self::FAILURE;
            }
            if ($disk->exists($documentPath)) {
                $this->error('Recovery storage already contains a document from this backup. Select empty recovery storage.');

                return self::FAILURE;
            }
        }
        $restoredPaths = [];
        try {
            DB::transaction(function () use ($payload, $disk, &$restoredPaths): void {
                foreach (array_reverse(CreateOfficeBackup::TABLES) as $table) {
                    DB::table($table)->delete();
                }
                foreach (CreateOfficeBackup::TABLES as $table) {
                    foreach (array_chunk($payload['tables'][$table] ?? [], 100) as $rows) {
                        DB::table($table)->insert($rows);
                    }
                    if (DB::getDriverName() === 'pgsql') {
                        DB::select("SELECT setval(pg_get_serial_sequence(?, 'id'), COALESCE((SELECT MAX(id) FROM ".$table.'), 1), EXISTS(SELECT 1 FROM '.$table.'))', [$table]);
                    }
                }
                foreach ($payload['documents'] as $documentPath => $contents) {
                    $restoredPaths[] = $documentPath;
                    if (! $disk->put($documentPath, base64_decode($contents, true))) {
                        throw new \RuntimeException('A document could not be restored; the database transaction was rolled back.');
                    }
                }
            });
        } catch (\Throwable $exception) {
            $disk->delete($restoredPaths);
            report($exception);
            $this->error('Restore failed. Database changes were rolled back and restored files were removed.');

            return self::FAILURE;
        }
        $this->info('Office data restored. Verify the recovered records and documents before changing application traffic.');

        return self::SUCCESS;
    }

    private function isEmptyRecoveryDatabase(): bool
    {
        foreach (array_diff(CreateOfficeBackup::TABLES, ['service_types', 'parish_settings']) as $table) {
            if (DB::table($table)->exists()) {
                return false;
            }
        }
        foreach (ParishSetting::all() as $setting) {
            if ($setting->key !== 'office' || $setting->value !== []) {
                return false;
            }
        }
        foreach (ServiceType::all() as $service) {
            $default = config('appointments.services.'.$service->slug);
            if (! $default || $service->name !== $default['label'] || $service->requirements !== $default['documents']
                || $service->fee_cents !== 0 || ! $service->active || $service->buffer_minutes !== 30
                || $service->duration_minutes !== 60) {
                return false;
            }
        }

        return true;
    }
}
