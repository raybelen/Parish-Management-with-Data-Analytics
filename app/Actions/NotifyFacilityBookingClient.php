<?php

namespace App\Actions;

use App\Mail\FacilityBookingUpdate;
use App\Models\FacilityBooking;
use Illuminate\Support\Facades\Mail;

class NotifyFacilityBookingClient
{
    public function handle(FacilityBooking $booking, string $event): void
    {
        if ($booking->contact_email === null || $booking->facility?->source !== 'operational') {
            return;
        }

        Mail::to($booking->contact_email)->queue(new FacilityBookingUpdate($booking, $event));
    }
}
