<?php

namespace App\Models;

use Database\Factories\AppointmentParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentParticipant extends Model
{
    /** @use HasFactory<AppointmentParticipantFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'role', 'full_name', 'date_of_birth'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }
}
