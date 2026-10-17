<?php

namespace App;

use Carbon\CarbonInterface;

class TrialPeriod
{
    /**
     * The public trial lasts this many calendar days in Sweden, through the end of the last day.
     */
    public static function endsAt(?CarbonInterface $from = null): CarbonInterface
    {
        $days = (int) config('klartfoto.trial_days', 30);

        if ($days < 1) {
            $days = 30;
        }

        return ($from ?? now())
            ->copy()
            ->timezone('Europe/Stockholm')
            ->startOfDay()
            ->addDays($days)
            ->endOfDay()
            ->utc();
    }
}
