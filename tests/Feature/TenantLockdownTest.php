<?php

namespace Tests\Feature;

use App\Livewire\Platform\DashboardPreview;
use App\Livewire\Practice\PracticePreview;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Regression tests for the cross-tenant hole found in the pre-launch review:
 * PracticePreview::$firmSlug was a public, unlocked Livewire property, so a
 * Partner of one firm could repoint the component at another firm.
 */
class TenantLockdownTest extends TestCase
{
    use RefreshDatabase;

    protected Firm $firmA;
    protected Firm $firmB;
    protected User $partnerA;
    protected User $partnerB;
    protected User $studentA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firmA = Firm::create(['name' => 'Firm A', 'slug' => 'firm-a', 'status' => 'active', 'tier' => 'professional']);
        $this->firmB = Firm::create(['name' => 'Firm B', 'slug' => 'firm-b', 'status' => 'active', 'tier' => 'professional']);

        $this->partnerA = $this->makeUser($this->firmA, 'partner', 'partner@firm-a.test');
        $this->partnerB = $this->makeUser($this->firmB, 'partner', 'partner@firm-b.test');
        $this->studentA = $this->makeUser($this->firmA, 'student', 'student@firm-a.test');
    }

    private function makeUser(Firm $firm, string $role, string $email): User
    {
        return User::create([
            'firm_id'      => $firm->id,
            'name'         => ucfirst($role) . ' ' . $firm->slug,
            'email'        => $email,
            'password'     => Hash::make('Secret123!'),
            'role'         => $role,
            'partner_unit' => 'Partner Unit 01',
        ]);
    }

    public function test_firm_slug_cannot_be_rewritten_from_the_browser(): void
    {
        $this->actingAs($this->partnerA);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'firm-a'])
            ->set('firmSlug', 'firm-b');
    }

    public function test_partner_unit_cannot_be_rewritten_directly_from_the_browser(): void
    {
        $this->actingAs($this->studentA);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'firm-a'])
            ->set('partnerUnit', 'Partner Unit 02');
    }

    public function test_a_session_switched_to_another_firm_is_refused_on_the_next_request(): void
    {
        $this->actingAs($this->partnerA);

        $component = Livewire::test(PracticePreview::class, ['firmSlug' => 'firm-a'])
            ->assertSet('firmSlug', 'firm-a');

        // Same component snapshot, but the session now belongs to firm B.
        $this->actingAs($this->partnerB);

        $this->expectException(HttpException::class);

        $component->call('setSection', 'payroll');
    }

    public function test_partner_of_one_firm_cannot_open_another_firms_workspace_over_http(): void
    {
        $this->actingAs($this->partnerA)
            ->get('/c/firm-b/dashboard')
            ->assertForbidden();
    }

    public function test_student_cannot_open_the_platform_dashboard(): void
    {
        $this->actingAs($this->studentA)
            ->get('/platform/dashboard')
            ->assertForbidden();
    }

    public function test_non_platform_user_cannot_mount_the_platform_component(): void
    {
        $this->actingAs($this->partnerA);

        $this->expectException(HttpException::class);

        Livewire::test(DashboardPreview::class);
    }

    public function test_role_lock_still_pins_a_student_to_student(): void
    {
        $this->actingAs($this->studentA);

        Livewire::test(PracticePreview::class, ['firmSlug' => 'firm-a', 'previewRole' => 'partner'])
            ->assertSet('previewRole', 'student');
    }

    public function test_env_example_ships_no_default_admin_password(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        $this->assertDoesNotMatchRegularExpression('/^PLATFORM_ADMIN_PASSWORD=.+$/m', $example);
        $this->assertStringNotContainsString('PLATFORM_ADMIN_PASSWORD="password"', $example);
    }

    public function test_preview_middleware_is_not_keyed_on_app_env(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/RestrictPreviewInProduction.php'));

        $this->assertStringNotContainsString("environment('local'", $source);
        $this->assertStringContainsString("config('app.demo_mode'", $source);
    }
}
