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

if ($adminOfficeId <= 0) {
    $_SESSION['scan_status'] = 'error';
    $_SESSION['scan_message'] = 'Your admin account is not assigned to an office.';
    header('Location: scan.php');
    exit;
}

if ($barcodeId === '') {
    $_SESSION['scan_status'] = 'error';
    $_SESSION['scan_message'] = 'Please scan or enter a barcode ID.';
    header('Location: scan.php');
    exit;
}

if (!in_array($scanType, ['in', 'out'], true)) {
    $_SESSION['scan_status'] = 'error';
    $_SESSION['scan_message'] = 'Invalid scan mode selected.';
    header('Location: scan.php');
    exit;
}

try {
    $hasAttendanceLogsTable = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
    if (!$hasAttendanceLogsTable) {
        $_SESSION['scan_status'] = 'error';
        $_SESSION['scan_message'] = 'Debug: table `attendance_logs` does not exist.';
        header('Location: scan.php');
        exit;
    }

    $hasEmployeeCodeColumn = $pdo->query("SHOW COLUMNS FROM employees LIKE 'employee_code'")->rowCount() > 0;
    if (!$hasEmployeeCodeColumn) {
        $_SESSION['scan_status'] = 'error';
        $_SESSION['scan_message'] = 'Debug: column `employees.employee_code` does not exist.';
        header('Location: scan.php');
        exit;
    }

    // Employee must exist and belong to the same office as the admin.
    $employeeStmt = $pdo->prepare('
        SELECT e.id AS employee_id, u.id AS user_id, u.full_name, u.office_id
        FROM users u
        INNER JOIN employees e ON e.user_id = u.id
        WHERE u.role = "employee" AND e.employee_code = ? AND u.is_active = 1
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

    $today = date('Y-m-d');

    $attendanceStmt = $pdo->prepare('
        SELECT id, time_in, time_out
        FROM attendance_logs
        WHERE employee_id = ? AND office_id = ? AND log_date = ?
        LIMIT 1
    ');
    $attendanceStmt->execute([(int) $employee['employee_id'], $adminOfficeId, $today]);
    $attendance = $attendanceStmt->fetch();

    if ($scanType === 'in' && !$attendance) {
        $insertStmt = $pdo->prepare('
            INSERT INTO attendance_logs (employee_id, office_id, log_date, time_in, time_out, status)
            VALUES (?, ?, ?, NOW(), NULL, "Present")
        ');
        $insertStmt->execute([(int) $employee['employee_id'], $adminOfficeId, $today]);

        $_SESSION['scan_status'] = 'success';
        $_SESSION['scan_message'] = $employee['full_name'] . ' timed in successfully.';
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
        $updateStmt = $pdo->prepare('UPDATE attendance_logs SET time_out = NOW(), status = "Present" WHERE id = ?');
        $updateStmt->execute([(int) $attendance['id']]);

        $_SESSION['scan_status'] = 'success';
        $_SESSION['scan_message'] = $employee['full_name'] . ' timed out successfully.';
        header('Location: scan.php');
        exit;
    }

    $_SESSION['scan_status'] = 'error';
    $_SESSION['scan_message'] = $employee['full_name'] . ' is already timed out for today.';
} catch (PDOException $e) {
    $_SESSION['scan_status'] = 'error';
    $_SESSION['scan_message'] = 'Unable to record attendance. Debug: ' . $e->getMessage();
}

header('Location: scan.php');
exit;
