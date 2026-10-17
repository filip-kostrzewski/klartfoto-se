<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwedishDateInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_forms_show_swedish_dates_and_no_us_date_placeholder(): void
    {
        $this->travelTo('2026-10-17 09:00:00');
        $owner = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create([
            'performed_on' => '2026-10-16',
        ]);

        $create = $this->actingAs($owner)->get(route('assignments.create'));
        $create->assertOk()
            ->assertSee('placeholder="ÅÅÅÅ-MM-DD"', false)
            ->assertSee('lang="sv-SE"', false)
            ->assertSee('value="17 okt 2026"', false)
            ->assertDontSee('value="2026-10-17"', false)
            ->assertDontSee('type="date"', false)
            ->assertDontSee('mm/dd/yyyy', false);

        $this->actingAs($owner)->get(route('assignments.edit', $assignment))
            ->assertOk()
            ->assertSee('value="16 okt 2026"', false)
            ->assertDontSee('value="2026-10-16"', false)
            ->assertDontSee('type="date"', false)
            ->assertDontSee('mm/dd/yyyy', false);

        $this->actingAs($owner)->get(route('assignments.index', [
            'from' => '2026-10-15',
            'to' => '17 okt 2026',
        ]))
            ->assertOk()
            ->assertSee('value="15 okt 2026"', false)
            ->assertSee('value="17 okt 2026"', false)
            ->assertDontSee('type="date"', false)
            ->assertDontSee('mm/dd/yyyy', false)
            ->assertViewHas('filters', function (array $filters): bool {
                return $filters['from'] === '2026-10-15' && $filters['to'] === '2026-10-17';
            });
    }

    public function test_swedish_date_text_is_stored_as_an_iso_date(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('assignments.store'), [
            'customer_name' => 'Datum Kund',
            'address' => 'Datavägen 1',
            'performed_on' => '17 oktober 2026',
        ])->assertRedirect();

        $assignment = Assignment::query()->sole();
        $this->assertSame('2026-10-17', $assignment->performed_on->toDateString());

        $this->actingAs($owner)->put(route('assignments.update', $assignment), [
            'customer_name' => 'Datum Kund',
            'address' => 'Datavägen 1',
            'performed_on' => '2 nov 2026',
        ])->assertRedirect();

        $this->assertSame('2026-11-02', $assignment->fresh()->performed_on->toDateString());
    }

    public function test_impossible_swedish_date_is_rejected(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->from(route('assignments.create'))
            ->post(route('assignments.store'), [
                'customer_name' => 'Datum Kund',
                'address' => 'Datavägen 1',
                'performed_on' => '32 okt 2026',
            ])
            ->assertRedirect(route('assignments.create'))
            ->assertSessionHasErrors('performed_on');

        $this->assertSame(0, Assignment::query()->count());
    }
}
