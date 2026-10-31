<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment, public ?string $reason = null)
    {
        $this->appointment->loadMissing(['doctor.specialty', 'patient.user']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Appointment Update ({$this->appointment->status->value}): {$this->appointment->reference_code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-cancelled',
        );
    }
}

