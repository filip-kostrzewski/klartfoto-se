<?php

namespace Tests\Feature;

use App\CompanyPlan;
use App\CompanyRole;
use App\Models\Assignment;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CompanyInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_invites_a_person_who_joins_and_uses_the_company_jobs(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['name' => 'Glans Städ AB']);
        $existing = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Anna Andersson',
        ]);

        $this->actingAs($owner)->post('/installningar/inbjudningar', [
            'email' => 'Kim@Exempel.se',
        ])->assertRedirect()->assertSessionHas('status', 'Inbjudan är skickad till kim@exempel.se.');

        $invitation = CompanyInvitation::query()->first();
        $this->assertNotNull($invitation);
        $this->assertSame('kim@exempel.se', $invitation->email);
        $this->assertSame(48, strlen($invitation->token));
        $this->assertSame($owner->company_id, $invitation->company_id);
        Notification::assertSentOnDemand(CompanyInvitationNotification::class);

        $html = (new CompanyInvitationNotification($invitation))->toMail(new \stdClass)->render();
        $this->assertStringContainsString('/inbjudan/'.$invitation->token, $html);
        $this->assertStringContainsString('Glans Städ AB', $html);

        $this->get('/inbjudan/'.$invitation->token)
            ->assertOk()
            ->assertSee('Gå med i Glans Städ AB', false)
            ->assertSee('kim@exempel.se', false)
            ->assertSee('href="/integritetspolicy"', false);

        $this->post('/inbjudan/'.$invitation->token, [
            'name' => 'Kim Berg',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ])->assertRedirect(route('assignments.index'));

        $member = User::query()->where('email', 'kim@exempel.se')->first();
        $this->assertNotNull($member);
        $this->assertAuthenticatedAs($member);
        $this->assertSame(CompanyRole::Member, $member->role);
        $this->assertSame($owner->company_id, $member->company_id);
        $this->assertNotNull($invitation->fresh()->accepted_at);

        $this->actingAs($member)->get('/uppdrag')
            ->assertOk()
            ->assertSee('Anna Andersson', false)
            ->assertSee('Glans Städ AB', false);

        $this->actingAs($member)->post('/uppdrag', [
            'customer_name' => 'Bo Ek',
            'address' => 'Storgatan 2, 111 22 Stockholm',
            'performed_on' => '2026-10-02',
        ])->assertRedirect();

        $created = Assignment::query()->where('customer_name', 'Bo Ek')->first();
        $this->assertNotNull($created);
        $this->assertSame($owner->company_id, $created->company_id);
        $this->assertSame($member->id, $created->user_id);

        $this->actingAs($owner)->get('/uppdrag')->assertOk()->assertSee('Bo Ek', false);
        $this->get('/r/'.$created->public_token)->assertOk()->assertSee('Glans Städ AB', false);
        $this->actingAs($owner)->get(route('assignments.show', $existing))->assertOk();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('assignments.show', $existing))->assertNotFound();
        $this->actingAs($outsider)->get(route('assignments.show', $created))->assertNotFound();
    }

    public function test_invitation_mail_escapes_the_company_name(): void
    {
        $owner = User::factory()->create();
        $owner->company->forceFill(['name' => "<script>alert('x')</script>"])->save();
        $invitation = CompanyInvitation::factory()->create([
            'company_id' => $owner->company_id,
            'invited_by' => $owner->id,
            'email' => 'kim@exempel.se',
        ]);

        $html = (new CompanyInvitationNotification($invitation))->toMail(new \stdClass)->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('x')</script>", $html);
    }

    public function test_member_cannot_invite_and_an_existing_address_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $other = User::factory()->create(['email' => 'finns@exempel.se']);

        $this->actingAs($member)->post('/installningar/inbjudningar', [
            'email' => 'ny@exempel.se',
        ])->assertForbidden();

        $this->actingAs($owner)->from('/installningar')->post('/installningar/inbjudningar', [
            'email' => $other->email,
        ])->assertRedirect('/installningar')->assertSessionHasErrors('email');

        $this->assertDatabaseCount('company_invitations', 0);
    }

    public function test_start_after_the_trial_allows_one_user_and_team_or_trial_allows_more(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $owner->company->forceFill([
            'plan' => CompanyPlan::Start,
            'trial_ends_at' => now()->subDay(),
        ])->save();

        $this->actingAs($owner)->from('/installningar')->post('/installningar/inbjudningar', [
            'email' => 'kim@exempel.se',
        ])->assertRedirect('/installningar')
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('company_invitations', 0);

        $owner->company->forceFill(['trial_ends_at' => now()->addDay()])->save();
        $this->actingAs($owner)->post('/installningar/inbjudningar', [
            'email' => 'kim@exempel.se',
        ])->assertSessionHas('status');
        $this->assertDatabaseCount('company_invitations', 1);

        $second = User::factory()->create();
        $second->company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->addMonth(),
        ])->save();
        $this->actingAs($second)->post('/installningar/inbjudningar', [
            'email' => 'team@exempel.se',
        ])->assertSessionHas('status');
    }

    public function test_trial_stops_at_ten_users_including_pending_invitations(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        User::factory()->count(8)->memberOf($owner->company)->create();
        $this->assertSame(10, $owner->company->seatLimit());

        $this->actingAs($owner)->post('/installningar/inbjudningar', [
            'email' => 'nio@exempel.se',
        ])->assertSessionHas('status');

        $this->actingAs($owner)->from('/installningar')->post('/installningar/inbjudningar', [
            'email' => 'tio@exempel.se',
        ])->assertRedirect('/installningar')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('company_invitations', 1);
    }

    public function test_sending_the_same_address_again_refreshes_one_invitation(): void
    {
        Notification::fake();
        $owner = User::factory()->create();

        $this->actingAs($owner)->post('/installningar/inbjudningar', ['email' => 'kim@exempel.se']);
        $firstToken = CompanyInvitation::query()->value('token');
        $this->actingAs($owner)->post('/installningar/inbjudningar', ['email' => 'kim@exempel.se']);

        $this->assertDatabaseCount('company_invitations', 1);
        $this->assertNotSame($firstToken, CompanyInvitation::query()->value('token'));
        Notification::assertSentOnDemandTimes(CompanyInvitationNotification::class, 2);
    }

    public function test_expired_invitation_does_not_create_an_account(): void
    {
        $owner = User::factory()->create(['name' => 'Glans Städ AB']);
        $invitation = CompanyInvitation::factory()->create([
            'company_id' => $owner->company_id,
            'invited_by' => $owner->id,
            'email' => 'kim@exempel.se',
            'expires_at' => now()->subDay(),
        ]);

        $this->get('/inbjudan/'.$invitation->token)
            ->assertOk()
            ->assertSee('Inbjudan är inte längre giltig', false);

        $this->post('/inbjudan/'.$invitation->token, [
            'name' => 'Kim Berg',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ])->assertSessionHasErrors('token');

        $this->assertDatabaseMissing('users', ['email' => 'kim@exempel.se']);
    }

    public function test_seat_line_counts_users_apart_from_pending_invitations(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        CompanyInvitation::factory()->create([
            'company_id' => $owner->company_id,
            'invited_by' => $owner->id,
            'email' => 'gammal@exempel.se',
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertSee('1 av 10 användare.', false)
            ->assertDontSee('inbjudan väntar', false)
            ->assertDontSee('inbjudningar väntar', false);

        $this->actingAs($owner)->post('/installningar/inbjudningar', [
            'email' => 'kim@exempel.se',
        ]);

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertSee('1 av 10 användare, 1 inbjudan väntar', false)
            ->assertDontSee('2 av 10 användare', false);

        $this->actingAs($owner)->post('/installningar/inbjudningar', [
            'email' => 'lee@exempel.se',
        ]);

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertSee('1 av 10 användare, 2 inbjudningar väntar', false)
            ->assertDontSee('1 inbjudan väntar', false)
            ->assertDontSee('3 av 10 användare', false);
    }

    public function test_member_settings_show_only_that_members_profile(): void
    {
        $owner = User::factory()->create([
            'name' => 'Anna Andersson',
            'email' => 'anna@glans.se',
        ]);
        $member = User::factory()->memberOf($owner->company)->create([
            'name' => 'Kim Berg',
            'email' => 'kim@glans.se',
        ]);
        User::factory()->memberOf($owner->company)->create([
            'name' => 'Lee Ek',
            'email' => 'lee@glans.se',
        ]);

        $this->actingAs($member)->get('/installningar')
            ->assertOk()
            ->assertSee('Kim Berg', false)
            ->assertSee('kim@glans.se', false)
            ->assertSee('Medlem', false)
            ->assertDontSee('lee@glans.se', false)
            ->assertDontSee('anna@glans.se', false)
            ->assertSee('Abonnemang', false)
            ->assertDontSee('Skicka inbjudan', false)
            ->assertDontSee('290 kr/mån', false);

        $this->actingAs($member)->get('/installningar/abonnemang')
            ->assertOk()
            ->assertSee('Endast kontoägaren kan begära en faktura.', false)
            ->assertDontSee('Fakturauppgifter', false);
    }

    public function test_owner_can_withdraw_an_invitation_and_another_company_cannot(): void
    {
        $owner = User::factory()->create();
        $invitation = CompanyInvitation::factory()->create([
            'company_id' => $owner->company_id,
            'invited_by' => $owner->id,
        ]);
        $other = User::factory()->create();

        $this->actingAs($other)->delete('/installningar/inbjudningar/'.$invitation->id)->assertNotFound();
        $this->assertModelExists($invitation);

        $this->actingAs($owner)->delete('/installningar/inbjudningar/'.$invitation->id)
            ->assertRedirect()
            ->assertSessionHas('status', 'Inbjudan är borttagen.');
        $this->assertModelMissing($invitation);
    }
}
