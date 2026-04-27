<?php
session_start();
require_once __DIR__ . '/../includes/csrf.php';

$wantsJson = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function role_json_exit(bool $ok, string $message, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    if ($wantsJson) {
        http_response_code(401);
        role_json_exit(false, 'Unauthorized.');
    }
    header('Location: ../auth/login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if ($wantsJson) {
        http_response_code(405);
        role_json_exit(false, 'Method not allowed.');
    }
    header('Location: employees.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!eg_csrf_validate(is_string($csrfToken) ? $csrfToken : null)) {
    if ($wantsJson) {
        http_response_code(419);
        role_json_exit(false, 'Security token mismatch. Refresh the page and try again.');
    }
    $_SESSION['role_update_status'] = 'error';
    $_SESSION['role_update_message'] = 'Security token mismatch. Please refresh and try again.';
    header('Location: employees.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) ($_POST['user_id'] ?? 0);
$newRole = trim($_POST['role'] ?? '');

if ($userId <= 0 || !in_array($newRole, ['employee', 'admin'], true)) {
    if ($wantsJson) {
        http_response_code(400);
        role_json_exit(false, 'Invalid user or role.');
    }
    $_SESSION['role_update_status'] = 'error';
    $_SESSION['role_update_message'] = 'Invalid user or role.';
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
            role_json_exit(false, 'Selected user was not found.');
        }
        $_SESSION['role_update_status'] = 'error';
        $_SESSION['role_update_message'] = 'Selected user was not found.';
        header('Location: employees.php');
        exit;
    }

    if ($targetUser['role'] === $newRole) {
        $labelShort = $newRole === 'admin' ? 'Team leader' : 'Employee';
        $msg = 'Role unchanged for ' . $targetUser['full_name'] . '.';
        if ($wantsJson) {
            role_json_exit(true, $msg, ['role' => $newRole, 'role_label' => $labelShort]);
        }
        $_SESSION['role_update_status'] = 'success';
        $_SESSION['role_update_message'] = $msg;
        header('Location: employees.php');
        exit;
    }

    $updateStmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
    $updateStmt->execute([$newRole, $userId]);

    $label = $newRole === 'admin' ? 'Team leader (admin)' : 'Employee';
    $labelShort = $newRole === 'admin' ? 'Team leader' : 'Employee';
    $msg = $targetUser['full_name'] . ' is now ' . $label . '.';
    if ($wantsJson) {
        role_json_exit(true, $msg, ['role' => $newRole, 'role_label' => $labelShort]);
    }
    $_SESSION['role_update_status'] = 'success';
    $_SESSION['role_update_message'] = $msg;
} catch (PDOException $e) {
    if ($wantsJson) {
        http_response_code(500);
        role_json_exit(false, 'Unable to update role. Please try again.');
    }
    $_SESSION['role_update_status'] = 'error';
    $_SESSION['role_update_message'] = 'Unable to update role. Please try again.';
}

if (!$wantsJson) {
    header('Location: employees.php');
}
exit;
