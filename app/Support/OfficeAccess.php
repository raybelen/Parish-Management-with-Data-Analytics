<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OfficeAccess
{
    public static function allows(User $user, string $ability): bool
    {
        if ($ability === 'forecasts.manage' && ! config('office.analytics_enabled')) {
            return false;
        }
        if ($user->role === User::ROLE_SUPER_ADMIN) {
            return true;
        }
        if (! in_array($user->role, ['admin', 'staff', 'priest'], true)) {
            return false;
        }

        $settings = OfficeSettings::all();
        $permissions = $settings['permissions'][$user->role] ?? config('office.permissions.'.$user->role, []);

        return in_array($ability, $permissions, true)
            && ($ability !== 'forecasts.manage' || in_array('finance.view', $permissions, true));
    }

    public static function appointments(User $user): Builder
    {
        return Appointment::query()->where('source', OfficeSettings::source())
            ->when($user->role === 'priest', fn (Builder $query) => $query->whereHas('personnel', fn (Builder $personnel) => $personnel->where('user_id', $user->id)));
    }

    public static function appointment(User $user, Appointment $appointment): void
    {
        abort_unless(self::appointments($user)->whereKey($appointment->id)->exists(), 404);
    }

    public static function schedules(User $user): Builder
    {
        return Schedule::query()->where('source', OfficeSettings::source())
            ->when($user->role === 'priest', fn (Builder $query) => $query->whereHas('personnel', fn (Builder $personnel) => $personnel->where('user_id', $user->id)));
    }
}
