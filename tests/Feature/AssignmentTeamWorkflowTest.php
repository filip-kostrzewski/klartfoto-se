<?php

namespace Tests\Feature;

use App\Actions\CreateAssignmentReportVersion;
use App\CompanyPlan;
use App\Models\Assignment;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssignmentTeamWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_assign_same_company_team_members_and_foreign_users_are_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $otherOwner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create([
            'assigned_to_user_id' => $owner->id,
            'reviewer_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)->put(route('assignments.team.update', $assignment, false), [
            'assigned_to_user_id' => $member->id,
            'reviewer_user_id' => $owner->id,
        ])->assertRedirect();

        $this->assertSame($member->id, $assignment->fresh()->assigned_to_user_id);
        $this->assertSame($owner->id, $assignment->fresh()->reviewer_user_id);

        $this->actingAs($owner)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Nytt besök', false)
            ->assertDontSee('>Ny visit<', false)
            ->assertSee('Ansvarig är den som utför besöket. En granskare kan kontrollera rapporten innan den skickas. Inte granskad betyder att ingen har markerat rapporten som kontrollerad.', false);

        $this->actingAs($owner)->from(route('assignments.show', $assignment, false))
            ->put(route('assignments.team.update', $assignment, false), [
                'assigned_to_user_id' => $otherOwner->id,
                'reviewer_user_id' => $member->id,
            ])->assertRedirect(route('assignments.show', $assignment, false))
            ->assertSessionHasErrors('assigned_to_user_id');

        $this->actingAs($member)->put(route('assignments.team.update', $assignment, false), [
            'assigned_to_user_id' => $member->id,
            'reviewer_user_id' => $member->id,
        ])->assertForbidden();
    }

    public function test_assigned_reviewer_can_review_only_the_current_snapshot_and_assignee_can_handoff(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $assignment = Assignment::factory()->for($owner)->create([
            'assigned_to_user_id' => $member->id,
            'reviewer_user_id' => $member->id,
        ]);
        Storage::disk('local')->put('assignments/'.$assignment->id.'/before.jpg', 'test image bytes');
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'assignments/'.$assignment->id.'/before.jpg',
            'sort_order' => 1,
            'uploaded_by_user_id' => $owner->id,
        ]);
        $version = app(CreateAssignmentReportVersion::class)->create($assignment, $owner, preserveCurrentToken: true);

        $detail = $this->actingAs($member)->get(route('assignments.show', $assignment))->assertOk();
        $this->assertTrue($detail->viewData('canReviewAssignment'));
        $this->assertTrue($detail->viewData('canHandoffAssignment'));

        $this->post(route('assignments.review', [$assignment, $version], false))->assertRedirect();
        $reviewed = $assignment->fresh();
        $this->assertSame($member->id, $reviewed->reviewed_by_user_id);
        $this->assertNotNull($reviewed->reviewed_at);
        $this->assertSame('ready', $version->fresh()->status);

        $this->post(route('assignments.handoff', $assignment, false))->assertRedirect();
        $handedOff = $assignment->fresh();
        $this->assertSame($owner->id, $handedOff->assigned_to_user_id);
        $this->assertSame($owner->id, $handedOff->reviewer_user_id);
        $this->assertNull($handedOff->reviewed_at);
        $this->assertNull($handedOff->reviewed_by_user_id);
    }

    public function test_review_is_blocked_when_the_draft_no_longer_matches_the_published_snapshot(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $assignment = Assignment::factory()->for($owner)->create(['reviewer_user_id' => $member->id]);
        Storage::disk('local')->put('assignments/'.$assignment->id.'/before.jpg', 'test image bytes');
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'assignments/'.$assignment->id.'/before.jpg',
            'sort_order' => 1,
        ]);
        app(CreateAssignmentReportVersion::class)->create($assignment, $owner, preserveCurrentToken: true);
        $assignment->forceFill(['customer_name' => 'Changed after publication'])->save();

        $this->actingAs($member)->post(route('assignments.review', [$assignment, $assignment->latestReportVersion()], false))
            ->assertRedirect()
            ->assertSessionHasErrors('review');

        $this->assertNull($assignment->fresh()->reviewed_at);
    }

    public function test_old_review_form_cannot_mark_a_newer_report_version_as_reviewed(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $assignment = Assignment::factory()->for($owner)->create(['reviewer_user_id' => $member->id]);
        Storage::disk('local')->put('assignments/'.$assignment->id.'/before.jpg', 'test image bytes');
        $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => 'assignments/'.$assignment->id.'/before.jpg',
            'sort_order' => 1,
        ]);
        $versionOne = app(CreateAssignmentReportVersion::class)->create($assignment, $owner, preserveCurrentToken: true);
        $staleReviewUrl = route('assignments.review', [$assignment, $versionOne], false);

        $assignment->forceFill(['customer_name' => 'Changed after first publication'])->save();
        $versionTwo = app(CreateAssignmentReportVersion::class)->create($assignment, $owner);

        $this->actingAs($member)->post($staleReviewUrl)
            ->assertRedirect()
            ->assertSessionHasErrors('review');

        $this->assertNull($assignment->fresh()->reviewed_at);
        $this->assertNull($assignment->fresh()->reviewed_by_user_id);
        $this->assertSame('ready', $versionTwo->fresh()->status);
    }

    public function test_start_plan_owner_cannot_use_team_assignment_controls(): void
    {
        $owner = User::factory()->expiredTrial()->create();
        $owner->company->forceFill([
            'plan' => CompanyPlan::Start,
            'paid_until' => now()->addMonth(),
        ])->save();
        $assignment = Assignment::factory()->for($owner)->create();
        $member = User::factory()->memberOf($owner->company)->create();

        $this->actingAs($owner)->put(route('assignments.team.update', $assignment, false), [
            'assigned_to_user_id' => $member->id,
            'reviewer_user_id' => $owner->id,
        ])->assertForbidden();

        $this->assertNull($assignment->fresh()->assigned_to_user_id);
    }
}
