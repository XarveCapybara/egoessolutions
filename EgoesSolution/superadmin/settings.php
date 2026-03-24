<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';

$settingsStatus = $_SESSION['settings_status'] ?? null;
$settingsMessage = $_SESSION['settings_message'] ?? null;
unset($_SESSION['settings_status'], $_SESSION['settings_message']);

$defaultHourly = '';
$employees = [];
$hasRateAmount = false;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;

try {
    $pdo->query('SELECT 1 FROM app_settings LIMIT 1');
    $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute(['hourly_rate_default']);
    $row = $stmt->fetchColumn();
    if ($row !== false && $row !== null && $row !== '') {
        $defaultHourly = (string) $row;
    }
} catch (PDOException $e) {
    // app_settings may not exist yet
}

if ($hasEmployeesTable) {
    $hasRateAmount = $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;
    $hasRateType = $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_type'")->rowCount() > 0;

    if ($hasRateAmount) {
        $cols = 'e.id, e.employee_code, e.rate_amount, u.full_name, u.role, o.name AS office_name';
        if ($hasRateType) {
            $cols .= ', e.rate_type';
        }
        $stmt = $pdo->query("
            SELECT {$cols}
            FROM employees e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN offices o ON u.office_id = o.id
            WHERE u.role IN ('employee', 'admin')
            ORDER BY u.full_name
        ");
        $employees = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("
            SELECT e.id, e.employee_code, u.full_name, u.role, o.name AS office_name
            FROM employees e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN offices o ON u.office_id = o.id
            WHERE u.role IN ('employee', 'admin')
            ORDER BY u.full_name
        ");
        $employees = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Super Admin - Settings</title>
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
      .eg-sortable-th {
        cursor: pointer;
        white-space: nowrap;
        user-select: none;
      }
      .eg-sortable-th:hover {
        background-color: rgba(0, 0, 0, 0.05);
      }
      .eg-sortable-th .eg-sort-hint {
        font-size: 0.7rem;
        opacity: 0.55;
        margin-left: 0.15rem;
      }
      th.eg-sort-active .eg-sort-hint {
        opacity: 1;
      }
    </style>
  </head>
  <body class="bg-light">
    <header class="eg-topbar d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <img src="../assets/images/egoes-logo.png?v=3" alt="E-GOES Solutions" class="eg-system-logo" />
      </div>
      <div class="d-flex align-items-center me-3">
        <div class="me-2 fw-bold fs-5">SuperAdmin-<?= htmlspecialchars($name) ?></div>
        <div class="eg-avatar-circle"></div>
      </div>
    </header>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="fw-bold mb-3">Settings</h3>
          <p class="text-muted mb-4">Configure default and per-user hourly rates for employees and admins.</p>

          <?php if (!empty($settingsMessage)): ?>
            <div class="alert <?= $settingsStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2 mb-3">
              <?= htmlspecialchars($settingsMessage) ?>
            </div>
          <?php endif; ?>

          <?php if (!$hasRateAmount && $hasEmployeesTable): ?>
            <div class="alert alert-warning mb-4">
              <strong>Database:</strong> Add hourly rate columns to <code>employees</code> to edit per-employee rates:
              <pre class="small mb-0 mt-2">ALTER TABLE employees ADD COLUMN rate_amount DECIMAL(12,2) NULL DEFAULT NULL;
ALTER TABLE employees ADD COLUMN rate_type VARCHAR(32) NULL DEFAULT NULL;</pre>
            </div>
          <?php endif; ?>

          <form action="save_settings_rates.php" method="post" class="eg-panel p-4 mb-4">
            <h5 class="mb-3">Default hourly rate</h5>
            <p class="text-muted small mb-3">Used as the system default reference (stored in <code>app_settings</code>).</p>
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label for="default_hourly_rate" class="form-label">Amount per hour</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  class="form-control"
                  id="default_hourly_rate"
                  name="default_hourly_rate"
                  value="<?= htmlspecialchars($defaultHourly) ?>"
                  placeholder="0.00"
                />
              </div>
            </div>

            <?php if ($hasRateAmount && !empty($employees)): ?>
              <hr class="my-4" />
              <h5 class="mb-3">Per-user hourly rate</h5>
              <p class="text-muted small mb-3">Overrides are saved for employee and admin accounts. Leave blank to clear. <code>rate_type</code> is set to <strong>hourly</strong> when a value is entered.</p>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="ratesSortTable">
                  <thead class="table-light">
                    <tr>
                      <th class="eg-sortable-th" scope="col" title="Click to sort">User</th>
                      <th class="eg-sortable-th" scope="col" title="Click to sort">Role</th>
                      <th class="eg-sortable-th" scope="col" title="Click to sort">Office</th>
                      <th class="eg-sortable-th" scope="col" title="Click to sort">Code</th>
                      <th class="eg-sortable-th" scope="col" style="width: 220px;" title="Click to sort">Rate / hour</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($employees as $emp): ?>
                      <?php
                      $roleLabel = (($emp['role'] ?? '') === 'admin') ? 'Team leader' : 'Employee';
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($emp['full_name']) ?></td>
                        <td><span class="badge bg-secondary bg-opacity-25 text-dark"><?= htmlspecialchars($roleLabel) ?></span></td>
                        <td><?= htmlspecialchars($emp['office_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($emp['employee_code']) ?></td>
                        <td>
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control form-control-sm"
                            name="rate_amount[<?= (int) $emp['id'] ?>]"
                            value="<?= isset($emp['rate_amount']) && $emp['rate_amount'] !== null && $emp['rate_amount'] !== '' ? htmlspecialchars($emp['rate_amount']) : '' ?>"
                            placeholder="—"
                          />
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php elseif ($hasEmployeesTable && empty($employees)): ?>
              <p class="text-muted small mb-0">No employee/admin records found yet.</p>
            <?php endif; ?>

            <div class="mt-4">
              <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
          </form>
        </main>
      </div>
    </div>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <?php if ($hasRateAmount && !empty($employees)): ?>
      <script>
        (function () {
          const table = document.getElementById('ratesSortTable');
          if (!table) return;
          const tbody = table.querySelector('tbody');
          const headers = Array.from(table.querySelectorAll('thead th'));
          let activeCol = -1;
          let dir = 1;

          function clearSortMarks() {
            headers.forEach(function (th) {
              th.classList.remove('eg-sort-active');
              const hint = th.querySelector('.eg-sort-hint');
              if (hint) hint.textContent = '';
            });
          }

          function setMark(th, ascending) {
            clearSortMarks();
            th.classList.add('eg-sort-active');
            let hint = th.querySelector('.eg-sort-hint');
            if (!hint) {
              hint = document.createElement('span');
              hint.className = 'eg-sort-hint';
              hint.setAttribute('aria-hidden', 'true');
              th.appendChild(document.createTextNode(' '));
              th.appendChild(hint);
            }
            hint.textContent = ascending ? '▲' : '▼';
          }

          function getTextCellValue(tr, colIdx) {
            return (tr.children[colIdx].textContent || '').trim().toLowerCase();
          }

          function getRateCellValue(tr, colIdx) {
            const inp = tr.children[colIdx].querySelector('input[type="number"]');
            if (!inp || inp.value === '') return null;
            const n = parseFloat(inp.value);
            return isNaN(n) ? null : n;
          }

          headers.forEach(function (th, colIdx) {
            const isRateCol = colIdx === headers.length - 1;
            th.addEventListener('click', function () {
              if (activeCol === colIdx) {
                dir = -dir;
              } else {
                activeCol = colIdx;
                dir = 1;
              }
              setMark(th, dir === 1);

              const rows = Array.from(tbody.querySelectorAll('tr'));
              rows.sort(function (a, b) {
                if (isRateCol) {
                  const av = getRateCellValue(a, colIdx);
                  const bv = getRateCellValue(b, colIdx);
                  if (av === null && bv === null) return 0;
                  if (av === null) return 1;
                  if (bv === null) return -1;
                  return (av - bv) * dir;
                }
                const av = getTextCellValue(a, colIdx);
                const bv = getTextCellValue(b, colIdx);
                if (av < bv) return -1 * dir;
                if (av > bv) return 1 * dir;
                return 0;
              });
              rows.forEach(function (r) {
                tbody.appendChild(r);
              });
            });
          });
        })();
      </script>
    <?php endif; ?>
  </body>
</html>
