<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Admin';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$officeId = (int) ($_SESSION['office_id'] ?? 0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/eg_worked_minutes.php';

$employeeId = 0;
$hourlyRate = 0.0;
$weeklyGross = 0.0;
$weeklyDeductions = 0.0;
$weeklyNet = 0.0;
$recentRows = [];
$hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
$hasAppSettingsTable = $pdo->query("SHOW TABLES LIKE 'app_settings'")->rowCount() > 0;
$hasRateAmountColumn = false;
$hasDeductionAmountColumn = false;
$hasLateMinutesColumn = false;
$weekStart = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$weekEnd = (new DateTimeImmutable('sunday this week'))->format('Y-m-d');

if ($hasEmployeesTable && $userId > 0) {
    $hasRateAmountColumn = $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;
    if ($hasRateAmountColumn) {
        $empStmt = $pdo->prepare('SELECT id, rate_amount FROM employees WHERE user_id = ? LIMIT 1');
        $empStmt->execute([$userId]);
        $empRow = $empStmt->fetch();
        if ($empRow) {
            $employeeId = (int) ($empRow['id'] ?? 0);
            if ($empRow['rate_amount'] !== null && $empRow['rate_amount'] !== '') {
                $candidate = (float) $empRow['rate_amount'];
                if ($candidate > 0) {
                    $hourlyRate = $candidate;
                }
            }
        }
    } else {
        $empStmt = $pdo->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1');
        $empStmt->execute([$userId]);
        $employeeId = (int) ($empStmt->fetchColumn() ?: 0);
    }
}

if ($hourlyRate <= 0 && $hasAppSettingsTable) {
    $rateStmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $rateStmt->execute(['hourly_rate_default']);
    $rateValue = $rateStmt->fetchColumn();
    if ($rateValue !== false && $rateValue !== null && is_numeric($rateValue)) {
        $hourlyRate = (float) $rateValue;
    }
}

if ($hasAttendanceLogs && $employeeId > 0 && $officeId > 0) {
    $hasDeductionAmountColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;
    $hasLateMinutesColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
    $selectDeduction = $hasDeductionAmountColumn ? 'al.deduction_amount' : '0.00 AS deduction_amount';
    $selectLateMinutes = $hasLateMinutesColumn ? 'al.late_minutes' : '0 AS late_minutes';

    $weekStmt = $pdo->prepare("
        SELECT
            al.log_date,
            al.time_in,
            al.time_out,
            {$selectLateMinutes},
            {$selectDeduction},
            o.time_in AS office_start,
            o.time_out AS office_end
        FROM attendance_logs al
        JOIN offices o ON al.office_id = o.id
        WHERE al.employee_id = ? AND al.office_id = ? AND al.log_date BETWEEN ? AND ?
        ORDER BY al.log_date DESC, al.time_in DESC, al.id DESC
    ");
    $weekStmt->execute([$employeeId, $officeId, $weekStart, $weekEnd]);
    $weekRows = $weekStmt->fetchAll();

    foreach ($weekRows as $row) {
        $ld = (string) ($row['log_date'] ?? '');
        $rawWm = eg_worked_minutes_within_office_hours(
            $ld,
            $row['time_in'] ?? null,
            $row['time_out'] ?? null,
            (string) ($row['office_start'] ?? '08:00:00'),
            (string) ($row['office_end'] ?? '17:00:00')
        );
        $workedMinutes = (int) floor($rawWm / 60) * 60;
        $rowGross = ($workedMinutes / 60) * $hourlyRate;
        $rowDeduction = (float) ($row['deduction_amount'] ?? 0);
        $lateMinutes = (int) ($row['late_minutes'] ?? 0);

        
        // Ensure deduction reflects actual lateness if not pre-calculated
        if ($rowDeduction <= 0 && $deductionPerMinute > 0 && $hasLateMinutesColumn) {
            $rowDeduction = max(0, $lateMinutes) * $deductionPerMinute;
        }

        $weeklyGross += $rowGross;
        $weeklyDeductions += $rowDeduction;

        $recentRows[] = [
            'log_date' => $row['log_date'],
            'time_in' => $row['time_in'],
            'time_out' => $row['time_out'],
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $lateMinutes,
            'deduction_amount' => $rowDeduction,
            'gross_amount' => $rowGross,
        ];
    }


    if (count($recentRows) > 10) {
        $recentRows = array_slice($recentRows, 0, 10);
    }
}

$weeklyNet = $weeklyGross - $weeklyDeductions;
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
        <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-3 fw-bold">My Payslip</h3>
          <p class="text-muted small mb-3">Your own payslip summary (current week: <?= htmlspecialchars(date('M d', strtotime($weekStart))) ?> - <?= htmlspecialchars(date('M d, Y', strtotime($weekEnd))) ?>).</p>

          <?php if ($employeeId <= 0): ?>
            <div class="alert alert-warning py-2 mb-3">
              Your admin account is not linked to an employee record yet, so payslip totals cannot be computed.
            </div>
          <?php endif; ?>

          <div class="row mb-3 g-3">
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small">My Hourly Rate</div>
                <div class="fw-bold fs-4"><?= number_format($hourlyRate, 2) ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small">My Weekly Gross</div>
                <div class="fw-bold fs-4"><?= number_format($weeklyGross, 2) ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small">My Weekly Deductions</div>
                <div class="fw-bold fs-4"><?= number_format($weeklyDeductions, 2) ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small">My Weekly Net</div>
                <div class="fw-bold fs-4"><?= number_format($weeklyNet, 2) ?></div>
              </div>
            </div>
          </div>
          <div class="eg-panel">
            <h6 class="mb-3 fw-semibold">My Recent Attendance for Payslip</h6>
            <?php if (empty($recentRows)): ?>
              <p class="text-muted small mb-0">No attendance rows found for your account in the current week.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Date</th>
                      <th>Time In</th>
                      <th>Time Out</th>
                      <th>Worked Hours</th>
                      <th>Late (min)</th>
                      <th>Gross</th>
                      <th>Deduction</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentRows as $row): ?>
                      <tr>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($row['log_date']))) ?></td>
                        <td><?= !empty($row['time_in']) ? htmlspecialchars(date('h:i A', strtotime($row['time_in']))) : '—' ?></td>
                        <td><?= !empty($row['time_out']) ? htmlspecialchars(date('h:i A', strtotime($row['time_out']))) : '—' ?></td>
                        <td><?= number_format($row['worked_minutes'] / 60, 2) ?></td>
                        <td><?= (int) $row['late_minutes'] ?></td>
                        <td><?= number_format((float) $row['gross_amount'], 2) ?></td>
                        <td><?= number_format((float) $row['deduction_amount'], 2) ?></td>
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







