<?php

namespace Tests\Feature;

use App\Http\Controllers\Erp\CustomDomainEntryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Tests\TestCase;

final class PublicPremiumLandingTest extends TestCase
{
    public function test_canonical_public_host_renders_the_premium_page_without_database_or_login():void
    {
        config()->set('app.url','https://erp.dhakawala.com');
        $request=Request::create('https://erp.dhakawala.com/','GET');
        $view=app(CustomDomainEntryController::class)->index($request);

        $this->assertInstanceOf(View::class,$view);
        $this->assertSame('erp.welcome',$view->name());
        $markup=$view->render();
        $this->assertStringContainsString('Less admin.',$markup);
        $this->assertStringContainsString('More ',$markup);
        $this->assertStringContainsString('momentum.',$markup);
        $this->assertStringContainsString('All your moving parts.',$markup);
        // AppServiceProvider may pin a synthetic test URL; the canonical-host
        // controller decision above is what this test needs to prove.
        $this->assertStringContainsString(route('erp.platform.login'),$markup);
        $this->assertStringContainsString('Public self-registration is not available',$markup);
    }

    public function test_public_page_is_accessible_responsive_self_contained_and_not_a_fake_authenticated_dashboard():void
    {
        $markup=view('erp.welcome')->render();

        $this->assertSame(1,substr_count($markup,'<h1'));
        $this->assertSame(6,substr_count($markup,'class="feature-card"'));
        $this->assertSame(7,substr_count($markup,'<details'));
        $this->assertStringContainsString('name="viewport"',$markup);
        $this->assertStringContainsString('class="skip"',$markup);
        $this->assertStringContainsString('prefers-reduced-motion',$markup);
        $this->assertStringContainsString('@media(max-width:600px)',$markup);
        $this->assertStringContainsString('Illustrative interface preview',$markup);
        $this->assertStringContainsString('mailto:support@dhakawala.com',$markup);
        $this->assertStringContainsString('--brand:#13ac9d',$markup);
        $this->assertStringContainsString('independent SaaS platform',$markup);
        $this->assertStringContainsString('monthly or annual billing',$markup);
        $this->assertStringNotContainsString('#fcdf02',$markup);
        $this->assertStringNotContainsString('RA&amp;C yellow',$markup);
        $this->assertStringNotContainsString('APP_KEY',$markup);
        $this->assertStringNotContainsString('dashboard-preview',$markup);
        $this->assertStringNotContainsString('/api/v1/',$markup);
        $this->assertStringNotContainsString('cdn.',strtolower($markup));
        $this->assertStringNotContainsString('src="http',strtolower($markup));
        $this->assertStringNotContainsString('fonts.googleapis.com',strtolower($markup));
        $this->assertStringNotContainsString('<script',$markup);
        $this->assertStringNotContainsString('@vite',$markup);

        preg_match_all('/href="#([a-z0-9-]+)"/',$markup,$match);
        foreach($match[1] as $id){
            $this->assertStringContainsString('id="'.$id.'"',$markup,
                'Every internal anchor must have an actual target: '.$id);
        }
    }

    public function test_landing_is_restricted_to_root_and_platform_sign_in_keeps_original_auth_guard():void
    {
        $root=Route::getRoutes()->getByName('home');
        $this->assertNotNull($root);
        $platform=Route::getRoutes()->getByName('erp.platform.login');
        $this->assertNotNull($platform);
        $this->assertStringContainsString('/platform/app/login','/'.ltrim($platform->uri(),'/'));
        $private=Route::getRoutes()->getByName('erp.platform.dashboard');
        $this->assertNotNull($private);
        $this->assertContains('auth',$private->gatherMiddleware());
        $this->assertContains('platform.admin',$private->gatherMiddleware());
        $this->assertContains('mfa.verified',$private->gatherMiddleware());
    }
}
