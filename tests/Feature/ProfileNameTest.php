<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_change_the_name_shown_in_settings(): void
    {
        $owner = User::factory()->create(['name' => 'Anna Andersson']);

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertSee('Ditt namn', false)
            ->assertSee('name="name"', false)
            ->assertSee('value="Anna Andersson"', false)
            ->assertSee('Spara namn', false);

        $this->actingAs($owner)->put('/installningar/profil', [
            'name' => 'Anna Berg',
        ])->assertRedirect('/installningar')
            ->assertSessionHas('toast', 'Ditt namn är sparat.');

        $this->assertSame('Anna Berg', $owner->fresh()->name);

        $this->actingAs($owner)->get('/installningar')
            ->assertOk()
            ->assertSee('Ditt namn är sparat.', false)
            ->assertSee('value="Anna Berg"', false)
            ->assertSee('Anna Berg', false);
    }

    public function test_member_can_change_only_the_name_in_their_profile(): void
    {
        $owner = User::factory()->create([
            'name' => 'Anna Andersson',
            'email' => 'anna@glans.se',
        ]);
        $member = User::factory()->memberOf($owner->company)->create([
            'name' => 'Kim Berg',
            'email' => 'kim@glans.se',
        ]);

        $this->actingAs($member)->get('/installningar')
            ->assertOk()
            ->assertSee('Din profil', false)
            ->assertSee('Ditt namn', false)
            ->assertSee('name="name"', false)
            ->assertSee('value="Kim Berg"', false)
            ->assertSee('kim@glans.se', false)
            ->assertDontSee('anna@glans.se', false);

        $this->actingAs($member)->put('/installningar/profil', [
            'name' => 'Kim Ek',
        ])->assertRedirect('/installningar')
            ->assertSessionHas('toast', 'Ditt namn är sparat.');

        $this->assertSame('Kim Ek', $member->fresh()->name);
        $this->assertSame('Anna Andersson', $owner->fresh()->name);
    }

    public function test_a_blank_name_is_rejected_in_swedish(): void
    {
        $owner = User::factory()->create(['name' => 'Anna Andersson']);

        $this->actingAs($owner)->from('/installningar')->put('/installningar/profil', [
            'name' => '   ',
        ])->assertRedirect('/installningar')
            ->assertSessionHasErrors(['name' => 'Ange ditt namn.']);

        $this->actingAs($owner)->from('/installningar')->put('/installningar/profil', [
            'name' => str_repeat('a', 121),
        ])->assertRedirect('/installningar')
            ->assertSessionHasErrors(['name' => 'Namnet får vara högst 120 tecken.']);

        $this->assertSame('Anna Andersson', $owner->fresh()->name);
    }

    public function test_a_guest_cannot_change_a_name(): void
    {
        $this->put('/installningar/profil', ['name' => 'Anna'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('users', 0);
    }
}
