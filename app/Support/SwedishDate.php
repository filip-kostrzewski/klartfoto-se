<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class SwedishDate
{
    /**
     * @var array<int, string>
     */
    private const SHORT_MONTHS = [
        1 => 'jan',
        2 => 'feb',
        3 => 'mar',
        4 => 'apr',
        5 => 'maj',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'okt',
        11 => 'nov',
        12 => 'dec',
    ];

    /**
     * @var array<string, int>
     */
    private const MONTH_NUMBERS = [
        'januari' => 1,
        'jan' => 1,
        'februari' => 2,
        'feb' => 2,
        'mars' => 3,
        'mar' => 3,
        'april' => 4,
        'apr' => 4,
        'maj' => 5,
        'juni' => 6,
        'jun' => 6,
        'juli' => 7,
        'jul' => 7,
        'augusti' => 8,
        'aug' => 8,
        'september' => 9,
        'sep' => 9,
        'oktober' => 10,
        'okt' => 10,
        'november' => 11,
        'nov' => 11,
        'december' => 12,
        'dec' => 12,
    ];

    public static function display(mixed $value): string
    {
        $iso = self::toIso($value);

        if (! is_string($iso) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) !== 1) {
            return is_string($value) ? trim($value) : '';
        }

        $date = Carbon::createFromFormat('Y-m-d', $iso);

        if (! $date instanceof Carbon || $date->toDateString() !== $iso) {
            return is_string($value) ? trim($value) : '';
        }

        return $date->day.' '.self::SHORT_MONTHS[$date->month].' '.$date->year;
    }

    public static function toIso(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $trimmed;
        }

        $normalized = str_replace('.', '', mb_strtolower($trimmed));

        if (preg_match('/^(\d{1,2})\s+([a-zåäö]+)\s+(\d{4})$/u', $normalized, $matches) !== 1) {
            return $trimmed;
        }

        $month = self::MONTH_NUMBERS[$matches[2]] ?? null;

        if ($month === null) {
            return $trimmed;
        }

        $date = Carbon::createFromDate((int) $matches[3], $month, (int) $matches[1]);

        if (! $date instanceof Carbon || $date->day !== (int) $matches[1] || $date->month !== $month) {
            return $trimmed;
        }

        return $date->toDateString();
    }
}
