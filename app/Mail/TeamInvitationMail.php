<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Direct login credentials notification email for practice & partner accounts.
 *
 * Delivers the direct workspace login URL (e.g. https://erp.dhakawala.com),
 * username (email), and password without requiring a separate activation link.
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
        public ?string $invitationToken = null,
    ) {
        // Keep $this->plainPassword intact so the recipient receives their login credentials directly.
    }

    public function envelope(): Envelope
    {
        $subject = $this->isPasswordReset
            ? 'Your Updated Login Credentials — ' . $this->firmName . ' (DhakaWala ERP)'
            : 'Your Login Credentials for ' . $this->firmName . ' — DhakaWala ERP';

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
                'roleLabel'       => method_exists($this->member, 'displayDesignation')
                    ? $this->member->displayDesignation()
                    : match ($this->member->role) {
                        'partner' => 'Partner / Principal',
                        'manager' => 'Audit Manager',
                        default   => 'Articled Student',
                    },
                'partnerUnit'     => $this->member->partner_unit ?? 'Partner Unit 01',
                'userId'          => $this->member->student_code ?: ('ID-' . $this->member->id),
                'email'           => $this->member->email,
                'plainPassword'   => $this->plainPassword,
                'loginUrl'        => $loginUrl,
                'isPasswordReset' => $this->isPasswordReset,
            ],
        );
    }

    protected function loginUrl(): string
    {
        try {
            $firm = $this->member->firm;
            if ($firm && ! empty($firm->custom_domain)) {
                return 'https://' . trim($firm->custom_domain, '/');
            }
            if ($firm && ! empty($firm->custom_subdomain)) {
                return 'https://' . trim($firm->custom_subdomain, '.') . '.dhakawala.com';
            }

            $appUrl = rtrim((string) config('app.url', 'https://erp.dhakawala.com'), '/');
            if ($appUrl !== '' && ! str_contains($appUrl, 'localhost') && ! str_contains($appUrl, '127.0.0.1')) {
                return $appUrl;
            }

            return 'https://erp.dhakawala.com';
        } catch (\Throwable $e) {
            Log::debug('TeamInvitationMail loginUrl fallback: ' . $e->getMessage());
            return 'https://erp.dhakawala.com';
        }
    }
}
