<?php

namespace Tests\Feature;

use App\Livewire\Auth\PlatformLogin;
use App\Models\Firm;
use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1-3.2 — optional TOTP second factor.
 *
 * The algorithm tests pin the RFC 6238 reference vectors (SHA-1, 6 digits,
 * 30 second steps) so a future refactor cannot quietly break every enrolled
 * operator. The flow tests pin the important safety property: accounts that
 * have NOT confirmed a secret must be able to sign in exactly as before.
 */
class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** Base32 of the RFC 6238 test secret "12345678901234567890". */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private Firm $firm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firm = Firm::create([
            'name'   => 'Rahman Anis & Co.',
            'slug'   => 'rac',
            'status' => 'active',
        ]);
    }

    // ─── Algorithm ────────────────────────────────────────────────────────

    public function test_it_matches_the_rfc_6238_reference_vectors(): void
    {
        $vectors = [
            59          => '287082',
            1111111109  => '081804',
            1111111111  => '050471',
            1234567890  => '005924',
            2000000000  => '279037',
        ];

        foreach ($vectors as $timestamp => $expected) {
            // codeForCounter is public precisely so this test can pin vectors.
            $this->assertSame(
                $expected,
                TwoFactor::codeForCounter(self::RFC_SECRET, intdiv($timestamp, 30)),
                "TOTP mismatch at t={$timestamp}"
            );
        }
    }

    public function test_base32_round_trips(): void
    {
        $binary = random_bytes(32);

        $this->assertSame($binary, TwoFactor::base32Decode(TwoFactor::base32Encode($binary)));
    }

    public function test_generated_secrets_are_32_char_base32(): void
    {
        $secret = TwoFactor::generateSecret();

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertSame(20, strlen(TwoFactor::base32Decode($secret)));
    }

    public function test_verification_accepts_the_current_code_and_one_step_of_drift(): void
    {
        $now = 1_700_000_000;
        $counter = intdiv($now, 30);

        $this->assertTrue(TwoFactor::verify(self::RFC_SECRET, TwoFactor::codeForCounter(self::RFC_SECRET, $counter), $now));
        $this->assertTrue(TwoFactor::verify(self::RFC_SECRET, TwoFactor::codeForCounter(self::RFC_SECRET, $counter - 1), $now));
        $this->assertTrue(TwoFactor::verify(self::RFC_SECRET, TwoFactor::codeForCounter(self::RFC_SECRET, $counter + 1), $now));
    }

    public function test_verification_rejects_stale_and_malformed_codes(): void
    {
        $now = 1_700_000_000;
        $counter = intdiv($now, 30);

        $this->assertFalse(TwoFactor::verify(self::RFC_SECRET, TwoFactor::codeForCounter(self::RFC_SECRET, $counter - 5), $now));
        $this->assertFalse(TwoFactor::verify(self::RFC_SECRET, '000000', $now + 3600));
        $this->assertFalse(TwoFactor::verify(self::RFC_SECRET, '', $now));
        $this->assertFalse(TwoFactor::verify(self::RFC_SECRET, 'notacode', $now));
    }

    public function test_recovery_codes_are_unique_and_uppercase(): void
    {
        $codes = TwoFactor::recoveryCodes();

        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));

        foreach ($codes as $code) {
            $this->assertSame(strtoupper($code), $code);
        }
    }

    // ─── Model state ──────────────────────────────────────────────────────

    public function test_a_secret_without_confirmation_does_not_enable_two_factor(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['two_factor_secret' => TwoFactor::generateSecret()])->save();

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_the_secret_is_hidden_from_serialisation(): void
    {
        $user = $this->makeUser();
        $user->forceFill([
            'two_factor_secret'         => TwoFactor::generateSecret(),
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => TwoFactor::recoveryCodes(),
        ])->save();

        $array = $user->fresh()->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
    }

    public function test_the_secret_is_encrypted_at_rest(): void
    {
        $secret = TwoFactor::generateSecret();
        $user = $this->makeUser();
        $user->forceFill(['two_factor_secret' => $secret])->save();

        $raw = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->assertNotSame($secret, $raw);
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
    }

    // ─── Sign-in flow ─────────────────────────────────────────────────────

    public function test_login_is_unchanged_when_two_factor_is_not_enrolled(): void
    {
        $this->makeUser(['email' => 'owner@rac.com']);

        Livewire::test(PlatformLogin::class)
            ->set('email', 'owner@rac.com')
            ->set('password', 'Secret123!')
            ->call('submitLogin')
            ->assertSet('requiresTwoFactor', false)
            ->assertSet('validationError', null);

        $this->assertTrue(Auth::check());
    }

    public function test_an_enrolled_account_cannot_sign_in_without_a_code(): void
    {
        $user = $this->makeUser(['email' => 'secure@rac.com']);
        $this->enrol($user);

        $component = Livewire::test(PlatformLogin::class)
            ->set('email', 'secure@rac.com')
            ->set('password', 'Secret123!')
            ->call('submitLogin')
            ->assertSet('requiresTwoFactor', true);

        $this->assertNotNull($component->get('twoFactorError'));
        $this->assertFalse(Auth::check());
    }

    public function test_an_enrolled_account_signs_in_with_the_current_code(): void
    {
        $user = $this->makeUser(['email' => 'secure@rac.com']);
        $this->enrol($user);

        Livewire::test(PlatformLogin::class)
            ->set('email', 'secure@rac.com')
            ->set('password', 'Secret123!')
            ->set('twoFactorCode', TwoFactor::currentCode((string) $user->fresh()->two_factor_secret))
            ->call('submitLogin')
            ->assertSet('requiresTwoFactor', false);

        $this->assertTrue(Auth::check());
    }

    public function test_a_wrong_code_keeps_the_session_signed_out(): void
    {
        $user = $this->makeUser(['email' => 'secure@rac.com']);
        $this->enrol($user);

        Livewire::test(PlatformLogin::class)
            ->set('email', 'secure@rac.com')
            ->set('password', 'Secret123!')
            ->set('twoFactorCode', '000000')
            ->call('submitLogin')
            ->assertSet('requiresTwoFactor', true);

        $this->assertFalse(Auth::check());
    }

    public function test_a_recovery_code_signs_in_once_and_is_consumed(): void
    {
        $user = $this->makeUser(['email' => 'secure@rac.com']);
        $recovery = $this->enrol($user)['recovery'];

        Livewire::test(PlatformLogin::class)
            ->set('email', 'secure@rac.com')
            ->set('password', 'Secret123!')
            ->set('twoFactorCode', $recovery[0])
            ->call('submitLogin')
            ->assertSet('requiresTwoFactor', false);

        $this->assertTrue(Auth::check());
        $this->assertCount(9, $user->fresh()->two_factor_recovery_codes);

        Auth::logout();

        // Re-using the consumed code must fail.
        Livewire::test(PlatformLogin::class)
            ->set('email', 'secure@rac.com')
            ->set('password', 'Secret123!')
            ->set('twoFactorCode', $recovery[0])
            ->call('submitLogin')
            ->assertSet('requiresTwoFactor', true);

        $this->assertFalse(Auth::check());
    }

    public function test_the_command_enrols_confirms_and_disables(): void
    {
        $user = $this->makeUser(['email' => 'cli@rac.com']);

        $this->artisan('erp:two-factor', ['email' => 'cli@rac.com'])->assertExitCode(0);

        $user->refresh();
        $this->assertNotEmpty($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled(), 'Enrolment must stay inert until confirmed.');

        $this->artisan('erp:two-factor', [
            'email'     => 'cli@rac.com',
            '--confirm' => TwoFactor::currentCode((string) $user->two_factor_secret),
        ])->assertExitCode(0);

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertCount(10, $user->two_factor_recovery_codes);

        $this->artisan('erp:two-factor', ['email' => 'cli@rac.com', '--disable' => true])->assertExitCode(0);

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'firm_id'     => $this->firm->id,
            'name'        => 'Platform Operator',
            'email'       => 'operator@rac.com',
            'password'    => Hash::make('Secret123!'),
            'role'        => 'platform_admin',
            'is_active'   => true,
        ], $attributes));
    }

    /**
     * @return array{secret: string, recovery: array<int, string>}
     */
    private function enrol(User $user): array
    {
        $secret = TwoFactor::generateSecret();
        $recovery = TwoFactor::recoveryCodes();

        $user->forceFill([
            'two_factor_secret'         => $secret,
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => $recovery,
        ])->save();

        return ['secret' => $secret, 'recovery' => $recovery];
    }
}
