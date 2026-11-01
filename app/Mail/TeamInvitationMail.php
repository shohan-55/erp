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
 * One-time invitation & password-setup notification email for team members.
 *
 * Delivers a single-use activation/password-setup link without exposing
 * plaintext passwords in email transit or logs.
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
        // Never retain or transmit a plaintext password via email.
        $this->plainPassword = null;
    }

    public function envelope(): Envelope
    {
        $subject = $this->isPasswordReset
            ? 'Set Your New Password — ' . $this->firmName . ' (DhakaWala ERP)'
            : 'Activate Your Account at ' . $this->firmName . ' — DhakaWala ERP';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $loginUrl = $this->loginUrl();
        $activationUrl = $this->activationUrl();

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
                'loginUrl'        => $loginUrl,
                'activationUrl'   => $activationUrl,
                'isPasswordReset' => $this->isPasswordReset,
            ],
        );
    }

    protected function activationUrl(): string
    {
        $token = $this->invitationToken ?: $this->member->invitation_token;
        try {
            $firm = $this->member->firm;
            $query = $token ? ('?token=' . urlencode($token)) : '';
            if ($firm && ! empty($firm->custom_domain)) {
                return 'https://' . $firm->custom_domain . '/c/' . $this->firmSlug . '/activate' . $query;
            }
            if ($firm && ! empty($firm->custom_subdomain)) {
                return 'https://' . $firm->custom_subdomain . '.dhakawala.com/c/' . $this->firmSlug . '/activate' . $query;
            }

            return route('firm.activate', array_filter([
                'firmSlug' => $this->firmSlug,
                'token'    => $token,
            ]));
        } catch (\Throwable $e) {
            Log::debug('TeamInvitationMail activationUrl fallback: ' . $e->getMessage());
            return url('/c/' . $this->firmSlug . '/activate' . ($token ? ('?token=' . urlencode($token)) : ''));
        }
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
        } catch (\Throwable $e) {
            Log::debug('TeamInvitationMail loginUrl fallback: ' . $e->getMessage());
            return url('/c/' . $this->firmSlug . '/login');
        }
    }
}
