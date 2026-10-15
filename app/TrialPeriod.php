<?php

namespace App;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TrialPeriod
{
    /**
     * A date-only value includes that calendar day. A value with a time is used as given.
     */
    public static function endsAt(): CarbonInterface
    {
        $fixed = config('klartfoto.trial_ends_at');

        if (is_string($fixed) && trim($fixed) !== '') {
            $parsed = Carbon::parse($fixed);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fixed)) === 1) {
                return $parsed->endOfDay();
            }

            return $parsed;
        }

        return now()->addDays((int) config('klartfoto.trial_days', 30));
    }
}
