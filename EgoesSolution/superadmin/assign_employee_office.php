<?php
session_start();
require_once __DIR__ . '/../includes/csrf.php';

$wantsJson = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function assign_json_exit(bool $ok, string $message, int $httpCode = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($httpCode);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    if ($wantsJson) {
        assign_json_exit(false, 'Unauthorized.', 401);
    }
    header('Location: ../auth/login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if ($wantsJson) {
        assign_json_exit(false, 'Method not allowed.', 405);
    }
    header('Location: offices.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? null;
if (!eg_csrf_validate(is_string($csrfToken) ? $csrfToken : null)) {
    if ($wantsJson) {
        assign_json_exit(false, 'Security token mismatch. Refresh the page and try again.', 419);
    }
    $_SESSION['office_assign_status'] = 'error';
    $_SESSION['office_assign_message'] = 'Security token mismatch. Please refresh and try again.';
    header('Location: offices.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$officeId = (int) ($_POST['office_id'] ?? 0);
$employeeUserId = (int) ($_POST['employee_user_id'] ?? 0);

if ($officeId <= 0 || $employeeUserId <= 0) {
    if ($wantsJson) {
        assign_json_exit(false, 'Invalid office or employee selected.', 400);
    }
    $_SESSION['office_assign_status'] = 'error';
    $_SESSION['office_assign_message'] = 'Invalid office or employee selected.';
    header('Location: office_overview.php?id=' . $officeId);
    exit;
}

try {
    $officeStmt = $pdo->prepare('SELECT id FROM offices WHERE id = ? LIMIT 1');
    $officeStmt->execute([$officeId]);
    if (!$officeStmt->fetch()) {
        if ($wantsJson) {
            assign_json_exit(false, 'Office not found.', 404);
        }
        $_SESSION['office_assign_status'] = 'error';
        $_SESSION['office_assign_message'] = 'Office not found.';
        header('Location: offices.php');
        exit;
    }

    $employeeStmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE id = ? AND role = "employee" LIMIT 1');
    $employeeStmt->execute([$employeeUserId]);
    $employeeRow = $employeeStmt->fetch();
    if (!$employeeRow) {
        if ($wantsJson) {
            assign_json_exit(false, 'Employee not found.', 404);
        }
        $_SESSION['office_assign_status'] = 'error';
        $_SESSION['office_assign_message'] = 'Employee not found.';
        header('Location: office_overview.php?id=' . $officeId);
        exit;
    }

    $updateStmt = $pdo->prepare('UPDATE users SET office_id = ? WHERE id = ?');
    $updateStmt->execute([$officeId, $employeeUserId]);

    $name = trim((string) ($employeeRow['full_name'] ?? ''));
    if ($name === '') {
        $name = 'Employee';
    }
    $msg = $name . ' added to office successfully.';
    if ($wantsJson) {
        assign_json_exit(true, $msg, 200);
    }
    $_SESSION['office_assign_status'] = 'success';
    $_SESSION['office_assign_message'] = $msg;
} catch (PDOException $e) {
    if ($wantsJson) {
        assign_json_exit(false, 'Unable to add employee to office. Please try again.', 500);
    }
    $_SESSION['office_assign_status'] = 'error';
    $_SESSION['office_assign_message'] = 'Unable to add employee to office. Please try again.';
}

if (!$wantsJson) {
    header('Location: office_overview.php?id=' . $officeId);
}
exit;
