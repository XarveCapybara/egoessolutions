<?php

/**
 * Seed mock attendance_logs for one office and one calendar month (Mon–Fri).
 * Uses the same late/deduction rules as admin/scan_submit.php:
 *   late_minutes = minutes after scheduled time-in;
 *   deductible = max(0, late_minutes - 60);
 *   deduction_amount = deductible × deduction_per_minute (from app_settings).
 *
 * Usage (from project root):
 *   php scripts/seed_mock_attendance.php
 *   php scripts/seed_mock_attendance.php 2026-03 5
 *   php scripts/seed_mock_attendance.php 2026-03 5 --replace
 *
 * CLI only (do not expose on the public web without authentication).
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Run from command line only.\n");
    exit(1);
}

$monthArg = $argv[1] ?? date('Y-m');
$officeId = isset($argv[2]) ? (int) $argv[2] : 5;
$replace = in_array('--replace', $argv, true);

if (!preg_match('/^\d{4}-\d{2}$/', $monthArg)) {
    fwrite(STDERR, "Invalid month. Use YYYY-MM, e.g. 2026-03\n");
    exit(1);
}

if ($officeId <= 0) {
    fwrite(STDERR, "Invalid office id.\n");
    exit(1);
}

require_once __DIR__ . '/../config/database.php';

$monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $monthArg . '-01');
if (!$monthStart) {
    fwrite(STDERR, "Could not parse month.\n");
    exit(1);
}
$monthStart = $monthStart->setTime(0, 0, 0);
$monthEnd = $monthStart->modify('last day of this month');

$deductionPerMinute = 0.0;
$hasAppSettings = $pdo->query("SHOW TABLES LIKE 'app_settings'")->rowCount() > 0;
if ($hasAppSettings) {
    $st = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $st->execute(['deduction_per_minute']);
    $v = $st->fetchColumn();
    if ($v !== false && $v !== null && is_numeric($v)) {
        $deductionPerMinute = (float) $v;
    }
}

$officeStmt = $pdo->prepare('SELECT id, time_in, time_out FROM offices WHERE id = ? LIMIT 1');
$officeStmt->execute([$officeId]);
$officeRow = $officeStmt->fetch();
if (!$officeRow) {
    fwrite(STDERR, "Office id {$officeId} not found.\n");
    exit(1);
}

$timeInRaw = $officeRow['time_in'] ?? null;
$timeOutRaw = $officeRow['time_out'] ?? null;
$startHms = '09:00:00';
$outHms = '17:00:00';
if (!empty($timeInRaw)) {
    $startHms = substr((string) $timeInRaw, 0, 8);
}
if (!empty($timeOutRaw)) {
    $outHms = substr((string) $timeOutRaw, 0, 8);
}

$empStmt = $pdo->prepare('
    SELECT e.id AS employee_id, u.full_name
    FROM employees e
    INNER JOIN users u ON u.id = e.user_id
    WHERE u.office_id = ? AND u.is_active = 1 AND u.role IN ("employee", "admin")
    ORDER BY u.full_name
');
$empStmt->execute([$officeId]);
$employees = $empStmt->fetchAll();
if (empty($employees)) {
    fwrite(STDERR, "No employees/admins linked to employees table for office_id {$officeId}.\n");
    exit(1);
}

if ($replace) {
    $del = $pdo->prepare('
        DELETE FROM attendance_logs
        WHERE office_id = ? AND log_date BETWEEN ? AND ?
    ');
    $del->execute([$officeId, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
    $n = $del->rowCount();
    fwrite(STDOUT, "Removed {$n} existing attendance row(s) for this office/month.\n");
}

$insert = $pdo->prepare('
    INSERT INTO attendance_logs (employee_id, office_id, log_date, time_in, time_out, status, late_minutes, deduction_amount)
    VALUES (?, ?, ?, ?, ?, "Present", ?, ?)
');

$inserted = 0;
$skipped = 0;

for ($d = $monthStart; $d <= $monthEnd; $d = $d->modify('+1 day')) {
    $dow = (int) $d->format('N');
    if ($dow >= 6) {
        continue;
    }

    $logDate = $d->format('Y-m-d');

    $scheduledIn = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $logDate . ' ' . $startHms);
    if (!$scheduledIn) {
        continue;
    }

    $timeOutDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $logDate . ' ' . $outHms);
    if (!$timeOutDt) {
        $timeOutDt = $scheduledIn->modify('+8 hours');
    }

    foreach ($employees as $emp) {
        $employeeId = (int) $emp['employee_id'];

        // ~10% absent (no row for that weekday)
        if (random_int(1, 100) <= 10) {
            $skipped++;
            continue;
        }

        $r = random_int(1, 100);
        if ($r <= 55) {
            $lateMinutes = 0;
        } elseif ($r <= 85) {
            $lateMinutes = random_int(1, 59);
        } elseif ($r <= 95) {
            $lateMinutes = random_int(60, 120);
        } else {
            $lateMinutes = random_int(121, 180);
        }

        $actualIn = $scheduledIn->modify('+' . $lateMinutes . ' minutes');
        $timeInStr = $actualIn->format('Y-m-d H:i:s');

        $deductible = max(0, $lateMinutes - 60);
        $deductionAmount = round($deductible * $deductionPerMinute, 2);

        if ($timeOutDt <= $actualIn) {
            $timeOutStr = $actualIn->modify('+8 hours')->format('Y-m-d H:i:s');
        } else {
            $timeOutStr = $timeOutDt->format('Y-m-d H:i:s');
        }

        try {
            $insert->execute([
                $employeeId,
                $officeId,
                $logDate,
                $timeInStr,
                $timeOutStr,
                $lateMinutes,
                $deductionAmount,
            ]);
            $inserted++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
                // duplicate natural key (employee, office, date)
                $skipped++;
                continue;
            }
            throw $e;
        }
    }
}

fwrite(STDOUT, "Done. Inserted {$inserted} row(s). Skipped/absent {$skipped} slot(s).\n");
fwrite(STDOUT, "Office {$officeId} schedule {$startHms}–{$outHms}, deduction_per_minute={$deductionPerMinute}\n");
exit(0);
