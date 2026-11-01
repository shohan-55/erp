<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Direct login credentials & password reset notification email for team members.
 *
 * Delivers the workspace login link, username (email / user ID), and assigned
 * or newly reset password directly to the member's mailbox without requiring
 * a separate activation token step.
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $member,
        public string $firmName,
        public string $firmSlug,
        public ?string $plainPassword = null,
        public bool $isPasswordReset = false,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->isPasswordReset
            ? 'Your Password Has Been Reset — ' . $this->firmName . ' (DhakaWala ERP)'
            : 'Your Account Login Credentials for ' . $this->firmName . ' — DhakaWala ERP';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $loginUrl = $this->loginUrl();

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
                'plainPassword'   => $this->plainPassword,
                'loginUrl'        => $loginUrl,
                'activationUrl'   => $loginUrl,
                'isPasswordReset' => $this->isPasswordReset,
            ],
        );
    }

    protected function loginUrl(): string
    {
        try {
            $firm = $this->member->firm;
            if ($firm && ! empty($firm->custom_domain)) {
                return 'https://' . $firm->custom_domain . '/c/' . $this->firmSlug . '/login';
            }
            if ($firm && ! empty($firm->custom_subdomain)) {
                return 'https://' . $firm->custom_subdomain . '.dhakawala.com/c/' . $this->firmSlug . '/login';
            }

            return route('firm.login', [
                'firmSlug' => $this->firmSlug,
            ]);
        } catch (\Throwable) {
            return url('/c/' . $this->firmSlug . '/login');
        }
    }
}
