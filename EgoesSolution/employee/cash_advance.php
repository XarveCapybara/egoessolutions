<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/eg_employee_suspension_guard.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$officeId = (int) ($_SESSION['office_id'] ?? 0);
$employeeId = 0;
$employeeCode = '';

$flashType = $_SESSION['cash_advance_flash_type'] ?? null;
$flashMsg = $_SESSION['cash_advance_flash_msg'] ?? null;
unset($_SESSION['cash_advance_flash_type'], $_SESSION['cash_advance_flash_msg']);

try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS cash_advances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            notes VARCHAR(255) NULL,
            advance_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "pending",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cash_adv_employee (employee_id),
            INDEX idx_cash_adv_status_date (status, advance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    if ($pdo->query("SHOW COLUMNS FROM cash_advances LIKE 'requested_by_user_id'")->rowCount() === 0) {
        $pdo->exec('ALTER TABLE cash_advances ADD COLUMN requested_by_user_id INT NULL AFTER employee_id');
    }
    if ($pdo->query("SHOW COLUMNS FROM cash_advances LIKE 'request_source'")->rowCount() === 0) {
        $pdo->exec('ALTER TABLE cash_advances ADD COLUMN request_source VARCHAR(20) NULL AFTER requested_by_user_id');
    }
} catch (Throwable $e) {
    // keep page usable
}

if ($userId > 0) {
    $empStmt = $pdo->prepare('SELECT id, COALESCE(employee_code, "") AS employee_code FROM employees WHERE user_id = ? LIMIT 1');
    $empStmt->execute([$userId]);
    $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
    if ($empRow) {
        $employeeId = (int) ($empRow['id'] ?? 0);
        $employeeCode = trim((string) ($empRow['employee_code'] ?? ''));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $amountRaw = trim((string) ($_POST['amount'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $amountRaw = str_replace(["\xC2\xA0", ' '], '', $amountRaw);
    $amountRaw = str_replace(',', '.', $amountRaw);

    if ($employeeId <= 0 || $userId <= 0) {
        $_SESSION['cash_advance_flash_type'] = 'danger';
        $_SESSION['cash_advance_flash_msg'] = 'Unable to identify your employee profile.';
        header('Location: cash_advance.php');
        exit;
    }
    if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw <= 0) {
        $_SESSION['cash_advance_flash_type'] = 'danger';
        $_SESSION['cash_advance_flash_msg'] = 'Please enter a valid amount.';
        header('Location: cash_advance.php');
        exit;
    }

    $amount = number_format((float) $amountRaw, 2, '.', '');
    $saveNotes = $notes !== '' ? $notes : null;
    if ($saveNotes === null) {
        $saveNotes = 'Employee request';
    }

    $insert = $pdo->prepare('
        INSERT INTO cash_advances (employee_id, requested_by_user_id, request_source, amount, notes, advance_date, status)
        VALUES (?, ?, "employee", ?, ?, CURDATE(), "pending")
    ');
    $insert->execute([$employeeId, $userId, $amount, $saveNotes]);

    $_SESSION['cash_advance_flash_type'] = 'success';
    $_SESSION['cash_advance_flash_msg'] = 'Cash advance request submitted to superadmin for approval.';
    header('Location: cash_advance.php');
    exit;
}

$requests = [];
if ($employeeId > 0) {
    $list = $pdo->prepare('
        SELECT id, amount, notes, advance_date, status, created_at
        FROM cash_advances
        WHERE employee_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 100
    ');
    $list->execute([$employeeId]);
    $requests = $list->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-GOES Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="../assets/css/style.css?v=blue1" />
  </head>
  <body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_employee.php'; ?>
        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="fw-bold mb-3">Cash Advance Request</h3>

          <?php if ($flashMsg): ?>
            <div class="alert alert-<?= htmlspecialchars((string) ($flashType ?: 'info'), ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars((string) $flashMsg, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <form method="post" class="eg-panel p-3 mb-4">
            <h5 class="mb-3">Submit Request</h5>
            <div class="row g-3">
              <div class="col-12 col-md-4">
                <label class="form-label" for="amount">Amount</label>
                <input class="form-control" id="amount" name="amount" type="number" min="0.01" step="0.01" required />
              </div>
              <div class="col-12 col-md-8">
                <label class="form-label" for="notes">Reason / Notes (optional)</label>
                <input class="form-control" id="notes" name="notes" type="text" maxlength="255" />
              </div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn btn-primary">Send Request</button>
            </div>
          </form>

          <div class="eg-panel p-3">
            <h5 class="mb-3">My Cash Advance Requests</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Requested</th>
                    <th class="text-end">Amount</th>
                    <th>Notes</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($requests)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">No cash advance requests yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($requests as $row): ?>
                      <?php
                      $status = strtolower((string) ($row['status'] ?? 'pending'));
                      $badge = 'text-bg-warning';
                      if ($status === 'deducted' || $status === 'approved') {
                          $badge = 'text-bg-success';
                      } elseif ($status === 'rejected' || $status === 'cancelled') {
                          $badge = 'text-bg-danger';
                      }
                      ?>
                      <tr>
                        <td><?= htmlspecialchars(date('M j, Y h:i A', strtotime((string) $row['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end"><?= number_format((float) $row['amount'], 2) ?></td>
                        <td><?= htmlspecialchars((string) ($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge <?= $badge ?>"><?= htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </main>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>
