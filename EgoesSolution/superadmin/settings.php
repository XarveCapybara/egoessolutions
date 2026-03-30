<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payroll_deduction_types.php';

$settingsStatus = $_SESSION['settings_status'] ?? null;
$settingsMessage = $_SESSION['settings_message'] ?? null;
unset($_SESSION['settings_status'], $_SESSION['settings_message']);

$defaultHourly = '';
$deductionPerMinute = '';
$employees = [];
$hasRateAmount = false;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;

try {
    $pdo->query('SELECT 1 FROM app_settings LIMIT 1');
    $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN (?, ?)');
    $stmt->execute(['hourly_rate_default', 'deduction_per_minute']);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        if (($row['setting_key'] ?? '') === 'hourly_rate_default' && ($row['setting_value'] ?? '') !== '') {
            $defaultHourly = (string) $row['setting_value'];
        }
        if (($row['setting_key'] ?? '') === 'deduction_per_minute' && ($row['setting_value'] ?? '') !== '') {
            $deductionPerMinute = (string) $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    // app_settings may not exist yet
}

$payrollDeductionTypes = [];
try {
    eg_ensure_payroll_deduction_types($pdo);
    $payrollDeductionTypes = $pdo
        ->query('SELECT id, label, default_amount FROM payroll_deduction_types ORDER BY id ASC')
        ->fetchAll();
} catch (Throwable $e) {
    $payrollDeductionTypes = [];
}

if ($hasEmployeesTable) {
    $hasRateAmount = $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;
    $hasRateType = $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_type'")->rowCount() > 0;

    if ($hasRateAmount) {
        $cols = 'e.id, e.employee_code, e.rate_amount, u.full_name, u.email, u.role, u.office_id, o.name AS office_name';
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
            SELECT e.id, e.employee_code, u.full_name, u.email, u.role, u.office_id, o.name AS office_name
            FROM employees e
            JOIN users u ON e.user_id = u.id
            LEFT JOIN offices o ON u.office_id = o.id
            WHERE u.role IN ('employee', 'admin')
            ORDER BY u.full_name
        ");
        $employees = $stmt->fetchAll();
    }
}

$offices = [];
try {
    $offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();
} catch (PDOException $e) {
    $offices = [];
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
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="fw-bold mb-3">Settings</h3>
          <p class="text-muted mb-4">Configure rates and deduction settings for payroll.</p>

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

          <form id="settingsRatesForm" action="save_settings_rates.php" method="post" class="eg-panel p-4 mb-4">
            <h5 class="mb-3">Rates</h5>
            <p class="text-muted small mb-3">Global rate defaults stored in <code>app_settings</code>.</p>
            <div class="row g-3 align-items-end mb-2">
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

            <hr class="my-4" />

            <h5 class="mb-3">Deductions</h5>
            <p class="text-muted small mb-3">Late-deduction defaults stored in <code>app_settings</code>.</p>
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label for="deduction_per_minute" class="form-label">Deduction per minute</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  class="form-control"
                  id="deduction_per_minute"
                  name="deduction_per_minute"
                  value="<?= htmlspecialchars($deductionPerMinute) ?>"
                  placeholder="0.00"
                />
              </div>
            </div>

            <?php if ($hasRateAmount && !empty($employees)): ?>
              <hr class="my-4" />
              <h5 class="mb-3">Per-user hourly rate</h5>
              <p class="text-muted small mb-3">Overrides are saved for employee and admin accounts. Leave blank or enter <strong>0</strong> to use the global default rate. <code>rate_type</code> is set to <strong>hourly</strong> when a positive value is entered.</p>
              <div class="eg-panel p-3 mb-3 bg-light border-0">
                <div class="row g-2 align-items-end flex-wrap">
                  <div class="col-12 col-md-4 col-lg-3">
                    <label for="js-settings-filter-office" class="form-label small text-muted mb-1">Office</label>
                    <select id="js-settings-filter-office" class="form-select form-select-sm" aria-label="Filter by office">
                      <option value="0">All offices</option>
                      <option value="-1" selected>Unassigned</option>
                      <?php foreach ($offices as $o): ?>
                        <option value="<?= (int) $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <label for="js-settings-filter-role" class="form-label small text-muted mb-1">Role</label>
                    <select id="js-settings-filter-role" class="form-select form-select-sm" aria-label="Filter by role">
                      <option value="">All</option>
                      <option value="employee">Employee</option>
                      <option value="admin">Team leader</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-5 col-lg-4">
                    <label for="js-settings-filter-search" class="form-label small text-muted mb-1">Search</label>
                    <input
                      type="search"
                      id="js-settings-filter-search"
                      class="form-control form-control-sm"
                      placeholder="Name, email, code, office…"
                      autocomplete="off"
                    />
                  </div>
                  <div class="col-auto d-flex gap-2 align-items-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="js-settings-filter-clear">Clear</button>
                  </div>
                </div>
                <p class="text-muted small mb-0 mt-2">Filtering happens in the browser — no page reload.</p>
              </div>
              <p id="settingsRatesFilterNoMatches" class="text-muted small d-none mb-2">No one matches these filters. Try clearing filters.</p>
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
                  <tbody id="settingsRatesTableBody">
                    <?php foreach ($employees as $emp): ?>
                      <?php
                      $roleLabel = (($emp['role'] ?? '') === 'admin') ? 'Team leader' : 'Employee';
                      $officeOid = (int) ($emp['office_id'] ?? 0);
                      $rawHay = trim(
                          ($emp['full_name'] ?? '')
                          . ' ' . ($emp['email'] ?? '')
                          . ' ' . ($emp['employee_code'] ?? '')
                          . ' ' . $roleLabel
                          . ' ' . ($emp['office_name'] ?? '')
                      );
                      $searchHay = function_exists('mb_strtolower') ? mb_strtolower($rawHay, 'UTF-8') : strtolower($rawHay);
                      ?>
                      <tr
                        class="eg-settings-rate-row"
                        data-office-id="<?= $officeOid ?>"
                        data-role="<?= htmlspecialchars((string) ($emp['role'] ?? 'employee'), ENT_QUOTES, 'UTF-8') ?>"
                        data-search="<?= htmlspecialchars($searchHay, ENT_QUOTES, 'UTF-8') ?>"
                      >
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
                            value="<?php
                            $ra = $emp['rate_amount'] ?? null;
                            echo ($ra !== null && $ra !== '' && is_numeric($ra) && (float) $ra > 0) ? htmlspecialchars((string) $ra) : '';
                            ?>"
                            placeholder="Default"
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

          <form action="save_payroll_deduction_types.php" method="post" class="eg-panel p-4 mb-4">
            <h5 class="mb-2">Payroll deduction lines</h5>
            <p class="text-muted small mb-3">
              Labels and default amounts shown on printable payslips (SSS, PhilHealth, etc.). Stored in <code>payroll_deduction_types</code>.
            </p>
            <?php if (empty($payrollDeductionTypes)): ?>
              <p class="text-muted small mb-0">Could not load deduction lines. Check the database connection or run <code>scripts/create_payroll_deduction_types.sql</code>.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Label</th>
                      <th style="width: 9rem;">Amount (PHP)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($payrollDeductionTypes as $dt): ?>
                      <?php $did = (int) ($dt['id'] ?? 0); ?>
                      <tr>
                        <td>
                          <input type="text" class="form-control form-control-sm" name="row[<?= $did ?>][label]" value="<?= htmlspecialchars((string) ($dt['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required maxlength="128" />
                        </td>
                        <td>
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control form-control-sm"
                            name="row[<?= $did ?>][amount]"
                            value="<?= htmlspecialchars(number_format((float) ($dt['default_amount'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                          />
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <hr class="my-3" />
              <h6 class="mb-2">Add line</h6>
              <div class="row g-2 align-items-end flex-wrap">
                <div class="col-md-5">
                  <label class="form-label small text-muted mb-1">Label</label>
                  <input type="text" name="new_label" class="form-control form-control-sm" maxlength="128" placeholder="e.g. SSS Contribution" />
                </div>
                <div class="col-md-3">
                  <label class="form-label small text-muted mb-1">Amount (PHP)</label>
                  <input type="number" name="new_amount" class="form-control form-control-sm" step="0.01" min="0" value="0" />
                </div>
              </div>
              <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save deduction lines</button>
              </div>
            <?php endif; ?>
          </form>
        </main>
      </div>
    </div>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <script>
      (function () {
        const form = document.getElementById('settingsRatesForm');
        if (!form) return;
        form.addEventListener('submit', function () {
          document.querySelectorAll('#settingsRatesTableBody tr.eg-settings-rate-row.d-none').forEach(function (tr) {
            tr.classList.remove('d-none');
          });
        });
      })();
    </script>
    <?php if ($hasRateAmount && !empty($employees)): ?>
      <script>
        (function () {
          const officeSel = document.getElementById('js-settings-filter-office');
          const roleSel = document.getElementById('js-settings-filter-role');
          const searchInp = document.getElementById('js-settings-filter-search');
          const clearBtn = document.getElementById('js-settings-filter-clear');
          const tbodyFilter = document.getElementById('settingsRatesTableBody');
          const noMatches = document.getElementById('settingsRatesFilterNoMatches');
          if (!officeSel || !roleSel || !searchInp || !tbodyFilter) return;

          function applySettingsFilters() {
            const rows = tbodyFilter.querySelectorAll('tr.eg-settings-rate-row');
            const office = officeSel.value || '0';
            const roleWant = (roleSel.value || '').trim();
            const q = (searchInp.value || '').trim().toLowerCase();
            let visible = 0;
            rows.forEach(function (el) {
              const oid = String(el.getAttribute('data-office-id') || '0');
              let okOffice = false;
              if (office === '0') {
                okOffice = true;
              } else if (office === '-1') {
                okOffice = oid === '0';
              } else {
                okOffice = oid === office;
              }
              const roleRaw = (el.getAttribute('data-role') || 'employee').toLowerCase();
              const okRole = roleWant === '' || roleRaw === roleWant;
              const hay = (el.getAttribute('data-search') || '').toLowerCase();
              const okSearch = q === '' || hay.indexOf(q) !== -1;
              const show = okOffice && okRole && okSearch;
              el.classList.toggle('d-none', !show);
              if (show) visible += 1;
            });
            if (noMatches) {
              noMatches.classList.toggle('d-none', visible !== 0);
            }
          }

          officeSel.addEventListener('change', applySettingsFilters);
          roleSel.addEventListener('change', applySettingsFilters);
          searchInp.addEventListener('input', applySettingsFilters);
          if (clearBtn) {
            clearBtn.addEventListener('click', function () {
              officeSel.value = '-1';
              roleSel.value = '';
              searchInp.value = '';
              applySettingsFilters();
              searchInp.focus();
            });
          }
          applySettingsFilters();
        })();
      </script>
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
