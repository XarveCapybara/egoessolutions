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

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$fullName = ($lastName !== '') ? $lastName . ', ' . $firstName : $firstName;
$email = strtolower(trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');
$officeId = (int) ($_POST['office_id'] ?? 0);
$redirectUrl = 'employees.php';
if ($officeId > 0) {
    $redirectUrl .= '?office_id=' . $officeId;
}

if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
    $_SESSION['employee_create_status'] = 'error';
    $_SESSION['employee_create_message'] = 'Please fill out all fields including First and Last names.';
    header('Location: ' . $redirectUrl);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['employee_create_status'] = 'error';
    $_SESSION['employee_create_message'] = 'Please enter a valid email address.';
    header('Location: ' . $redirectUrl);
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['employee_create_status'] = 'error';
    $_SESSION['employee_create_message'] = 'Password must be at least 8 characters.';
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    if ($officeId > 0) {
        $officeStmt = $pdo->prepare('SELECT id FROM offices WHERE id = ? LIMIT 1');
        $officeStmt->execute([$officeId]);
        if (!$officeStmt->fetch()) {
            $_SESSION['employee_create_status'] = 'error';
            $_SESSION['employee_create_message'] = 'Selected office was not found.';
            header('Location: employees.php');
            exit;
        }
    } else {
        $officeId = null;
    }

    $existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $existsStmt->execute([$email]);
    if ($existsStmt->fetch()) {
        $_SESSION['employee_create_status'] = 'error';
        $_SESSION['employee_create_message'] = 'Email already exists.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $insertUserStmt = $pdo->prepare('INSERT INTO users (office_id, role, full_name, email, password_hash, is_active) VALUES (?, "employee", ?, ?, ?, 1)');

    $pdo->beginTransaction();
    $insertUserStmt->execute([$officeId, $fullName, $email, $passwordHash]);
    $userId = (int) $pdo->lastInsertId();

    // Also create/update user_profiles entry
    $upsertProfileStmt = $pdo->prepare('
        INSERT INTO user_profiles (user_id, first_name, last_name)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name)
    ');
    $upsertProfileStmt->execute([$userId, $firstName, $lastName]);

    $pdo->commit();

    $_SESSION['employee_create_status'] = 'success';
    $_SESSION['employee_create_message'] = 'Employee account created successfully.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['employee_create_status'] = 'error';
    $_SESSION['employee_create_message'] = 'Unable to create employee account. Please try again.';
}

header('Location: ' . $redirectUrl);
exit;
