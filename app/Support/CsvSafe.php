<?php

namespace App\Support;

/**
 * CSV cell hardening.
 *
 * Audit finding P1-3.9: exported registers are opened in Excel/Sheets, and any
 * cell beginning with =, +, -, @ or a control character is treated as a
 * *formula*. A member could therefore name themselves
 * `=HYPERLINK("http://evil","click")` and have it execute on the Partner's
 * machine when the attendance register was opened.
 *
 * Plain numbers are left untouched so payroll figures stay numeric.
 */
class CsvSafe
{
    /**
     * Neutralise a single cell value.
     */
    public static function cell(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        // Embedded newlines are legal in RFC 4180 but break most spreadsheet
        // importers and make logs unreadable; flatten them.
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        $trimmed = ltrim($value);

        // A genuine number must stay a number (payroll, deductions, days).
        if (is_numeric($trimmed)) {
            return $trimmed;
        }

        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t"], true)) {
            // Leading apostrophe is the spreadsheet-safe way to force text.
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Sanitise a whole row.
     *
     * @param  array<int|string, mixed>  $cells
     * @return array<int|string, mixed>
     */
    public static function row(array $cells): array
    {
        return array_map([self::class, 'cell'], $cells);
    }
}
