<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
$csrfToken = eg_csrf_token();

$officeId = (int) ($_GET['id'] ?? 0);
if ($officeId <= 0) {
    header('Location: offices.php');
    exit;
}

$hasTeamLeaderColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'team_leader'")->rowCount() > 0;
$hasTimeInColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_in'")->rowCount() > 0;
$hasTimeOutColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_out'")->rowCount() > 0;
if ($hasTeamLeaderColumn) {
    $selectTimeIn = $hasTimeInColumn ? 'time_in' : 'NULL AS time_in';
    $selectTimeOut = $hasTimeOutColumn ? 'time_out' : 'NULL AS time_out';
    $officeStmt = $pdo->prepare("SELECT id, name, address, team_leader, {$selectTimeIn}, {$selectTimeOut} FROM offices WHERE id = ? LIMIT 1");
} else {
    $selectTimeIn = $hasTimeInColumn ? 'time_in' : 'NULL AS time_in';
    $selectTimeOut = $hasTimeOutColumn ? 'time_out' : 'NULL AS time_out';
    $officeStmt = $pdo->prepare("SELECT id, name, address, NULL AS team_leader, {$selectTimeIn}, {$selectTimeOut} FROM offices WHERE id = ? LIMIT 1");
}
$officeStmt->execute([$officeId]);
$office = $officeStmt->fetch();

if (!$office) {
    header('Location: offices.php');
    exit;
}

$assignStatus = $_SESSION['office_assign_status'] ?? null;
$assignMessage = $_SESSION['office_assign_message'] ?? null;
unset($_SESSION['office_assign_status'], $_SESSION['office_assign_message']);

$employeeCountStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = "employee" AND office_id = ?');
$employeeCountStmt->execute([$officeId]);
$employeeCount = (int) $employeeCountStmt->fetchColumn();

$employeeListStmt = $pdo->prepare('SELECT full_name, email FROM users WHERE role = "employee" AND office_id = ? ORDER BY full_name');
$employeeListStmt->execute([$officeId]);
$employees = $employeeListStmt->fetchAll();

$assignableEmployeesStmt = $pdo->prepare('
    SELECT u.id, u.full_name, u.email, u.office_id AS current_office_id, o.name AS current_office
    FROM users u
    LEFT JOIN offices o ON u.office_id = o.id
    WHERE u.role = "employee" AND (u.office_id IS NULL OR u.office_id <> ?)
    ORDER BY u.full_name
');
$assignableEmployeesStmt->execute([$officeId]);
$assignableEmployees = $assignableEmployeesStmt->fetchAll();

$officesForAddFilter = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();

$attendanceToday = 0;
$recentAttendance = [];
$hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
$presentEmployeeEmails = [];

if ($hasAttendanceLogs && $hasEmployeesTable) {
    $presentEmployeesStmt = $pdo->prepare('
        SELECT DISTINCT u.email
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        JOIN users u ON e.user_id = u.id
        WHERE al.office_id = ? AND DATE(al.log_date) = CURDATE()
    ');
    $presentEmployeesStmt->execute([$officeId]);
    foreach ($presentEmployeesStmt->fetchAll() as $row) {
        $presentEmployeeEmails[$row['email']] = true;
    }

    $attendanceTodayStmt = $pdo->prepare('SELECT COUNT(*) FROM attendance_logs WHERE office_id = ? AND DATE(log_date) = CURDATE()');
    $attendanceTodayStmt->execute([$officeId]);
    $attendanceToday = (int) $attendanceTodayStmt->fetchColumn();

    $recentAttendanceStmt = $pdo->prepare('
        SELECT al.log_date, al.time_in, al.time_out, al.status, u.full_name
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        JOIN users u ON e.user_id = u.id
        WHERE al.office_id = ?
        ORDER BY al.log_date DESC, al.time_in DESC
        LIMIT 10
    ');
    $recentAttendanceStmt->execute([$officeId]);
    $recentAttendance = $recentAttendanceStmt->fetchAll();
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
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h3 class="fw-bold mb-1"><?= htmlspecialchars($office['name']) ?> Overview</h3>
              <p class="text-muted mb-0"><?= htmlspecialchars($office['address'] ?? 'No address provided') ?></p>
              <p class="text-muted small mb-0">Team Leader: <?= htmlspecialchars($office['team_leader'] ?: 'Not assigned') ?></p>
              <p class="text-muted small mb-0">
                Work Time:
                <?php if (!empty($office['time_in']) && !empty($office['time_out'])): ?>
                  <?= date('h:i A', strtotime($office['time_in'])) ?> - <?= date('h:i A', strtotime($office['time_out'])) ?>
                <?php else: ?>
                  Not set
                <?php endif; ?>
              </p>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                Add Existing Employee
              </button>
              <a href="offices.php" class="btn btn-outline-secondary btn-sm">Back to Offices</a>
            </div>
          </div>

          <?php if (!empty($assignMessage)): ?>
            <div class="alert <?= $assignStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2">
              <?= htmlspecialchars($assignMessage) ?>
            </div>
          <?php endif; ?>

          <div class="row g-3 mb-4">
            <div class="col-md-4">
              <div class="eg-metric-card">
                <div class="text-muted small">Office ID</div>
                <div class="fw-bold fs-4"><?= (int) $office['id'] ?></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="eg-metric-card">
                <div class="text-muted small">Total Employees</div>
                <div class="fw-bold fs-4" id="officeEmployeeCount"><?= $employeeCount ?></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="eg-metric-card">
                <div class="text-muted small">Attendance Logs Today</div>
                <div class="fw-bold fs-4"><?= $attendanceToday ?></div>
              </div>
            </div>
          </div>

          <div class="eg-panel p-3 mb-4">
            <h5 class="mb-3">Employees in This Office</h5>
            <div id="officeEmployeesSection">
            <?php if (empty($employees)): ?>
              <p id="officeEmployeesEmpty" class="text-muted small mb-0">No employees assigned to this office yet.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Today Status</th></tr></thead>
                  <tbody id="officeEmployeesTableBody">
                    <?php foreach ($employees as $employee): ?>
                      <?php $isPresent = !empty($presentEmployeeEmails[$employee['email']]); ?>
                      <tr>
                        <td><?= htmlspecialchars($employee['full_name']) ?></td>
                        <td><?= htmlspecialchars($employee['email']) ?></td>
                        <td>
                          <?php if ($isPresent): ?>
                            <span class="badge text-bg-success">Present</span>
                          <?php else: ?>
                            <span class="badge text-bg-secondary">Absent</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
            </div>
          </div>

          <div class="eg-panel p-3">
            <h5 class="mb-3">Recent Attendance (Last 10)</h5>
            <?php if (!$hasAttendanceLogs): ?>
              <p class="text-muted small mb-0">Attendance table is not available yet.</p>
            <?php elseif (empty($recentAttendance)): ?>
              <p class="text-muted small mb-0">No attendance records for this office yet.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr><th>Employee</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Status</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentAttendance as $entry): ?>
                      <tr>
                        <td><?= htmlspecialchars($entry['full_name']) ?></td>
                        <td><?= date('M j, Y', strtotime($entry['log_date'])) ?></td>
                        <td><?= $entry['time_in'] ? date('h:i A', strtotime($entry['time_in'])) : '—' ?></td>
                        <td><?= $entry['time_out'] ? date('h:i A', strtotime($entry['time_out'])) : '—' ?></td>
                        <td><?= htmlspecialchars($entry['status']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </main>
      </div>
    </div>

    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addEmployeeModalLabel">Add Existing Employee to This Office</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="addEmployeeAssignFeedback" class="alert py-2 d-none mb-3" role="alert"></div>
            <?php if (empty($assignableEmployees)): ?>
              <p class="text-muted small mb-0">No available employees to assign.</p>
            <?php else: ?>
              <div class="row g-2 mb-2">
                <div class="col-sm-6">
                  <label for="addEmployeeOfficeFilter" class="form-label small text-muted mb-1">Office</label>
                  <select id="addEmployeeOfficeFilter" class="form-select form-select-sm" aria-label="Filter by current office">
                    <option value="0">All offices</option>
                    <option value="-1">Unassigned</option>
                    <?php foreach ($officesForAddFilter as $of): ?>
                      <option value="<?= (int) $of['id'] ?>"><?= htmlspecialchars($of['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-sm-6">
                  <label for="addEmployeeSearch" class="form-label small text-muted mb-1">Search</label>
                  <input
                    type="search"
                    id="addEmployeeSearch"
                    class="form-control form-control-sm"
                    placeholder="Name, email, or current office…"
                    autocomplete="off"
                    aria-label="Filter employees to add"
                  />
                </div>
              </div>
              <p id="addEmployeeNoMatches" class="text-muted small d-none mb-0">No employees match these filters.</p>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Current Office</th><th></th></tr></thead>
                  <tbody id="addEmployeeTableBody">
                    <?php foreach ($assignableEmployees as $candidate): ?>
                      <?php
                      $officeLabel = $candidate['current_office'] ?? '';
                      $officeLabel = $officeLabel !== '' ? $officeLabel : 'Unassigned';
                      $currentOfficeId = (int) ($candidate['current_office_id'] ?? 0);
                      $rawHay = trim(($candidate['full_name'] ?? '') . ' ' . ($candidate['email'] ?? '') . ' ' . $officeLabel);
                      $searchHay = function_exists('mb_strtolower') ? mb_strtolower($rawHay, 'UTF-8') : strtolower($rawHay);
                      ?>
                      <tr
                        data-search="<?= htmlspecialchars($searchHay, ENT_QUOTES, 'UTF-8') ?>"
                        data-office-id="<?= $currentOfficeId ?>"
                      >
                        <td><?= htmlspecialchars($candidate['full_name']) ?></td>
                        <td><?= htmlspecialchars($candidate['email']) ?></td>
                        <td><?= htmlspecialchars($officeLabel) ?></td>
                        <td class="text-end">
                          <form action="assign_employee_office.php" method="post" class="d-inline js-assign-employee-form">
                            <input type="hidden" name="office_id" value="<?= (int) $office['id'] ?>" />
                            <input type="hidden" name="employee_user_id" value="<?= (int) $candidate['id'] ?>" />
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
                            <button type="submit" class="btn btn-sm btn-outline-primary">Add to This Office</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <script>
      (function () {
        const searchInput = document.getElementById('addEmployeeSearch');
        const officeFilter = document.getElementById('addEmployeeOfficeFilter');
        const tbody = document.getElementById('addEmployeeTableBody');
        const noMatches = document.getElementById('addEmployeeNoMatches');
        if (!searchInput || !tbody) return;

        function applyAddEmployeeFilter() {
          const rows = tbody.querySelectorAll('tr[data-search]');
          const q = (searchInput.value || '').trim().toLowerCase();
          const office = officeFilter ? officeFilter.value || '0' : '0';
          let visible = 0;
          rows.forEach(function (tr) {
            const oid = String(tr.getAttribute('data-office-id') || '0');
            let okOffice = false;
            if (office === '0') {
              okOffice = true;
            } else if (office === '-1') {
              okOffice = oid === '0';
            } else {
              okOffice = oid === office;
            }
            const hay = (tr.getAttribute('data-search') || '').toLowerCase();
            const okSearch = q === '' || hay.indexOf(q) !== -1;
            const show = okOffice && okSearch;
            tr.classList.toggle('d-none', !show);
            if (show) visible += 1;
          });
          if (noMatches) {
            noMatches.classList.toggle('d-none', visible > 0 || rows.length === 0);
          }
        }

        searchInput.addEventListener('input', applyAddEmployeeFilter);
        if (officeFilter) {
          officeFilter.addEventListener('change', applyAddEmployeeFilter);
        }
        window.applyAddEmployeeFilter = applyAddEmployeeFilter;

        const modal = document.getElementById('addEmployeeModal');
        if (modal) {
          modal.addEventListener('shown.bs.modal', function () {
            searchInput.value = '';
            if (officeFilter) officeFilter.value = '0';
            applyAddEmployeeFilter();
            searchInput.focus();
            const fb = document.getElementById('addEmployeeAssignFeedback');
            if (fb) {
              fb.classList.add('d-none');
              fb.textContent = '';
            }
          });
        }
      })();

      (function () {
        const modalBody = document.querySelector('#addEmployeeModal .modal-body');
        const feedback = document.getElementById('addEmployeeAssignFeedback');
        const countEl = document.getElementById('officeEmployeeCount');
        const section = document.getElementById('officeEmployeesSection');

        function showAssignFeedback(message, isError) {
          if (!feedback) return;
          feedback.textContent = message;
          feedback.className = 'alert py-2 mb-3 ' + (isError ? 'alert-danger' : 'alert-success');
          feedback.classList.remove('d-none');
        }

        function appendEmployeeToOfficeTable(fullName, email) {
          if (!section) return;
          let tbody = document.getElementById('officeEmployeesTableBody');
          const empty = document.getElementById('officeEmployeesEmpty');
          if (empty) {
            empty.remove();
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            wrap.innerHTML =
              '<table class="table table-sm align-middle mb-0">' +
              '<thead class="table-light"><tr><th>Name</th><th>Email</th><th>Today Status</th></tr></thead>' +
              '<tbody id="officeEmployeesTableBody"></tbody></table>';
            section.appendChild(wrap);
            tbody = document.getElementById('officeEmployeesTableBody');
          }
          if (!tbody) return;
          const tr = document.createElement('tr');
          const esc = function (s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
          };
          tr.innerHTML =
            '<td>' +
            esc(fullName) +
            '</td><td>' +
            esc(email) +
            '</td><td><span class="badge text-bg-secondary">Absent</span></td>';
          tbody.appendChild(tr);
        }

        function bumpEmployeeCount() {
          if (!countEl) return;
          const n = parseInt(countEl.textContent, 10);
          countEl.textContent = String(isNaN(n) ? 1 : n + 1);
        }

        if (!modalBody) return;

        modalBody.addEventListener('submit', async function (e) {
          const form = e.target;
          if (!form || !form.classList.contains('js-assign-employee-form')) return;
          e.preventDefault();

          const row = form.closest('tr');
          const nameCell = row ? row.querySelector('td') : null;
          const emailCell = row && row.cells.length > 1 ? row.cells[1] : null;
          const fullName = nameCell ? nameCell.textContent.trim() : '';
          const email = emailCell ? emailCell.textContent.trim() : '';

          const btn = form.querySelector('[type="submit"]');
          const prev = btn ? btn.textContent : '';
          if (btn) {
            btn.disabled = true;
            btn.textContent = 'Adding…';
          }

          try {
            const res = await fetch('assign_employee_office.php', {
              method: 'POST',
              body: new FormData(form),
              headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
              credentials: 'same-origin',
            });
            const ct = (res.headers.get('content-type') || '').toLowerCase();
            if (!ct.includes('application/json')) {
              showAssignFeedback('Session may have expired. Refresh the page and try again.', true);
              return;
            }
            const data = await res.json();
            if (!data || typeof data.ok === 'undefined') {
              showAssignFeedback('Invalid response from server.', true);
              return;
            }
            if (!data.ok) {
              showAssignFeedback(data.message || 'Could not add employee.', true);
              return;
            }
            showAssignFeedback(data.message || 'Added to office successfully.', false);
            if (row) row.remove();
            bumpEmployeeCount();
            appendEmployeeToOfficeTable(fullName, email);
            if (typeof window.applyAddEmployeeFilter === 'function') {
              window.applyAddEmployeeFilter();
            }
          } catch (err) {
            showAssignFeedback(err.message || 'Network error.', true);
          } finally {
            if (btn) {
              btn.disabled = false;
              btn.textContent = prev;
            }
          }
        });
      })();
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>
