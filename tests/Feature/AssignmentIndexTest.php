<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_scoped_search_filters_status_and_date_and_paginates_results(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        for ($index = 1; $index <= 21; $index++) {
            $assignment = Assignment::factory()->for($owner)->create([
                'customer_name' => 'Target Client '.$index,
                'address' => 'Main street '.$index,
                'performed_on' => $index % 2 === 0 ? '2026-10-16' : '2026-10-15',
            ]);
            $assignment->forceFill(['status' => 'sent'])->save();

            if ($index === 1) {
                foreach ([PhotoPhase::Before, PhotoPhase::After] as $phase) {
                    $assignment->photos()->create([
                        'phase' => $phase,
                        'path' => 'assignments/'.$assignment->id.'/'.$phase->value.'.jpg',
                        'sort_order' => 1,
                    ]);
                }
            }
        }

        $foreignAssignment = Assignment::factory()->for($otherOwner)->create([
            'customer_name' => 'Target Client outside tenant',
            'address' => 'Main street outside tenant',
            'performed_on' => '2026-10-15',
        ]);
        $foreignAssignment->forceFill(['status' => 'sent'])->save();

        $this->actingAs($owner)
            ->get('/uppdrag?q=Target&status=sent&from=2026-10-15&to=2026-10-16&page=2')
            ->assertOk()
            ->assertViewHas('assignments', function ($assignments) use ($owner): bool {
                return $assignments->total() === 21
                    && $assignments->currentPage() === 2
                    && $assignments->count() === 1
                    && $assignments->every(fn (Assignment $assignment): bool => $assignment->company_id === $owner->company_id);
            })
            ->assertViewHas('filters', [
                'q' => 'Target',
                'status' => 'sent',
                'from' => '2026-10-15',
                'to' => '2026-10-16',
                'arkiv' => '',
            ])
            ->assertViewHas('assignmentTotal', 21)
            ->assertViewHas('photoTotal', 2);

        $this->actingAs($owner)
            ->get('/uppdrag?page=2')
            ->assertOk()
            ->assertViewHas('assignments', fn ($assignments): bool => $assignments->total() === 21 && $assignments->count() === 1)
            ->assertDontSee('Target Client outside tenant', false);
    }
}
