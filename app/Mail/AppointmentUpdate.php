<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AppointmentUpdate extends Mailable implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /** @var array{name: string, reference: string, service: string, schedule: ?string, church: string, status: string, deadline: ?string} */
    public array $details;

    public function __construct(Appointment $appointment, public string $event)
    {
        $timezone = config('office.timezone');
        $this->details = [
            'name' => $appointment->client_full_name,
            'reference' => $appointment->reference_number,
            'service' => (string) str($appointment->service_type)->headline(),
            'schedule' => $appointment->starts_at?->timezone($timezone)->format('F j, Y \a\t g:i A'),
            'church' => $appointment->confirmed_church ?? $appointment->preferred_church,
            'status' => $appointment->status,
            'deadline' => $appointment->documentVerificationDeadline()?->timezone($timezone)->format('F j, Y \a\t g:i A'),
        ];
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading().': '.$this->details['reference']);
    }

    public function heading(): string
    {
        return match ($this->event) {
            'upcoming' => 'Upcoming appointment reminder',
            'documents' => 'Appointment document deadline reminder',
            'rescheduled' => 'Appointment schedule updated',
            'cancelled' => 'Appointment cancelled',
            'declined' => 'Appointment declined',
            'expired' => 'Appointment reservation expired',
        };
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.appointments.update', with: [
            'heading' => $this->heading(),
            'manageUrl' => route('appointments.lookup.create'),
        ]);
    }
}
