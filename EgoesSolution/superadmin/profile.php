<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$name = $_SESSION['display_name'] ?? 'Super Admin';
$email = $_SESSION['user_email'] ?? '';
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = trim($_POST['current_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $error = 'Please complete all password fields.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } elseif ($userId <= 0) {
        $error = 'Invalid user session. Please log in again.';
    } else {
        try {
            $userStmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE id = ? AND role = "superadmin" LIMIT 1');
            $userStmt->execute([$userId]);
            $row = $userStmt->fetch();
            if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $updateStmt->execute([$newHash, $userId]);
                $message = 'Password updated successfully.';
            }
        } catch (PDOException $e) {
            $error = 'Unable to update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Superadmin Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="../assets/css/style.css?v=blue1" />
  </head>
  <body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-3 fw-bold">Superadmin Profile</h3>
          <p class="text-muted mb-3">Manage your superadmin account details and password.</p>

          <?php if ($message): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <div class="eg-panel p-4 mb-4">
            <h5 class="mb-3">Account Information</h5>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted small">Display Name</div>
                <div class="fw-semibold"><?= htmlspecialchars($name) ?></div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small">Email</div>
                <div class="fw-semibold"><?= htmlspecialchars($email !== '' ? $email : '—') ?></div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small">Role</div>
                <div class="fw-semibold">Superadmin</div>
              </div>
            </div>
          </div>

          <div class="eg-panel p-4">
            <h5 class="mb-3">Change Password</h5>
            <form method="post" class="row g-3">
              <div class="col-md-4">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required />
              </div>
              <div class="col-md-4">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required />
              </div>
              <div class="col-md-4">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required />
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Update Password</button>
              </div>
            </form>
          </div>
        </main>
      </div>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>
