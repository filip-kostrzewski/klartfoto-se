<?php

namespace Tests\Feature;

use App\CompanyPlan;
use App\Mail\ManualBillingRequestReceived;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_request_is_encrypted_and_does_not_activate_a_plan(): void
    {
        Mail::fake();
        config(['klartfoto.billing_notify_email' => 'billing@example.test']);

        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill([
            'plan' => CompanyPlan::Start,
            'trial_ends_at' => now()->subDay(),
        ])->save();

        $this->actingAs($owner)->get('/installningar/abonnemang')
            ->assertOk()
            ->assertSee('href="/villkor"', false)
            ->assertSee('innan ni begär faktura', false);

        $this->from(route('settings.billing', absolute: false))
            ->actingAs($owner)
            ->post(route('settings.billing.request', absolute: false), $this->validRequest())
            ->assertRedirect(route('settings.billing', absolute: false))
            ->assertSessionHas('status', 'Tack. Er fakturaförfrågan är registrerad.');

        $company->refresh();
        $this->assertSame(CompanyPlan::Start, $company->plan);
        $this->assertNull($company->paid_until);
        $this->assertSame('start', $company->requested_plan);
        $this->assertNotNull($company->requested_at);
        $this->assertFalse($company->canManageJobs());
        $this->assertSame('Billing AB', $company->billing_details['company_name']);

        $storedDetails = DB::table('companies')->where('id', $company->id)->value('billing_details');
        $this->assertIsString($storedDetails);
        $this->assertStringNotContainsString('Billing AB', $storedDetails);
        $this->assertStringNotContainsString('invoice@example.test', $storedDetails);

        Mail::assertSent(ManualBillingRequestReceived::class, fn (ManualBillingRequestReceived $mail): bool => $mail->hasTo('billing@example.test')
            && $mail->companyId === $company->id
            && $mail->plan === CompanyPlan::Start,
        );
    }

    public function test_request_is_still_saved_when_no_notification_address_is_configured(): void
    {
        Mail::fake();
        config([
            'klartfoto.billing_notify_email' => null,
            'klartfoto.contact_email' => null,
        ]);

        $owner = User::factory()->create();
        $this->actingAs($owner)
            ->post(route('settings.billing.request', absolute: false), $this->validRequest())
            ->assertRedirect();

        $this->assertSame('start', $owner->company->refresh()->requested_plan);
        Mail::assertNothingOutgoing();

        $this->assertSame(0, Artisan::call('billing:requests'));
        $cliOutput = preg_replace('/\s+/u', '', Artisan::output()) ?? '';
        $this->assertStringContainsString((string) $owner->company_id, $cliOutput);
        $this->assertStringContainsString('invoice@example.test', $cliOutput);
    }

    public function test_members_can_read_billing_status_but_cannot_see_invoice_details_or_request_a_plan(): void
    {
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $this->storePendingRequest($company);
        $member = User::factory()->memberOf($company)->create();

        $this->actingAs($owner)->get(route('settings.billing', absolute: false))
            ->assertSee('Fakturaförfrågan väntar', false)
            ->assertDontSee('Ett annat abonnemang är aktivt för kontot', false);

        $this->actingAs($member)->get(route('settings.billing', absolute: false))
            ->assertOk()
            ->assertSee('Fakturaförfrågan väntar', false)
            ->assertSee('Endast kontoägaren kan begära en faktura.', false)
            ->assertDontSee('Billing AB', false)
            ->assertDontSee('invoice@example.test', false);

        $this->actingAs($member)
            ->post(route('settings.billing.request', absolute: false), $this->validRequest())
            ->assertForbidden();

        $this->assertSame('Billing AB', $company->fresh()->billing_details['company_name']);
    }

    public function test_active_manual_plan_takes_precedence_over_an_unexpired_trial_in_billing_status(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => '2026-11-15 23:59:59',
            'paid_until' => '2026-12-31 23:59:59',
        ])->save();

        $this->actingAs($owner)->get(route('settings.billing', absolute: false))
            ->assertSee('billing-status-active', false)
            ->assertSee('Abonnemanget är aktivt', false)
            ->assertSee('Planen gäller till 31 december 2026.', false)
            ->assertDontSee('Provperiod pågår', false)
            ->assertDontSee('Gratis till 15 november 2026.', false);
    }

    public function test_invalid_invoice_request_and_duplicate_request_do_not_overwrite_existing_details(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);

        $this->actingAs($owner)->from(route('settings.billing', absolute: false))
            ->post(route('settings.billing.request', absolute: false), [
                ...$this->validRequest(),
                'invoice_email' => 'not-an-email',
                'billing_address' => '',
            ])
            ->assertRedirect(route('settings.billing', absolute: false))
            ->assertSessionHasErrors(['invoice_email', 'billing_address']);

        $this->assertNull($company->refresh()->requested_plan);

        $this->actingAs($owner)->post(route('settings.billing.request', absolute: false), $this->validRequest())
            ->assertRedirect();

        $company->refresh();
        $requestedAt = $company->requested_at?->toDateTimeString();
        $this->actingAs($owner)->from(route('settings.billing', absolute: false))
            ->post(route('settings.billing.request', absolute: false), [
                ...$this->validRequest(),
                'plan' => 'team',
                'billing_company_name' => 'Replacement AB',
            ])
            ->assertRedirect(route('settings.billing', absolute: false))
            ->assertSessionHas('status');

        $company->refresh();
        $this->assertSame('start', $company->requested_plan);
        $this->assertSame($requestedAt, $company->requested_at?->toDateTimeString());
        $this->assertSame('Billing AB', $company->billing_details['company_name']);
    }

    public function test_start_activation_keeps_owner_access_but_blocks_existing_members_and_prevents_accidental_shortening(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        Storage::fake('local');

        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $member = User::factory()->memberOf($company)->create();
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => now()->subDay(),
            'paid_until' => '2026-11-30 23:59:59',
        ])->save();
        $this->storePendingRequest($company, 'start');

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'start',
            '--until' => '2026-11-20',
            '--invoice' => 'F-START-SHORT',
            '--by' => 'Ops Example',
        ])->assertFailed();

        $company->refresh();
        $this->assertSame(CompanyPlan::Team, $company->plan);
        $this->assertSame('2026-11-30', $company->paid_until?->toDateString());
        $this->assertSame('start', $company->requested_plan);
        $this->assertDatabaseCount('manual_billing_activations', 0);

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'start',
            '--until' => '2026-12-31',
            '--invoice' => 'F-START-001',
            '--by' => 'Ops Example',
        ])->assertSuccessful();

        $company->refresh();
        $this->assertSame(CompanyPlan::Start, $company->plan);
        $this->assertSame('2026-12-31', $company->paid_until?->toDateString());
        $this->assertNull($company->requested_plan);
        $this->assertSame('2026-10-15', $company->trial_ends_at?->toDateString());
        $this->assertTrue($owner->fresh()->canManageJobs());
        $this->assertFalse($member->fresh()->canManageJobs());
        $this->assertFalse($member->fresh()->maySignIn());
        $this->assertSame(1, $company->seatLimit());
        $this->assertDatabaseHas('manual_billing_activations', [
            'company_id' => $company->id,
            'plan' => 'start',
            'invoice_reference' => 'F-START-001',
            'activated_by' => 'Ops Example',
        ]);
        $activation = DB::table('manual_billing_activations')->where('invoice_reference', 'F-START-001')->first();
        $this->assertSame('2026-10-16 12:00:00', $activation?->starts_at);

        $assignment = Assignment::factory()->for($owner)->create();
        $photoPath = 'assignments/'.$assignment->id.'/existing.jpg';
        Storage::disk('local')->put($photoPath, 'existing photo');
        $photo = $assignment->photos()->create([
            'phase' => 'before',
            'path' => $photoPath,
            'sort_order' => 1,
        ]);
        $this->actingAs($member)->get('/uppdrag')
            ->assertRedirect(route('login'));

        $this->actingAs($member)->post('/uppdrag', [
            'customer_name' => 'Unauthorized customer',
            'address' => 'Storgatan 2, 111 22 Stockholm',
            'performed_on' => '2026-10-16',
        ])->assertRedirect(route('login'));

        $this->actingAs($member)->post(route('assignments.photos.store', $assignment), [
            'before_photos' => [UploadedFile::fake()->image('before.jpg')],
            'after_photos' => [UploadedFile::fake()->image('after.jpg')],
        ])->assertRedirect(route('login'));

        $this->actingAs($member)->delete(route('assignments.photos.destroy', [
            'assignment' => $assignment,
            'photo' => $photo,
        ]))->assertRedirect(route('login'));

        $this->assertDatabaseCount('assignments', 1);
        $this->assertDatabaseCount('assignment_photos', 1);
        Storage::disk('local')->assertExists($photoPath);
    }

    public function test_team_activation_restores_member_access_and_team_features(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $member = User::factory()->memberOf($company)->create();
        $company->forceFill([
            'trial_ends_at' => now()->subDay(),
            'paid_until' => '2026-11-30 23:59:59',
        ])->save();
        $this->storePendingRequest($company, 'team');

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'team',
            '--until' => '2026-12-16',
            '--invoice' => 'F-TEAM-001',
            '--by' => 'Ops Example',
        ])->assertSuccessful();

        $company->refresh();
        $this->assertTrue($company->allowsTeamFeatures());
        $this->assertTrue($company->canManageJobs());
        $this->assertTrue($member->fresh()->canManageJobs());
        $this->assertSame(10, $company->seatLimit());
        $this->assertSame('2026-10-15', $company->trial_ends_at?->toDateString());
        $activation = DB::table('manual_billing_activations')->where('invoice_reference', 'F-TEAM-001')->first();
        $this->assertSame('2026-10-16 12:00:00', $activation?->starts_at);
    }

    public function test_active_team_owner_can_request_start_without_changing_access_until_operator_activation(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        Mail::fake();
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $member = User::factory()->memberOf($company)->create();
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => now()->subDay(),
            'paid_until' => '2026-11-30 23:59:59',
        ])->save();

        $this->actingAs($owner)->post(route('settings.billing.request', absolute: false), $this->validRequest('start'))
            ->assertRedirect();

        $company->refresh();
        $this->assertSame(CompanyPlan::Team, $company->plan);
        $this->assertSame('2026-11-30', $company->paid_until?->toDateString());
        $this->assertTrue($member->fresh()->canManageJobs());

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'start',
            '--until' => '2026-12-31',
            '--invoice' => 'F-TEAM-TO-START',
            '--by' => 'Ops Example',
        ])->assertSuccessful();

        $company->refresh();
        $this->assertSame(CompanyPlan::Start, $company->plan);
        $this->assertSame('2026-12-31', $company->paid_until?->toDateString());
        $this->assertFalse($member->fresh()->canManageJobs());
        $activation = DB::table('manual_billing_activations')->where('invoice_reference', 'F-TEAM-TO-START')->first();
        $this->assertSame('2026-10-16 12:00:00', $activation?->starts_at);
    }

    public function test_active_same_plan_invoice_request_extends_the_existing_period_contiguously(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        Mail::fake();
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => now()->subDay(),
            'paid_until' => '2026-11-30 23:59:59',
        ])->save();

        $this->actingAs($owner)->post(route('settings.billing.request', absolute: false), $this->validRequest('team'))
            ->assertRedirect();

        $this->assertSame('2026-11-30', $company->refresh()->paid_until?->toDateString());
        $this->assertSame(CompanyPlan::Team, $company->plan);

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'team',
            '--until' => '2026-12-31',
            '--invoice' => 'F-TEAM-EXTENSION',
            '--by' => 'Ops Example',
        ])->assertSuccessful();

        $activation = DB::table('manual_billing_activations')->where('invoice_reference', 'F-TEAM-EXTENSION')->first();
        $this->assertSame('2026-11-30 23:59:59', $activation?->starts_at);
        $this->assertSame('2026-12-31', $company->refresh()->paid_until?->toDateString());
    }

    public function test_invoice_requests_are_throttled_to_five_per_hour_per_owner(): void
    {
        Mail::fake();
        config(['klartfoto.billing_notify_email' => 'billing@example.test']);
        $owner = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($owner)->post(route('settings.billing.request', absolute: false), $this->validRequest())
                ->assertRedirect();
        }

        $this->actingAs($owner)->post(route('settings.billing.request', absolute: false), $this->validRequest())
            ->assertTooManyRequests();

        Mail::assertSentTimes(ManualBillingRequestReceived::class, 1);
    }

    public function test_expired_paid_reports_remain_readable_and_owner_can_request_and_activate_a_renewal(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        Mail::fake();
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => now()->subDays(40),
            'paid_until' => now()->subSecond(),
        ])->save();
        $assignment = Assignment::factory()->for($owner)->create();

        $this->assertFalse($owner->fresh()->canManageJobs());
        $this->assertFalse($company->fresh()->allowsTeamFeatures());
        $this->get('/r/'.$assignment->public_token)->assertOk()->assertSee('Anna Andersson', false);

        $this->actingAs($owner)->post(route('settings.billing.request', absolute: false), $this->validRequest('team'))
            ->assertRedirect();
        $this->assertFalse($company->fresh()->canManageJobs());

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'team',
            '--until' => '2026-11-16',
            '--invoice' => 'F-TEAM-RENEWAL',
            '--by' => 'Ops Example',
        ])->assertSuccessful();

        $company->refresh();
        $this->assertTrue($owner->fresh()->canManageJobs());
        $this->assertTrue($company->allowsTeamFeatures());
        $this->assertDatabaseCount('manual_billing_activations', 1);
        $activation = DB::table('manual_billing_activations')->where('invoice_reference', 'F-TEAM-RENEWAL')->first();
        $this->assertSame('2026-10-16 12:00:00', $activation?->starts_at);
    }

    public function test_activation_validates_company_request_plan_dates_and_unique_invoice_reference(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $this->storePendingRequest($company, 'start');
        $otherCompanyOwner = User::factory()->create();
        $otherCompany = $otherCompanyOwner->company;
        $this->assertInstanceOf(Company::class, $otherCompany);

        $this->artisan('billing:activate', [
            'company' => $otherCompany->id,
            '--plan' => 'start',
            '--until' => '2026-11-16',
            '--invoice' => 'F-NONE',
            '--by' => 'Ops Example',
        ])->assertFailed();

        $this->artisan('billing:activate', [
            'company' => $otherCompany->id,
            '--plan' => 'start',
            '--until' => '2026-11-16',
            '--invoice' => 'F-NONE-RENEW',
            '--by' => 'Ops Example',
            '--renew' => true,
        ])->assertFailed();

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'team',
            '--until' => '2026-11-16',
            '--invoice' => 'F-MISMATCH',
            '--by' => 'Ops Example',
        ])->assertFailed();

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'start',
            '--until' => '2026-10-16',
            '--invoice' => 'F-PAST',
            '--by' => 'Ops Example',
        ])->assertFailed();

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'start',
            '--until' => '2026-11-16',
            '--invoice' => 'F-UNIQUE',
            '--by' => 'Ops Example',
        ])->assertSuccessful();

        $nextOwner = User::factory()->create();
        $this->storePendingRequest($nextOwner->company, 'start');
        $this->artisan('billing:activate', [
            'company' => $nextOwner->company_id,
            '--plan' => 'start',
            '--until' => '2026-11-16',
            '--invoice' => 'F-UNIQUE',
            '--by' => 'Ops Example',
        ])->assertFailed();

        $this->assertNull($nextOwner->company->refresh()->paid_until);
        $this->assertSame('start', $nextOwner->company->requested_plan);
        $this->assertDatabaseCount('manual_billing_activations', 1);
    }

    public function test_operator_can_renew_a_previous_manual_plan_without_a_new_request(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => now()->subDays(40),
            'paid_until' => '2026-10-01 23:59:59',
        ])->save();

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'team',
            '--until' => '2026-11-16',
            '--invoice' => 'F-TEAM-RENEW-NO-REQUEST',
            '--by' => 'Ops Example',
            '--renew' => true,
        ])->assertSuccessful();

        $company->refresh();
        $this->assertSame(CompanyPlan::Team, $company->plan);
        $this->assertSame('2026-11-16', $company->paid_until?->toDateString());
        $this->assertNull($company->requested_plan);
        $this->assertTrue($owner->fresh()->canManageJobs());
        $this->assertDatabaseHas('manual_billing_activations', [
            'company_id' => $company->id,
            'plan' => 'team',
            'invoice_reference' => 'F-TEAM-RENEW-NO-REQUEST',
        ]);
    }

    public function test_renew_without_request_rejects_a_plan_change_or_a_non_extension(): void
    {
        $this->travelTo('2026-10-16 12:00:00');
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill([
            'plan' => CompanyPlan::Start,
            'trial_ends_at' => now()->subDays(40),
            'paid_until' => '2026-11-30 23:59:59',
        ])->save();

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'team',
            '--until' => '2026-12-31',
            '--invoice' => 'F-INVALID-PLAN-RENEW',
            '--by' => 'Ops Example',
            '--renew' => true,
        ])->assertFailed();

        $this->artisan('billing:activate', [
            'company' => $company->id,
            '--plan' => 'start',
            '--until' => '2026-11-30',
            '--invoice' => 'F-INVALID-DATE-RENEW',
            '--by' => 'Ops Example',
            '--renew' => true,
        ])->assertFailed();

        $this->assertSame(CompanyPlan::Start, $company->refresh()->plan);
        $this->assertSame('2026-11-30', $company->paid_until?->toDateString());
        $this->assertDatabaseCount('manual_billing_activations', 0);
    }

    private function storePendingRequest(Company $company, string $plan = 'start'): void
    {
        $company->forceFill([
            'requested_plan' => $plan,
            'requested_at' => now(),
            'billing_details' => [
                'company_name' => 'Billing AB',
                'organisation_number' => '556000-0000',
                'invoice_email' => 'invoice@example.test',
                'billing_address' => "Storgatan 1\n111 22 Stockholm\nSweden",
            ],
        ])->save();
    }

    /**
     * @return array{plan: string, billing_company_name: string, organisation_number: string, invoice_email: string, billing_address: string}
     */
    private function validRequest(string $plan = 'start'): array
    {
        return [
            'plan' => $plan,
            'billing_company_name' => 'Billing AB',
            'organisation_number' => '556000-0000',
            'invoice_email' => 'INVOICE@example.test',
            'billing_address' => "Storgatan 1\n111 22 Stockholm\nSweden",
        ];
    }
}
