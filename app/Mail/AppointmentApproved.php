<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Services\IcsGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
        $this->appointment->loadMissing(['doctor.specialty', 'patient.user']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Appointment Confirmed: {$this->appointment->reference_code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-approved',
        );
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $ics = IcsGenerator::generate($this->appointment);

        return [
            Attachment::fromData(fn (): string => $ics, "{$this->appointment->reference_code}.ics")
                ->withMime('text/calendar'),
        ];
    }
}
