<?php

/**
 * Worked minutes = overlap of actual attendance [time_in, time_out] with the office schedule.
 * Pass $officeStart/$officeEnd from offices.time_in / offices.time_out for the employee's office
 * (same office_id as the attendance row). Graveyard (office start > end on the clock): office
 * end is the next calendar day. If office start/end are empty, uses full actual span (no clamping).
 */
function eg_worked_minutes_within_office_hours(
    string $logDateYmd,
    $timeIn,
    $timeOut,
    ?string $officeStart,
    ?string $officeEnd
): int {
    if ($timeIn === null || $timeIn === '' || $timeOut === null || $timeOut === '') {
        return 0;
    }
    $actualInTs = strtotime((string) $timeIn);
    $actualOutTs = strtotime((string) $timeOut);
    if ($actualInTs === false || $actualOutTs === false) {
        return 0;
    }
    if ($actualOutTs <= $actualInTs) {
        return 0;
    }

    $os = $officeStart !== null && $officeStart !== '' ? substr(trim((string) $officeStart), 0, 8) : '';
    $oe = $officeEnd !== null && $officeEnd !== '' ? substr(trim((string) $officeEnd), 0, 8) : '';

    if ($os === '' || $oe === '') {
        return (int) floor(($actualOutTs - $actualInTs) / 60);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDateYmd)) {
        return (int) floor(($actualOutTs - $actualInTs) / 60);
    }

    $isGraveyard = $os > $oe;
    $schedStartTs = strtotime($logDateYmd . ' ' . $os);
    $schedEndTs = strtotime($logDateYmd . ' ' . $oe);
    if ($schedStartTs === false || $schedEndTs === false) {
        return (int) floor(($actualOutTs - $actualInTs) / 60);
    }
    if ($isGraveyard) {
        $schedEndTs = strtotime('+1 day', $schedEndTs);
        if ($schedEndTs === false) {
            return (int) floor(($actualOutTs - $actualInTs) / 60);
        }
    }

    $effInTs = max($actualInTs, $schedStartTs);
    $effOutTs = min($actualOutTs, $schedEndTs);
    if ($effOutTs <= $effInTs) {
        return 0;
    }

    return (int) floor(($effOutTs - $effInTs) / 60);
}
