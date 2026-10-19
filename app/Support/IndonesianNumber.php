<?php

namespace App\Support;

/**
 * Parses numbers written the Indonesian way, where dots group thousands and a
 * comma starts the decimals: "Rp 1.250.000", "1.250.000,00", "2,5".
 */
final class IndonesianNumber
{
    public static function parse(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = (string) preg_replace('/^(rp\.?|idr)|\s+/i', '', trim($value));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $value) || preg_match('/^-?\d+,\d+$/', $value)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Ungrouped output is for form fields holding quantities and rates, where
     * "1.000" would read back as one instead of a thousand.
     */
    public static function format(int|float $value, bool $grouped = true): string
    {
        $thousands = $grouped ? '.' : '';

        if (floor($value) === (float) $value) {
            return number_format($value, 0, ',', $thousands);
        }

        return rtrim(rtrim(number_format($value, 2, ',', $thousands), '0'), ',');
    }
}
