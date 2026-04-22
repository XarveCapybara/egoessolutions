<?php
session_start();

require_once __DIR__ . '/../config/database.php';

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Please enter a username and password.';
    header('Location: login.php');
    exit;
}

// Allow short usernames: superadmin, admin, employee -> map to full email
$loginEmail = $username;
if (strpos($username, '@') === false) {
    $map = ['superadmin' => 'superadmin@egoes.com', 'admin' => 'admin@egoes.com', 'employee' => 'employee@egoes.com'];
    $loginEmail = $map[strtolower($username)] ?? $username;
}

$stmt = $pdo->prepare('SELECT id, office_id, role, full_name, email, password_hash FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
$stmt->execute([':email' => $loginEmail]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Invalid credentials.';
    header('Location: login.php');
    exit;
}

// Safety block: terminated employees cannot log in.
if (($user['role'] ?? '') === 'employee') {
    try {
        $terminationStmt = $pdo->prepare(
            "SELECT id
             FROM employee_memos
             WHERE user_id = ?
               AND consequence_type = 'termination'
               AND status = 'active'
             ORDER BY id DESC
             LIMIT 1"
        );
        $terminationStmt->execute([(int) $user['id']]);
        $hasActiveTermination = (bool) $terminationStmt->fetchColumn();
        if ($hasActiveTermination) {
            $_SESSION['login_error'] = 'Your account has been terminated. Please contact HR.';
            header('Location: login.php');
            exit;
        }
    } catch (Throwable $e) {
        // If lookup fails, continue with existing auth behavior.
    }
}

// Login OK
$displayName = (string) ($user['full_name'] ?? 'User');
try {
    $hasUserProfiles = $pdo->query("SHOW TABLES LIKE 'user_profiles'")->rowCount() > 0;
    if ($hasUserProfiles) {
        $nickStmt = $pdo->prepare('SELECT nickname FROM user_profiles WHERE user_id = ? LIMIT 1');
        $nickStmt->execute([(int) $user['id']]);
        $nickname = trim((string) ($nickStmt->fetchColumn() ?: ''));
        if ($nickname !== '') {
            $displayName = $nickname;
        }
    }
} catch (PDOException $e) {
    // Fallback to users.full_name if user_profiles lookup fails.
}
$_SESSION['role'] = $user['role'];
$_SESSION['display_name'] = $displayName;
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['office_id'] = $user['office_id'];

switch ($user['role']) {
    case 'superadmin':
        header('Location: ../superadmin/dashboard.php');
        break;
    case 'admin':
        header('Location: ../admin/dashboard.php');
        break;
    case 'employee':
        header('Location: ../employee/dashboard.php');
        break;
    default:
        $_SESSION['login_error'] = 'Unknown account type.';
        header('Location: login.php');
}
exit;
