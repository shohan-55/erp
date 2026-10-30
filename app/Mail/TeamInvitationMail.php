<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation email for a newly enrolled team member.
 *
 * Audit finding P1-3.1: invitations were never sent at all — the Partner had to
 * read a temporary password off the screen and pass it on by hand, and the
 * invitation_token column was never used to reach the person it belonged to.
 *
 * The link carries the token only. The activation page re-validates the token
 * server-side, so the email is never a credential by itself.
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $member,
        public string $firmName,
        public string $firmSlug,
        public ?string $plainPassword = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to ' . $this->firmName . ' on DhakaWala ERP',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
            with: [
                'memberName'    => $this->member->name,
                'firmName'      => $this->firmName,
                'firmSlug'      => $this->firmSlug,
                'roleLabel'     => match ($this->member->role) {
                    'partner' => 'Partner / Principal',
                    'manager' => 'Audit Manager',
                    default   => 'Articled Student',
                },
                'email'         => $this->member->email,
                'plainPassword' => $this->plainPassword,
                'activationUrl' => $this->activationUrl(),
                'expiresInDays' => 7,
            ],
        );
    }

    protected function activationUrl(): string
    {
        try {
            return route('firm.activate', [
                'firmSlug' => $this->firmSlug,
                'token'    => $this->member->invitation_token,
            ]);
        } catch (\Throwable) {
            return url('/c/' . $this->firmSlug . '/activate?token=' . urlencode((string) $this->member->invitation_token));
        }
    }
}
