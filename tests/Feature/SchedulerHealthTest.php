<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchedulerHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_sees_when_the_scheduler_last_ran_and_how_many_jobs_are_waiting(): void
    {
        $this->get('/installningar')->assertRedirect(route('login'));

        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertSee('Schemaläggaren har inte körts. Inga jobb väntar.', false);

        $this->actingAs($member)->get('/installningar')
            ->assertOk()
            ->assertDontSee('Schemaläggaren', false)
            ->assertDontSee('jobb väntar', false);

        $this->travelTo('2026-10-16 12:00:00');
        Artisan::call('schedule:run');

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertSee('Schemaläggaren kördes 16 oktober 2026 kl. 14:00. 1 jobb väntar.', false);

        Artisan::call('schedule:list');
        $list = Artisan::output();
        $this->assertStringContainsString('scheduler-heartbeat', $list);
        $this->assertStringContainsString('app:purge-expired-early-access-signups', $list);
    }
}
