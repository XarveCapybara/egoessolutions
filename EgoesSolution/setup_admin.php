<?php
/**
 * One-time setup: creates superadmin, admin, and employee test users.
 * Run this in your browser once, then delete this file for security.
 */

require_once __DIR__ . '/config/database.php';

$appEnv = strtolower((string) (getenv('APP_ENV') ?: 'development'));
if (in_array($appEnv, ['prod', 'production'], true)) {
    http_response_code(403);
    exit('Forbidden in production.');
}

$users = [
    ['email' => 'superadmin@egoes.com', 'password' => 'Password123!', 'fullName' => 'Super Admin', 'role' => 'superadmin', 'office_id' => null],
    ['email' => 'admin@egoes.com', 'password' => 'Password123!', 'fullName' => 'Office Admin', 'role' => 'admin', 'office_id' => 1],
    ['email' => 'employee@egoes.com', 'password' => 'Password123!', 'fullName' => 'Employee', 'role' => 'employee', 'office_id' => 1],
];

try {
    // Ensure at least one office exists
    $stmt = $pdo->query('SELECT COUNT(*) FROM offices');
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO offices (name, address, is_active) VALUES ('Main Office', 'N/A', 1)");
    }

    $insertStmt = $pdo->prepare('INSERT INTO users (office_id, role, full_name, email, password_hash, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $updateStmt = $pdo->prepare('UPDATE users SET office_id = ?, role = ?, full_name = ?, password_hash = ?, is_active = 1 WHERE email = ?');
    $created = [];
    $updated = [];

    foreach ($users as $u) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$u['email']]);
        $hash = password_hash($u['password'], PASSWORD_DEFAULT);
        if ($check->fetch()) {
            $updateStmt->execute([$u['office_id'], $u['role'], $u['fullName'], $hash, $u['email']]);
            $updated[] = $u['role'] . ': ' . $u['email'];
        } else {
            $insertStmt->execute([$u['office_id'], $u['role'], $u['fullName'], $u['email'], $hash]);
            $created[] = $u['role'] . ': ' . $u['email'];
        }
    }

    echo '<p style="color:green;"><strong>Setup complete.</strong></p>';
    if (!empty($created)) {
        echo '<p>Created users:</p><ul>';
        foreach ($created as $c) {
            echo '<li>' . htmlspecialchars($c) . '</li>';
        }
        echo '</ul>';
    }
    if (!empty($updated)) {
        echo '<p>Updated users:</p><ul>';
        foreach ($updated as $u) {
            echo '<li>' . htmlspecialchars($u) . '</li>';
        }
        echo '</ul>';
    }
    echo '<p>Log in with any of these (password: Password123!):</p><ul>';
    foreach ($users as $u) {
        echo '<li>' . htmlspecialchars($u['role']) . ': ' . htmlspecialchars($u['email']) . '</li>';
    }
    echo '</ul><p><strong>Delete setup_admin.php for security.</strong></p>';
    echo '<p><a href="auth/login.php">Go to Login</a></p>';

} catch (PDOException $e) {
    echo '<p style="color:red;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
