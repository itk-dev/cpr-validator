<?php

declare(strict_types=1);

namespace ItkDev\CprValidator;

/**
 * Class CprValidator.
 *
 * Validates and scans text for valid Danish CPR numbers.
 */
class CprValidator
{
    /**
     * Check if a string contains a CPR number.
     */
    public function containsCpr(string $text): bool
    {
        return !empty($this->extractCpr($text));
    }

    /**
     * Extract all CPR numbers from a string.
     */
    public function extractCpr(string $text): array
    {
        $cprs = [];

        // We assume that CPR numbers use only space and dash for formatting.
        $pattern = '/(?<=^|\D)\d(?:[ -]?\d){9}(?=\D|$)/';

        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches as $match) {
                foreach ($match as $cpr) {
                    if ($this->isCpr($cpr)) {
                        $cprs[] = $cpr;
                    }
                }
            }
        }

        return $cprs;
    }

    /**
     * Check if a string is a valid CPR number.
     */
    public function isCpr(string $cpr): bool
    {
        // Remove space and dash.
        $cpr = preg_replace('/[ -]/', '', $cpr);
        // Check that the cpr consists of 10 decimal digits
        // and contains a valid date
        if (!preg_match('/^\d{10}$/', $cpr)
            || !$this->hasValidDate($cpr)) {
            return false;
        }

        // Check for numbers that does not have a valid modulo 11 control digit.
        if (\in_array(substr($cpr, 0, 6), $this->noModuloCheckNumbers)) {
            return true;
        }

        // Compute weighted sum.
        $digits = array_map('intval', str_split($cpr));
        $weights = [4, 3, 2, 7, 6, 5, 4, 3, 2, 1];
        $weightedSum = 0;
        foreach ($digits as $index => $digit) {
            $weightedSum += $digit * ($weights[$index] ?? 0);
        }

        // Check weighted sum.
        return 0 === $weightedSum % 11;
    }

    // Due to lacking capacity of valid modulo 11 numbers some years, there are
    // valid CPR numbers that don't pass modulo 11 check. In those cases we just
    // return true.
    // @See https://cpr.dk/cpr-systemet/personnumre-uden-kontrolciffer-modulus-11-kontrol/
    private array $noModuloCheckNumbers = [
        '010160',
        '010164',
        '010165',
        '010166',
        '010169',
        '010170',
        '010174',
        '010180',
        '010182',
        '010184',
        '010185',
        '010186',
        '010187',
        '010188',
        '010189',
        '010190',
        '010191',
        '010192',
    ];

    /**
     * Check if string contains valid CPR number.
     *
     * @deprecated use CprValidator::containsCpr()
     *
     * @param string $cpr
     *   The string to check
     *
     * @return bool
     *   True if string contains a number that could be a valid CPR number
     */
    public function checkCpr(string $cpr): bool
    {
        return $this->containsCpr($cpr);
    }

    /**
     * Check that a candidate CPR number contains a valid date.
     *
     * @param string $cpr
     *   A string that could be a CPR number
     *
     * @return bool return
     *   True if it's considered a date
     */
    private function hasValidDate(string $cpr): bool
    {
        $day = (int) substr($cpr, 0, 2);
        $month = (int) substr($cpr, 2, 2);
        $year = (int) substr($cpr, 4, 2);

        // Map from a year (0-99) and control digit (0-9) to a century.
        // https://cpr.dk/media/12066/personnummeret-i-cpr.pdf
        // The keys of the dict corespond to the control digit.
        // The first value of the tuples is the cutoff year. If the input year is equal to or below the first value
        // the first century is used. If the year is above the first value, the second century is used.
        // E.g. If the control is 4 and the year is smaller or equal to 36 -> 2000-2036
        // E.g. If the control is 7 and the year is larger than 57 -> 1858-1899
        $ranges = [
            0 => [99, 1900, null],
            1 => [99, 1900, null],
            2 => [99, 1900, null],
            3 => [99, 1900, null],
            4 => [36, 2000, 1900],
            5 => [57, 2000, 1800],
            6 => [57, 2000, 1800],
            7 => [57, 2000, 1800],
            8 => [57, 2000, 1800],
            9 => [36, 2000, 1900],
        ];
        $control = (int) substr($cpr, 6, 1);
        $range = $ranges[$control];
        $year += $year <= $range[0] ? $range[1] : $range[2];

        return checkdate($month, $day, $year);
    }
}
