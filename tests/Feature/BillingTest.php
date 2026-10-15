<?php

namespace Tests\Feature;

use App\CompanyPlan;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;
use Mockery;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\StripeClient;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private ?array $stripeCustomerOptions = null;

    private ?array $stripeSessionPayload = null;

    private ?array $stripePortalOptions = null;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cashier::$calculatesTaxes = false;
        parent::tearDown();
    }

    public function test_billing_stays_dormant_until_stripe_is_configured(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $user = User::factory()->create();
        $company = $user->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'trial_ends_at' => '2026-11-14 12:00:00',
        ])->save();

        $this->actingAs($user)->get('/installningar')
            ->assertOk()
            ->assertSee('Abonnemang', false)
            ->assertSee('Nuvarande plan: Team', false)
            ->assertSee('290 kr/mån exkl. moms', false)
            ->assertSee('590 kr/mån exkl. moms', false)
            ->assertSee('1 användare', false)
            ->assertSee('Upp till 10 användare och egen logotyp', false)
            ->assertSee('Ingen bindningstid', false)
            ->assertSee('Betalning aktiveras snart', false)
            ->assertSee('14 november 2026', false)
            ->assertDontSee('Välj Start', false);

        $this->actingAs($user)->get('/installningar/abonnemang')
            ->assertOk()
            ->assertSee('Betalning aktiveras snart', false)
            ->assertSee('30 dagar kvar', false)
            ->assertSee('14 november 2026', false)
            ->assertSee('Nuvarande plan: Team', false)
            ->assertSee('290 kr/mån exkl. moms', false)
            ->assertSee('590 kr/mån exkl. moms', false)
            ->assertDontSee('Välj Start', false)
            ->assertDontSee('Betalningen är mottagen', false);

        $this->actingAs($user)->from('/installningar/abonnemang')
            ->post('/installningar/abonnemang', ['plan' => 'start'])
            ->assertRedirect('/installningar/abonnemang')
            ->assertSessionHas('status', 'Betalning aktiveras snart');

        $company->refresh();
        $this->assertSame(CompanyPlan::Team, $company->plan);
        $this->assertNull($company->stripe_id);
        $this->assertTrue($company->trial_ends_at?->isFuture());

        $company->forceFill(['stripe_id' => 'cus_test'])->save();

        $this->postJson('/stripe/webhook', $this->subscriptionPayload('customer.subscription.created', 'price_start'))
            ->assertForbidden();

        $company->refresh();
        $this->assertSame(CompanyPlan::Team, $company->plan);
        $this->assertTrue($company->trial_ends_at?->isFuture());
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_guest_and_member_cannot_start_checkout(): void
    {
        $this->enableBilling();
        $owner = User::factory()->create();
        $company = $owner->company;
        $this->assertInstanceOf(Company::class, $company);
        $member = User::factory()->memberOf($company)->create();

        $this->get('/installningar/abonnemang')->assertRedirect(route('login'));

        $this->actingAs($member)->get('/installningar/abonnemang')
            ->assertForbidden();

        $this->actingAs($member)->post('/installningar/abonnemang', ['plan' => 'team'])
            ->assertForbidden();

        $this->assertNull($company->refresh()->stripe_id);
    }

    public function test_owner_is_sent_to_stripe_checkout_for_start_without_a_stripe_trial(): void
    {
        $this->enableBilling();
        $this->fakeStripe(expectCustomer: true, expectCheckout: true);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/installningar/abonnemang', ['plan' => 'start'])
            ->assertRedirect('https://checkout.stripe.test/c/pay/cs_test');

        $this->assertSame($user->email, $this->stripeCustomerOptions['email'] ?? null);
        $this->assertSame($user->company?->name, $this->stripeCustomerOptions['name'] ?? null);
        $this->assertSame(['sv'], $this->stripeCustomerOptions['preferred_locales'] ?? null);
        $this->assertArrayNotHasKey('address', $this->stripeCustomerOptions ?? []);
        $this->assertArrayNotHasKey('automatic_tax', $this->stripeSessionPayload ?? []);
        $this->assertSame('sv', $this->stripeSessionPayload['locale'] ?? null);
        $this->assertSame('price_start', $this->stripeSessionPayload['line_items'][0]['price'] ?? null);
        $this->assertArrayNotHasKey('trial_end', $this->stripeSessionPayload['subscription_data'] ?? []);
        $this->assertSame('cus_test', $user->company?->refresh()->stripe_id);
    }

    public function test_checkout_adds_swedish_vat_only_when_stripe_tax_is_enabled(): void
    {
        $this->enableBilling(tax: true);
        $this->fakeStripe(expectCustomer: true, expectCheckout: true);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/installningar/abonnemang')
            ->assertOk()
            ->assertSee('Svensk moms läggs till i kassan', false);

        $this->actingAs($user)->post('/installningar/abonnemang', ['plan' => 'team'])
            ->assertRedirect('https://checkout.stripe.test/c/pay/cs_test');

        $this->assertSame('SE', $this->stripeCustomerOptions['address']['country'] ?? null);
        $this->assertTrue($this->stripeSessionPayload['automatic_tax']['enabled'] ?? false);
        $this->assertSame('price_team', $this->stripeSessionPayload['line_items'][0]['price'] ?? null);
    }

    public function test_existing_subscriber_is_sent_to_the_customer_portal(): void
    {
        $this->enableBilling();
        $this->fakeStripe(expectPortal: true);
        $user = User::factory()->create();
        $company = $user->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill(['stripe_id' => 'cus_test'])->save();
        $company->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_existing',
            'stripe_status' => 'active',
            'stripe_price' => 'price_start',
            'quantity' => 1,
        ]);

        $this->actingAs($user)->get('/installningar/abonnemang')
            ->assertOk()
            ->assertSee('Hantera betalning', false);

        $this->actingAs($user)->post('/installningar/abonnemang', ['plan' => 'team'])
            ->assertRedirect('https://billing.stripe.test/session');

        $this->actingAs($user)->post('/installningar/abonnemang/portal')
            ->assertRedirect('https://billing.stripe.test/session');

        $this->assertSame('cus_test', $this->stripePortalOptions['customer'] ?? null);
        $this->assertStringContainsString('/installningar/abonnemang', (string) ($this->stripePortalOptions['return_url'] ?? ''));
        $this->assertSame(1, $company->subscriptions()->count());
    }

    public function test_invalid_plan_is_rejected_in_swedish(): void
    {
        $this->enableBilling();
        $user = User::factory()->create();

        $this->actingAs($user)->from('/installningar/abonnemang')
            ->post('/installningar/abonnemang', ['plan' => 'enterprise'])
            ->assertRedirect('/installningar/abonnemang')
            ->assertSessionHasErrors([
                'plan' => 'Välj Start eller Team.',
            ]);

        $this->assertNull($user->company?->refresh()->stripe_id);
    }

    public function test_webhook_sets_the_team_plan_and_keeps_job_creation(): void
    {
        $this->enableBilling();
        $user = User::factory()->create();
        $company = $user->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill(['stripe_id' => 'cus_test'])->save();
        $this->assertTrue($company->trial_ends_at?->isFuture());

        $this->postJson('/stripe/webhook', $this->subscriptionPayload('customer.subscription.created', 'price_team'))
            ->assertOk();

        $company->refresh();
        $this->assertSame(CompanyPlan::Team, $company->plan);
        $this->assertNull($company->trial_ends_at);
        $this->assertTrue($company->allowsTeamFeatures());
        $this->assertTrue($company->canManageJobs());
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'stripe_id' => 'sub_test',
            'stripe_price' => 'price_team',
            'stripe_status' => 'active',
        ]);

        $this->actingAs($user)->get('/uppdrag')
            ->assertOk()
            ->assertSee('Nytt uppdrag', false)
            ->assertDontSee('Provperioden är slut', false);

        $this->actingAs($user)->post('/uppdrag', [
            'customer_name' => 'Anna Andersson',
            'address' => 'Storgatan 1, 111 22 Stockholm',
            'performed_on' => '2026-10-01',
        ])->assertRedirect();

        $this->assertDatabaseCount('assignments', 1);
    }

    public function test_webhook_moves_the_company_between_plans_and_back_to_start_when_canceled(): void
    {
        $this->enableBilling();
        $user = User::factory()->create();
        $company = $user->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill(['stripe_id' => 'cus_test'])->save();

        $this->postJson('/stripe/webhook', $this->subscriptionPayload('customer.subscription.created', 'price_team'))
            ->assertOk();

        $this->postJson('/stripe/webhook', $this->subscriptionPayload('customer.subscription.updated', 'price_start'))
            ->assertOk();

        $company->refresh();
        $this->assertSame(CompanyPlan::Start, $company->plan);
        $this->assertFalse($company->allowsTeamFeatures());
        $this->assertTrue($company->canManageJobs());

        $this->postJson('/stripe/webhook', [
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'id' => 'sub_test',
                    'customer' => 'cus_test',
                ],
            ],
        ])->assertOk();

        $company->refresh();
        $this->assertSame(CompanyPlan::Start, $company->plan);
        $this->assertFalse($company->canManageJobs());
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'stripe_id' => 'sub_test',
            'stripe_status' => 'canceled',
        ]);
        $this->assertNotNull($company->subscription('default')?->ends_at);
    }

    public function test_unknown_or_inactive_subscription_does_not_invent_a_team_plan(): void
    {
        $this->enableBilling();
        $user = User::factory()->create();
        $company = $user->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'stripe_id' => 'cus_test',
        ])->save();

        $this->postJson('/stripe/webhook', $this->subscriptionPayload('customer.subscription.created', 'price_unknown'))
            ->assertOk();

        $company->refresh();
        $this->assertSame(CompanyPlan::Team, $company->plan);
        $this->assertTrue($company->subscribed('default'));

        $this->postJson('/stripe/webhook', $this->subscriptionPayload('customer.subscription.updated', 'price_team', 'incomplete'))
            ->assertOk();

        $company->refresh();
        $this->assertSame(CompanyPlan::Start, $company->plan);
        $this->assertFalse($company->canManageJobs());
    }

    public function test_plan_sync_ignores_events_while_stripe_is_unconfigured(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $this->assertInstanceOf(Company::class, $company);
        $company->forceFill([
            'plan' => CompanyPlan::Team,
            'stripe_id' => 'cus_test',
        ])->save();
        $company->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test',
            'stripe_status' => 'active',
            'stripe_price' => 'price_start',
            'quantity' => 1,
        ]);

        WebhookHandled::dispatch($this->subscriptionPayload('customer.subscription.updated', 'price_start'));

        $this->assertSame(CompanyPlan::Team, $company->refresh()->plan);
    }

    public function test_unsigned_webhook_is_rejected_outside_testing_when_the_signing_secret_is_missing(): void
    {
        $this->enableBilling();
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->postJson('/stripe/webhook', $this->subscriptionPayload('customer.subscription.created', 'price_team'))
            ->assertForbidden();

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_checkout_success_message_is_shown_after_returning_from_stripe(): void
    {
        $this->enableBilling();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/installningar/abonnemang?checkout=success')
            ->assertOk()
            ->assertSee('Betalningen är mottagen. Abonnemanget uppdateras när Stripe har bekräftat den.', false);
    }

    private function enableBilling(bool $tax = false): void
    {
        config([
            'cashier.secret' => 'sk_test_x',
            'cashier.webhook.secret' => null,
            'cashier.tax_enabled' => $tax,
            'klartfoto.stripe_price_start' => 'price_start',
            'klartfoto.stripe_price_team' => 'price_team',
        ]);
    }

    private function fakeStripe(bool $expectCustomer = false, bool $expectCheckout = false, bool $expectPortal = false): void
    {
        $this->stripeCustomerOptions = null;
        $this->stripeSessionPayload = null;
        $this->stripePortalOptions = null;

        $customers = Mockery::mock();
        $customer = $customers->shouldReceive('create');

        if ($expectCustomer) {
            $customer->once()->andReturnUsing(function (array $options = [], mixed ...$unused): Customer {
                $this->stripeCustomerOptions = $options;

                return Customer::constructFrom([
                    'id' => 'cus_test',
                    'object' => 'customer',
                ]);
            });
        } else {
            $customer->never();
        }

        $sessions = Mockery::mock();
        $checkout = $sessions->shouldReceive('create');

        if ($expectCheckout) {
            $checkout->once()->andReturnUsing(function (array $payload = [], mixed ...$unused): Session {
                $this->stripeSessionPayload = $payload;

                return Session::constructFrom([
                    'id' => 'cs_test',
                    'object' => 'checkout.session',
                    'url' => 'https://checkout.stripe.test/c/pay/cs_test',
                ]);
            });
        } else {
            $checkout->never();
        }

        $portalSessions = Mockery::mock();
        $portal = $portalSessions->shouldReceive('create');

        if ($expectPortal) {
            $portal->atLeast()->once()->andReturnUsing(function (array $options = [], mixed ...$unused): BillingPortalSession {
                $this->stripePortalOptions = $options;

                return BillingPortalSession::constructFrom([
                    'id' => 'bps_test',
                    'object' => 'billing_portal.session',
                    'url' => 'https://billing.stripe.test/session',
                ]);
            });
        } else {
            $portal->never();
        }

        $client = new \stdClass;
        $client->customers = $customers;
        $client->checkout = new \stdClass;
        $client->checkout->sessions = $sessions;
        $client->billingPortal = new \stdClass;
        $client->billingPortal->sessions = $portalSessions;

        $this->app->extend(StripeClient::class, fn (): object => $client);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionPayload(string $type, string $priceId, string $status = 'active'): array
    {
        return [
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => 'sub_test',
                    'customer' => 'cus_test',
                    'status' => $status,
                    'metadata' => ['type' => 'default'],
                    'trial_end' => null,
                    'items' => [
                        'data' => [[
                            'id' => 'si_test',
                            'quantity' => 1,
                            'price' => [
                                'id' => $priceId,
                                'product' => 'prod_test',
                            ],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
