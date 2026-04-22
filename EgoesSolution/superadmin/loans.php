<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';

// Ensure cash_advances table exists and clean up legacy columns (once per session)
if (empty($_SESSION['_eg_cash_advances_migrated'])) {
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
        // Drop legacy columns if they exist
        foreach (['deducted_period_type', 'deducted_period_start', 'deducted_period_end'] as $legacyCol) {
            if ($pdo->query("SHOW COLUMNS FROM cash_advances LIKE '{$legacyCol}'")->rowCount() > 0) {
                $pdo->exec("ALTER TABLE cash_advances DROP COLUMN {$legacyCol}");
            }
        }
        if ($pdo->query("SHOW COLUMNS FROM cash_advances LIKE 'requested_by_user_id'")->rowCount() === 0) {
            $pdo->exec('ALTER TABLE cash_advances ADD COLUMN requested_by_user_id INT NULL AFTER employee_id');
        }
        if ($pdo->query("SHOW COLUMNS FROM cash_advances LIKE 'request_source'")->rowCount() === 0) {
            $pdo->exec('ALTER TABLE cash_advances ADD COLUMN request_source VARCHAR(20) NULL AFTER requested_by_user_id');
        }
        // One-time status cleanup
        $pdo->exec("UPDATE cash_advances SET status = 'pending' WHERE status = 'accredited'");
    } catch (Throwable $e) {
        // ignore migration issues
    }
    $_SESSION['_eg_cash_advances_migrated'] = true;
}


$flashType = $_SESSION['loans_flash_type'] ?? null;
$flashMsg = $_SESSION['loans_flash_msg'] ?? null;
unset($_SESSION['loans_flash_type'], $_SESSION['loans_flash_msg']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (isset($_POST['approve_id'])) {
        $approveId = (int) ($_POST['approve_id'] ?? 0);
        if ($approveId <= 0) {
            $_SESSION['loans_flash_type'] = 'danger';
            $_SESSION['loans_flash_msg'] = 'Invalid cash advance record.';
            header('Location: loans.php');
            exit;
        }
        $approve = $pdo->prepare("UPDATE cash_advances SET status = 'deducted', advance_date = CURDATE() WHERE id = ? AND status = 'pending' LIMIT 1");
        $approve->execute([$approveId]);
        $_SESSION['loans_flash_type'] = 'success';
        $_SESSION['loans_flash_msg'] = 'Cash advance approved, date set to today, and marked as deducted.';
        header('Location: loans.php');
        exit;
    }

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
    $amountRaw = str_replace(["\xC2\xA0", ' '], '', $amountRaw);
    $amountRaw = str_replace(',', '.', $amountRaw);
    if ($employeeId <= 0 || $amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw <= 0) {
        $_SESSION['loans_flash_type'] = 'danger';
        $_SESSION['loans_flash_msg'] = 'Please provide valid employee and amount.';
        header('Location: loans.php');
        exit;
    }
    $amount = number_format((float) $amountRaw, 2, '.', '');
    $ins = $pdo->prepare('
        INSERT INTO cash_advances (employee_id, amount, notes, advance_date, status)
        VALUES (?, ?, ?, CURDATE(), "pending")
    ');
    $ins->execute([$employeeId, $amount, $notes !== '' ? $notes : null]);

    $_SESSION['loans_flash_type'] = 'success';
    $_SESSION['loans_flash_msg'] = 'Cash advance added. It will be auto-deducted in the payroll week of the advance date.';
    header('Location: loans.php');
    exit;
}

$employees = $pdo->query('
    SELECT 
      e.id AS employee_id, 
      CONCAT(u.full_name, " (", COALESCE(e.employee_code, "N/A"), ") — ", COALESCE(o.name, "Unassigned")) AS display_name,
      LOWER(CONCAT(u.full_name, " (", COALESCE(e.employee_code, "N/A"), ") — ", COALESCE(o.name, "Unassigned"))) AS search_name
    FROM employees e
    JOIN users u ON u.id = e.user_id
    LEFT JOIN offices o ON o.id = u.office_id
    WHERE u.role = "employee" AND u.is_active = 1
    ORDER BY u.full_name
')->fetchAll(PDO::FETCH_ASSOC);

$selectedEmployeeId = (int) ($_GET['employee_filter_id'] ?? 0);

$cashAdvancesSql = '
    SELECT
      ca.id,
      ca.employee_id,
      ca.amount,
      ca.notes,
      ca.advance_date,
      ca.status,
      ca.created_at,
      ca.request_source,
      u.full_name,
      e.employee_code,
      o.name AS office_name
    FROM cash_advances ca
    JOIN employees e ON e.id = ca.employee_id
    JOIN users u ON u.id = e.user_id
    LEFT JOIN offices o ON o.id = u.office_id
';
$cashAdvancesParams = [];
if ($selectedEmployeeId > 0) {
    $cashAdvancesSql .= ' WHERE ca.employee_id = ? ';
    $cashAdvancesParams[] = $selectedEmployeeId;
}
$cashAdvancesSql .= '
    ORDER BY
      CASE ca.status
        WHEN "pending" THEN 0
        ELSE 1
      END,
      ca.advance_date DESC,
      ca.id DESC
    LIMIT 200
';
$cashAdvancesStmt = $pdo->prepare($cashAdvancesSql);
$cashAdvancesStmt->execute($cashAdvancesParams);
$cashAdvances = $cashAdvancesStmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="../assets/css/style.css?v=blue1" />
    <style>
      .eg-confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(17, 24, 39, 0.45);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1080;
      }
      .eg-confirm-overlay.show { display: flex; }
      .eg-confirm-box {
        width: min(92vw, 380px);
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 20px 48px rgba(0, 0, 0, 0.25);
        padding: 18px;
      }
      .eg-confirm-title { font-weight: 700; margin-bottom: 8px; }
      .eg-confirm-message { color: #374151; margin-bottom: 14px; }
    </style>
  </head>
  <body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>
        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="fw-bold mb-3">Cash Advances</h3>
          
          <form method="post" class="eg-panel p-3 mb-4">
            <h5 class="mb-3">Add Cash Advance</h5>
            <div class="row g-3">
              <div class="col-12 col-md-5">
                <label class="form-label" for="employee_id">Employee</label>
                <select class="form-select text-truncate" id="employee_id" name="employee_id" required style="max-width: 100%;">
                  <option value="">Select employee...</option>
                  <?php foreach ($employees as $emp): ?>
                    <option
                      value="<?= (int) $emp['employee_id'] ?>"
                      data-search="<?= htmlspecialchars($emp['search_name'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                      <?= htmlspecialchars($emp['display_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-3">
                <label class="form-label" for="amount">Amount</label>
                <input class="form-control" id="amount" name="amount" type="number" min="0.01" step="0.01" placeholder="0.00" required />
                <div class="form-text d-none d-md-block" style="font-size:0.75rem;">Date set when approved.</div>
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label" for="notes">Notes (optional)</label>
                <input class="form-control" id="notes" name="notes" type="text" maxlength="255" placeholder="Cash advance note..." />
              </div>
              <div class="col-12 d-md-none text-muted" style="font-size:0.8rem;">
                Date is set automatically when approved.
              </div>
            </div>
            <div class="mt-3">
              <button class="btn btn-primary" type="submit">Save Cash Advance</button>
            </div>
          </form>

          <div class="eg-panel p-3">
            <h5 class="mb-3">Cash Advance Records</h5>
            <form method="get" class="row g-2 align-items-end mb-3">
              <div class="col-12 col-md-5 col-lg-4">
                <label class="form-label" for="employee_filter_id">Filter by employee</label>
                <select class="form-select" id="employee_filter_id" name="employee_filter_id">
                  <option value="">All employees</option>
                  <?php foreach ($employees as $emp): ?>
                    <?php $filterEmployeeId = (int) $emp['employee_id']; ?>
                    <option
                      value="<?= $filterEmployeeId ?>"
                      <?= $selectedEmployeeId === $filterEmployeeId ? 'selected' : '' ?>
                    >
                      <?= htmlspecialchars($emp['display_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-auto">
                <a class="btn btn-outline-secondary" href="loans.php">Reset</a>
              </div>
            </form>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Code</th>
                    <th>Office</th>
                    <th>Requested Via</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th>Deducted In</th>
                    <th>Notes</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($cashAdvances)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-3">No cash advances yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($cashAdvances as $ca): ?>
                      <?php
                        $status = (string) ($ca['status'] ?? 'pending');
                        $pending = ($status === 'pending');
                      ?>
                      <tr>
                        <td><?= htmlspecialchars((string) $ca['advance_date']) ?></td>
                        <td><?= htmlspecialchars((string) $ca['full_name']) ?></td>
                        <td><?= htmlspecialchars((string) ($ca['employee_code'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($ca['office_name'] ?? '—')) ?></td>
                        <td>
                          <?php
                          $reqSource = strtolower(trim((string) ($ca['request_source'] ?? '')));
                          if ($reqSource === '') {
                              $reqSource = 'superadmin';
                          }
                          ?>
                          <?= htmlspecialchars(ucfirst($reqSource)) ?>
                        </td>
                        <td class="text-end"><?= number_format((float) $ca['amount'], 2) ?></td>
                        <td>
                          <span class="badge <?= $pending ? 'text-bg-warning' : 'text-bg-success' ?>">
                            <?= htmlspecialchars($status) ?>
                          </span>
                        </td>
                        <td>
                          <?php if ($status === 'deducted'): ?>
                            Week of <?= htmlspecialchars((string) $ca['advance_date']) ?>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string) ($ca['notes'] ?? '')) ?></td>
                        <td class="text-end">
                          <?php if ($pending): ?>
                            <form method="post" class="d-inline">
                              <input type="hidden" name="approve_id" value="<?= (int) $ca['id'] ?>" />
                              <button
                                type="submit"
                                class="btn btn-sm btn-outline-success"
                                data-confirm-message="Approve this cash advance and mark as deducted?"
                              >
                                Approve
                              </button>
                            </form>
                          <?php endif; ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="delete_id" value="<?= (int) $ca['id'] ?>" />
                            <button
                              type="submit"
                              class="btn btn-sm btn-outline-danger"
                              data-confirm-message="Remove this cash advance record?"
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
    <div class="eg-confirm-overlay" id="egConfirmOverlay" aria-hidden="true">
      <div class="eg-confirm-box" role="dialog" aria-modal="true" aria-labelledby="egConfirmTitle">
        <div class="eg-confirm-title" id="egConfirmTitle">Please confirm</div>
        <div class="eg-confirm-message" id="egConfirmMessage">Proceed with this action?</div>
        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="egConfirmCancel">Cancel</button>
          <button type="button" class="btn btn-sm btn-primary" id="egConfirmOk">Confirm</button>
        </div>
      </div>
    </div>
    <script>
      (function () {
        var overlay = document.getElementById('egConfirmOverlay');
        var msgEl = document.getElementById('egConfirmMessage');
        var btnCancel = document.getElementById('egConfirmCancel');
        var btnOk = document.getElementById('egConfirmOk');
        var pendingForm = null;
        if (!overlay || !msgEl || !btnCancel || !btnOk) return;

        function openConfirm(message, form) {
          pendingForm = form;
          msgEl.textContent = message || 'Proceed with this action?';
          overlay.classList.add('show');
          overlay.setAttribute('aria-hidden', 'false');
        }

        function closeConfirm() {
          overlay.classList.remove('show');
          overlay.setAttribute('aria-hidden', 'true');
          pendingForm = null;
        }

        document.querySelectorAll('form button[type="submit"][data-confirm-message]').forEach(function (btn) {
          btn.addEventListener('click', function (event) {
            var form = btn.closest('form');
            if (!form) return;
            event.preventDefault();
            openConfirm(btn.getAttribute('data-confirm-message') || '', form);
          });
        });

        var addForm = document.querySelector('form.eg-panel');
        if (addForm) {
          addForm.addEventListener('submit', function (event) {
            if (addForm.getAttribute('data-confirmed') === '1') {
              addForm.removeAttribute('data-confirmed');
              return;
            }
            event.preventDefault();
            openConfirm('Save this cash advance entry?', addForm);
          });
        }

        var employeeFilter = document.getElementById('employee_filter_id');
        if (employeeFilter) {
          employeeFilter.addEventListener('change', function () {
            var filterForm = employeeFilter.closest('form');
            if (!filterForm) return;
            filterForm.submit();
          });
        }

        btnCancel.addEventListener('click', closeConfirm);
        overlay.addEventListener('click', function (event) {
          if (event.target === overlay) closeConfirm();
        });
        document.addEventListener('keydown', function (event) {
          if (event.key === 'Escape' && overlay.classList.contains('show')) {
            closeConfirm();
          }
        });
        btnOk.addEventListener('click', function () {
          if (!pendingForm) return closeConfirm();
          if (pendingForm.classList.contains('eg-panel')) {
            pendingForm.setAttribute('data-confirmed', '1');
          }
          pendingForm.submit();
          closeConfirm();
        });
      })();
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>

