<?php

namespace Tests\Feature;

use App\Mail\TrialEnded;
use App\Mail\TrialEndingSoon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TrialReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_get_one_reminder_before_the_trial_ends_and_one_after(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-17 09:00:00');

        $owner = User::factory()->create();
        $member = User::factory()->memberOf($owner->company)->create();
        $owner->company->forceFill(['trial_ends_at' => '2026-10-19 21:59:59'])->save();

        $farAway = User::factory()->create();
        $farAway->company->forceFill(['trial_ends_at' => '2026-10-30 21:59:59'])->save();

        $paying = User::factory()->create();
        $paying->company->forceFill(['trial_ends_at' => '2026-10-18 21:59:59', 'paid_until' => '2026-12-31 23:59:59'])->save();

        $this->artisan('billing:trial-reminders')->assertSuccessful();
        $this->artisan('billing:trial-reminders')->assertSuccessful();

        Mail::assertSentTimes(TrialEndingSoon::class, 1);
        Mail::assertSent(TrialEndingSoon::class, fn (TrialEndingSoon $mail): bool => $mail->hasTo($owner->email)
            && ! $mail->hasTo($member->email)
            && $mail->company->is($owner->company));
        Mail::assertNotSent(TrialEnded::class);
        $this->assertNotNull($owner->company->fresh()->trial_ending_notified_at);
        $this->assertNull($farAway->company->fresh()->trial_ending_notified_at);
        $this->assertNull($paying->company->fresh()->trial_ending_notified_at);

        $mail = new TrialEndingSoon($owner->company->fresh());
        $mail->assertSeeInHtml('Provperioden slutar 19 oktober 2026');
        $mail->assertSeeInHtml('390 kr per månad');
        $mail->assertSeeInHtml('790 kr per månad');
        $mail->assertSeeInHtml('/installningar/abonnemang');
        $this->assertSame('Er provperiod i Klartfoto slutar om 3 dagar', $mail->envelope()->subject);

        $this->travelTo('2026-10-20 09:00:00');
        $this->artisan('billing:trial-reminders')->assertSuccessful();
        $this->artisan('billing:trial-reminders')->assertSuccessful();

        Mail::assertSentTimes(TrialEnded::class, 1);
        Mail::assertSent(TrialEnded::class, fn (TrialEnded $mail): bool => $mail->hasTo($owner->email));
        $this->assertNotNull($owner->company->fresh()->trial_ended_notified_at);

        $endedMail = new TrialEnded($owner->company->fresh());
        $endedMail->assertSeeInHtml('Provperioden har löpt ut');
        $endedMail->assertSeeInHtml('Aktivera plan');
    }

    public function test_companies_with_a_pending_invoice_request_or_an_old_trial_are_not_nagged(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-17 09:00:00');

        $requested = User::factory()->create();
        $requested->company->forceFill([
            'trial_ends_at' => '2026-10-16 21:59:59',
            'requested_plan' => 'team',
            'requested_interval' => 'year',
            'requested_at' => now(),
        ])->save();

        $stale = User::factory()->create();
        $stale->company->forceFill(['trial_ends_at' => '2026-09-01 21:59:59'])->save();

        $this->artisan('billing:trial-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
