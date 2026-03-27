<?php
session_start();

$wantsJson = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function pwd_json_exit(bool $ok, string $message): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    if ($wantsJson) {
        http_response_code(401);
        pwd_json_exit(false, 'Unauthorized.');
    }
    header('Location: ../auth/login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if ($wantsJson) {
        http_response_code(405);
        pwd_json_exit(false, 'Method not allowed.');
    }
    header('Location: employees.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) ($_POST['user_id'] ?? 0);
$newPassword = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

if ($userId <= 0 || $newPassword === '' || $confirmPassword === '') {
    if ($wantsJson) {
        http_response_code(400);
        pwd_json_exit(false, 'Please complete all password fields.');
    }
    $_SESSION['password_update_status'] = 'error';
    $_SESSION['password_update_message'] = 'Please complete all password fields.';
    header('Location: employees.php');
    exit;
}

if (strlen($newPassword) < 8) {
    if ($wantsJson) {
        http_response_code(400);
        pwd_json_exit(false, 'Password must be at least 8 characters.');
    }
    $_SESSION['password_update_status'] = 'error';
    $_SESSION['password_update_message'] = 'Password must be at least 8 characters.';
    header('Location: employees.php');
    exit;
}

if ($newPassword !== $confirmPassword) {
    if ($wantsJson) {
        http_response_code(400);
        pwd_json_exit(false, 'Password confirmation does not match.');
    }
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
        if ($wantsJson) {
            http_response_code(404);
            pwd_json_exit(false, 'Selected user was not found.');
        }
        $_SESSION['password_update_status'] = 'error';
        $_SESSION['password_update_message'] = 'Selected user was not found.';
        header('Location: employees.php');
        exit;
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $updateStmt->execute([$passwordHash, $userId]);

    $msg = 'Password updated for ' . $targetUser['full_name'] . '.';
    if ($wantsJson) {
        pwd_json_exit(true, $msg);
    }
    $_SESSION['password_update_status'] = 'success';
    $_SESSION['password_update_message'] = $msg;
} catch (PDOException $e) {
    if ($wantsJson) {
        http_response_code(500);
        pwd_json_exit(false, 'Unable to update password. Please try again.');
    }
    $_SESSION['password_update_status'] = 'error';
    $_SESSION['password_update_message'] = 'Unable to update password. Please try again.';
}

if (!$wantsJson) {
    header('Location: employees.php');
}
exit;
