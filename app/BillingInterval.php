<?php

namespace App;

enum BillingInterval: string
{
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Month => 'Månadsvis',
            self::Year => 'Årsvis',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::Month => 1,
            self::Year => 12,
        };
    }
}
