<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeedbackPilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_can_grant_and_revoke_the_invitation_pilot(): void
    {
        $this->travelTo('2026-10-17 12:00:00');
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $owner->company->forceFill([
            'trial_ends_at' => now()->subDay(),
            'feedback_pilot' => false,
        ])->save();

        $this->assertFalse($owner->fresh()->canManageJobs());

        $this->actingAs($owner)->put(route('settings.company.update'), [
            'company_name' => $owner->company->name,
            'feedback_pilot' => '1',
        ])->assertRedirect();
        $this->assertFalse($owner->company->fresh()->feedback_pilot);

        $this->artisan('company:feedback-pilot', ['company' => $owner->company_id])
            ->assertSuccessful();
        $this->artisan('company:feedback-pilot', ['company' => $owner->company_id])
            ->assertSuccessful();

        $company = $owner->company->fresh();
        $this->assertTrue($company->feedback_pilot);
        $this->assertSame('2026-12-31', $company->trial_ends_at?->timezone('Europe/Stockholm')->toDateString());
        $this->assertTrue($owner->fresh()->canManageJobs());
        $this->assertTrue($member->fresh()->maySignIn());

        $this->actingAs($owner->fresh())->get(route('assignments.index'))
            ->assertOk()
            ->assertSee('Feedbackpilot – Team gratis till 31 december 2026', false)
            ->assertDontSee('Inget kort behövs', false);

        $this->actingAs($owner->fresh())->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Feedbackpilot – Team gratis till 31 december 2026', false);

        $this->travelTo(Carbon::parse('2026-11-20 12:00:00', 'Europe/Stockholm'));
        $this->assertTrue($owner->company->fresh()->canManageJobs());

        $this->artisan('company:feedback-pilot', [
            'company' => $owner->company_id,
            '--revoke' => true,
        ])->assertSuccessful();

        $revoked = $owner->company->fresh();
        $this->assertFalse($revoked->feedback_pilot);
        $this->assertTrue($revoked->trial_ends_at?->lessThan(now()));
        $this->assertFalse($owner->fresh()->canManageJobs());
        $this->assertModelExists($owner);
        $this->assertModelExists($member);

        $this->travelTo(Carbon::parse('2027-01-01 00:00:01', 'Europe/Stockholm'));
        $this->assertFalse($owner->company->fresh()->onActiveFeedbackPilot());
    }

    public function test_unknown_company_ids_are_rejected(): void
    {
        $this->artisan('company:feedback-pilot', ['company' => '0'])->assertFailed();
        $this->artisan('company:feedback-pilot', ['company' => '999999'])->assertFailed();
    }
}
