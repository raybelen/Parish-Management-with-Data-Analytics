<?php

namespace App\Console\Commands;

use App\Actions\ExpirePendingAppointment;
use App\Models\Appointment;
use App\Support\OfficeSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('appointments:expire-unverified')]
#[Description('Release pending appointment slots when their document cutoff or scheduled start has passed')]
class ExpireUnverifiedAppointments extends Command
{
    public function handle(ExpirePendingAppointment $expirePendingAppointment): int
    {
        $expired = 0;

        Appointment::query()
            ->where('source', OfficeSettings::source())
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->where('documents_due_at', '<=', now())
                    ->orWhere('starts_at', '<=', now());
            })
            ->lazyById(250)
            ->each(function (Appointment $appointment) use ($expirePendingAppointment, &$expired): void {
                DB::transaction(function () use ($appointment, $expirePendingAppointment, &$expired): void {
                    $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
                    if ($expirePendingAppointment->handle($locked) !== null) {
                        $expired++;
                    }
                });
            });

        $this->info($expired.' unverified appointment reservation(s) expired.');

        return self::SUCCESS;
    }
}
