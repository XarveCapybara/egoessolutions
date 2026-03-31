<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';

function eg_ensure_cash_advances(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS cash_advances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            notes VARCHAR(255) NULL,
            advance_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "pending",
            deducted_period_type VARCHAR(10) NULL,
            deducted_period_start DATE NULL,
            deducted_period_end DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cash_adv_employee (employee_id),
            INDEX idx_cash_adv_status_date (status, advance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
}

eg_ensure_cash_advances($pdo);

$flashType = $_SESSION['loans_flash_type'] ?? null;
$flashMsg = $_SESSION['loans_flash_msg'] ?? null;
unset($_SESSION['loans_flash_type'], $_SESSION['loans_flash_msg']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (isset($_POST['delete_id'])) {
        $deleteId = (int) ($_POST['delete_id'] ?? 0);
        if ($deleteId <= 0) {
            $_SESSION['loans_flash_type'] = 'danger';
            $_SESSION['loans_flash_msg'] = 'Invalid cash advance record.';
            header('Location: loans.php');
            exit;
        }
        $del = $pdo->prepare('DELETE FROM cash_advances WHERE id = ? LIMIT 1');
        $del->execute([$deleteId]);
        $_SESSION['loans_flash_type'] = 'success';
        $_SESSION['loans_flash_msg'] = 'Cash advance removed.';
        header('Location: loans.php');
        exit;
    }

    $employeeId = (int) ($_POST['employee_id'] ?? 0);
    $amountRaw = trim((string) ($_POST['amount'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $advanceDate = trim((string) ($_POST['advance_date'] ?? date('Y-m-d')));
    $amountRaw = str_replace(["\xC2\xA0", ' '], '', $amountRaw);
    $amountRaw = str_replace(',', '.', $amountRaw);
    $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $advanceDate) === 1;
    if ($employeeId <= 0 || $amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw <= 0 || !$validDate) {
        $_SESSION['loans_flash_type'] = 'danger';
        $_SESSION['loans_flash_msg'] = 'Please provide valid employee, amount, and date.';
        header('Location: loans.php');
        exit;
    }
    $amount = number_format((float) $amountRaw, 2, '.', '');
    $ins = $pdo->prepare('
        INSERT INTO cash_advances (employee_id, amount, notes, advance_date, status)
        VALUES (?, ?, ?, ?, "pending")
    ');
    $ins->execute([$employeeId, $amount, $notes !== '' ? $notes : null, $advanceDate]);
    $_SESSION['loans_flash_type'] = 'success';
    $_SESSION['loans_flash_msg'] = 'Cash advance added. It will be auto-deducted on next eligible payroll.';
    header('Location: loans.php');
    exit;
}

$employees = $pdo->query('
    SELECT e.id AS employee_id, e.employee_code, u.full_name, o.name AS office_name
    FROM employees e
    JOIN users u ON u.id = e.user_id
    LEFT JOIN offices o ON o.id = u.office_id
    WHERE u.role = "employee" AND u.is_active = 1
    ORDER BY u.full_name
')->fetchAll(PDO::FETCH_ASSOC);

$cashAdvances = $pdo->query('
    SELECT
      ca.id,
      ca.employee_id,
      ca.amount,
      ca.notes,
      ca.advance_date,
      ca.status,
      ca.deducted_period_type,
      ca.deducted_period_start,
      ca.deducted_period_end,
      ca.created_at,
      u.full_name,
      e.employee_code,
      o.name AS office_name
    FROM cash_advances ca
    JOIN employees e ON e.id = ca.employee_id
    JOIN users u ON u.id = e.user_id
    LEFT JOIN offices o ON o.id = u.office_id
    ORDER BY ca.status = "pending" DESC, ca.advance_date DESC, ca.id DESC
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Super Admin - Cash Advances</title>
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
          <h3 class="fw-bold mb-3">Cash Advances</h3>
          <p class="text-muted mb-4">Create cash advances for employees. Pending advances are deducted automatically on the next eligible payroll period.</p>

          <?php if (!empty($flashMsg)): ?>
            <div class="alert alert-<?= $flashType === 'success' ? 'success' : 'danger' ?> py-2"><?= htmlspecialchars($flashMsg) ?></div>
          <?php endif; ?>

          <form method="post" class="eg-panel p-3 mb-4">
            <h5 class="mb-3">Add Cash Advance</h5>
            <div class="row g-3">
              <div class="col-md-5">
                <label class="form-label" for="employee_id">Employee</label>
                <select class="form-select" id="employee_id" name="employee_id" required>
                  <option value="">Select employee...</option>
                  <?php foreach ($employees as $emp): ?>
                    <option value="<?= (int) $emp['employee_id'] ?>">
                      <?= htmlspecialchars((string) $emp['full_name']) ?> (<?= htmlspecialchars((string) ($emp['employee_code'] ?? 'N/A')) ?>) — <?= htmlspecialchars((string) ($emp['office_name'] ?? 'Unassigned')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label" for="amount">Amount</label>
                <input class="form-control" id="amount" name="amount" type="number" min="0.01" step="0.01" placeholder="0.00" required />
              </div>
              <div class="col-md-2">
                <label class="form-label" for="advance_date">Advance Date</label>
                <input class="form-control" id="advance_date" name="advance_date" type="date" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required />
              </div>
              <div class="col-md-3">
                <label class="form-label" for="notes">Notes (optional)</label>
                <input class="form-control" id="notes" name="notes" type="text" maxlength="255" placeholder="Cash advance note..." />
              </div>
            </div>
            <div class="mt-3">
              <button class="btn btn-primary" type="submit">Save Cash Advance</button>
            </div>
          </form>

          <div class="eg-panel p-3">
            <h5 class="mb-3">Cash Advance Records</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Code</th>
                    <th>Office</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th>Deducted In</th>
                    <th>Notes</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($cashAdvances)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-3">No cash advances yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($cashAdvances as $ca): ?>
                      <?php $pending = (($ca['status'] ?? 'pending') === 'pending'); ?>
                      <tr>
                        <td><?= htmlspecialchars((string) $ca['advance_date']) ?></td>
                        <td><?= htmlspecialchars((string) $ca['full_name']) ?></td>
                        <td><?= htmlspecialchars((string) ($ca['employee_code'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($ca['office_name'] ?? '—')) ?></td>
                        <td class="text-end"><?= number_format((float) $ca['amount'], 2) ?></td>
                        <td>
                          <span class="badge <?= $pending ? 'text-bg-warning' : 'text-bg-success' ?>">
                            <?= htmlspecialchars((string) $ca['status']) ?>
                          </span>
                        </td>
                        <td>
                          <?php if (!$pending && !empty($ca['deducted_period_start'])): ?>
                            <?= htmlspecialchars((string) $ca['deducted_period_type']) ?>:
                            <?= htmlspecialchars((string) $ca['deducted_period_start']) ?> to <?= htmlspecialchars((string) ($ca['deducted_period_end'] ?? '')) ?>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string) ($ca['notes'] ?? '')) ?></td>
                        <td class="text-end">
                          <form method="post" class="d-inline">
                            <input type="hidden" name="delete_id" value="<?= (int) $ca['id'] ?>" />
                            <button
                              type="submit"
                              class="btn btn-sm btn-outline-danger"
                              onclick="return confirm('Remove this cash advance record?');"
                            >
                              Remove
                            </button>
                          </form>
                        </td>
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
  </body>
</html>

