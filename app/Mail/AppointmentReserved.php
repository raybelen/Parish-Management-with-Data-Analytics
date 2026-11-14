<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\AppointmentInvoice;
use App\Support\FinancialDocumentSeal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReserved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ?AppointmentInvoice $invoice = null;

    public function __construct(public Appointment $appointment, ?AppointmentInvoice $invoice = null)
    {
        $this->invoice = $invoice;
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->appointment->requiresOfficeApproval() ? 'Service request awaiting approval: ' : 'Appointment reserved: ').$this->appointment->reference_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        if ($this->invoice?->integrity_seal !== null && ! FinancialDocumentSeal::valid($this->invoice)) {
            throw new \LogicException('Invoice integrity check failed. Email delivery stopped.');
        }

        return new Content(
            markdown: 'mail.appointments.reserved',
            with: [
                'manageUrl' => route('appointments.lookup.create'),
            ],
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
}
