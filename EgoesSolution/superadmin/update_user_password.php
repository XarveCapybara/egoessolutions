<?php
session_start();

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: employees.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) ($_POST['user_id'] ?? 0);
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

if ($userId <= 0 || $newPassword === '' || $confirmPassword === '') {
    $_SESSION['password_update_status'] = 'error';
    $_SESSION['password_update_message'] = 'Please complete all password fields.';
    header('Location: employees.php');
    exit;
}

if (strlen($newPassword) < 8) {
    $_SESSION['password_update_status'] = 'error';
    $_SESSION['password_update_message'] = 'Password must be at least 8 characters.';
    header('Location: employees.php');
    exit;
}

if ($newPassword !== $confirmPassword) {
    $_SESSION['password_update_status'] = 'error';
    $_SESSION['password_update_message'] = 'Password confirmation does not match.';
    header('Location: employees.php');
    exit;
}

try {
    $targetStmt = $pdo->prepare('SELECT id, role, full_name FROM users WHERE id = ? AND role IN ("employee", "admin") LIMIT 1');
    $targetStmt->execute([$userId]);
    $targetUser = $targetStmt->fetch();
    if (!$targetUser) {
        $_SESSION['password_update_status'] = 'error';
        $_SESSION['password_update_message'] = 'Selected user was not found.';
        header('Location: employees.php');
        exit;
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $updateStmt->execute([$passwordHash, $userId]);

    $_SESSION['password_update_status'] = 'success';
    $_SESSION['password_update_message'] = 'Password updated for ' . $targetUser['full_name'] . '.';
} catch (PDOException $e) {
    $_SESSION['password_update_status'] = 'error';
    $_SESSION['password_update_message'] = 'Unable to update password. Please try again.';
}

header('Location: employees.php');
exit;
