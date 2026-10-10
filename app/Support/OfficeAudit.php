<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\OfficeNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OfficeAudit
{
    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     */
    public static function record(string $action, ?Model $entity = null, array $before = [], array $after = [], ?string $reason = null): void
    {
        AuditLog::create([
            'actor_id' => auth()->id(), 'action' => $action,
            'entity_type' => $entity ? $entity::class : null, 'entity_id' => $entity?->getKey(),
            'before' => $before, 'after' => $after, 'reason' => $reason,
        ]);
    }

    public static function notify(Appointment $appointment, string $event, string $message, ?string $key = null): void
    {
        OfficeNotification::firstOrCreate(
            ['deduplication_key' => $key ?? (string) Str::uuid()],
            ['appointment_id' => $appointment->id, 'event' => $event, 'message' => $message],
        );
    }
}
