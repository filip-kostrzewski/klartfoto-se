<?php

namespace App;

class PlanPricing
{
    /**
     * Price in SEK excluding VAT for one billing period of the plan.
     */
    public static function amount(CompanyPlan $plan, BillingInterval $interval): int
    {
        return (int) config("klartfoto.prices.{$plan->value}.{$interval->value}", 0);
    }

    /**
     * Swedish-formatted amount such as "3 900 kr".
     */
    public static function formatted(CompanyPlan $plan, BillingInterval $interval): string
    {
        return number_format(self::amount($plan, $interval), 0, ',', ' ').' kr';
    }

    /**
     * Whole months that are free when paying per year compared with twelve monthly invoices.
     */
    public static function freeMonthsPerYear(CompanyPlan $plan): int
    {
        $monthly = self::amount($plan, BillingInterval::Month);

        if ($monthly < 1) {
            return 0;
        }

        return max(0, 12 - (int) ceil(self::amount($plan, BillingInterval::Year) / $monthly));
    }
}
