<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

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
    }
}
