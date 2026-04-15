<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';

// --- Metric queries ---
$totalOffices = (int) $pdo->query('SELECT COUNT(*) FROM offices')->fetchColumn();
$totalEmployees = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "employee"')->fetchColumn();
$totalAdmins = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn();

$hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
$hasAppSettingsTable = $pdo->query("SHOW TABLES LIKE 'app_settings'")->rowCount() > 0;
$hasCashAdvancesTable = $pdo->query("SHOW TABLES LIKE 'cash_advances'")->rowCount() > 0;

// Attendance today
$totalAttendanceToday = 0;
$presentToday = 0;
$absentToday = 0;
if ($hasAttendanceLogs) {
    $totalAttendanceToday = (int) $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE DATE(log_date) = CURDATE()")->fetchColumn();
    $presentToday = (int) $pdo->query("SELECT COUNT(DISTINCT employee_id) FROM attendance_logs WHERE DATE(log_date) = CURDATE()")->fetchColumn();
    $absentToday = max(0, $totalEmployees - $presentToday);
}

// Weekly payroll summary (current week Mon-Fri)
$weekMonday = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$weekFriday = (new DateTimeImmutable('friday this week'))->format('Y-m-d');
$weeklyGross = 0.0;
$weeklyDeductions = 0.0;
$weeklyNet = 0.0;

$defaultHourly = 0.0;
$deductionPerMinute = 0.0;
if ($hasAppSettingsTable) {
    $rv = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $rv->execute(['hourly_rate_default']);
    $val = $rv->fetchColumn();
    if ($val !== false && is_numeric($val)) $defaultHourly = (float) $val;

    $dv = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $dv->execute(['deduction_per_minute']);
    $val2 = $dv->fetchColumn();
    if ($val2 !== false && is_numeric($val2)) $deductionPerMinute = (float) $val2;
}

$hasRateAmountColumn = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;

if ($hasAttendanceLogs && $hasEmployeesTable) {
    $hasDeductionAmountColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;
    $hasLateMinutesColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
    $selectDeduction = $hasDeductionAmountColumn ? 'al.deduction_amount' : '0.00 AS deduction_amount';
    $selectLateMinutes = $hasLateMinutesColumn ? 'al.late_minutes' : '0 AS late_minutes';
    $selectRate = $hasRateAmountColumn ? 'e.rate_amount' : 'NULL AS rate_amount';

    $weekStmt = $pdo->prepare("
        SELECT al.employee_id, al.time_in, al.time_out, {$selectDeduction}, {$selectLateMinutes}, {$selectRate}
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        WHERE al.log_date BETWEEN ? AND ?
    ");
    $weekStmt->execute([$weekMonday, $weekFriday]);
    foreach ($weekStmt->fetchAll() as $row) {
        $wm = 0;
        if (!empty($row['time_in']) && !empty($row['time_out'])) {
            $inTs = strtotime((string) $row['time_in']);
            $outTs = strtotime((string) $row['time_out']);
            if ($outTs > $inTs) $wm = (int) floor(($outTs - $inTs) / 60);
        }
        $rate = 0.0;
        $ra = $row['rate_amount'] ?? null;
        if ($ra !== null && $ra !== '' && (float) $ra > 0) $rate = (float) $ra;
        if ($rate <= 0) $rate = $defaultHourly;

        $rowGross = ($wm / 60) * $rate;
        $rowDed = (float) ($row['deduction_amount'] ?? 0);
        $lateMin = (int) ($row['late_minutes'] ?? 0);
        if ($rowDed <= 0 && $deductionPerMinute > 0 && $hasLateMinutesColumn) {
            $rowDed = max(0, $lateMin - 60) * $deductionPerMinute;
        }
        $weeklyGross += $rowGross;
        $weeklyDeductions += $rowDed;
    }
    $weeklyNet = $weeklyGross - $weeklyDeductions;
}

// Pending cash advances
$pendingLoansCount = 0;
$pendingLoansAmount = 0.0;
if ($hasCashAdvancesTable) {
    $loansRow = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total FROM cash_advances WHERE status = 'pending'")->fetch();
    $pendingLoansCount = (int) ($loansRow['cnt'] ?? 0);
    $pendingLoansAmount = (float) ($loansRow['total'] ?? 0);
}

// Recent attendance (last 10 logs)
$recentAttendance = [];
if ($hasAttendanceLogs && $hasEmployeesTable) {
    $recentStmt = $pdo->query("
        SELECT al.log_date, al.time_in, al.time_out, u.full_name, o.name AS office_name
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        JOIN users u ON e.user_id = u.id
        JOIN offices o ON al.office_id = o.id
        ORDER BY al.log_date DESC, al.time_in DESC, al.id DESC
        LIMIT 10
    ");
    $recentAttendance = $recentStmt->fetchAll();
}

// Office breakdown
$officeBreakdown = [];
try {
    $officeStmt = $pdo->query("
        SELECT o.name, COUNT(DISTINCT u.id) AS emp_count
        FROM offices o
        LEFT JOIN users u ON u.office_id = o.id AND u.role IN ('employee', 'admin')
        GROUP BY o.id, o.name
        ORDER BY emp_count DESC
    ");
    $officeBreakdown = $officeStmt->fetchAll();
} catch (Throwable $e) {}

$weekLabel = (new DateTimeImmutable('monday this week'))->format('M j') . ' – ' . (new DateTimeImmutable('friday this week'))->format('M j, Y');
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
        <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="fw-bold">Super Admin Dashboard</h3>
          <p class="text-muted mb-4">
            Overview of offices, workforce, attendance, and payroll.
          </p>

          <!-- Row 1: Key metrics -->
          <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
              <div class="eg-metric-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-building text-primary"></i>
                  <span class="text-muted small">Total Offices</span>
                </div>
                <div class="fw-bold fs-4"><?= $totalOffices ?></div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="eg-metric-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-people text-success"></i>
                  <span class="text-muted small">Employees</span>
                </div>
                <div class="fw-bold fs-4"><?= $totalEmployees ?></div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="eg-metric-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-person-badge text-info"></i>
                  <span class="text-muted small">Team Leaders</span>
                </div>
                <div class="fw-bold fs-4"><?= $totalAdmins ?></div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="eg-metric-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-journal-check text-warning"></i>
                  <span class="text-muted small">Attendance Logs Today</span>
                </div>
                <div class="fw-bold fs-4"><?= $totalAttendanceToday ?></div>
              </div>
            </div>
          </div>

          <!-- Row 2: Attendance & Payroll -->
          <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
              <div class="eg-metric-card border-success border-opacity-50">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-check-circle text-success"></i>
                  <span class="text-muted small">Present Today</span>
                </div>
                <div class="fw-bold fs-4"><?= $presentToday ?></div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="eg-metric-card border-danger border-opacity-50">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-x-circle text-danger"></i>
                  <span class="text-muted small">Absent Today</span>
                </div>
                <div class="fw-bold fs-4"><?= $absentToday ?></div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="eg-metric-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-cash-stack text-primary"></i>
                  <span class="text-muted small">Weekly Gross</span>
                </div>
                <div class="fw-bold fs-5">₱<?= number_format($weeklyGross, 2) ?></div>
                <div class="text-muted" style="font-size: .7rem;"><?= htmlspecialchars($weekLabel) ?></div>
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="eg-metric-card">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-wallet2 text-success"></i>
                  <span class="text-muted small">Weekly Net Pay</span>
                </div>
                <div class="fw-bold fs-5">₱<?= number_format($weeklyNet, 2) ?></div>
                <div class="text-muted" style="font-size: .7rem;">Deductions: ₱<?= number_format($weeklyDeductions, 2) ?></div>
              </div>
            </div>
          </div>

          <!-- Row 3: Cash advances -->
          <?php if ($hasCashAdvancesTable && $pendingLoansCount > 0): ?>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="eg-metric-card border-warning border-opacity-50">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-exclamation-triangle text-warning"></i>
                  <span class="text-muted small">Pending Cash Advances</span>
                </div>
                <div class="fw-bold fs-4"><?= $pendingLoansCount ?> <span class="fs-6 fw-normal text-muted">request<?= $pendingLoansCount !== 1 ? 's' : '' ?></span></div>
                <div class="text-muted small">Total amount: ₱<?= number_format($pendingLoansAmount, 2) ?></div>
                <a href="loans.php" class="text-primary small mt-1 d-inline-block">Review &rarr;</a>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Row 4: Recent attendance & Office breakdown -->
          <div class="row g-3">
            <div class="col-lg-8">
              <div class="eg-panel p-3">
                <h6 class="fw-semibold mb-3"><i class="bi bi-clock-history me-1"></i> Recent Attendance</h6>
                <?php if (empty($recentAttendance)): ?>
                  <p class="text-muted small mb-0">No attendance records yet.</p>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th>Employee</th>
                          <th>Office</th>
                          <th>Date</th>
                          <th>Time In</th>
                          <th>Time Out</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($recentAttendance as $att): ?>
                          <tr>
                            <td><?= htmlspecialchars($att['full_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($att['office_name'] ?? '') ?></td>
                            <td style="white-space: nowrap;"><?= htmlspecialchars(date('M j, Y', strtotime($att['log_date']))) ?></td>
                            <td style="white-space: nowrap;"><?= !empty($att['time_in']) ? htmlspecialchars(date('h:i A', strtotime($att['time_in']))) : '—' ?></td>
                            <td style="white-space: nowrap;"><?= !empty($att['time_out']) ? htmlspecialchars(date('h:i A', strtotime($att['time_out']))) : '—' ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <a href="attendance.php" class="text-primary small mt-2 d-inline-block">View all attendance &rarr;</a>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="eg-panel p-3">
                <h6 class="fw-semibold mb-3"><i class="bi bi-pie-chart me-1"></i> Office Breakdown</h6>
                <?php if (empty($officeBreakdown)): ?>
                  <p class="text-muted small mb-0">No offices found.</p>
                <?php else: ?>
                  <div class="list-group list-group-flush">
                    <?php foreach ($officeBreakdown as $ob): ?>
                      <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-start-0 border-end-0">
                        <span class="small"><?= htmlspecialchars($ob['name']) ?></span>
                        <span class="badge bg-primary rounded-pill"><?= (int) $ob['emp_count'] ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <a href="offices.php" class="text-primary small mt-2 d-inline-block">Manage offices &rarr;</a>
                <?php endif; ?>
              </div>
            </div>
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




