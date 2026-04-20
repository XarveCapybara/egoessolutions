<?php
session_start();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: scan.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$adminOfficeId = (int) ($_SESSION['office_id'] ?? 0);
$barcodeId = trim($_POST['barcode_id'] ?? '');
$scanType = $_POST['scan_type'] ?? 'in';
$clientNowRaw = trim($_POST['client_now'] ?? '');
$isBulkTimeout = isset($_POST['bulk_timeout']) && $_POST['bulk_timeout'] === '1';

if ($adminOfficeId <= 0) {
    $_SESSION['scan_status'] = 'error';
    $_SESSION['scan_message'] = 'Your admin account is not assigned to an office.';
    header('Location: scan.php');
    exit;
}

if (!$isBulkTimeout && $barcodeId === '') {
    $_SESSION['scan_status'] = 'error';
    $_SESSION['scan_message'] = 'Please scan or enter a barcode ID.';
    header('Location: scan.php');
    exit;
}

if (!$isBulkTimeout && !in_array($scanType, ['in', 'out'], true)) {
    $_SESSION['scan_status'] = 'error';
    $_SESSION['scan_message'] = 'Invalid scan mode selected.';
    header('Location: scan.php');
    exit;
}

try {
    $hasAppSettingsTable = $pdo->query("SHOW TABLES LIKE 'app_settings'")->rowCount() > 0;
    $deductionPerMinute = 0.0;
    if ($hasAppSettingsTable) {
        $deductionStmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $deductionStmt->execute(['deduction_per_minute']);
        $deductionValue = $deductionStmt->fetchColumn();
        if ($deductionValue !== false && $deductionValue !== null && is_numeric($deductionValue)) {
            $deductionPerMinute = (float) $deductionValue;
        }
    }

    $hasOfficeTimeInColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_in'")->rowCount() > 0;
    $hasOfficeTimeOutColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_out'")->rowCount() > 0;
    $officeTimeIn = null;
    $officeTimeOut = null;
    if ($hasOfficeTimeInColumn && $hasOfficeTimeOutColumn) {
        $officeScheduleStmt = $pdo->prepare('SELECT time_in, time_out FROM offices WHERE id = ? LIMIT 1');
        $officeScheduleStmt->execute([$adminOfficeId]);
        $officeSchedule = $officeScheduleStmt->fetch();
        if ($officeSchedule) {
            $officeTimeIn = $officeSchedule['time_in'] ?? null;
            $officeTimeOut = $officeSchedule['time_out'] ?? null;
        }
    }

    $hasAttendanceLogsTable = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
    if (!$hasAttendanceLogsTable) {
        $_SESSION['scan_status'] = 'error';
        $_SESSION['scan_message'] = 'Debug: table `attendance_logs` does not exist.';
        header('Location: scan.php');
        exit;
    }

    $hasLateMinutesColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
    if (!$hasLateMinutesColumn) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN late_minutes INT NOT NULL DEFAULT 0 AFTER status");
    }
    $hasDeductionAmountColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;
    if (!$hasDeductionAmountColumn) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN deduction_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER late_minutes");
    }

    $hasEmployeeCodeColumn = $pdo->query("SHOW COLUMNS FROM employees LIKE 'employee_code'")->rowCount() > 0;
    if (!$hasEmployeeCodeColumn && !$isBulkTimeout) {
        $_SESSION['scan_status'] = 'error';
        $_SESSION['scan_message'] = 'Debug: column `employees.employee_code` does not exist.';
        header('Location: scan.php');
        exit;
    }

    if (!$isBulkTimeout) {
        // Employee must exist and belong to the same office as the admin.
        $employeeStmt = $pdo->prepare('
            SELECT e.id AS employee_id, u.id AS user_id, u.full_name, u.office_id
            FROM users u
            INNER JOIN employees e ON e.user_id = u.id
            WHERE u.role IN (\'employee\', \'admin\') AND e.employee_code = ? AND u.is_active = 1
            LIMIT 1
        ');
        $employeeStmt->execute([$barcodeId]);
        $employee = $employeeStmt->fetch();

        if (!$employee) {
            $_SESSION['scan_status'] = 'error';
            $_SESSION['scan_message'] = 'No active employee found for this barcode.';
            header('Location: scan.php');
            exit;
        }

        $employeeOfficeId = (int) ($employee['office_id'] ?? 0);
        if ($employeeOfficeId !== $adminOfficeId) {
            $_SESSION['scan_status'] = 'error';
            $_SESSION['scan_message'] = 'Office mismatch. This employee belongs to a different office.';
            header('Location: scan.php');
            exit;
        }

        $hasEmployeeMemos = $pdo->query("SHOW TABLES LIKE 'employee_memos'")->rowCount() > 0;
        if ($hasEmployeeMemos) {
            $disciplineStmt = $pdo->prepare(
                "SELECT consequence_type, consequence, suspension_start, suspension_end
                 FROM employee_memos
                 WHERE user_id = ? AND office_id = ? AND status = 'active'
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1"
            );
            $disciplineStmt->execute([(int) ($employee['user_id'] ?? 0), $adminOfficeId]);
            $discipline = $disciplineStmt->fetch(PDO::FETCH_ASSOC);
            if ($discipline) {
                $consequenceType = strtolower((string) ($discipline['consequence_type'] ?? ''));
                if ($consequenceType === 'termination') {
                    $_SESSION['scan_status'] = 'error';
                    $_SESSION['scan_message'] = $employee['full_name'] . ' cannot be scanned. Employee is marked terminated.';
                    header('Location: scan.php');
                    exit;
                }
                if ($consequenceType === 'suspension') {
                    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
                    $sStart = (string) ($discipline['suspension_start'] ?? '');
                    $sEnd = (string) ($discipline['suspension_end'] ?? '');
                    if ($sStart !== '' && $sEnd !== '' && $today >= $sStart && $today <= $sEnd) {
                        $_SESSION['scan_status'] = 'error';
                        $_SESSION['scan_message'] = $employee['full_name'] . ' is suspended and cannot be scanned until ' . date('M d, Y', strtotime($sEnd)) . '.';
                        header('Location: scan.php');
                        exit;
                    }
                }
            }
        }
    }

    $now = new DateTimeImmutable('now');
    if ($clientNowRaw !== '') {
        try {
            // Expect local wall-clock format from browser: YYYY-MM-DD HH:MM:SS
            $parsedNow = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $clientNowRaw);
            if ($parsedNow instanceof DateTimeImmutable) {
                $now = $parsedNow;
            } else {
                $now = new DateTimeImmutable($clientNowRaw);
            }
        } catch (Exception $e) {
            // Fallback to server time when client timestamp is invalid.
        }
    }
    $nowSql = $now->format('Y-m-d H:i:s');
    $effectiveLogDate = $now->format('Y-m-d');
    $isGraveyardShift = false;
    $scheduledTimeInAt = null;
    $scheduledTimeOutAt = null;
    $withinAllowedWindow = true;
    $withinBulkWindow = true;
    if (!empty($officeTimeIn) && !empty($officeTimeOut)) {
        $officeTimeInOnly = substr((string) $officeTimeIn, 0, 8);
        $officeTimeOutOnly = substr((string) $officeTimeOut, 0, 8);
        $isGraveyardShift = $officeTimeInOnly > $officeTimeOutOnly;

        $toSeconds = static function (string $hhmmss): int {
            [$h, $m, $s] = array_map('intval', explode(':', $hhmmss));
            return ($h * 3600) + ($m * 60) + $s;
        };
        $nowSec = $toSeconds($now->format('H:i:s'));
        $inSec = $toSeconds($officeTimeInOnly);
        $outSec = $toSeconds($officeTimeOutOnly);
        $allowedStartSec = ($inSec - 3600 + 86400) % 86400;

        // Graveyard log_date = calendar day of shift start (evening). After office_end the clock date jumps
        // to "today" but rows are still under yesterday — allow 1 hour after office_end to resolve that shift.
        $graveyardGraceEndSec = min($outSec + 3600, 86399);
        if ($isGraveyardShift) {
            if ($nowSec <= $outSec) {
                $shiftBaseDate = $now->modify('-1 day')->format('Y-m-d');
            } elseif ($nowSec > $outSec && $nowSec <= $graveyardGraceEndSec) {
                $shiftBaseDate = $now->modify('-1 day')->format('Y-m-d');
            } else {
                $shiftBaseDate = $now->format('Y-m-d');
            }
        } else {
            $shiftBaseDate = $now->format('Y-m-d');
        }

        $withinAllowedWindow = false;
        if ($isGraveyardShift) {
            $withinAllowedWindow = ($nowSec >= $allowedStartSec) || ($nowSec <= $outSec);
        } else {
            $withinAllowedWindow = ($nowSec >= $allowedStartSec) && ($nowSec <= $outSec);
        }
        // Individual time-out during 1h after office_end (same window as bulk) for graveyard
        $graveyardOutGrace = $isGraveyardShift
            && $scanType === 'out'
            && $nowSec > $outSec
            && $nowSec <= $graveyardGraceEndSec;

        // Bulk time-out window:
        // - Normal shift: from 1 hour before time-in until 1 hour after time-out.
        // - Graveyard shift (e.g., 20:00–05:00): ONLY from time-out (e.g., 05:00)
        //   until 1 hour after time-out (e.g., 06:00) on the following morning.
        $withinBulkWindow = false;
        if ($isGraveyardShift) {
            $bulkStartSec = $outSec;
            $bulkEndSec = ($outSec + 3600) % 86400;
            $withinBulkWindow = ($nowSec >= $bulkStartSec) && ($nowSec <= $bulkEndSec);
        } else {
            $bulkEndSec = min($outSec + 3600, 86399);
            $withinBulkWindow = ($nowSec >= $allowedStartSec) && ($nowSec <= $bulkEndSec);
        }

        $scheduledTimeInAt = new DateTimeImmutable($shiftBaseDate . ' ' . $officeTimeInOnly);
        $scheduledTimeOutAt = new DateTimeImmutable($shiftBaseDate . ' ' . $officeTimeOutOnly);
        if ($isGraveyardShift) {
            $scheduledTimeOutAt = $scheduledTimeOutAt->modify('+1 day');
        }
        $effectiveLogDate = $scheduledTimeInAt->format('Y-m-d');

        if (!$withinAllowedWindow && !$graveyardOutGrace && !$isBulkTimeout) {
            if ($scanType === 'in') {
                $_SESSION['scan_status'] = 'error';
                $_SESSION['scan_message'] = 'Time-in is only allowed within 1 hour before shift start and before scheduled time-out.';
                header('Location: scan.php');
                exit;
            }
            if ($scanType === 'out') {
                $_SESSION['scan_status'] = 'error';
                $_SESSION['scan_message'] = 'Attendance can only be set before scheduled time-out.';
                header('Location: scan.php');
                exit;
            }
        }
    }

    if ($isBulkTimeout) {
        if (!$withinBulkWindow) {
            $_SESSION['scan_status'] = 'error';
            $_SESSION['scan_message'] = 'Bulk time-out is only allowed until 1 hour after scheduled time-out.';
            header('Location: scan.php');
            exit;
        }

        $bulkStmt = $pdo->prepare('
            UPDATE attendance_logs
            SET time_out = ?, status = "Present"
            WHERE office_id = ?
              AND log_date = ?
              AND time_in IS NOT NULL
              AND time_out IS NULL
        ');
        $bulkStmt->execute([$nowSql, $adminOfficeId, $effectiveLogDate]);
        $affected = $bulkStmt->rowCount();

        if ($affected === 0) {
            $_SESSION['scan_status'] = 'error';
            $_SESSION['scan_message'] = 'No employees to time out for this workday.';
        } else {
            $_SESSION['scan_status'] = 'success';
            $_SESSION['scan_message'] = 'Timed out ' . $affected . ' employee(s) for this workday.';
        }

        header('Location: scan.php');
        exit;
    }

    if (!$isBulkTimeout) {
        $attendanceStmt = $pdo->prepare('
            SELECT id, time_in, time_out, late_minutes, deduction_amount
            FROM attendance_logs
            WHERE employee_id = ? AND office_id = ? AND log_date = ?
            LIMIT 1
        ');
        $attendanceStmt->execute([(int) $employee['employee_id'], $adminOfficeId, $effectiveLogDate]);
        $attendance = $attendanceStmt->fetch();

        if ($scanType === 'in' && !$attendance) {
            $lateMinutes = 0;
            $deductionAmount = 0.00;
            if ($scheduledTimeInAt instanceof DateTimeImmutable && $now > $scheduledTimeInAt) {
                $lateMinutes = (int) floor(($now->getTimestamp() - $scheduledTimeInAt->getTimestamp()) / 60);
                $deductibleMinutes = max(0, $lateMinutes);
                if ($deductibleMinutes > 0 && $deductionPerMinute > 0) {
                    $deductionAmount = round($deductibleMinutes * $deductionPerMinute, 2);
                }
            }


            $insertStmt = $pdo->prepare('
                INSERT INTO attendance_logs (employee_id, office_id, log_date, time_in, time_out, status, late_minutes, deduction_amount)
                VALUES (?, ?, ?, ?, NULL, "Present", ?, ?)
            ');
            $insertStmt->execute([(int) $employee['employee_id'], $adminOfficeId, $effectiveLogDate, $nowSql, $lateMinutes, $deductionAmount]);

            $_SESSION['scan_status'] = 'success';
            if ($deductionAmount > 0) {
                $_SESSION['scan_message'] = $employee['full_name'] . ' timed in successfully. Deduction: ' . number_format($deductionAmount, 2) . '.';
            } else {
                $_SESSION['scan_message'] = $employee['full_name'] . ' timed in successfully.';
            }
            header('Location: scan.php');
            exit;
        }

        if ($scanType === 'in') {
            if (!empty($attendance['time_in']) && empty($attendance['time_out'])) {
                $_SESSION['scan_status'] = 'error';
                $_SESSION['scan_message'] = $employee['full_name'] . ' is already timed in for today.';
                header('Location: scan.php');
                exit;
            }

            $_SESSION['scan_status'] = 'error';
            $_SESSION['scan_message'] = $employee['full_name'] . ' already completed attendance for today.';
            header('Location: scan.php');
            exit;
        }

        // scan_type = out
        if (!$attendance || empty($attendance['time_in'])) {
            $_SESSION['scan_status'] = 'error';
            $_SESSION['scan_message'] = $employee['full_name'] . ' has no time-in record for today.';
            header('Location: scan.php');
            exit;
        }

        if (empty($attendance['time_out'])) {
            $updateStmt = $pdo->prepare('UPDATE attendance_logs SET time_out = ?, status = "Present" WHERE id = ?');
            $updateStmt->execute([$nowSql, (int) $attendance['id']]);

            $_SESSION['scan_status'] = 'success';
            $_SESSION['scan_message'] = $employee['full_name'] . ' timed out successfully.';
            header('Location: scan.php');
            exit;
        }

        $_SESSION['scan_status'] = 'error';
        $_SESSION['scan_message'] = $employee['full_name'] . ' is already timed out for today.';
    }
} catch (PDOException $e) {
    $_SESSION['scan_status'] = 'error';
    $_SESSION['scan_message'] = 'Unable to record attendance. Debug: ' . $e->getMessage();
}

header('Location: scan.php');
exit;
