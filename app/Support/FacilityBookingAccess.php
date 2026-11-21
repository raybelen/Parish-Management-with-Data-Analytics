<?php

namespace App\Support;

use App\Models\FacilityBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FacilityBookingAccess
{
    private const SESSION_KEY = 'facility_booking_access_tokens';

    public function issue(Request $request, FacilityBooking $booking): string
    {
        $token = Str::random(64);
        $tokens = $request->session()->get(self::SESSION_KEY, []);
        $tokens = is_array($tokens) ? $tokens : [];
        $tokens[$token] = $booking->getKey();
        $request->session()->put(self::SESSION_KEY, array_slice($tokens, -20, null, true));

        return URL::temporarySignedRoute(
            'facilities.bookings.show',
            now()->addMinutes(30),
            ['booking' => $booking->reference, 'access_token' => $token],
        );
    }

    public function consume(Request $request, FacilityBooking $booking): bool
    {
        $token = $request->string('access_token')->toString();
        $tokens = $request->session()->get(self::SESSION_KEY, []);
        $tokens = is_array($tokens) ? $tokens : [];
        $bookingId = $tokens[$token] ?? null;

        unset($tokens[$token]);
        $request->session()->put(self::SESSION_KEY, $tokens);

        return $token !== '' && $bookingId === $booking->getKey();
    }
}
