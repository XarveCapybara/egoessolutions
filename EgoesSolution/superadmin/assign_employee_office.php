<?php
session_start();

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: offices.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$officeId = (int) ($_POST['office_id'] ?? 0);
$employeeUserId = (int) ($_POST['employee_user_id'] ?? 0);

if ($officeId <= 0 || $employeeUserId <= 0) {
    $_SESSION['office_assign_status'] = 'error';
    $_SESSION['office_assign_message'] = 'Invalid office or employee selected.';
    header('Location: office_overview.php?id=' . $officeId);
    exit;
}

try {
    $officeStmt = $pdo->prepare('SELECT id FROM offices WHERE id = ? LIMIT 1');
    $officeStmt->execute([$officeId]);
    if (!$officeStmt->fetch()) {
        $_SESSION['office_assign_status'] = 'error';
        $_SESSION['office_assign_message'] = 'Office not found.';
        header('Location: offices.php');
        exit;
    }

    $employeeStmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = "employee" LIMIT 1');
    $employeeStmt->execute([$employeeUserId]);
    if (!$employeeStmt->fetch()) {
        $_SESSION['office_assign_status'] = 'error';
        $_SESSION['office_assign_message'] = 'Employee not found.';
        header('Location: office_overview.php?id=' . $officeId);
        exit;
    }

    $updateStmt = $pdo->prepare('UPDATE users SET office_id = ? WHERE id = ?');
    $updateStmt->execute([$officeId, $employeeUserId]);

    $_SESSION['office_assign_status'] = 'success';
    $_SESSION['office_assign_message'] = 'Employee added to office successfully.';
} catch (PDOException $e) {
    $_SESSION['office_assign_status'] = 'error';
    $_SESSION['office_assign_message'] = 'Unable to add employee to office. Please try again.';
}

header('Location: office_overview.php?id=' . $officeId);
exit;
