<?php

namespace Tests\Feature;

use App\Livewire\Auth\FirmLogin;
use App\Livewire\Auth\PlatformLogin;
use App\Models\Firm;
use App\Models\User;
use App\Support\FirmFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Guards added for the 10–15 firm launch.
 *
 *  1. The seven demo sign-in personas must not exist unless somebody sets
 *     ERP_ALLOW_TEST_LOGINS=true by hand. They used to activate whenever
 *     APP_ENV was "testing", so one stray .env value opened the platform.
 *  2. Receipts and avatars are financial/personal files: they live on the
 *     private disk and are only reachable through an authorised route.
 *
 * Note on Sleep::fake(): a rejected Auth::attempt() makes Laravel's Timebox
 * sleep for 200ms. The no-CI php-wasm runtime used to verify this project
 * crashes inside that sleep, so the two tests that exercise a *failed*
 * login capture the sleep instead of performing it. That is the framework's
 * own idiom for this and does not change what is being asserted.
 */
class FileAndLoginGuardTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firm = Firm::create([
            'name'   => 'Rahman Anis & Co.',
            'slug'   => 'rac',
            'status' => 'active',
        ]);

        $this->student = User::create([
            'firm_id'      => $this->firm->id,
            'name'         => 'Zubair Hossain Siddique',
            'email'        => 'zubair.student@rahmananis.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'student',
            'partner_unit' => 'Partner Unit 01',
            'student_code' => 'S-101',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/private/conveyance-receipts/rac'));
        File::deleteDirectory(storage_path('app/public/conveyance-receipts/rac'));
        File::deleteDirectory(storage_path('app/private/avatars/rac'));
        File::deleteDirectory(storage_path('app/public/avatars/rac'));

        parent::tearDown();
    }

    // ─── 1. Demo personas are opt-in ──────────────────────────────────────

    public function test_the_flag_defaults_to_off_when_nobody_sets_it(): void
    {
        // The suite itself sets the flag (phpunit.xml), so read the config file
        // the way a production boot would: with the variable absent entirely.
        $saved = [
            'ENV'    => $_ENV['ERP_ALLOW_TEST_LOGINS'] ?? null,
            'SERVER' => $_SERVER['ERP_ALLOW_TEST_LOGINS'] ?? null,
        ];

        unset($_ENV['ERP_ALLOW_TEST_LOGINS'], $_SERVER['ERP_ALLOW_TEST_LOGINS']);
        putenv('ERP_ALLOW_TEST_LOGINS');

        try {
            $config = require base_path('config/erp.php');

            $this->assertFalse(
                $config['allow_test_logins'],
                'ERP_ALLOW_TEST_LOGINS must default to off; only the suite turns it on.'
            );
        } finally {
            if ($saved['ENV'] !== null) {
                $_ENV['ERP_ALLOW_TEST_LOGINS'] = $saved['ENV'];
            }
            if ($saved['SERVER'] !== null) {
                $_SERVER['ERP_ALLOW_TEST_LOGINS'] = $saved['SERVER'];
            }
        }
    }

    public function test_demo_persona_cannot_sign_in_without_the_flag(): void
    {
        config(['erp.allow_test_logins' => false]);
        Sleep::fake();

        $component = Livewire::test(FirmLogin::class, ['firmSlug' => 'rac'])
            ->set('email', 'mohitul.haq@rahmananis.com')
            ->set('password', 'AnyPreviewPass123!')
            ->call('submitLogin');

        $this->assertNull($component->get('simulatedSuccessMessage'));
        $this->assertStringContainsString(
            'Arbitrary credentials cannot be verified or authenticated',
            (string) $component->get('validationError')
        );
        $this->assertGuest();
    }

    public function test_platform_demo_operator_cannot_sign_in_without_the_flag(): void
    {
        config(['erp.allow_test_logins' => false]);
        Sleep::fake();

        $component = Livewire::test(PlatformLogin::class)
            ->set('email', 'admin@dhakawala.com')
            ->set('password', 'AnyPreviewPass123!')
            ->call('submitLogin');

        $this->assertNull($component->get('simulatedSuccessMessage'));
        $this->assertNotNull($component->get('validationError'));
        $this->assertGuest();
    }

    public function test_a_real_account_still_signs_in_normally(): void
    {
        config(['erp.allow_test_logins' => false]);

        Livewire::test(FirmLogin::class, ['firmSlug' => 'rac'])
            ->set('email', 'zubair.student@rahmananis.com')
            ->set('password', 'Secret123!')
            ->call('submitLogin')
            ->assertSet('validationError', null);

        $this->assertAuthenticatedAs($this->student->fresh());
    }

    // ─── 2. Files are private ─────────────────────────────────────────────

    public function test_voucher_route_rejects_guests(): void
    {
        $this->get('/c/rac/voucher-file/voucher_20261030_120000_abc123.pdf')
            ->assertRedirect();
    }

    public function test_voucher_is_served_from_the_private_disk(): void
    {
        $this->writePrivate('conveyance-receipts', 'voucher_private.pdf', 'private copy');

        $this->actingAs($this->student)
            ->get('/c/rac/voucher-file/voucher_private.pdf')
            ->assertOk();
    }

    public function test_legacy_public_vouchers_are_still_served(): void
    {
        // Files uploaded before the move must not turn into 404s.
        $path = storage_path('app/public/conveyance-receipts/rac/voucher_legacy.pdf');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'legacy copy');

        $this->actingAs($this->student)
            ->get('/c/rac/voucher-file/voucher_legacy.pdf')
            ->assertOk();
    }

    public function test_a_member_cannot_read_another_firms_voucher(): void
    {
        $other = Firm::create(['name' => 'Other & Co.', 'slug' => 'other', 'status' => 'active']);
        $outsider = User::create([
            'firm_id'      => $other->id,
            'name'         => 'Other Student',
            'email'        => 'other@other.com',
            'password'     => Hash::make('Secret123!'),
            'role'         => 'student',
            'partner_unit' => 'Partner Unit 01',
        ]);

        $this->writePrivate('conveyance-receipts', 'voucher_rac.pdf', 'rac receipt');

        $this->actingAs($outsider)
            ->get('/c/rac/voucher-file/voucher_rac.pdf')
            ->assertForbidden();
    }

    public function test_missing_files_are_404_not_a_directory_listing(): void
    {
        $this->actingAs($this->student)
            ->get('/c/rac/voucher-file/does-not-exist.pdf')
            ->assertNotFound();
    }

    public function test_traversal_attempts_resolve_to_nothing(): void
    {
        $this->assertNull(FirmFile::locate('conveyance-receipts', '../../etc', 'passwd'));
        $this->assertNull(FirmFile::locate('conveyance-receipts', 'rac', '../../../../etc/passwd'));
        $this->assertNull(FirmFile::locate('conveyance-receipts', 'rac', '..'));
    }

    public function test_avatars_are_served_from_the_private_disk(): void
    {
        $this->writePrivate('avatars', 'avatar_2_123.png', 'png-bytes');

        $this->actingAs($this->student)
            ->get('/c/rac/avatar-file/avatar_2_123.png')
            ->assertOk();
    }

    public function test_the_migration_command_moves_legacy_files(): void
    {
        $path = storage_path('app/public/conveyance-receipts/rac/voucher_old.pdf');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'old receipt');

        $this->artisan('erp:privatise-uploads')
            ->expectsOutputToContain('moved')
            ->assertExitCode(0);

        $this->assertFalse(File::exists($path));
        $this->assertTrue(File::exists(storage_path('app/private/conveyance-receipts/rac/voucher_old.pdf')));
    }

    public function test_the_migration_command_dry_run_changes_nothing(): void
    {
        $path = storage_path('app/public/conveyance-receipts/rac/voucher_keep.pdf');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'keep me');

        $this->artisan('erp:privatise-uploads', ['--dry-run' => true])->assertExitCode(0);

        $this->assertTrue(File::exists($path));
        $this->assertFalse(File::exists(storage_path('app/private/conveyance-receipts/rac/voucher_keep.pdf')));
    }

    // ─── helpers ──────────────────────────────────────────────────────────

    private function writePrivate(string $folder, string $filename, string $contents): void
    {
        $path = storage_path("app/private/{$folder}/rac/{$filename}");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }
}
