<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Admin';
$officeId = $_SESSION['office_id'] ?? null;
$designatedOfficeName = 'Unassigned';
$designatedOfficeTimeRange = 'Not set';

require_once __DIR__ . '/../config/database.php';
$totalEmployees = 0;
$todayPresent = 0;
$scansToday = 0;
$lateArrivals = 0;
$recentLogs = [];
$employeeStatusList = [];
$effectiveWorkdayDate = date('Y-m-d');
$isGraveyardShift = false;
if ($officeId) {
    $hasOfficeTimeInColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_in'")->rowCount() > 0;
    $hasOfficeTimeOutColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_out'")->rowCount() > 0;
    $selectTimeIn = $hasOfficeTimeInColumn ? 'time_in' : 'NULL AS time_in';
    $selectTimeOut = $hasOfficeTimeOutColumn ? 'time_out' : 'NULL AS time_out';
    $officeStmt = $pdo->prepare("SELECT name, {$selectTimeIn}, {$selectTimeOut} FROM offices WHERE id = ? LIMIT 1");
    $officeStmt->execute([$officeId]);
    $officeRow = $officeStmt->fetch();
    if ($officeRow) {
        if (!empty($officeRow['name'])) {
            $designatedOfficeName = (string) $officeRow['name'];
        }
        if (!empty($officeRow['time_in']) && !empty($officeRow['time_out'])) {
            $designatedOfficeTimeRange = date('h:i A', strtotime($officeRow['time_in'])) . ' - ' . date('h:i A', strtotime($officeRow['time_out']));
            $officeTimeInOnly = substr((string) $officeRow['time_in'], 0, 8);
            $officeTimeOutOnly = substr((string) $officeRow['time_out'], 0, 8);
            $isGraveyardShift = $officeTimeInOnly > $officeTimeOutOnly;
            if ($isGraveyardShift && date('H:i:s') <= $officeTimeOutOnly) {
                $effectiveWorkdayDate = date('Y-m-d', strtotime('-1 day'));
            }
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = "employee" AND office_id = ?');
    $stmt->execute([$officeId]);
    $totalEmployees = (int) $stmt->fetchColumn();

    $hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
    if ($hasAttendanceLogs) {
        $hasLateMinutesColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
        $presentStmt = $pdo->prepare('
            SELECT COUNT(DISTINCT employee_id)
            FROM attendance_logs
            WHERE office_id = ? AND log_date = ? AND time_in IS NOT NULL
        ');
        $presentStmt->execute([$officeId, $effectiveWorkdayDate]);
        $todayPresent = (int) $presentStmt->fetchColumn();

        $scansStmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM attendance_logs
            WHERE office_id = ? AND log_date = ?
        ');
        $scansStmt->execute([$officeId, $effectiveWorkdayDate]);
        $scansToday = (int) $scansStmt->fetchColumn();

        $lateThreshold = !empty($officeTimeInOnly ?? null) ? $officeTimeInOnly : '09:00:00';
        if ($hasLateMinutesColumn) {
            // Prefer stored late_minutes so dashboard matches scan/deduction rules.
            $lateStmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM attendance_logs
                WHERE office_id = ? AND log_date = ? AND COALESCE(late_minutes, 0) > 0
            ');
            $lateStmt->execute([$officeId, $effectiveWorkdayDate]);
            $lateArrivals = (int) $lateStmt->fetchColumn();
        } else {
            // Backward-compatible fallback when late_minutes column does not exist.
            $lateStmt = $pdo->prepare('
                SELECT COUNT(*)
                FROM attendance_logs
                WHERE office_id = ? AND log_date = ? AND time_in IS NOT NULL AND TIME(time_in) > ?
            ');
            $lateStmt->execute([$officeId, $effectiveWorkdayDate, $lateThreshold]);
            $lateArrivals = (int) $lateStmt->fetchColumn();
        }

        $recentStmt = $pdo->prepare('
            SELECT u.full_name, al.log_date, al.time_in, al.time_out, al.status
            FROM attendance_logs al
            INNER JOIN employees e ON al.employee_id = e.id
            INNER JOIN users u ON e.user_id = u.id
            WHERE al.office_id = ? AND al.log_date = ?
            ORDER BY al.log_date DESC, al.time_in DESC, al.id DESC
            LIMIT 10
        ');
        $recentStmt->execute([$officeId, $effectiveWorkdayDate]);
        $recentLogs = $recentStmt->fetchAll();

        $statusStmt = $pdo->prepare('
            SELECT
                u.full_name,
                al.time_in,
                al.time_out,
                ' . ($hasLateMinutesColumn ? 'al.late_minutes' : 'NULL AS late_minutes') . '
            FROM users u
            INNER JOIN employees e ON e.user_id = u.id
            LEFT JOIN attendance_logs al
                ON al.employee_id = e.id
                AND al.office_id = u.office_id
                AND al.log_date = ?
            WHERE u.role = "employee" AND u.office_id = ? AND u.is_active = 1
            ORDER BY u.full_name
        ');
        $statusStmt->execute([$effectiveWorkdayDate, $officeId]);
        $employeeStatusRows = $statusStmt->fetchAll();
        foreach ($employeeStatusRows as $row) {
            $statusLabel = 'Absent';
            $statusClass = 'secondary';
            if (!empty($row['time_in'])) {
                $lateMinutes = (int) ($row['late_minutes'] ?? 0);
                if ($hasLateMinutesColumn && $lateMinutes > 0) {
                    $statusLabel = 'Late';
                    $statusClass = 'danger';
                } else {
                    $timeInOnly = date('H:i:s', strtotime($row['time_in']));
                    if (!$hasLateMinutesColumn && $timeInOnly > $lateThreshold) {
                        $statusLabel = 'Late';
                        $statusClass = 'danger';
                    } else {
                        $statusLabel = 'Present';
                        $statusClass = 'success';
                    }
                }
            }
            $employeeStatusList[] = [
                'full_name' => $row['full_name'],
                'status' => $statusLabel,
                'status_class' => $statusClass,
                'time_in' => $row['time_in'],
                'time_out' => $row['time_out'],
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EGoes Solutions</title>
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
        <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

        <!-- Main Content -->
        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-3 fw-bold">Admin Dashboard</h3>
          <p class="text-muted mb-4">
            Overview of your office dashboard and employee activity.
          </p>
          <div class="eg-panel p-3 mb-4 border border-primary-subtle">
            <div class="row g-3 align-items-center">
              <div class="col-md-6">
                <div class="text-muted small text-uppercase fw-semibold">Designated Office</div>
                <div class="fw-bold fs-5 text-primary"><?= htmlspecialchars($designatedOfficeName) ?></div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small text-uppercase fw-semibold">Working Time</div>
                <div class="fw-bold fs-5"><?= htmlspecialchars($designatedOfficeTimeRange) ?></div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small">Total Employees</div>
                <div class="fw-bold fs-3"><?= $totalEmployees ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small">Today Present</div>
                <div class="fw-bold fs-3 text-success"><?= $todayPresent ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small">Attendance Scans Today</div>
                <div class="fw-bold fs-3 text-warning"><?= $scansToday ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small">Late Arrivals</div>
                <div class="fw-bold fs-5 text-danger"><?= $lateArrivals ?></div>
              </div>
            </div>
          </div>

          <div class="eg-panel mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">Employees - <?= htmlspecialchars(date('M d, Y', strtotime($effectiveWorkdayDate))) ?></h5>
            </div>
            <?php if (empty($employeeStatusList)): ?>
              <p class="text-muted small mb-0">No employee records found for this office.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Employee Name</th>
                      <th>Status</th>
                      <th class="text-muted">Time In</th>
                      <th class="text-muted">Time Out</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($employeeStatusList as $emp): ?>
                      <tr>
                        <td><?= htmlspecialchars($emp['full_name']) ?></td>
                        <td><span class="badge text-bg-<?= htmlspecialchars($emp['status_class']) ?>"><?= htmlspecialchars($emp['status']) ?></span></td>
                        <td><?= $emp['time_in'] ? htmlspecialchars(date('h:i A', strtotime($emp['time_in']))) : '—' ?></td>
                        <td><?= $emp['time_out'] ? htmlspecialchars(date('h:i A', strtotime($emp['time_out']))) : '—' ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <div class="eg-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">Recent Scanning Activity</h5>
              <a href="scan.php" class="small text-decoration-none">Open scanner</a>
            </div>
            <?php if (empty($recentLogs)): ?>
              <p class="text-muted small mb-0">No attendance logs found yet for this office.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Employee</th>
                      <th>Date</th>
                      <th>Time In</th>
                      <th>Time Out</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                      <tr>
                        <td><?= htmlspecialchars($log['full_name']) ?></td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($log['log_date']))) ?></td>
                        <td><?= $log['time_in'] ? htmlspecialchars(date('h:i A', strtotime($log['time_in']))) : '—' ?></td>
                        <td><?= $log['time_out'] ? htmlspecialchars(date('h:i A', strtotime($log['time_out']))) : '—' ?></td>
                        <td><?= htmlspecialchars($log['status'] ?? 'Present') ?></td>
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

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>







