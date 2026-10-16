<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentReportResponse;
use App\Models\AssignmentReportVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignmentReportResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_acknowledgement_is_idempotent_for_each_immutable_report_version(): void
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $firstVersion = $this->createVersion($assignment, $owner, 1, $assignment->public_token);

        $this->post(route('reports.responses.store', $firstVersion->token, false), [
            'response_type' => 'received',
        ])->assertRedirect(route('reports.show', $firstVersion->token, false))
            ->assertSessionHas('report_response_status', 'Tack, ditt svar har sparats.');

        $this->post(route('reports.responses.store', $firstVersion->token, false), [
            'response_type' => 'received',
        ])->assertRedirect(route('reports.show', $firstVersion->token, false))
            ->assertSessionHas('report_response_status', 'Ditt svar är redan registrerat.');

        $this->assertDatabaseCount('assignment_report_responses', 1);
        $this->assertDatabaseHas('assignment_report_responses', [
            'company_id' => $owner->company_id,
            'assignment_id' => $assignment->id,
            'assignment_report_version_id' => $firstVersion->id,
            'type' => 'received',
            'response_key' => 'received',
            'message' => null,
        ]);

        $secondVersion = $this->createVersion($assignment, $owner, 2, Str::random(48));
        $this->post(route('reports.responses.store', $secondVersion->token, false), [
            'response_type' => 'received',
        ])->assertRedirect(route('reports.show', $secondVersion->token, false));

        $this->assertDatabaseCount('assignment_report_responses', 2);
        $this->assertDatabaseHas('assignment_report_responses', [
            'assignment_report_version_id' => $secondVersion->id,
            'response_key' => 'received',
        ]);
    }

    public function test_comment_is_bound_to_a_pair_in_the_exact_version_and_visible_only_inside_its_tenant(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $otherOwner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $version = $this->createVersion($assignment, $owner, 1, $assignment->public_token);

        $this->post(route('reports.responses.store', $version->token, false), [
            'response_type' => 'comment',
            'message' => 'Please check the kitchen door.',
            'place_index' => 0,
            'pair_index' => 1,
        ])->assertRedirect(route('reports.show', $version->token, false))
            ->assertSessionHas('report_response_status', 'Din kommentar har skickats till företaget.');

        $response = $assignment->reportResponses()->sole();
        $this->assertSame('comment', $response->type);
        $this->assertSame(0, $response->place_index);
        $this->assertSame(1, $response->pair_index);
        $this->assertSame('Please check the kitchen door.', $response->message);
        $this->assertSame($version->id, $response->assignment_report_version_id);

        $publicView = $this->get(route('reports.show', $version->token, false))->assertOk();
        $publicView->assertDontSee('Please check the kitchen door.', false);

        $memberView = $this->actingAs($member)->get(route('assignments.show', $assignment))->assertOk();
        $this->assertSame([], $memberView->viewData('customerResponses'));

        $ownerView = $this->actingAs($owner)->get(route('assignments.show', $assignment))->assertOk();
        $this->assertSame('Kitchen', $ownerView->viewData('customerResponses')[0]['placeName']);
        $this->assertSame('Window', $ownerView->viewData('customerResponses')[0]['pairLabel']);
        $this->assertSame('Please check the kitchen door.', $ownerView->viewData('customerResponses')[0]['message']);
        $this->assertSame(0, $ownerView->viewData('customerResponses')[0]['placeIndex']);
        $this->assertSame(1, $ownerView->viewData('customerResponses')[0]['pairIndex']);
        $this->assertSame(
            route('reports.show', ['token' => $version->token]).'#report-pair-0-1',
            $ownerView->viewData('customerResponses')[0]['reportUrl'],
        );

        $otherAssignment = Assignment::factory()->for($otherOwner)->create();
        $this->actingAs($otherOwner)->get(route('assignments.show', $otherAssignment))->assertOk()
            ->assertViewHas('customerResponses', []);
    }

    public function test_owner_history_uses_a_stable_fallback_label_for_unlabeled_pairs(): void
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $version = $this->createVersion($assignment, $owner, 1, $assignment->public_token);
        $version->forceFill([
            'snapshot' => [
                'places' => [[
                    'name' => 'Bathroom',
                    'pairs' => [
                        ['label' => null],
                        ['label' => null],
                    ],
                ]],
            ],
        ])->save();
        AssignmentReportResponse::factory()->create([
            'company_id' => $owner->company_id,
            'assignment_id' => $assignment->id,
            'assignment_report_version_id' => $version->id,
            'type' => AssignmentReportResponse::TYPE_COMMENT,
            'place_index' => 0,
            'pair_index' => 1,
            'message' => 'The shower door sticks.',
        ]);

        $this->actingAs($owner)
            ->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertViewHas('customerResponses', function (array $responses) use ($version): bool {
                return count($responses) === 1
                    && $responses[0]['pairLabel'] === 'Bildpar 2'
                    && $responses[0]['placeIndex'] === 0
                    && $responses[0]['pairIndex'] === 1
                    && $responses[0]['reportUrl'] === route('reports.show', ['token' => $version->token]).'#report-pair-0-1';
            });
    }

    public function test_invalid_pair_and_legacy_token_do_not_create_customer_responses(): void
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $version = $this->createVersion($assignment, $owner, 1, $assignment->public_token);

        $this->from(route('reports.show', $version->token, false))
            ->post(route('reports.responses.store', $version->token, false), [
                'response_type' => 'comment',
                'message' => 'This is for a nonexistent pair.',
                'place_index' => 0,
                'pair_index' => 9,
            ])->assertRedirect(route('reports.show', $version->token, false))
            ->assertSessionHasErrors('pair_index');

        $legacyAssignment = Assignment::factory()->for($owner)->create();
        $this->post(route('reports.responses.store', $legacyAssignment->public_token, false), [
            'response_type' => 'received',
        ])->assertNotFound();

        $this->assertDatabaseCount('assignment_report_responses', 0);
    }

    public function test_factory_keeps_response_tenant_and_assignment_aligned_with_its_version(): void
    {
        $response = AssignmentReportResponse::factory()->create();
        $response->load(['assignment', 'company', 'reportVersion']);

        $this->assertSame($response->assignment->company_id, $response->company_id);
        $this->assertSame($response->assignment_id, $response->reportVersion->assignment_id);
        $this->assertSame($response->company_id, $response->reportVersion->assignment->company_id);
    }

    private function createVersion(Assignment $assignment, User $owner, int $versionNumber, string $token): AssignmentReportVersion
    {
        $snapshot = [
            'schema_version' => 1,
            'company_name' => $owner->company->name,
            'company_logo_path' => null,
            'customer_name' => $assignment->customer_name,
            'address' => $assignment->address,
            'performed_on' => $assignment->performed_on->toDateString(),
            'places' => [
                [
                    'id' => 1,
                    'name' => 'Kitchen',
                    'sort_order' => 1,
                    'pairs' => [
                        ['id' => 1, 'label' => 'Door', 'customer_comment' => null, 'sort_order' => 1, 'before' => null, 'after' => null],
                        ['id' => 2, 'label' => 'Window', 'customer_comment' => null, 'sort_order' => 2, 'before' => null, 'after' => null],
                    ],
                ],
            ],
            'assets' => [],
        ];
        $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $assignment->reportVersions()->create([
            'version_number' => $versionNumber,
            'token' => $token,
            'status' => AssignmentReportVersion::STATUS_READY,
            'snapshot' => $snapshot,
            'snapshot_hash' => hash('sha256', $snapshotJson),
            'created_by_user_id' => $owner->id,
        ]);
    }
}
