<?php

namespace App\Observers;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class UserObserver implements ShouldHandleEventsAfterCommit
{
    public function created(User $user): void
    {
        SecurityAudit::record('user.created', $user);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('password')) {
            SecurityAudit::record('user.password_changed', $user);
        }
        if ($user->wasChanged('role')) {
            SecurityAudit::record('user.role_changed', $user, $user->getPrevious()['role'] ?? null);
        }
    }

    public function deleted(User $user): void
    {
        SecurityAudit::record('user.deleted', $user);
    }
}
