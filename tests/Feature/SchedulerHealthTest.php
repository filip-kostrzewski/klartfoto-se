<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SchedulerHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scheduler_logs_its_heartbeat_and_settings_does_not_show_it(): void
    {
        $this->get('/installningar')->assertRedirect(route('login'));

        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertDontSee('Schemaläggaren', false)
            ->assertDontSee('jobb väntar', false);

        $this->actingAs($member)->get('/installningar')
            ->assertOk()
            ->assertDontSee('Schemaläggaren', false)
            ->assertDontSee('jobb väntar', false);

        $logged = null;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if (str_contains($event->message, 'Schemaläggaren kördes')) {
                $logged = $event->message;
            }
        });

        $this->travelTo('2026-10-16 12:00:00');
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        Artisan::call('schedule:run');

        $this->assertSame('Schemaläggaren kördes 16 oktober 2026 kl. 14:00. 1 jobb väntar.', $logged);

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertDontSee('Schemaläggaren', false)
            ->assertDontSee('jobb väntar', false);

        Artisan::call('schedule:list');
        $list = Artisan::output();
        $this->assertStringContainsString('scheduler-heartbeat', $list);
        $this->assertStringContainsString('app:purge-expired-early-access-signups', $list);
        $this->assertStringContainsString('app:prune-spam-submissions', $list);
        $this->assertStringContainsString('billing:trial-reminders', $list);
    }
}
