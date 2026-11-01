<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One-time account activation link for a newly enrolled team member.
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $member,
        public string $firmName,
        public string $firmSlug,
        public string $activationUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = 'Activate Your ' . $this->firmName . ' ERP Account';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            with: [
                'memberName'      => $this->member->name,
                'firmName'        => $this->firmName,
                'firmSlug'        => $this->firmSlug,
                'roleLabel'       => match ($this->member->role) {
                    'partner' => 'Partner / Principal',
                    'manager' => 'Audit Manager',
                    default   => 'Articled Student',
                },
                'partnerUnit'     => $this->member->partner_unit ?? 'Partner Unit 01',
                'userId'          => $this->member->student_code ?: ('ID-' . $this->member->id),
                'email'           => $this->member->email,
                'activationUrl'   => $this->activationUrl,
            ],
        );
    }

}
