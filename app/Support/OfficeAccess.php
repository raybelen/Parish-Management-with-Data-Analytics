<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OfficeAccess
{
    public const SUPER_ADMIN_ABILITIES = ['settings.manage', 'audit.view', 'payments.approve'];

    /** @return list<string> */
    public static function configurableAbilities(): array
    {
        return array_values(array_diff(config('office.permissions.admin'), self::SUPER_ADMIN_ABILITIES));
    }

    public static function allows(User $user, string $ability): bool
    {
        if ($ability === 'forecasts.manage' && ! config('office.analytics_enabled')) {
            return false;
        }
        if ($user->role === User::ROLE_SUPER_ADMIN) {
            return true;
        }
        if (in_array($ability, self::SUPER_ADMIN_ABILITIES, true)) {
            return false;
        }
        if ($user->role !== User::ROLE_ADMIN) {
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
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function appointment(User $user, Appointment $appointment): void
    {
        abort_unless(self::appointments($user)->whereKey($appointment->id)->exists(), 404);
    }

    public static function schedules(User $user): Builder
    {
        return Schedule::query()->where('source', OfficeSettings::source())
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }
}
