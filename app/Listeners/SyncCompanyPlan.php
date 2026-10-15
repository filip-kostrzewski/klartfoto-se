<?php

namespace App\Listeners;

use App\Models\Company;
use Laravel\Cashier\Events\WebhookHandled;

class SyncCompanyPlan
{
    public function handle(WebhookHandled $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (! in_array($type, [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
        ], true)) {
            return;
        }

        $customer = $event->payload['data']['object']['customer'] ?? null;

        if (! is_string($customer) || $customer === '') {
            return;
        }

        Company::query()->where('stripe_id', $customer)->first()?->syncPlanFromSubscription();
    }
}
