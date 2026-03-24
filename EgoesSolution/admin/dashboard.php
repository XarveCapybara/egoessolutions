<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Admin';
$officeId = $_SESSION['office_id'] ?? null;

require_once __DIR__ . '/../config/database.php';
$totalEmployees = 0;
$todayPresent = 0;
$scansToday = 0;
$lateArrivals = 0;
$recentLogs = [];
if ($officeId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = "employee" AND office_id = ?');
    $stmt->execute([$officeId]);
    $totalEmployees = (int) $stmt->fetchColumn();

    $hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
    if ($hasAttendanceLogs) {
        $presentStmt = $pdo->prepare('
            SELECT COUNT(DISTINCT employee_id)
            FROM attendance_logs
            WHERE office_id = ? AND log_date = CURDATE() AND time_in IS NOT NULL
        ');
        $presentStmt->execute([$officeId]);
        $todayPresent = (int) $presentStmt->fetchColumn();

        $scansStmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM attendance_logs
            WHERE office_id = ? AND log_date = CURDATE()
        ');
        $scansStmt->execute([$officeId]);
        $scansToday = (int) $scansStmt->fetchColumn();

        $lateStmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM attendance_logs
            WHERE office_id = ? AND log_date = CURDATE() AND time_in IS NOT NULL AND TIME(time_in) > "09:00:00"
        ');
        $lateStmt->execute([$officeId]);
        $lateArrivals = (int) $lateStmt->fetchColumn();

        $recentStmt = $pdo->prepare('
            SELECT u.full_name, al.log_date, al.time_in, al.time_out, al.status
            FROM attendance_logs al
            INNER JOIN employees e ON al.employee_id = e.id
            INNER JOIN users u ON e.user_id = u.id
            WHERE al.office_id = ?
            ORDER BY al.log_date DESC, al.time_in DESC, al.id DESC
            LIMIT 10
        ');
        $recentStmt->execute([$officeId]);
        $recentLogs = $recentStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - Attendance & Payroll</title>
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
    <header class="eg-topbar d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <img src="../assets/images/egoes-logo.png?v=3" alt="E-GOES Solutions" class="eg-system-logo" />
      </div>
      <div class="d-flex align-items-center me-3">
        <div class="me-2 fw-bold fs-4">
          Hi <?= htmlspecialchars($name) ?>!
        </div>
        <div class="eg-avatar-circle"></div>
      </div>
    </header>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

        <!-- Main Content -->
        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-3 fw-bold">Admin Dashboard</h3>
          <p class="text-muted mb-4">
            Overview of your office dashboard and employee activity.
          </p>

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
  </body>
</html>







