<?php

namespace Tests\Feature;

use App\Actions\CreateAssignmentReportVersion;
use App\Models\Assignment;
use App\Models\AssignmentEmailAttempt;
use App\Models\AssignmentReportResponse;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_erase_a_job_and_its_share_links_even_after_the_trial_ends(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('s3');
        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $assignment = Assignment::factory()->for($owner)->create([
            'customer_name' => 'Anna Andersson',
        ]);
        $photoPath = 'assignments/'.$assignment->id.'/report.jpg';
        Storage::disk('local')->put($photoPath, 'report-photo');
        Storage::disk('public')->put($photoPath, 'public-copy');
        Storage::disk('s3')->put($photoPath, 'remote-copy');
        Storage::disk('local')->put('assignments/'.$assignment->id.'/thumbs/report.jpg', 'thumb');
        $photo = $assignment->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => $photoPath,
            'sort_order' => 1,
        ]);
        $firstVersion = app(CreateAssignmentReportVersion::class)->create($assignment, $owner, preserveCurrentToken: true);
        $secondVersion = app(CreateAssignmentReportVersion::class)->create($assignment->fresh(), $owner);
        $assignment->emailAttempts()->create([
            'company_id' => $owner->company_id,
            'user_id' => $owner->id,
            'assignment_report_version_id' => $firstVersion->id,
            'recipient_email' => 'customer@example.test',
            'status' => AssignmentEmailAttempt::STATUS_SENT,
        ]);
        $firstVersion->responses()->create([
            'company_id' => $owner->company_id,
            'assignment_id' => $assignment->id,
            'type' => AssignmentReportResponse::TYPE_RECEIVED,
            'response_key' => 'received',
        ]);
        $logoPath = 'companies/'.$assignment->company_id.'/logo.svg';
        Storage::disk('local')->put($logoPath, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $firstVersion->assets()->create([
            'type' => 'logo',
            'path' => $logoPath,
        ]);
        Cache::store('file')->put('assignment-report-pdf:'.$firstVersion->id.':'.$firstVersion->snapshot_hash, '%PDF-1.4 first', 60);
        Cache::store('file')->put('assignment-report-pdf:'.$secondVersion->id.':'.$secondVersion->snapshot_hash, '%PDF-1.4 second', 60);
        Cache::store('file')->put('assignment-report-pdf:control', '%PDF-1.4 control', 60);

        $otherOwner = User::factory()->create();
        $other = Assignment::factory()->for($otherOwner)->create();
        $otherPath = 'assignments/'.$other->id.'/keep.jpg';
        Storage::disk('local')->put($otherPath, 'keep');
        $other->photos()->create([
            'phase' => PhotoPhase::Before,
            'path' => $otherPath,
            'sort_order' => 1,
        ]);
        $otherVersion = app(CreateAssignmentReportVersion::class)->create($other, $otherOwner, preserveCurrentToken: true);

        $owner->company->forceFill([
            'trial_ends_at' => now()->subDay(),
            'paid_until' => now()->subDay(),
        ])->save();

        $this->actingAs($owner)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Radera uppdrag', false)
            ->assertSee('Vill du radera det här uppdraget? Alla bilder, rapportversioner och delningslänkar tas bort permanent.', false);

        $this->actingAs($member)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertDontSee('Radera uppdrag', false);

        $this->actingAs($member)->delete(route('assignments.destroy', $assignment))
            ->assertForbidden();
        $this->assertModelExists($assignment);
        Storage::disk('local')->assertExists($photoPath);

        $this->actingAs($otherOwner)->delete(route('assignments.destroy', $assignment))
            ->assertNotFound();
        $this->post('/logout')->assertRedirect('/login');
        $this->delete(route('assignments.destroy', $assignment))
            ->assertRedirect(route('login'));

        $this->actingAs($owner)->delete(route('assignments.destroy', $assignment))
            ->assertRedirect(route('assignments.index'))
            ->assertSessionHas('toast', 'Uppdraget är raderat.');

        $this->assertModelMissing($assignment);
        $this->assertModelMissing($photo);
        $this->assertDatabaseMissing('assignment_report_versions', ['token' => $firstVersion->token]);
        $this->assertDatabaseMissing('assignment_report_versions', ['token' => $secondVersion->token]);
        $this->assertDatabaseMissing('assignment_email_attempts', ['assignment_id' => $assignment->id]);
        $this->assertDatabaseMissing('assignment_report_responses', ['assignment_id' => $assignment->id]);
        $this->get('/r/'.$assignment->public_token)->assertNotFound();
        $this->get('/r/'.$firstVersion->token)->assertNotFound();
        $this->get('/r/'.$secondVersion->token)->assertNotFound();
        $this->get('/r/'.$firstVersion->token.'/pdf')->assertNotFound();
        Storage::disk('local')->assertMissing($photoPath);
        Storage::disk('public')->assertMissing($photoPath);
        Storage::disk('s3')->assertMissing($photoPath);
        Storage::disk('local')->assertMissing('assignments/'.$assignment->id.'/thumbs/report.jpg');
        Storage::disk('local')->assertExists($logoPath);
        Storage::disk('local')->assertExists($otherPath);
        $this->get('/r/'.$otherVersion->token)->assertOk();
        $this->assertNull(Cache::store('file')->get('assignment-report-pdf:'.$firstVersion->id.':'.$firstVersion->snapshot_hash));
        $this->assertNull(Cache::store('file')->get('assignment-report-pdf:'.$secondVersion->id.':'.$secondVersion->snapshot_hash));
        $this->assertSame('%PDF-1.4 control', Cache::store('file')->get('assignment-report-pdf:control'));
    }
}
