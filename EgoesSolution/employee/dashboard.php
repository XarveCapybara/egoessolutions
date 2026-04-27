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
require_once __DIR__ . '/../includes/eg_suspension_weekdays.php';
require_once __DIR__ . '/../includes/eg_employee_suspension_guard.php';

if ($userId > 0) {
    $officeFromUser = $pdo->prepare('SELECT office_id FROM users WHERE id = ? LIMIT 1');
    $officeFromUser->execute([$userId]);
    $dbOfficeId = (int) ($officeFromUser->fetchColumn() ?: 0);
    if ($dbOfficeId > 0) {
        $officeId = $dbOfficeId;
    }
}
require_once __DIR__ . '/../includes/eg_worked_minutes.php';

$monthParam = trim($_GET['month'] ?? '');
if ($monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $monthParam . '-01') ?: new DateTimeImmutable('first day of this month');
} else {
    $monthStart = new DateTimeImmutable('first day of this month');
}
$monthStart = $monthStart->setTime(0, 0, 0);
$monthEnd = $monthStart->modify('last day of this month');
$today = new DateTimeImmutable('today');
$calendarTodayYmd = $today->format('Y-m-d');
$monthLabel = $monthStart->format('F Y');
$currentMonthKey = $monthStart->format('Y-m');
$prevMonthKey = $monthStart->modify('-1 month')->format('Y-m');
$nextMonthKey = $monthStart->modify('+1 month')->format('Y-m');
$daysInMonth = (int) $monthStart->format('t');
// Monday-first grid (ISO weekday N: Mon=1 … Sun=7)
$firstWeekday = (int) $monthStart->format('N') - 1;
$lateThreshold = '09:00:00';
$attendanceByDate = [];
$attendanceDetailsByDate = [];
$suspendedDates = [];
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
$weekEnd = (new DateTimeImmutable('friday this week'))->format('Y-m-d');

$assignedOfficeName = 'Not assigned';
$assignedOfficeAddress = '';
$assignedOfficeTimeRange = 'Not set';

if ($officeId > 0) {
    $hasOfficeNameColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'name'")->rowCount() > 0;
    $hasOfficeAddressColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'address'")->rowCount() > 0;
    $hasOfficeTimeInColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_in'")->rowCount() > 0;
    $hasOfficeTimeOutColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_out'")->rowCount() > 0;
    $selectName = $hasOfficeNameColumn ? 'name' : 'NULL AS name';
    $selectAddress = $hasOfficeAddressColumn ? 'address' : 'NULL AS address';
    $selectTimeIn = $hasOfficeTimeInColumn ? 'time_in' : 'NULL AS time_in';
    $selectTimeOut = $hasOfficeTimeOutColumn ? 'time_out' : 'NULL AS time_out';
    $officeStmt = $pdo->prepare("SELECT {$selectName}, {$selectAddress}, {$selectTimeIn}, {$selectTimeOut} FROM offices WHERE id = ? LIMIT 1");
    $officeStmt->execute([$officeId]);
    $officeRow = $officeStmt->fetch(PDO::FETCH_ASSOC);
    if ($officeRow) {
        if (!empty($officeRow['name'])) {
            $assignedOfficeName = (string) $officeRow['name'];
        }
        if (!empty($officeRow['address'])) {
            $addr = trim((string) $officeRow['address']);
            if ($addr !== '') {
                $assignedOfficeAddress = function_exists('mb_strlen') && mb_strlen($addr) > 120
                    ? mb_substr($addr, 0, 117) . '…'
                    : (strlen($addr) > 120 ? substr($addr, 0, 117) . '…' : $addr);
            }
        }
        $officeTimeIn = $officeRow['time_in'] ?? null;
        $officeTimeOut = $officeRow['time_out'] ?? null;
        if (!empty($officeTimeIn)) {
            $lateThreshold = substr((string) $officeTimeIn, 0, 8);
        }
        if (!empty($officeTimeIn) && !empty($officeTimeOut)) {
            $assignedOfficeTimeRange = date('h:i A', strtotime((string) $officeTimeIn))
                . ' – '
                . date('h:i A', strtotime((string) $officeTimeOut));
        } elseif (!empty($officeTimeIn)) {
            $assignedOfficeTimeRange = 'From ' . date('h:i A', strtotime((string) $officeTimeIn));
        } elseif (!empty($officeTimeOut)) {
            $assignedOfficeTimeRange = 'Until ' . date('h:i A', strtotime((string) $officeTimeOut));
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
    $selectDeduction = $hasDeductionAmountColumn ? 'al.deduction_amount' : '0.00 AS deduction_amount';
    $selectLateMinutes = $hasLateMinutesColumn ? 'al.late_minutes' : '0 AS late_minutes';

    $weekStmt = $pdo->prepare("
        SELECT
            al.log_date,
            al.time_in,
            al.time_out,
            {$selectDeduction},
            {$selectLateMinutes},
            o.time_in AS office_start,
            o.time_out AS office_end
        FROM attendance_logs al
        LEFT JOIN offices o ON o.id = al.office_id
        WHERE al.employee_id = ? AND al.office_id = ? AND al.log_date BETWEEN ? AND ?
    ");
    $weekStmt->execute([$employeeId, $officeId, $weekStart, $weekEnd]);
    foreach ($weekStmt->fetchAll() as $row) {
        $workedMinutes = eg_worked_minutes_within_office_hours(
            (string) ($row['log_date'] ?? ''),
            $row['time_in'] ?? null,
            $row['time_out'] ?? null,
            $row['office_start'] ?? null,
            $row['office_end'] ?? null
        );
        $weeklyGross += ($workedMinutes / 60) * $hourlyRate;
        $rowDeduction = (float) ($row['deduction_amount'] ?? 0);
        if ($rowDeduction <= 0 && $deductionPerMinute > 0) {
            $lateMinutes = (int) ($row['late_minutes'] ?? 0);
            $rowDeduction = max(0, $lateMinutes - 60) * $deductionPerMinute;
        }
        $weeklyDeductions += $rowDeduction;
    }
    $weeklyNet = $weeklyGross - $weeklyDeductions;

    $logsStmt = $pdo->prepare("
        SELECT
            al.log_date,
            al.time_in,
            al.time_out,
            {$selectDeduction},
            {$selectLateMinutes},
            o.time_in AS office_start,
            o.time_out AS office_end
        FROM attendance_logs al
        LEFT JOIN offices o ON o.id = al.office_id
        WHERE al.employee_id = ? AND al.office_id = ? AND al.log_date BETWEEN ? AND ?
        ORDER BY al.log_date DESC, al.id DESC
    ");
    $logsStmt->execute([$employeeId, $officeId, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
    foreach ($logsStmt->fetchAll() as $row) {
        $dateKey = (string) $row['log_date'];
        if (!isset($attendanceByDate[$dateKey])) {
            $workedMinutes = eg_worked_minutes_within_office_hours(
                $dateKey,
                $row['time_in'] ?? null,
                $row['time_out'] ?? null,
                $row['office_start'] ?? null,
                $row['office_end'] ?? null
            );
            $lateMinutes = (int) ($row['late_minutes'] ?? 0);
            $rowDeduction = (float) ($row['deduction_amount'] ?? 0);
            if ($rowDeduction <= 0 && $deductionPerMinute > 0) {
                $rowDeduction = max(0, $lateMinutes - 60) * $deductionPerMinute;
            }
            $attendanceByDate[$dateKey] = [
                'log_date' => $dateKey,
                'time_in' => $row['time_in'] ?? null,
                'time_out' => $row['time_out'] ?? null,
                'late_minutes' => $lateMinutes,
                'deduction_amount' => $rowDeduction,
                'worked_minutes' => $workedMinutes,
            ];
        }
    }
}

$hasEmployeeMemosTable = $pdo->query("SHOW TABLES LIKE 'employee_memos'")->rowCount() > 0;
if ($hasEmployeeMemosTable && $userId > 0) {
    try {
        $suspendStmt = $pdo->prepare("
            SELECT suspension_start, suspension_end
            FROM employee_memos
            WHERE user_id = ?
              AND status = 'active'
              AND LOWER(consequence_type) = 'suspension'
              AND suspension_start IS NOT NULL
              AND suspension_end IS NOT NULL
              AND suspension_end >= ?
              AND suspension_start <= ?
            ORDER BY suspension_start ASC, id ASC
        ");
        $suspendStmt->execute([$userId, $monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')]);
        foreach ($suspendStmt->fetchAll(PDO::FETCH_ASSOC) as $sr) {
            $rangeStart = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($sr['suspension_start'] ?? ''));
            $rangeEnd = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($sr['suspension_end'] ?? ''));
            if (!$rangeStart || !$rangeEnd || $rangeEnd < $rangeStart) {
                continue;
            }
            if ($rangeStart < $monthStart) {
                $rangeStart = $monthStart;
            }
            if ($rangeEnd > $monthEnd) {
                $rangeEnd = $monthEnd;
            }
            for ($d = $rangeStart; $d <= $rangeEnd; $d = $d->modify('+1 day')) {
                if (!eg_is_workday_in_suspension_range($d, (string) ($sr['suspension_start'] ?? ''), (string) ($sr['suspension_end'] ?? ''))) {
                    continue;
                }
                $key = $d->format('Y-m-d');
                $suspendedDates[$key] = [
                    'start' => $rangeStart->format('Y-m-d'),
                    'end' => $rangeEnd->format('Y-m-d'),
                ];
            }
        }
    } catch (Throwable $e) {
        // Keep dashboard working even if memo query fails.
    }
}

$calendarCells = [];
for ($i = 0; $i < $firstWeekday; $i++) {
    $calendarCells[] = null;
}
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateObj = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $day);
    $dateKey = $dateObj->format('Y-m-d');
    $dayAttendance = $attendanceByDate[$dateKey] ?? null;
    $isoDow = (int) $dateObj->format('N');
    $isWeekend = $isoDow > 5;
    $status = null;
    $statusClass = '';
    if (isset($suspendedDates[$dateKey])) {
        $status = 'Suspended';
        $statusClass = 'eg-dot-yellow';
    } elseif ($isWeekend) {
        $status = 'Rest Day';
        $statusClass = '';
    } elseif ($dateObj <= $today) {
        if (!empty($dayAttendance) && !empty($dayAttendance['time_in'])) {
            $timeInOnly = date('H:i:s', strtotime((string) $dayAttendance['time_in']));
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
        'is_today' => $dateKey === $calendarTodayYmd,
    ];
    $attendanceDetailsByDate[$dateKey] = [
        'date' => $dateKey,
        'date_label' => $dateObj->format('M j, Y'),
        'status' => $status ?? ($dateObj > $today ? 'Future' : 'Absent'),
        'is_weekend' => $isWeekend,
        'suspension_start' => $suspendedDates[$dateKey]['start'] ?? null,
        'suspension_end' => $suspendedDates[$dateKey]['end'] ?? null,
        'time_in' => $dayAttendance['time_in'] ?? null,
        'time_out' => $dayAttendance['time_out'] ?? null,
        'late_minutes' => (int) ($dayAttendance['late_minutes'] ?? 0),
        'deduction_amount' => (float) ($dayAttendance['deduction_amount'] ?? 0),
        'worked_minutes' => (int) ($dayAttendance['worked_minutes'] ?? 0),
        'time_in_label' => !empty($dayAttendance['time_in']) ? date('h:i A', strtotime((string) $dayAttendance['time_in'])) : '-',
        'time_out_label' => !empty($dayAttendance['time_out']) ? date('h:i A', strtotime((string) $dayAttendance['time_out'])) : '-',
        'hours_label' => number_format(((int) ($dayAttendance['worked_minutes'] ?? 0)) / 60, 2),
        'deduction_label' => number_format((float) ($dayAttendance['deduction_amount'] ?? 0), 2),
    ];
}
$attendanceDetailsJson = json_encode(
    $attendanceDetailsByDate,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);
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
      .eg-calendar-day[data-date] {
        cursor: pointer;
      }
      .eg-calendar-day[data-date]:hover {
        background: #f8fafc;
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
      .eg-dashboard-clock-time {
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.02em;
      }
      .eg-dashboard-clock-date {
        margin-top: 0.25rem;
      }
    </style>
  </head>
  <body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_employee.php'; ?>
        <main class="col-12 col-md-9 col-lg-10 py-4">
      <div class="eg-panel p-3 mb-3 border border-primary-subtle">
        <div class="row g-3 align-items-start">
          <div class="col-12 col-md-4">
            <div class="text-muted small text-uppercase fw-semibold">Your office</div>
            <div class="fw-bold fs-5 text-primary"><?= htmlspecialchars($assignedOfficeName) ?></div>
            <?php if ($assignedOfficeAddress !== ''): ?>
              <div class="small text-muted mt-1"><?= htmlspecialchars($assignedOfficeAddress) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-12 col-md-4">
            <div class="text-muted small text-uppercase fw-semibold">Office hours</div>
            <div class="fw-bold fs-5"><?= htmlspecialchars($assignedOfficeTimeRange) ?></div>
          </div>
          <div class="col-12 col-md-4">
            <div class="text-muted small text-uppercase fw-semibold">
              <i class="bi bi-clock" aria-hidden="true"></i> Current time
            </div>
            <div class="fw-bold fs-5 eg-dashboard-clock-time" id="egLiveClockTime">—</div>
            <div class="eg-dashboard-clock-date small text-muted" id="egLiveClockDate" aria-live="polite"></div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-lg-4">
          <div class="eg-panel">
            <h5 class="mb-3">My Earnings</h5>
            <p class="text-muted small mb-3">Current week (Mon&ndash;Fri): <?= htmlspecialchars(date('M d', strtotime($weekStart))) ?> &ndash; <?= htmlspecialchars(date('M d, Y', strtotime($weekEnd))) ?></p>
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
              <span><span class="eg-dot eg-dot-yellow me-1"></span>Suspended</span>
              <span><span class="eg-dot eg-dot-gray me-1"></span>Absent</span>
            </div>

            <div class="eg-calendar-grid mb-2">
              <div class="eg-calendar-head">Mon</div>
              <div class="eg-calendar-head">Tue</div>
              <div class="eg-calendar-head">Wed</div>
              <div class="eg-calendar-head">Thu</div>
              <div class="eg-calendar-head">Fri</div>
              <div class="eg-calendar-head">Sat</div>
              <div class="eg-calendar-head">Sun</div>
            </div>

            <div class="eg-calendar-grid" id="attendanceCalendarGrid">
              <?php foreach ($calendarCells as $cell): ?>
                <?php if ($cell === null): ?>
                  <div class="eg-calendar-empty"></div>
                <?php else: ?>
                  <div
                    class="eg-calendar-day <?= $cell['is_today'] ? 'is-today' : '' ?>"
                    data-date="<?= htmlspecialchars($cell['date']) ?>"
                    title="<?= htmlspecialchars(($cell['status'] ?? 'No status') . ' — click for details') ?>"
                  >
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
      </div></main>
      </div>
    </div>

    <div class="modal" id="attendanceDayModal" tabindex="-1" aria-labelledby="attendanceDayModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="attendanceDayModalLabel">Attendance Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-2 small text-muted" id="attDayDate">-</div>
            <div class="d-flex justify-content-between mb-2"><span>Status</span><strong id="attDayStatus">-</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>Time In</span><strong id="attDayIn">-</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>Time Out</span><strong id="attDayOut">-</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>Worked Hours</span><strong id="attDayHours">0.00</strong></div>
            <div class="d-flex justify-content-between mb-2"><span>Late Minutes</span><strong id="attDayLate">0</strong></div>
            <div class="d-flex justify-content-between"><span>Deduction</span><strong id="attDayDed">0.00</strong></div>
            <div class="d-flex justify-content-between mt-2 d-none" id="attDaySuspWrap"><span>Suspension Period</span><strong id="attDaySusp">-</strong></div>
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
        const details = <?= $attendanceDetailsJson ?: '{}' ?>;
        const modalEl = document.getElementById('attendanceDayModal');
        if (!modalEl) return;
        const modal = new bootstrap.Modal(modalEl);
        const elDate = document.getElementById('attDayDate');
        const elStatus = document.getElementById('attDayStatus');
        const elIn = document.getElementById('attDayIn');
        const elOut = document.getElementById('attDayOut');
        const elHours = document.getElementById('attDayHours');
        const elLate = document.getElementById('attDayLate');
        const elDed = document.getElementById('attDayDed');
        const elSuspWrap = document.getElementById('attDaySuspWrap');
        const elSusp = document.getElementById('attDaySusp');

        const grid = document.getElementById('attendanceCalendarGrid');
        if (!grid) return;

        grid.addEventListener('click', function (evt) {
          const dayEl = evt.target.closest('.eg-calendar-day[data-date]');
          if (!dayEl || !grid.contains(dayEl)) return;
          const dateKey = dayEl.getAttribute('data-date') || '';
            const row = details[dateKey] || null;
            if (!row) return;
            elDate.textContent = row.date_label || row.date || dateKey;
            elStatus.textContent = row.status || '-';
            elIn.textContent = row.time_in_label || '-';
            elOut.textContent = row.time_out_label || '-';
            elHours.textContent = row.hours_label || '0.00';
            elLate.textContent = String(Number(row.late_minutes || 0));
            elDed.textContent = row.deduction_label || '0.00';
            if (row.status === 'Suspended' && row.suspension_start && row.suspension_end) {
              elSusp.textContent = row.suspension_start + ' to ' + row.suspension_end;
              elSuspWrap.classList.remove('d-none');
            } else {
              elSusp.textContent = '-';
              elSuspWrap.classList.add('d-none');
            }
            modal.show();
        });
      })();
    </script>
    <script>
      (function () {
        var elTime = document.getElementById('egLiveClockTime');
        var elDate = document.getElementById('egLiveClockDate');
        if (!elTime) return;
        function tick() {
          var now = new Date();
          elTime.textContent = now.toLocaleTimeString(undefined, {
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
          });
          if (elDate) {
            elDate.textContent = now.toLocaleDateString(undefined, {
              weekday: 'long',
              year: 'numeric',
              month: 'long',
              day: 'numeric'
            });
          }
        }
        tick();
        setInterval(tick, 1000);
      })();
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>


