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

// Login OK
$_SESSION['role'] = $user['role'];
$_SESSION['display_name'] = $user['full_name'];
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
