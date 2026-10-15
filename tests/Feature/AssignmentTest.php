<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login_before_creating_a_job(): void
    {
        $this->get('/uppdrag')->assertRedirect(route('login'));
        $this->post('/uppdrag', [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_company_creates_a_job_and_gets_a_share_link(): void
    {
        $user = User::factory()->create(['name' => 'Glans Städ AB']);

        $response = $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
        ]);

        $assignment = Assignment::query()->first();
        $this->assertNotNull($assignment);
        $this->assertSame($user->id, $assignment->user_id);
        $this->assertSame('Anna Andersson', $assignment->customer_name);
        $this->assertSame('Storgatan 1, 111 22 Stockholm', $assignment->address);
        $this->assertSame('2026-10-01', $assignment->performed_on->toDateString());
        $this->assertSame(48, strlen($assignment->public_token));
        $this->assertNotSame((string) $assignment->id, $assignment->public_token);

        $response->assertRedirect(route('assignments.show', $assignment));
        $this->actingAs($user)->get(route('assignments.show', $assignment))
            ->assertOk()
            ->assertSee(route('reports.show', ['token' => $assignment->public_token]), false)
            ->assertSee('Anna Andersson', false);
    }

    public function test_job_form_is_built_for_phone_photos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/uppdrag/nytt')
            ->assertOk()
            ->assertSee('accept="image/*"', false)
            ->assertSee('capture', false)
            ->assertSee('multiple', false)
            ->assertSee('name="before_photos[]"', false)
            ->assertSee('name="after_photos[]"', false);
    }

    public function test_incomplete_job_is_rejected_in_swedish(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/uppdrag/nytt')->post('/uppdrag', [])
            ->assertRedirect('/uppdrag/nytt')
            ->assertSessionHasErrors(['customer_name', 'address', 'performed_on']);

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_another_company_cannot_open_the_job(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('assignments.show', $assignment))->assertNotFound();
    }

    public function test_expired_trial_cannot_create_another_job(): void
    {
        $user = User::factory()->expiredTrial()->create();

        $this->actingAs($user)->from('/uppdrag/nytt')->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
        ])->assertRedirect('/uppdrag/nytt')
            ->assertSessionHasErrors('trial');

        $this->assertDatabaseCount('assignments', 0);
    }
}
