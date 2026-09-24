<?php
/**
 * Release Date Calculator
 * Counts N working days forward from a given date,
 * skipping weekends and Philippine public holidays.
 *
 * Usage:
 *   require_once '../includes/release_date.php';
 *   $date = calculateReleaseDate(7);          // 7 working days from today
 *   $date = calculateReleaseDate(15, '2025-08-01'); // from a specific start date
 *
 * Returns a Y-m-d string.
 */

/**
 * Returns an array of Philippine public holiday date strings (Y-m-d)
 * for the current and next year, covering regular + special non-working days.
 */
function getPhHolidays(): array
{
    $holidays = [];
    $years    = [date('Y'), date('Y') + 1];

    foreach ($years as $y) {
        // Regular holidays (fixed dates)
        $fixed = [
            "$y-01-01", // New Year's Day
            "$y-04-09", // Araw ng Kagitingan
            "$y-05-01", // Labor Day
            "$y-06-12", // Independence Day
            "$y-08-21", // Ninoy Aquino Day
            "$y-08-26", // National Heroes Day (last Mon Aug — approximated as 26th; adjust if needed)
            "$y-11-01", // All Saints Day
            "$y-11-02", // All Souls Day
            "$y-11-30", // Bonifacio Day
            "$y-12-08", // Feast of Immaculate Conception
            "$y-12-25", // Christmas Day
            "$y-12-30", // Rizal Day
        ];

        // Moveable holidays — Maundy Thursday & Good Friday
        // Calculated from Easter Sunday
        $easter         = easter_date($y);
        $maundy         = date('Y-m-d', $easter - (3 * 86400));
        $good_friday    = date('Y-m-d', $easter - (2 * 86400));
        $black_saturday = date('Y-m-d', $easter - 86400);

        $holidays = array_merge($holidays, $fixed, [$maundy, $good_friday, $black_saturday]);
    }

    return array_unique($holidays);
}

/**
 * Adds $working_days working days to $from_date (Y-m-d or 'today').
 * Skips Saturdays, Sundays, and PH public holidays.
 * If the resulting date lands on a non-working day it is shifted forward.
 *
 * @param  int    $working_days  Number of working days to add (uses max_days bound)
 * @param  string $from_date     Start date in Y-m-d format, defaults to today
 * @return string                Result date in Y-m-d format
 */
function calculateReleaseDate(int $working_days, string $from_date = ''): string
{
    $holidays = getPhHolidays();
    $current  = new DateTime($from_date ?: 'today');
    $added    = 0;

    while ($added < $working_days) {
        $current->modify('+1 day');
        $dow  = (int) $current->format('N'); // 1=Mon … 7=Sun
        $ymd  = $current->format('Y-m-d');

        if ($dow < 6 && !in_array($ymd, $holidays)) {
            $added++;
        }
    }

    // Safety shift: if the result itself is a weekend/holiday, push forward
    while (true) {
        $dow = (int) $current->format('N');
        $ymd = $current->format('Y-m-d');
        if ($dow >= 6 || in_array($ymd, $holidays)) {
            $current->modify('+1 day');
        } else {
            break;
        }
    }

    return $current->format('Y-m-d');
}

/**
 * Calculates the number of working days elapsed between $startDate and $endDate (or today).
 * Skips weekends (Saturday, Sunday) and Philippine public holidays.
 *
 * @param string $startDate Start date (Y-m-d or date string)
 * @param string|null $endDate End date (Y-m-d or date string, defaults to now)
 * @return int Number of working days
 */
function calculateWorkingDays(string $startDate, ?string $endDate = null): int
{
    if (empty($startDate)) return 0;
    try {
        $start = new DateTime($startDate);
        $end   = $endDate ? new DateTime($endDate) : new DateTime();

        $start->setTime(0, 0, 0);
        $end->setTime(0, 0, 0);

        if ($start > $end) return 0;

        $holidays = getPhHolidays();
        $days = 0;
        $current = clone $start;

        while ($current <= $end) {
            $dow = (int)$current->format('N');
            $ymd = $current->format('Y-m-d');
            if ($dow < 6 && !in_array($ymd, $holidays)) {
                $days++;
            }
            $current->modify('+1 day');
        }

        return $days;
    } catch (Exception $e) {
        return 0;
    }
}

