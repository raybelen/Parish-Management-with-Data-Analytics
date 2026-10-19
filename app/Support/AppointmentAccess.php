<?php

namespace App\Support;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AppointmentAccess
{
    private const SESSION_KEY = 'appointment_access_tokens';

    public function issue(Request $request, Appointment $appointment): string
    {
        $token = Str::random(64);
        $tokens = $request->session()->get(self::SESSION_KEY, []);
        $tokens = is_array($tokens) ? $tokens : [];
        $tokens[$token] = $appointment->getKey();
        $request->session()->put(self::SESSION_KEY, array_slice($tokens, -20, null, true));

        return URL::temporarySignedRoute(
            'appointments.show',
            now()->addMinutes(30),
            ['appointment' => $appointment, 'access_token' => $token],
        );
    }

    public function consume(Request $request, Appointment $appointment): bool
    {
        $token = $request->string('access_token')->toString();
        $tokens = $request->session()->get(self::SESSION_KEY, []);
        $tokens = is_array($tokens) ? $tokens : [];
        $appointmentId = $tokens[$token] ?? null;

        unset($tokens[$token]);
        $request->session()->put(self::SESSION_KEY, $tokens);

        return $token !== '' && $appointmentId === $appointment->getKey();
    }
}
