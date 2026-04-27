<?php

if (!isset($pdo) || !$pdo instanceof PDO) {
    return;
}

$guardUserId = (int) ($_SESSION['user_id'] ?? 0);
if ($guardUserId <= 0) {
    return;
}

require_once __DIR__ . '/eg_suspension_weekdays.php';

try {
    $hasMemosTable = $pdo->query("SHOW TABLES LIKE 'employee_memos'")->rowCount() > 0;
    if (!$hasMemosTable) {
        return;
    }

    $now = new DateTimeImmutable('now');
    $todayYmd = $now->format('Y-m-d');
    $officeId = (int) ($_SESSION['office_id'] ?? 0);

    if ($officeId > 0) {
        $suspensionStmt = $pdo->prepare(
            "SELECT suspension_start, suspension_end
             FROM employee_memos
             WHERE user_id = ?
               AND office_id = ?
               AND status = 'active'
               AND LOWER(consequence_type) = 'suspension'
             ORDER BY created_at DESC, id DESC
             LIMIT 1"
        );
        $suspensionStmt->execute([$guardUserId, $officeId]);
    } else {
        $suspensionStmt = $pdo->prepare(
            "SELECT suspension_start, suspension_end
             FROM employee_memos
             WHERE user_id = ?
               AND status = 'active'
               AND LOWER(consequence_type) = 'suspension'
             ORDER BY created_at DESC, id DESC
             LIMIT 1"
        );
        $suspensionStmt->execute([$guardUserId]);
    }
    $suspension = $suspensionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$suspension) {
        return;
    }

    $suspensionStart = (string) ($suspension['suspension_start'] ?? '');
    $suspensionEnd = (string) ($suspension['suspension_end'] ?? '');
    if (!eg_is_workday_in_suspension_range($now, $suspensionStart, $suspensionEnd)) {
        return;
    }

    $hasAttendanceLogsTable = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
    $hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
    if ($hasAttendanceLogsTable && $hasEmployeesTable) {
        $employeeIdStmt = $pdo->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1');
        $employeeIdStmt->execute([$guardUserId]);
        $employeeId = (int) ($employeeIdStmt->fetchColumn() ?: 0);

        if ($employeeId > 0) {
            $nowSql = $now->format('Y-m-d H:i:s');
            if ($officeId > 0) {
                $openAttendanceStmt = $pdo->prepare(
                    'SELECT id FROM attendance_logs
                     WHERE employee_id = ? AND office_id = ? AND time_in IS NOT NULL AND time_out IS NULL
                     ORDER BY log_date DESC, id DESC
                     LIMIT 1'
                );
                $openAttendanceStmt->execute([$employeeId, $officeId]);
            } else {
                $openAttendanceStmt = $pdo->prepare(
                    'SELECT id FROM attendance_logs
                     WHERE employee_id = ? AND time_in IS NOT NULL AND time_out IS NULL
                     ORDER BY log_date DESC, id DESC
                     LIMIT 1'
                );
                $openAttendanceStmt->execute([$employeeId]);
            }
            $openAttendanceId = (int) ($openAttendanceStmt->fetchColumn() ?: 0);

            if ($openAttendanceId > 0) {
                $timeoutStmt = $pdo->prepare('UPDATE attendance_logs SET time_out = ?, status = "Present" WHERE id = ?');
                $timeoutStmt->execute([$nowSql, $openAttendanceId]);
            }
        }
    }

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    header('Location: ../auth/login.php?suspended=1');
    exit;
} catch (Throwable $e) {
    // Keep employee pages usable when guard checks fail unexpectedly.
}
