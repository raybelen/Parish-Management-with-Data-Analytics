<?php

namespace App\Mail;

use App\Models\FacilityBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class FacilityBookingUpdate extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /** @var array{organizer: string, facility: string, reference: string, schedule: string, status: string, reason: ?string} */
    public array $details;

    public string $accessUrl;

    public function __construct(FacilityBooking $booking, public string $event)
    {
        $timezone = config('office.timezone');
        $this->details = [
            'organizer' => (string) $booking->organizer,
            'facility' => $booking->facility->name,
            'reference' => (string) $booking->reference,
            'schedule' => $booking->starts_at->timezone($timezone)->format('F j, Y \a\t g:i A').'–'.$booking->ends_at->timezone($timezone)->format('g:i A'),
            'status' => $booking->status,
            'reason' => $booking->cancellation_reason,
        ];
        $route = $event === 'verification' ? 'facilities.bookings.verify' : 'facilities.bookings.show';
        $expiresAt = $event === 'verification'
            ? now()->addMinutes(config('facilities.verification_minutes'))
            : now()->addDays(config('facilities.access_days'));
        $this->accessUrl = URL::temporarySignedRoute($route, $expiresAt, ['booking' => $booking->reference]);
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->heading().': '.$this->details['reference'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.facilities.update',
            with: ['heading' => $this->heading()],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    private function heading(): string
    {
        return match ($this->event) {
            'verification' => 'Verify your facility request',
            'access' => 'Access your facility request',
            'approved' => 'Facility request approved',
            'cancelled' => 'Facility booking cancelled',
            'expired' => 'Facility request expired',
        };
    }
}
