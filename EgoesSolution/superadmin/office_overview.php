<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';

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
    SELECT u.id, u.full_name, u.email, o.name AS current_office
    FROM users u
    LEFT JOIN offices o ON u.office_id = o.id
    WHERE u.role = "employee" AND (u.office_id IS NULL OR u.office_id <> ?)
    ORDER BY u.full_name
');
$assignableEmployeesStmt->execute([$officeId]);
$assignableEmployees = $assignableEmployeesStmt->fetchAll();

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
    <title>Office Overview</title>
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
                <div class="fw-bold fs-4"><?= $employeeCount ?></div>
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
            <?php if (empty($employees)): ?>
              <p class="text-muted small mb-0">No employees assigned to this office yet.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Today Status</th></tr></thead>
                  <tbody>
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
            <?php if (empty($assignableEmployees)): ?>
              <p class="text-muted small mb-0">No available employees to assign.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Current Office</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($assignableEmployees as $candidate): ?>
                      <tr>
                        <td><?= htmlspecialchars($candidate['full_name']) ?></td>
                        <td><?= htmlspecialchars($candidate['email']) ?></td>
                        <td><?= htmlspecialchars($candidate['current_office'] ?? 'Unassigned') ?></td>
                        <td class="text-end">
                          <form action="assign_employee_office.php" method="post" class="d-inline">
                            <input type="hidden" name="office_id" value="<?= (int) $office['id'] ?>" />
                            <input type="hidden" name="employee_user_id" value="<?= (int) $candidate['id'] ?>" />
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
  </body>
</html>
