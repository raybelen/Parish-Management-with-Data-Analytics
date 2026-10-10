<?php

namespace App\Support;

use App\Models\Appointment;

class AppointmentParticipants
{
    public static function sync(Appointment $appointment): void
    {
        $fields = match ($appointment->service_type) {
            'baptism' => ['child' => 'child_full_name', 'father' => 'father_name', 'mother' => 'mother_name', 'godfather' => 'godfather_name', 'godmother' => 'godmother_name'],
            'wedding' => ['bride' => 'bride_full_name', 'groom' => 'groom_full_name'],
            'funeral' => ['deceased' => 'deceased_full_name'],
            default => ['recipient' => 'recipient_full_name'],
        };
        foreach ($fields as $role => $field) {
            if (! empty($appointment->service_details[$field])) {
                $appointment->participants()->updateOrCreate(['role' => $role], [
                    'full_name' => $appointment->service_details[$field],
                    'date_of_birth' => match ($role) {
                        'child' => $appointment->service_details['child_date_of_birth'] ?? null,
                        'deceased' => $appointment->service_details['date_of_birth'] ?? null,
                        default => null,
                    },
                ]);
            }
        }
    }
}
