<?php

namespace App\Console\Commands;

use App\Actions\NotifyFacilityBookingClient;
use App\Models\FacilityBooking;
use App\Support\OfficeAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('facilities:expire-pending')]
#[Description('Release pending facility requests after their approval deadline')]
class ExpireFacilityBookings extends Command
{
    public function handle(NotifyFacilityBookingClient $notifyClient): int
    {
        $expired = 0;
        FacilityBooking::query()->with('facility')->where('status', 'pending')
            ->whereNull('cancelled_at')->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->lazyById(100)->each(function (FacilityBooking $booking) use ($notifyClient, &$expired): void {
                $expiredBooking = DB::transaction(function () use ($booking): ?FacilityBooking {
                    $locked = FacilityBooking::query()->lockForUpdate()->findOrFail($booking->id);
                    if ($locked->status !== 'pending' || $locked->cancelled_at !== null || $locked->expires_at?->isFuture()) {
                        return null;
                    }
                    $before = $locked->toArray();
                    $reason = 'Staff approval deadline passed 24 hours before the requested schedule.';
                    $locked->update(['status' => 'expired', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
                    OfficeAudit::record('facility.reservation.expired', $locked, $before, $locked->toArray());

                    return $locked->load('facility');
                });
                if ($expiredBooking !== null) {
                    $notifyClient->handle($expiredBooking, 'expired');
                    $expired++;
                }
            });
        $this->info($expired.' facility request(s) expired.');

        return self::SUCCESS;
    }
}
