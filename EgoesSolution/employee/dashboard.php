<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Employee';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$officeId = (int) ($_SESSION['office_id'] ?? 0);

require_once __DIR__ . '/../config/database.php';

$monthParam = trim($_GET['month'] ?? '');
if ($monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $monthParam . '-01') ?: new DateTimeImmutable('first day of this month');
} else {
    $monthStart = new DateTimeImmutable('first day of this month');
}
$monthStart = $monthStart->setTime(0, 0, 0);
$monthEnd = $monthStart->modify('last day of this month');
$today = new DateTimeImmutable('today');
$monthLabel = $monthStart->format('F Y');
$currentMonthKey = $monthStart->format('Y-m');
$prevMonthKey = $monthStart->modify('-1 month')->format('Y-m');
$nextMonthKey = $monthStart->modify('+1 month')->format('Y-m');
$daysInMonth = (int) $monthStart->format('t');
$firstWeekday = (int) $monthStart->format('w'); // 0=Sun, 6=Sat
$lateThreshold = '09:00:00';
$attendanceByDate = [];
$employeeId = 0;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
$hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
$hasAppSettingsTable = $pdo->query("SHOW TABLES LIKE 'app_settings'")->rowCount() > 0;
$hourlyRate = 0.0;
$deductionPerMinute = 0.0;
$weeklyGross = 0.0;
$weeklyDeductions = 0.0;
$weeklyNet = 0.0;
$weekStart = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$weekEnd = (new DateTimeImmutable('sunday this week'))->format('Y-m-d');

if ($officeId > 0) {
    $hasOfficeTimeInColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_in'")->rowCount() > 0;
    if ($hasOfficeTimeInColumn) {
        $officeStmt = $pdo->prepare('SELECT time_in FROM offices WHERE id = ? LIMIT 1');
        $officeStmt->execute([$officeId]);
        $officeTimeIn = $officeStmt->fetchColumn();
        if ($officeTimeIn) {
            $lateThreshold = substr((string) $officeTimeIn, 0, 8);
        }
    }
}

if ($hasEmployeesTable && $userId > 0) {
    $hasRateAmountColumn = $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;
    if ($hasRateAmountColumn) {
        $empStmt = $pdo->prepare('SELECT id, rate_amount FROM employees WHERE user_id = ? LIMIT 1');
        $empStmt->execute([$userId]);
        $empRow = $empStmt->fetch();
        if ($empRow) {
            $employeeId = (int) ($empRow['id'] ?? 0);
            if ($empRow['rate_amount'] !== null && $empRow['rate_amount'] !== '') {
                $hourlyRate = (float) $empRow['rate_amount'];
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

if ($hasAppSettingsTable) {
    $deductStmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $deductStmt->execute(['deduction_per_minute']);
    $deductValue = $deductStmt->fetchColumn();
    if ($deductValue !== false && $deductValue !== null && is_numeric($deductValue)) {
        $deductionPerMinute = (float) $deductValue;
    }
}

if ($hasAttendanceLogs && $employeeId > 0) {
    $hasDeductionAmountColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;
    $hasLateMinutesColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
    $selectDeduction = $hasDeductionAmountColumn ? 'deduction_amount' : '0.00 AS deduction_amount';
    $selectLateMinutes = $hasLateMinutesColumn ? 'late_minutes' : '0 AS late_minutes';

    $weekStmt = $pdo->prepare("
        SELECT time_in, time_out, {$selectDeduction}, {$selectLateMinutes}
        FROM attendance_logs
        WHERE employee_id = ? AND office_id = ? AND log_date BETWEEN ? AND ?
    ");
    $weekStmt->execute([$employeeId, $officeId, $weekStart, $weekEnd]);
    foreach ($weekStmt->fetchAll() as $row) {
        $workedMinutes = 0;
        if (!empty($row['time_in']) && !empty($row['time_out'])) {
            $inTs = strtotime((string) $row['time_in']);
            $outTs = strtotime((string) $row['time_out']);
            if ($outTs > $inTs) {
                $workedMinutes = (int) floor(($outTs - $inTs) / 60);
            }
        }
        $weeklyGross += ($workedMinutes / 60) * $hourlyRate;
        $rowDeduction = (float) ($row['deduction_amount'] ?? 0);
        if ($rowDeduction <= 0 && $deductionPerMinute > 0) {
            $lateMinutes = (int) ($row['late_minutes'] ?? 0);
            $rowDeduction = max(0, $lateMinutes - 60) * $deductionPerMinute;
        }
        $weeklyDeductions += $rowDeduction;
    }
    $weeklyNet = $weeklyGross - $weeklyDeductions;

    $logsStmt = $pdo->prepare('
        SELECT log_date, time_in, time_out
        FROM attendance_logs
        WHERE employee_id = ? AND office_id = ? AND log_date BETWEEN ? AND ?
        ORDER BY log_date DESC, id DESC
    ');
    $logsStmt->execute([$employeeId, $officeId, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
    foreach ($logsStmt->fetchAll() as $row) {
        $dateKey = (string) $row['log_date'];
        if (!isset($attendanceByDate[$dateKey])) {
            $attendanceByDate[$dateKey] = $row;
        }
    }
}

$calendarCells = [];
for ($i = 0; $i < $firstWeekday; $i++) {
    $calendarCells[] = null;
}
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateObj = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $day);
    $dateKey = $dateObj->format('Y-m-d');
    $status = null;
    $statusClass = '';
    if ($dateObj <= $today) {
        if (!empty($attendanceByDate[$dateKey]) && !empty($attendanceByDate[$dateKey]['time_in'])) {
            $timeInOnly = date('H:i:s', strtotime((string) $attendanceByDate[$dateKey]['time_in']));
            if ($timeInOnly > $lateThreshold) {
                $status = 'Late';
                $statusClass = 'eg-dot-red';
            } else {
                $status = 'Present';
                $statusClass = 'eg-dot-green';
            }
        } else {
            $status = 'Absent';
            $statusClass = 'eg-dot-gray';
        }
    }
    $calendarCells[] = [
        'day' => $day,
        'date' => $dateKey,
        'status' => $status,
        'status_class' => $statusClass,
        'is_today' => $dateKey === $today->format('Y-m-d'),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Employee Dashboard</title>
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
    <link rel="stylesheet" href="../assets/css/style.css" />
    <style>
      .eg-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.5rem;
      }
      .eg-calendar-head {
        font-size: 0.75rem;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        text-align: center;
      }
      .eg-calendar-day {
        position: relative;
        min-height: 72px;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        background: #fff;
        padding: 0.45rem 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .eg-calendar-day-number {
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
        line-height: 1;
      }
      .eg-calendar-day.is-today {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
      }
      .eg-calendar-dot {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 10px;
        height: 10px;
        border-radius: 999px;
      }
      .eg-calendar-empty {
        min-height: 72px;
      }
      @media (max-width: 768px) {
        .eg-calendar-grid {
          gap: 0.3rem;
        }
        .eg-calendar-day,
        .eg-calendar-empty {
          min-height: 52px;
        }
        .eg-calendar-day {
          border-radius: 0.55rem;
          padding: 0.3rem;
        }
        .eg-calendar-day-number {
          font-size: 0.9rem;
        }
        .eg-calendar-dot {
          width: 8px;
          height: 8px;
          top: 6px;
          right: 6px;
        }
        .eg-calendar-head {
          font-size: 0.65rem;
        }
      }
    </style>
  </head>
  <body class="bg-light">
    <?php
    $name = $_SESSION['display_name'] ?? 'Employee';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="container-fluid py-4">
      <nav class="eg-employee-nav mb-4">
        <a href="dashboard.php" class="eg-employee-nav-link active">
          <i class="bi bi-house-door"></i>
          <span>Dashboard</span>
        </a>
        <a href="payslip.php" class="eg-employee-nav-link">
          <i class="bi bi-receipt"></i>
          <span>Payslip Archive</span>
        </a>
        <a href="../auth/logout.php" class="eg-employee-nav-link eg-employee-nav-link-danger">
          <i class="bi bi-box-arrow-right"></i>
          <span>Logout</span>
        </a>
      </nav>

      <div class="row g-3">
        <div class="col-lg-4">
          <div class="eg-panel">
            <h5 class="mb-3">My Earnings</h5>
            <p class="text-muted small mb-3">Current week: <?= htmlspecialchars(date('M d', strtotime($weekStart))) ?> - <?= htmlspecialchars(date('M d, Y', strtotime($weekEnd))) ?></p>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">Hourly Rate</span>
              <span class="fw-semibold"><?= number_format($hourlyRate, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">Gross Pay</span>
              <span class="fw-semibold"><?= number_format($weeklyGross, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">Deductions</span>
              <span class="fw-semibold text-danger"><?= number_format($weeklyDeductions, 2) ?></span>
            </div>
            <hr class="my-2" />
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Net Pay</span>
              <span class="fw-bold text-success"><?= number_format($weeklyNet, 2) ?></span>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="eg-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0">Attendance Calendar</h5>
              <div class="btn-toolbar" role="toolbar" aria-label="Calendar navigation">
                <div class="d-flex align-items-center me-2" role="group" aria-label="Month navigation">
                  <a href="?month=<?= urlencode($prevMonthKey) ?>" class="btn btn-sm btn-link text-secondary text-decoration-none px-2" aria-label="Previous month">
                    <i class="bi bi-chevron-left"></i>
                  </a>
                  <span class="small fw-semibold text-muted px-1">
                    <?= htmlspecialchars($monthLabel) ?>
                  </span>
                  <a href="?month=<?= urlencode($nextMonthKey) ?>" class="btn btn-sm btn-link text-secondary text-decoration-none px-2" aria-label="Next month">
                    <i class="bi bi-chevron-right"></i>
                  </a>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="Calendar quick actions">
                  <a href="dashboard.php?month=<?= urlencode((new DateTimeImmutable('first day of this month'))->format('Y-m')) ?>" class="btn btn-outline-primary">
                    Today
                  </a>
                </div>
              </div>
            </div>

            <div class="d-flex align-items-center gap-3 small mb-3">
              <span><span class="eg-dot eg-dot-green me-1"></span>Present</span>
              <span><span class="eg-dot eg-dot-red me-1"></span>Late</span>
              <span><span class="eg-dot eg-dot-gray me-1"></span>Absent</span>
            </div>

            <div class="eg-calendar-grid mb-2">
              <div class="eg-calendar-head">Sun</div>
              <div class="eg-calendar-head">Mon</div>
              <div class="eg-calendar-head">Tue</div>
              <div class="eg-calendar-head">Wed</div>
              <div class="eg-calendar-head">Thu</div>
              <div class="eg-calendar-head">Fri</div>
              <div class="eg-calendar-head">Sat</div>
            </div>

            <div class="eg-calendar-grid">
              <?php foreach ($calendarCells as $cell): ?>
                <?php if ($cell === null): ?>
                  <div class="eg-calendar-empty"></div>
                <?php else: ?>
                  <div class="eg-calendar-day <?= $cell['is_today'] ? 'is-today' : '' ?>" title="<?= htmlspecialchars($cell['status'] ?? 'No status') ?>">
                    <div class="eg-calendar-day-number"><?= (int) $cell['day'] ?></div>
                    <?php if (!empty($cell['status_class'])): ?>
                      <span class="eg-calendar-dot <?= htmlspecialchars($cell['status_class']) ?>"></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
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


