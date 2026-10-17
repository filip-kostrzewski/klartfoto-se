<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use App\PhotoPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmptyPhotoPairTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_remove_an_empty_photo_pair_and_not_one_with_photos(): void
    {
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();
        $place = $assignment->places()->create([
            'name' => 'Kök',
            'sort_order' => 1,
        ]);
        $emptyPair = $place->photoPairs()->create(['sort_order' => 1]);
        $filledPair = $place->photoPairs()->create(['sort_order' => 2]);
        $assignment->photos()->create([
            'assignment_photo_pair_id' => $filledPair->id,
            'phase' => PhotoPhase::Before,
            'path' => 'assignments/'.$assignment->id.'/before.jpg',
            'sort_order' => 1,
        ]);
        $other = User::factory()->create();

        $this->actingAs($owner)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Ta bort tomt bildpar', false);

        $this->actingAs($other)
            ->delete(route('assignments.places.pairs.destroy', [$assignment, $place, $emptyPair]))
            ->assertNotFound();
        $this->assertModelExists($emptyPair);

        $this->actingAs($owner)
            ->delete(route('assignments.places.pairs.destroy', [$assignment, $place, $filledPair]))
            ->assertRedirect()
            ->assertSessionHasErrors('pair');
        $this->assertModelExists($filledPair);

        $this->actingAs($owner)
            ->delete(route('assignments.places.pairs.destroy', [$assignment, $place, $emptyPair]))
            ->assertRedirect()
            ->assertSessionHas('toast', 'Det tomma bildparet är borttaget.');

        $this->assertModelMissing($emptyPair);
        $this->assertModelExists($filledPair);
        $this->assertSame('draft', $assignment->fresh()->status);
    }
}
