<?php

/**
 * Weekday-only suspension (Mon–Fri). Weekends are rest days and do not count toward duration or blocking.
 */

function eg_next_weekday_on_or_after(DateTimeImmutable $date): DateTimeImmutable
{
    $cursor = $date;
    while ((int) $cursor->format('N') > 5) {
        $cursor = $cursor->modify('+1 day');
    }

    return $cursor;
}

/**
 * Last calendar day of an N-weekday suspension starting from $startDate (issue date).
 * Weekends in the span are skipped when counting and when anchoring the first day.
 */
function eg_add_weekdays(DateTimeImmutable $startDate, int $weekdayCount): DateTimeImmutable
{
    $start = eg_next_weekday_on_or_after($startDate);
    $remaining = max(1, $weekdayCount) - 1;
    $cursor = $start;

    while ($remaining > 0) {
        $cursor = $cursor->modify('+1 day');
        if ((int) $cursor->format('N') <= 5) {
            $remaining--;
        }
    }

    return $cursor;
}

/**
 * True when $on is a weekday and falls inside the inclusive suspension calendar range.
 * Scan and calendar use this so Saturday/Sunday are never treated as suspended workdays.
 */
function eg_is_workday_in_suspension_range(DateTimeImmutable $on, string $sStartYmd, string $sEndYmd): bool
{
    if ($sStartYmd === '' || $sEndYmd === '') {
        return false;
    }
    if ((int) $on->format('N') > 5) {
        return false;
    }
    $onYmd = $on->format('Y-m-d');

    return $onYmd >= $sStartYmd && $onYmd <= $sEndYmd;
}
