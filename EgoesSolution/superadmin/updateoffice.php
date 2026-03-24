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
$officeName = trim($_POST['name'] ?? '');
$officeAddress = trim($_POST['address'] ?? '');
$teamLeaderUserId = (int) ($_POST['team_leader_user_id'] ?? 0);

if ($officeId <= 0 || $officeName === '' || $officeAddress === '') {
    $_SESSION['office_edit_status'] = 'error';
    $_SESSION['office_edit_message'] = 'Please provide valid office details.';
    header('Location: offices.php');
    exit;
}

try {
    $hasTeamLeaderColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'team_leader'")->rowCount() > 0;
    if (!$hasTeamLeaderColumn) {
        $pdo->exec("ALTER TABLE offices ADD COLUMN team_leader VARCHAR(150) NULL AFTER address");
    }
    $hasTeamLeaderUserIdColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'team_leader_user_id'")->rowCount() > 0;
    if (!$hasTeamLeaderUserIdColumn) {
        $pdo->exec("ALTER TABLE offices ADD COLUMN team_leader_user_id INT NULL AFTER team_leader");
    }

    $existsStmt = $pdo->prepare('SELECT id FROM offices WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1');
    $existsStmt->execute([$officeName, $officeId]);
    if ($existsStmt->fetch()) {
        $_SESSION['office_edit_status'] = 'error';
        $_SESSION['office_edit_message'] = 'Office name already exists.';
        header('Location: offices.php');
        exit;
    }

    $teamLeaderName = null;
    if ($teamLeaderUserId > 0) {
        $leaderInUseStmt = $pdo->prepare('SELECT id FROM offices WHERE team_leader_user_id = ? AND id <> ? LIMIT 1');
        $leaderInUseStmt->execute([$teamLeaderUserId, $officeId]);
        if ($leaderInUseStmt->fetch()) {
            $_SESSION['office_edit_status'] = 'error';
            $_SESSION['office_edit_message'] = 'This admin is already a team leader of another office.';
            header('Location: offices.php');
            exit;
        }

        $teamLeaderStmt = $pdo->prepare('SELECT id, full_name FROM users WHERE id = ? AND role = "admin" LIMIT 1');
        $teamLeaderStmt->execute([$teamLeaderUserId]);
        $teamLeaderRow = $teamLeaderStmt->fetch();
        if (!$teamLeaderRow) {
            $_SESSION['office_edit_status'] = 'error';
            $_SESSION['office_edit_message'] = 'Selected team leader must be an admin user.';
            header('Location: offices.php');
            exit;
        }
        $teamLeaderName = $teamLeaderRow['full_name'];
    }

    $updateStmt = $pdo->prepare('UPDATE offices SET name = ?, address = ?, team_leader = ?, team_leader_user_id = ? WHERE id = ?');
    $updateStmt->execute([$officeName, $officeAddress, $teamLeaderName, $teamLeaderUserId > 0 ? $teamLeaderUserId : null, $officeId]);

    if ($teamLeaderUserId > 0) {
        $assignStmt = $pdo->prepare('UPDATE users SET office_id = ? WHERE id = ?');
        $assignStmt->execute([$officeId, $teamLeaderUserId]);
    }

    $_SESSION['office_edit_status'] = 'success';
    $_SESSION['office_edit_message'] = 'Office updated successfully.';
} catch (PDOException $e) {
    $_SESSION['office_edit_status'] = 'error';
    $_SESSION['office_edit_message'] = 'Unable to update office. Please try again.';
}

header('Location: offices.php');
exit;
