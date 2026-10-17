<?php

namespace Tests\Feature;

use App\CompanyPlan;
use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_uploads_a_resized_png_that_appears_on_the_customer_report_and_the_sample_report(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['name' => 'Glans Städ AB']);
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($owner)->post('/installningar/logotyp', [
            'logo' => UploadedFile::fake()->image('logo.png', 1000, 400),
        ])->assertRedirect()
            ->assertSessionHas('status', 'Logotypen är sparad.');

        $owner->company->refresh();
        [$width, $height, $type] = getimagesize(Storage::disk('local')->path($owner->company->logo_path));
        $this->assertSame(IMAGETYPE_PNG, $type);
        $this->assertSame(800, $width);
        $this->assertSame(320, $height);

        $logoPath = '/r/'.$assignment->public_token.'/logotyp';
        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee($logoPath, false)
            ->assertSee('Glans Städ AB', false);
        $this->get($logoPath)->assertOk()->assertHeader('content-type', 'image/png');

        $this->actingAs($owner)->get('/exempel-rapport')
            ->assertOk()
            ->assertDontSee('/installningar/logotyp', false)
            ->assertSee('images/demo/company-logo.svg?v='.config('klartfoto.demo_asset_version'), false);
    }

    public function test_sample_report_shows_a_drawing_when_the_company_has_no_logo(): void
    {
        $this->get('/exempel-rapport')
            ->assertOk()
            ->assertSee('images/demo/company-logo.svg?v='.config('klartfoto.demo_asset_version'), false)
            ->assertSee('Exempel Städ AB', false);
    }

    public function test_jpeg_logo_is_reencoded_and_an_unsafe_svg_is_rejected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();

        $this->actingAs($owner)->post('/installningar/logotyp', [
            'logo' => UploadedFile::fake()->image('logo.jpg', 400, 200),
        ])->assertRedirect();

        $owner->company->refresh();
        [$width, $height, $type] = getimagesize(Storage::disk('local')->path($owner->company->logo_path));
        $this->assertSame(IMAGETYPE_JPEG, $type);
        $this->assertSame(400, $width);
        $this->assertSame(200, $height);

        $this->actingAs($owner)->from('/installningar')->post('/installningar/logotyp', [
            'logo' => UploadedFile::fake()->createWithContent(
                'logo.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>',
            ),
        ])->assertRedirect('/installningar')
            ->assertSessionHasErrors('logo');

        $this->assertStringEndsWith('.jpg', (string) $owner->company->fresh()->logo_path);
        $this->assertStringNotContainsString('<script', (string) Storage::disk('local')->get($owner->company->fresh()->logo_path));
    }

    public function test_safe_svg_is_sanitized_resized_and_served_without_active_content(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['name' => 'Glans Städ AB']);
        $assignment = Assignment::factory()->for($owner)->create();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="2000" height="500" viewBox="0 0 2000 500"><rect width="200" height="50" fill="#111715"/></svg>';

        $this->actingAs($owner)->post('/installningar/logotyp', [
            'logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
        ])->assertRedirect();

        $stored = (string) Storage::disk('local')->get($owner->company->fresh()->logo_path);
        $this->assertStringContainsString('width="800"', $stored);
        $this->assertStringContainsString('<rect', $stored);
        $this->assertStringNotContainsString('<script', $stored);

        $this->get('/r/'.$assignment->public_token.'/logotyp')
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_svg_with_an_external_reference_is_rejected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();

        $this->actingAs($owner)->from('/installningar')->post('/installningar/logotyp', [
            'logo' => UploadedFile::fake()->createWithContent(
                'logo.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><image href="https://evil.test/a.png" width="10" height="10"/></svg>',
            ),
        ])->assertRedirect('/installningar')->assertSessionHasErrors('logo');

        $this->assertNull($owner->company->fresh()->logo_path);
    }

    public function test_start_plan_hides_the_logo_after_the_trial_and_team_keeps_it(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['name' => 'Glans Städ AB']);
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($owner)->post('/installningar/logotyp', [
            'logo' => UploadedFile::fake()->image('logo.png', 40, 40),
        ])->assertRedirect();

        $owner->company->forceFill([
            'plan' => CompanyPlan::Start,
            'trial_ends_at' => now()->subDay(),
        ])->save();

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertDontSee('/logotyp', false);
        $this->get('/r/'.$assignment->public_token.'/logotyp')->assertNotFound();
        $this->actingAs($owner)->from('/installningar')->post('/installningar/logotyp', [
            'logo' => UploadedFile::fake()->image('ny.png', 40, 40),
        ])->assertRedirect('/installningar')
            ->assertSessionHasErrors('logo');

        $owner->company->forceFill([
            'plan' => CompanyPlan::Team,
            'paid_until' => now()->addMonth(),
        ])->save();

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee('/r/'.$assignment->public_token.'/logotyp', false);
    }

    public function test_a_member_cannot_replace_the_logo_and_a_guest_is_sent_to_login(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();

        $this->actingAs($member)->post('/installningar/logotyp', [
            'logo' => UploadedFile::fake()->image('logo.png', 40, 40),
        ])->assertForbidden();

        auth()->logout();

        $this->get('/installningar')->assertRedirect(route('login'));
    }

    public function test_public_report_escapes_the_company_name(): void
    {
        $owner = User::factory()->create();
        $owner->company->forceFill(['name' => "<script>alert('x')</script>"])->save();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->get('/r/'.$assignment->public_token)
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('x')</script>", false);
    }
}
