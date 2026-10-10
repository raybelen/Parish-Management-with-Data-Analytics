<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SecurityAudit
{
    public static function record(string $event, ?User $subject = null, ?string $previousRole = null): void
    {
        Log::channel('security')->info($event, [
            'actor_id' => auth()->id(),
            'subject_id' => $subject?->getKey(),
            'role' => $subject?->role,
            'previous_role' => $previousRole,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
        if (Schema::hasTable('audit_logs')) {
            OfficeAudit::record($event, $subject, ['role' => $previousRole], ['role' => $subject?->role]);
        }
    }
}
