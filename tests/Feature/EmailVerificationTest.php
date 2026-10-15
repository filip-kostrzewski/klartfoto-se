<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SwedishResetPassword;
use App\Notifications\SwedishVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_synchronous_verification_mail_and_keeps_access_open(): void
    {
        Notification::fake();

        $this->post('/register', [
            'company_name' => 'Glans Städ AB',
            'name' => 'Anna Andersson',
            'email' => 'anna@glans.se',
            'password' => 'hemligt-losen',
            'password_confirmation' => 'hemligt-losen',
            'privacy' => '1',
        ])->assertRedirect(route('assignments.index'));

        $user = User::query()->where('email', 'anna@glans.se')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertNotInstanceOf(ShouldQueue::class, new SwedishVerifyEmail);
        $this->assertNotInstanceOf(ShouldQueue::class, new SwedishResetPassword('token'));

        Notification::assertSentTo($user, SwedishVerifyEmail::class);
        Notification::assertCount(1);

        $this->actingAs($user)->get('/uppdrag')
            ->assertOk()
            ->assertSee('Bekräfta din e-postadress', false)
            ->assertSee('anna@glans.se', false)
            ->assertSee('Skicka länken igen', false)
            ->assertSee('Byt e-postadress', false);
    }

    public function test_resend_and_email_change_keep_the_account_usable(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create([
            'email' => 'anna@glans.se',
            'name' => 'Anna Andersson',
        ]);
        $other = User::factory()->create(['email' => 'annan@glans.se']);

        $this->actingAs($user)->get('/uppdrag')->assertOk();

        $this->actingAs($user)->from('/uppdrag')->post('/epost/skicka-igen')
            ->assertRedirect('/uppdrag')
            ->assertSessionHas('status', 'Vi har skickat en ny länk till anna@glans.se.');

        Notification::assertSentToTimes($user, SwedishVerifyEmail::class, 1);

        $this->actingAs($user)->from('/uppdrag')->put('/epost', [
            'account_email' => 'annan@glans.se',
        ])->assertRedirect('/uppdrag')
            ->assertSessionHasErrors('account_email', errorBag: 'accountEmail');

        $this->assertSame('anna@glans.se', $user->refresh()->email);
        $this->assertSame('annan@glans.se', $other->refresh()->email);

        $this->actingAs($user)->from('/uppdrag')->put('/epost', [
            'account_email' => 'ny@glans.se',
        ])->assertRedirect('/uppdrag')
            ->assertSessionHas('status', 'E-postadressen är uppdaterad. Bekräfta den via mejlet.');

        $user->refresh();
        $this->assertSame('ny@glans.se', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentToTimes($user, SwedishVerifyEmail::class, 2);

        $this->actingAs($user)->get('/installningar')
            ->assertOk()
            ->assertSee('ny@glans.se', false)
            ->assertSee('Anna Andersson', false);
    }

    public function test_signed_link_confirms_the_address_without_blocking_guests(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'anna@glans.se']);

        $this->get('/epost/bekrafta/'.$user->id.'/'.sha1($user->email))
            ->assertForbidden();
        $this->assertNull($user->refresh()->email_verified_at);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'E-postadressen är bekräftad.');

        $this->assertNotNull($user->refresh()->email_verified_at);

        $this->actingAs($user)->get('/uppdrag')
            ->assertOk()
            ->assertDontSee('Bekräfta din e-postadress', false);
    }

    public function test_guest_cannot_resend_or_change_the_address(): void
    {
        $this->post('/epost/skicka-igen')->assertRedirect(route('login'));
        $this->put('/epost', ['account_email' => 'ny@glans.se'])->assertRedirect(route('login'));
    }
}
