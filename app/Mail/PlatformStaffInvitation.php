<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformStaffInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $firmName,
        public readonly string $activationUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->firmName.' ERP account invitation');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.platform-staff-invitation');
    }
}
