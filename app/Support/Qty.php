<?php

namespace App\Support;

/**
 * Display formatting for quantities: thousands separators and no trailing
 * zeros, so 231.0000 shows as 231 and 1.5000 as 1.5. Stored values keep
 * their full precision; this is for reading only.
 */
final class Qty
{
    public static function kg(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $formatted = number_format((float) $value, $decimals, '.', ',');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    /** For a form field's value: no thousands separator (a number input rejects "1,000"), up to 4 decimals. */
    public static function input(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $formatted = number_format((float) $value, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    public static function money(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : number_format((float) $value, 2, '.', ',');
    }
}
