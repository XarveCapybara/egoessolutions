<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/eg_worked_minutes.php';
require_once __DIR__ . '/../includes/payroll_deduction_types.php';

$offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();

function eg_payroll_monday(DateTimeImmutable $d): DateTimeImmutable
{
    $w = (int) $d->format('N');
    return $d->modify('-' . ($w - 1) . ' days')->setTime(0, 0, 0);
}

function eg_cash_advance_week_range(string $advanceDate): ?array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $advanceDate)) {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $advanceDate);
    if (!$d) {
        return null;
    }
    $weekStart = eg_payroll_monday($d->setTime(0, 0, 0));
    // Cash advances follow payroll workweek cutoff (Mon-Fri).
    $weekEnd = $weekStart->modify('+4 days');
    return [
        'start' => $weekStart->format('Y-m-d'),
        'end' => $weekEnd->format('Y-m-d'),
    ];
}

function eg_last_payroll_day_of_month(DateTimeImmutable $dateInMonth): DateTimeImmutable
{
    $monthEnd = $dateInMonth->modify('last day of this month')->setTime(0, 0, 0);
    $weekday = (int) $monthEnd->format('N'); // 1=Mon ... 7=Sun
    if ($weekday >= 6) {
        $monthEnd = $monthEnd->modify('-' . ($weekday - 5) . ' days');
    }
    return $monthEnd;
}

$period = trim((string) ($_GET['period'] ?? 'week'));
if ($period !== 'month') {
    $period = 'week';
}

$weekInput = trim((string) ($_GET['week'] ?? ''));
if ($weekInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekInput)) {
    $picked = DateTimeImmutable::createFromFormat('Y-m-d', $weekInput) ?: new DateTimeImmutable('monday this week');
} else {
    $picked = new DateTimeImmutable('today');
}
$weekMonday = eg_payroll_monday($picked);
$weekFriday = $weekMonday->modify('+4 days');
$weekStartStr = $weekMonday->format('Y-m-d');
$weekEndStr = $weekFriday->format('Y-m-d');

$monthInput = trim((string) ($_GET['month'] ?? ''));
if ($monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
    $monthPicked = DateTimeImmutable::createFromFormat('Y-m-d', $monthInput . '-01') ?: new DateTimeImmutable('first day of this month');
} else {
    $monthPicked = new DateTimeImmutable('first day of this month');
}
$monthPicked = $monthPicked->setTime(0, 0, 0);
$monthStartDay = $monthPicked->format('Y-m-d');
$monthEndDay = $monthPicked->modify('last day of this month')->format('Y-m-d');
$monthValueForInput = $monthPicked->format('Y-m');

if ($period === 'month') {
    $rangeStart = $monthStartDay;
    $rangeEnd = $monthEndDay;
    $periodSummaryLabel = $monthPicked->format('F Y') . ' (full month)';
} else {
    $rangeStart = $weekStartStr;
    $rangeEnd = $weekEndStr;
    $periodSummaryLabel = $weekMonday->format('M j') . ' – ' . $weekFriday->format('M j, Y') . ' (Mon–Fri)';
}

$lastPayrollDay = eg_last_payroll_day_of_month($weekFriday);
$defaultShowDeductions = ($period === 'month') || ($period === 'week' && $weekFriday >= $lastPayrollDay);
$showDeductionsRaw = trim((string) ($_GET['show_deductions'] ?? ''));
if ($showDeductionsRaw === '1' || $showDeductionsRaw === '0') {
    $showDeductions = $showDeductionsRaw === '1';
} else {
    $showDeductions = $defaultShowDeductions;
}

$officeFilter = (int) ($_GET['office_id'] ?? 0);

$hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
$hasAppSettingsTable = $pdo->query("SHOW TABLES LIKE 'app_settings'")->rowCount() > 0;
$hasRateAmountColumn = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;
$hasCashAdvancesTable = false;
try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS cash_advances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            notes VARCHAR(255) NULL,
            advance_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "pending",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cash_adv_employee (employee_id),
            INDEX idx_cash_adv_status_date (status, advance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    if ($pdo->query("SHOW COLUMNS FROM cash_advances LIKE 'deducted_period_type'")->rowCount() > 0) {
        $pdo->exec('ALTER TABLE cash_advances DROP COLUMN deducted_period_type');
    }
    if ($pdo->query("SHOW COLUMNS FROM cash_advances LIKE 'deducted_period_start'")->rowCount() > 0) {
        $pdo->exec('ALTER TABLE cash_advances DROP COLUMN deducted_period_start');
    }
    if ($pdo->query("SHOW COLUMNS FROM cash_advances LIKE 'deducted_period_end'")->rowCount() > 0) {
        $pdo->exec('ALTER TABLE cash_advances DROP COLUMN deducted_period_end');
    }
    $pdo->exec("UPDATE cash_advances SET status = 'pending' WHERE status = 'accredited'");
    $hasCashAdvancesTable = true;
} catch (Throwable $e) {
    $hasCashAdvancesTable = $pdo->query("SHOW TABLES LIKE 'cash_advances'")->rowCount() > 0;
}

$defaultHourly = 0.0;
$deductionPerMinute = 0.0;
$configurableDeductionsPerEmployee = 0.0;
$payrollDeductionLines = [];
if ($hasAppSettingsTable) {
    $rateStmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $rateStmt->execute(['hourly_rate_default']);
    $rv = $rateStmt->fetchColumn();
    if ($rv !== false && $rv !== null && is_numeric($rv)) {
        $defaultHourly = (float) $rv;
    }
    $deductStmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $deductStmt->execute(['deduction_per_minute']);
    $dv = $deductStmt->fetchColumn();
    if ($dv !== false && $dv !== null && is_numeric($dv)) {
        $deductionPerMinute = (float) $dv;
    }
}
try {
    eg_ensure_payroll_deduction_types($pdo);
    $payrollDeductionLines = $pdo->query('SELECT label, default_amount FROM payroll_deduction_types ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $configurableDeductionsPerEmployee = (float) $pdo->query('SELECT COALESCE(SUM(default_amount), 0) FROM payroll_deduction_types')->fetchColumn();
} catch (Throwable $e) {
    $payrollDeductionLines = [];
    $configurableDeductionsPerEmployee = 0.0;
}

$payrollRows = [];
$employeeDetailsMap = [];
$totalGross = 0.0;
$totalDeductions = 0.0;
$totalNet = 0.0;
$totalTardinessDeductions = 0.0;
$totalCashAdvanceDeductions = 0.0;

if ($hasAttendanceLogs && $hasEmployeesTable) {
    $hasDeductionAmountColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;
    $hasLateMinutesColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
    $selectDeduction = $hasDeductionAmountColumn ? 'al.deduction_amount' : '0.00 AS deduction_amount';
    $selectLateMinutes = $hasLateMinutesColumn ? 'al.late_minutes' : '0 AS late_minutes';
    $selectRate = $hasRateAmountColumn ? 'e.rate_amount' : 'NULL AS rate_amount';


    $sql = "
        SELECT
            al.employee_id,
            al.log_date,
            al.time_in,
            al.time_out,
            {$selectDeduction},
            {$selectLateMinutes},
            u.full_name,
            o.name AS office_name,
            o.time_in AS office_start,
            o.time_out AS office_end,
            {$selectRate}
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        JOIN users u ON e.user_id = u.id
        JOIN offices o ON al.office_id = o.id
        WHERE al.log_date BETWEEN ? AND ?
    ";
    $params = [$rangeStart, $rangeEnd];
    if ($officeFilter > 0) {
        $sql .= ' AND al.office_id = ?';
        $params[] = $officeFilter;
    }
    $sql .= ' ORDER BY u.full_name, al.log_date, al.id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logRows = $stmt->fetchAll();

    $byEmployee = [];
    foreach ($logRows as $row) {
        $eid = (int) $row['employee_id'];
        if (!isset($byEmployee[$eid])) {
            $byEmployee[$eid] = [
                'full_name' => (string) ($row['full_name'] ?? ''),
                'office_name' => (string) ($row['office_name'] ?? ''),
                'rate_amount' => $row['rate_amount'] ?? null,
                'worked_minutes' => 0,
                'deductions' => 0.0,
                'loan_deduction' => 0.0,
            ];
        }
        $ld = (string) ($row['log_date'] ?? '');
        $rawWm = eg_worked_minutes_within_office_hours(
            $ld,
            $row['time_in'] ?? null,
            $row['time_out'] ?? null,
            (string) ($row['office_start'] ?? '08:00:00'),
            (string) ($row['office_end'] ?? '17:00:00')
        );
        $workedMinutes = (int) floor($rawWm / 60) * 60;
        $byEmployee[$eid]['worked_minutes'] += $workedMinutes;

        $rowDeduction = (float) ($row['deduction_amount'] ?? 0);
        $lateMinutes = (int) ($row['late_minutes'] ?? 0);
        if ($rowDeduction <= 0 && $deductionPerMinute > 0 && $hasLateMinutesColumn) {
            // Removed 60-minute grace period per "deduction per minute" instruction.
            $rowDeduction = max(0, $lateMinutes) * $deductionPerMinute;
        }
        $byEmployee[$eid]['deductions'] += $rowDeduction;
        $totalTardinessDeductions += $rowDeduction;



        if (!isset($employeeDetailsMap[$eid])) {
            $employeeDetailsMap[$eid] = [];
        }
        $employeeDetailsMap[$eid][] = [
            'date' => (string) ($row['log_date'] ?? ''),
            'time_in' => (string) ($row['time_in'] ?? ''),
            'time_out' => (string) ($row['time_out'] ?? ''),
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $lateMinutes,
            'deduction_amount' => $rowDeduction,
        ];
    }

    // Apply only approved (deducted) cash advances in the payroll week of the advance date.
    if ($hasCashAdvancesTable && !empty($byEmployee)) {
        $employeeIds = array_values(array_unique(array_map('intval', array_keys($byEmployee))));
        $ph = implode(',', array_fill(0, count($employeeIds), '?'));
        $loanStmt = $pdo->prepare("
            SELECT id, employee_id, amount, advance_date, status
            FROM cash_advances
            WHERE employee_id IN ({$ph})
            ORDER BY advance_date, id
        ");
        $loanStmt->execute($employeeIds);
        $loanRows = $loanStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($loanRows as $lr) {
            $eid = (int) ($lr['employee_id'] ?? 0);
            if (!isset($byEmployee[$eid])) {
                continue;
            }
            if ((string) ($lr['status'] ?? '') !== 'deducted') {
                continue;
            }
            $isDueThisPeriod = false;
            if ($period === 'week') {
                $targetWeek = eg_cash_advance_week_range((string) ($lr['advance_date'] ?? ''));
                $isDueThisPeriod = $targetWeek
                    && $targetWeek['start'] === $rangeStart
                    && $targetWeek['end'] === $rangeEnd;
            } else {
                $ad = (string) ($lr['advance_date'] ?? '');
                $isDueThisPeriod = $ad !== '' && $ad >= $rangeStart && $ad <= $rangeEnd;
            }
            if (!$isDueThisPeriod) {
                continue;
            }
            $amt = (float) ($lr['amount'] ?? 0);
            if ($amt <= 0) {
                continue;
            }
            $byEmployee[$eid]['deductions'] += $amt;
            $byEmployee[$eid]['loan_deduction'] += $amt;
            $totalCashAdvanceDeductions += $amt;
        }
    }

    foreach ($byEmployee as $eid => $agg) {
        $hourly = 0.0;
        $ra = $agg['rate_amount'];
        if ($ra !== null && $ra !== '') {
            $c = (float) $ra;
            if ($c > 0) {
                $hourly = $c;
            }
        }
        if ($hourly <= 0 && $hasAppSettingsTable) {
            $hourly = $defaultHourly;
        }
        $hours = $agg['worked_minutes'] / 60;
        $gross = $hours * $hourly;
        $baseDeductions = (float) $agg['deductions'];
        $ded = $baseDeductions + ($showDeductions ? $configurableDeductionsPerEmployee : 0.0);
        $net = $gross - $ded;
        $payrollRows[] = [
            'employee_id' => $eid,
            'full_name' => $agg['full_name'],
            'office_name' => $agg['office_name'],
            'hourly_rate' => $hourly,
            'hours' => $hours,
            'gross' => $gross,
            'deductions' => $ded,
            'net' => $net,
        ];
        $totalGross += $gross;
        $totalDeductions += $ded;
        $totalNet += $net;
    }

    usort($payrollRows, function ($a, $b) {
        return strcmp($a['full_name'], $b['full_name']);
    });

    $egFormatPayrollDetailTime = static function (string $ts): string {
        if ($ts === '') {
            return '-';
        }
        $norm = str_replace(' ', 'T', $ts);
        $dt = date_create($norm);
        return $dt ? $dt->format('g:i A') : $ts;
    };

    $payrollDetailRowsHtmlById = [];
    foreach ($employeeDetailsMap as $eid => $rows) {
        $parts = [];
        foreach ($rows as $r) {
            $dateStr = (string) ($r['date'] ?? '');
            $dateLabel = '-';
            if ($dateStr !== '') {
                $d = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
                $dateLabel = $d ? $d->format('M j, Y') : $dateStr;
            }
            $tin = $egFormatPayrollDetailTime((string) ($r['time_in'] ?? ''));
            $tout = $egFormatPayrollDetailTime((string) ($r['time_out'] ?? ''));
            $workedH = number_format(((float) ($r['worked_minutes'] ?? 0)) / 60.0, 2, '.', '');
            $lateM = (string) (int) ($r['late_minutes'] ?? 0);
            $dedAmt = number_format((float) ($r['deduction_amount'] ?? 0), 2, '.', '');
            $parts[] =
                '<tr><td>' . htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') . '</td>' .
                '<td>' . htmlspecialchars($tin, ENT_QUOTES, 'UTF-8') . '</td>' .
                '<td>' . htmlspecialchars($tout, ENT_QUOTES, 'UTF-8') . '</td>' .
                '<td class="text-end">' . htmlspecialchars($workedH, ENT_QUOTES, 'UTF-8') . '</td>' .
                '<td class="text-end">' . htmlspecialchars($lateM, ENT_QUOTES, 'UTF-8') . '</td>' .
                '<td class="text-end">' . htmlspecialchars($dedAmt, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $payrollDetailRowsHtmlById[$eid] = implode('', $parts);
    }
} else {
    $payrollDetailRowsHtmlById = [];
}

try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS payroll_receipts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            period_type VARCHAR(10) NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "pending",
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_payroll_receipt (employee_id, period_type, period_start, period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
} catch (PDOException $e) {
    // ignore
}

if (!empty($payrollRows)) {
    $ids = array_values(array_unique(array_map('intval', array_column($payrollRows, 'employee_id'))));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $receiptStmt = $pdo->prepare("
        SELECT employee_id, status FROM payroll_receipts
        WHERE period_type = ? AND period_start = ? AND period_end = ? AND employee_id IN ({$placeholders})
    ");
    $receiptStmt->execute(array_merge([$period, $rangeStart, $rangeEnd], $ids));
    $receiptMap = [];
    foreach ($receiptStmt->fetchAll() as $rr) {
        $receiptMap[(int) $rr['employee_id']] = $rr['status'] === 'received' ? 'received' : 'pending';
    }
    foreach ($payrollRows as &$prRow) {
        $eid = (int) $prRow['employee_id'];
        $prRow['receipt_status'] = $receiptMap[$eid] ?? 'pending';
    }
    unset($prRow);
} else {
    $receiptMap = [];
}

$employeeCount = count($payrollRows);
$pendingReceiptCount = 0;
$receivedReceiptCount = 0;
foreach ($payrollRows as $pr) {
    if (($pr['receipt_status'] ?? 'pending') === 'received') {
        $receivedReceiptCount++;
    } else {
        $pendingReceiptCount++;
    }
}
$isMonthPeriod = ($period === 'month');
$deductionListCollapseId = 'deductionListCollapse';
$payrollDetailRowsHtmlJson = json_encode(
    (object) $payrollDetailRowsHtmlById,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);

// Build ordered employee ID list for payslip navigation
$payslipEmployeeIdList = implode(',', array_map(function ($pr) {
    return (int) $pr['employee_id'];
}, $payrollRows));
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
          <h3 class="mb-3 fw-bold">Payroll</h3>
          

          <form method="get" action="payroll.php" class="eg-panel p-3 mb-3" id="payrollFilterForm">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <label class="form-label" for="periodSelect">Period</label>
                <select class="form-select" name="period" id="periodSelect" aria-label="Week or month">
                  <option value="week" <?= $period === 'week' ? ' selected' : '' ?>>Week (Mon–Fri)</option>
                  <option value="month" <?= $period === 'month' ? ' selected' : '' ?>>Month</option>
                </select>
              </div>
              <div class="col-12 col-sm-6 col-md-4 col-lg-3<?= $isMonthPeriod ? ' d-none' : '' ?>" id="weekFieldWrap">
                <label class="form-label" for="week">Week (any day in the week)</label>
                <input type="date" class="form-control" id="week" name="week" value="<?= htmlspecialchars($weekStartStr) ?>" />
              </div>
              <div class="col-12 col-sm-6 col-md-4 col-lg-3<?= !$isMonthPeriod ? ' d-none' : '' ?>" id="monthFieldWrap">
                <label class="form-label" for="month">Month</label>
                <input type="month" class="form-control" id="month" name="month" value="<?= htmlspecialchars($monthValueForInput) ?>" />
              </div>
              <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <label class="form-label" for="office_id">Office</label>
                <select class="form-select" id="office_id" name="office_id">
                  <option value="" <?= $officeFilter === 0 ? ' selected' : '' ?>>Select office</option>
                  <option value="0">All offices</option>
                  <?php foreach ($offices as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= $officeFilter === (int) $o['id'] ? ' selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-4 col-lg-3">
                <label class="form-label d-block">Payslip deductions</label>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="showDeductionsToggle"<?= $showDeductions ? ' checked' : '' ?> />
                  <label class="form-check-label" for="showDeductionsToggle">Show deductions by default</label>
                </div>
                <input type="hidden" name="show_deductions" id="showDeductionsField" value="<?= $showDeductions ? '1' : '0' ?>" />
              </div>
              <div class="col-12 col-md-3 col-lg-2 d-grid">
                <button type="submit" class="btn btn-primary">Apply</button>
              </div>
              <div class="col-12 col-md-6 col-lg-3 d-grid">
                <button
                  type="button"
                  class="btn btn-outline-success js-open-dept-summary-modal"
                  data-summary-url="payroll_department_summary.php?<?= htmlspecialchars(http_build_query([
                      'period' => $period,
                      'week' => $weekStartStr,
                      'month' => $monthValueForInput,
                      'office_id' => $officeFilter,
                      'show_deductions' => $showDeductions ? '1' : '0',
                  ]), ENT_QUOTES, 'UTF-8') ?>"
                >
                  Generate Department Summary
                </button>
              </div>
              <div class="col-12 col-md-6 col-lg-3 d-grid">
                <button
                  type="button"
                  class="btn btn-outline-primary js-open-office-summary-modal"
                  data-office-summary-url="payroll_office_summary.php?<?= htmlspecialchars(http_build_query([
                      'period' => $period,
                      'week' => $weekStartStr,
                      'month' => $monthValueForInput,
                      'office_id' => $officeFilter,
                      'show_deductions' => $showDeductions ? '1' : '0',
                  ]), ENT_QUOTES, 'UTF-8') ?>"
                >
                  Generate Office List Summary
                </button>
              </div>
            </div>
            <p class="text-muted small mb-0 mt-2">
              Showing <strong><?= htmlspecialchars($periodSummaryLabel) ?></strong>
              <?php if ($officeFilter > 0): ?>
                <?php
                $on = null;
                foreach ($offices as $o) {
                    if ((int) $o['id'] === $officeFilter) {
                        $on = $o['name'];
                        break;
                    }
                }
                ?>
                · Office: <strong><?= htmlspecialchars($on ?? (string) $officeFilter) ?></strong>
              <?php endif; ?>
            </p>
          </form>

          <div class="row mb-3 g-3">
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small">Employees (with hours)</div>
                <div class="fw-bold fs-4"><?= $employeeCount ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small"><?= $isMonthPeriod ? 'Monthly gross' : 'Weekly gross' ?></div>
                <div class="fw-bold fs-4"><?= number_format($totalGross, 2) ?></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <div class="text-muted small"><?= $isMonthPeriod ? 'Monthly deductions' : 'Weekly deductions' ?></div>
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary py-0 px-2 js-deduction-collapse-toggle"
                    data-bs-toggle="collapse"
                    data-bs-target="#<?= $deductionListCollapseId ?>"
                    aria-expanded="false"
                    aria-controls="<?= $deductionListCollapseId ?>"
                    title="Show deduction list"
                  >
                    <i class="bi bi-chevron-down"></i>
                  </button>
                </div>
                <div class="fw-bold fs-4"><?= number_format($totalDeductions, 2) ?></div>
                <div class="collapse mt-2" id="<?= $deductionListCollapseId ?>">
                  <?php if (!empty($payrollDeductionLines) || $totalTardinessDeductions > 0 || $totalCashAdvanceDeductions > 0): ?>
                    <ul class="list-unstyled small mb-0">
                      <li class="d-flex justify-content-between align-items-center py-1">
                        <span class="text-muted">Tardiness deduction</span>
                        <span class="fw-semibold"><?= number_format($totalTardinessDeductions, 2) ?></span>
                      </li>
                      <li class="d-flex justify-content-between align-items-center py-1">
                        <span class="text-muted">Cash advance deduction</span>
                        <span class="fw-semibold"><?= number_format($totalCashAdvanceDeductions, 2) ?></span>
                      </li>
                      <?php foreach ($payrollDeductionLines as $line): ?>
                        <?php
                        $lineBaseAmount = (float) ($line['default_amount'] ?? 0);
                        $lineContributingTotal = $showDeductions ? ($lineBaseAmount * $employeeCount) : 0.0;
                        ?>
                        <li class="d-flex justify-content-between align-items-center py-1">
                          <span class="text-muted"><?= htmlspecialchars((string) ($line['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                          <span class="fw-semibold"><?= number_format($lineContributingTotal, 2) ?></span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                    <div class="small text-muted mt-1">
                      Based on <?= (int) $employeeCount ?> employee<?= $employeeCount === 1 ? '' : 's' ?> in this payroll.
                    </div>
                  <?php else: ?>
                    <div class="small text-muted">No deduction lines configured.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="eg-metric-card">
                <div class="text-muted small"><?= $isMonthPeriod ? 'Monthly net pay' : 'Weekly net pay' ?></div>
                <div class="fw-bold fs-4"><?= number_format($totalNet, 2) ?></div>
              </div>
            </div>
          </div>
          <?php if ($employeeCount > 0): ?>
            <div class="row mb-3 g-3">
              <div class="col-md-6 col-lg-4">
                <div class="eg-metric-card border-warning border-opacity-50">
                  <div class="text-muted small">Payroll pending</div>
                  <div class="fw-bold fs-4"><?= (int) $pendingReceiptCount ?></div>
                </div>
              </div>
              <div class="col-md-6 col-lg-4">
                <div class="eg-metric-card border-success border-opacity-50">
                  <div class="text-muted small">Payroll received</div>
                  <div class="fw-bold fs-4"><?= (int) $receivedReceiptCount ?></div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="eg-panel p-3">
            <?php if (!$hasAttendanceLogs || !$hasEmployeesTable): ?>
              <p class="text-muted small mb-0">Attendance or employee data is not available. Check that <code>attendance_logs</code> and <code>employees</code> exist.</p>
            <?php elseif (empty($payrollRows)): ?>
              <p class="text-muted small mb-0">No attendance in this period for the selected filter. Try another week, month, or office.</p>
            <?php else: ?>
              <div class="table-responsive d-none d-md-block">
                <table class="table table-sm table-hover align-middle mb-0" style="table-layout: fixed; width: 100%;">
                  <colgroup>
                    <col style="width: 18%;" />
                    <col style="width: 12%;" />
                    <col style="width: 10%;" />
                    <col style="width: 8%;" />
                    <col style="width: 11%;" />
                    <col style="width: 11%;" />
                    <col style="width: 11%;" />
                    <col style="width: 5%;" />
                    <col style="width: 14%;" />
                  </colgroup>
                  <thead class="table-light">
                    <tr>
                      <th>Employee</th>
                      <th>Office</th>
                      <th class="text-end">Hourly Rate</th>
                      <th class="text-end">Hours</th>
                      <th class="text-end">Gross</th>
                      <th class="text-end">Deductions</th>
                      <th class="text-end">Net Pay</th>
                      <th class="text-center">Payslip</th>
                      <th>Receipt</th>
                    </tr>
                  </thead>
                  <tbody id="payrollEmployeeTableBody">
                    <?php foreach ($payrollRows as $pr): ?>
                      <?php $recv = ($pr['receipt_status'] ?? 'pending') === 'received'; ?>
                      <tr>
                        <td>
                          <button
                            type="button"
                            class="btn btn-link p-0 align-baseline text-start text-decoration-none js-open-payroll-detail"
                            data-employee-id="<?= (int) $pr['employee_id'] ?>"
                            data-employee-name="<?= htmlspecialchars($pr['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-office="<?= htmlspecialchars($pr['office_name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-hourly-rate="<?= number_format((float) $pr['hourly_rate'], 2, '.', '') ?>"
                            data-hours="<?= number_format((float) $pr['hours'], 2, '.', '') ?>"
                            data-gross="<?= number_format((float) $pr['gross'], 2, '.', '') ?>"
                            data-deductions="<?= number_format((float) $pr['deductions'], 2, '.', '') ?>"
                            data-net="<?= number_format((float) $pr['net'], 2, '.', '') ?>"
                          >
                            <?= htmlspecialchars($pr['full_name']) ?>
                          </button>
                        </td>
                        <td class="text-truncate"><?= htmlspecialchars($pr['office_name']) ?></td>
                        <td class="text-end" style="white-space: nowrap;"><?= number_format($pr['hourly_rate'], 2) ?></td>
                        <td class="text-end" style="white-space: nowrap;"><?= number_format($pr['hours'], 2) ?></td>
                        <td class="text-end" style="white-space: nowrap;"><?= number_format($pr['gross'], 2) ?></td>
                        <td class="text-end" style="white-space: nowrap;"><?= number_format($pr['deductions'], 2) ?></td>
                        <td class="text-end fw-semibold" style="white-space: nowrap;"><?= number_format($pr['net'], 2) ?></td>
                        <td class="text-center">
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary js-open-payslip-modal"
                            data-payslip-url="payslip_print.php?<?= htmlspecialchars(http_build_query([
                                'employee_id' => (int) $pr['employee_id'],
                                'period' => $period,
                                'week' => $weekStartStr,
                                'month' => $monthValueForInput,
                                'office_id' => $officeFilter,
                                'employees' => $payslipEmployeeIdList,
                                'show_deductions' => $showDeductions ? '1' : '0',
                            ]), ENT_QUOTES, 'UTF-8') ?>"
                            title="Print payslip"
                            aria-label="Print payslip for <?= htmlspecialchars($pr['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                          ><i class="bi bi-printer"></i></button>
                        </td>
                        <td>
                          <form method="post" action="save_payroll_receipt.php" class="d-flex flex-wrap align-items-center gap-1">
                            <input type="hidden" name="period_type" value="<?= htmlspecialchars($period) ?>" />
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekStartStr) ?>" />
                            <input type="hidden" name="month" value="<?= htmlspecialchars($monthValueForInput) ?>" />
                            <input type="hidden" name="office_id" value="<?= (int) $officeFilter ?>" />
                            <input type="hidden" name="employee_id" value="<?= (int) $pr['employee_id'] ?>" />
                            <select name="status" class="form-select form-select-sm" style="width: auto; min-width: 7.5rem;" aria-label="Receipt status for <?= htmlspecialchars($pr['full_name']) ?>" onchange="this.form.submit()">
                              <option value="pending" <?= !$recv ? ' selected' : '' ?>>Pending</option>
                              <option value="received" <?= $recv ? ' selected' : '' ?>>Received</option>
                            </select>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="d-md-none">
                <?php foreach ($payrollRows as $pr): ?>
                  <?php $recv = ($pr['receipt_status'] ?? 'pending') === 'received'; ?>
                  <div class="border rounded p-2 mb-2 bg-white">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                      <button
                        type="button"
                        class="btn btn-link p-0 text-start text-decoration-none fw-semibold js-open-payroll-detail"
                        data-employee-id="<?= (int) $pr['employee_id'] ?>"
                        data-employee-name="<?= htmlspecialchars($pr['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                        data-office="<?= htmlspecialchars($pr['office_name'], ENT_QUOTES, 'UTF-8') ?>"
                        data-hourly-rate="<?= number_format((float) $pr['hourly_rate'], 2, '.', '') ?>"
                        data-hours="<?= number_format((float) $pr['hours'], 2, '.', '') ?>"
                        data-gross="<?= number_format((float) $pr['gross'], 2, '.', '') ?>"
                        data-deductions="<?= number_format((float) $pr['deductions'], 2, '.', '') ?>"
                        data-net="<?= number_format((float) $pr['net'], 2, '.', '') ?>"
                      >
                        <?= htmlspecialchars($pr['full_name']) ?>
                      </button>
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary js-open-payslip-modal"
                        data-payslip-url="payslip_print.php?<?= htmlspecialchars(http_build_query([
                            'employee_id' => (int) $pr['employee_id'],
                            'period' => $period,
                            'week' => $weekStartStr,
                            'month' => $monthValueForInput,
                            'office_id' => $officeFilter,
                            'employees' => $payslipEmployeeIdList,
                            'show_deductions' => $showDeductions ? '1' : '0',
                        ]), ENT_QUOTES, 'UTF-8') ?>"
                        title="Print payslip"
                        aria-label="Print payslip for <?= htmlspecialchars($pr['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                      ><i class="bi bi-printer"></i></button>
                    </div>
                    <div class="small text-muted mb-2"><?= htmlspecialchars($pr['office_name']) ?></div>
                    <div class="small">
                      <div class="d-flex justify-content-between"><span class="text-muted">Rate</span><span><?= number_format($pr['hourly_rate'], 2) ?></span></div>
                      <div class="d-flex justify-content-between"><span class="text-muted">Hours</span><span><?= number_format($pr['hours'], 2) ?></span></div>
                      <div class="d-flex justify-content-between"><span class="text-muted">Gross</span><span><?= number_format($pr['gross'], 2) ?></span></div>
                      <div class="d-flex justify-content-between"><span class="text-muted">Deductions</span><span><?= number_format($pr['deductions'], 2) ?></span></div>
                      <div class="d-flex justify-content-between fw-semibold"><span>Net Pay</span><span><?= number_format($pr['net'], 2) ?></span></div>
                    </div>
                    <form method="post" action="save_payroll_receipt.php" class="mt-2">
                      <input type="hidden" name="period_type" value="<?= htmlspecialchars($period) ?>" />
                      <input type="hidden" name="week" value="<?= htmlspecialchars($weekStartStr) ?>" />
                      <input type="hidden" name="month" value="<?= htmlspecialchars($monthValueForInput) ?>" />
                      <input type="hidden" name="office_id" value="<?= (int) $officeFilter ?>" />
                      <input type="hidden" name="employee_id" value="<?= (int) $pr['employee_id'] ?>" />
                      <select name="status" class="form-select form-select-sm" aria-label="Receipt status for <?= htmlspecialchars($pr['full_name']) ?>" onchange="this.form.submit()">
                        <option value="pending" <?= !$recv ? ' selected' : '' ?>>Pending</option>
                        <option value="received" <?= $recv ? ' selected' : '' ?>>Received</option>
                      </select>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="modal" id="payrollDetailModal" tabindex="-1" aria-labelledby="payrollDetailModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="payrollDetailModalLabel">Employee Payslip Details</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div class="mb-2"><strong id="pdEmployeeName">-</strong> <span class="text-muted" id="pdOffice"></span></div>
                  <div class="small text-muted mb-3" id="pdPeriod"></div>
                  <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3"><div class="small text-muted">Rate</div><div id="pdRate" class="fw-semibold">0.00</div></div>
                    <div class="col-6 col-md-3"><div class="small text-muted">Hours</div><div id="pdHours" class="fw-semibold">0.00</div></div>
                    <div class="col-6 col-md-2"><div class="small text-muted">Gross</div><div id="pdGross" class="fw-semibold">0.00</div></div>
                    <div class="col-6 col-md-2"><div class="small text-muted">Deduction</div><div id="pdDed" class="fw-semibold">0.00</div></div>
                    <div class="col-6 col-md-2"><div class="small text-muted">Net</div><div id="pdNet" class="fw-semibold">0.00</div></div>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead class="table-light">
                        <tr><th>Date</th><th>Time In</th><th>Time Out</th><th class="text-end">Worked</th><th class="text-end">Late (min)</th><th class="text-end">Deduction</th></tr>
                      </thead>
                      <tbody id="pdRows"></tbody>
                    </table>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" id="pdPrintPayslip" class="btn btn-primary js-open-payslip-modal" data-payslip-url="#">Print payslip</button>
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Payslip iframe modal -->
          <div class="modal" id="payslipIframeModal" tabindex="-1" aria-labelledby="payslipIframeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 900px;">
              <div class="modal-content" style="height: 85vh;">
                <div class="modal-header py-2">
                  <h5 class="modal-title" id="payslipIframeModalLabel">Payslip Preview</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                  <iframe id="payslipIframe" src="about:blank" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
              </div>
            </div>
          </div>

          <!-- Department Summary iframe modal -->
          <div class="modal" id="deptSummaryIframeModal" tabindex="-1" aria-labelledby="deptSummaryIframeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 960px;">
              <div class="modal-content" style="height: 85vh;">
                <div class="modal-header py-2">
                  <h5 class="modal-title" id="deptSummaryIframeModalLabel">Department Summary</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                  <iframe id="deptSummaryIframe" src="about:blank" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
              </div>
            </div>
          </div>

          <!-- Office List Summary iframe modal -->
          <div class="modal" id="officeSummaryIframeModal" tabindex="-1" aria-labelledby="officeSummaryIframeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 960px;">
              <div class="modal-content" style="height: 85vh;">
                <div class="modal-header py-2">
                  <h5 class="modal-title" id="officeSummaryIframeModalLabel">Office Payroll Summary</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="overflow: hidden;">
                  <iframe id="officeSummaryIframe" src="about:blank" style="width: 100%; height: 100%; border: none;"></iframe>
                </div>
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
    <script>
      (function () {
        var toggle = document.getElementById('showDeductionsToggle');
        var hidden = document.getElementById('showDeductionsField');
        if (!toggle || !hidden) return;
        function syncHidden() {
          hidden.value = toggle.checked ? '1' : '0';
        }
        toggle.addEventListener('change', syncHidden);
        syncHidden();
      })();

      (function () {
        const periodSelect = document.getElementById('periodSelect');
        const weekWrap = document.getElementById('weekFieldWrap');
        const monthWrap = document.getElementById('monthFieldWrap');
        if (!periodSelect || !weekWrap || !monthWrap) return;

        function syncPeriodUi() {
          const isMonth = periodSelect.value === 'month';
          weekWrap.classList.toggle('d-none', isMonth);
          monthWrap.classList.toggle('d-none', !isMonth);
        }

        periodSelect.addEventListener('change', syncPeriodUi);
        syncPeriodUi();
      })();

      (function () {
        var collapseEl = document.getElementById(<?= json_encode($deductionListCollapseId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        var toggleBtn = document.querySelector('.js-deduction-collapse-toggle');
        if (!collapseEl || !toggleBtn) return;
        var icon = toggleBtn.querySelector('i');

        function syncIcon(isOpen) {
          if (!icon) return;
          icon.classList.toggle('bi-chevron-down', !isOpen);
          icon.classList.toggle('bi-chevron-up', isOpen);
          toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        collapseEl.addEventListener('shown.bs.collapse', function () { syncIcon(true); });
        collapseEl.addEventListener('hidden.bs.collapse', function () { syncIcon(false); });
        syncIcon(collapseEl.classList.contains('show'));
      })();

      (function () {
        const detailRowsHtml = <?= $payrollDetailRowsHtmlJson ?: '{}' ?>;
        const modalEl = document.getElementById('payrollDetailModal');
        const tableBody = document.getElementById('payrollEmployeeTableBody');
        if (!modalEl || !tableBody) return;
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const rowsEl = document.getElementById('pdRows');
        const nameEl = document.getElementById('pdEmployeeName');
        const officeEl = document.getElementById('pdOffice');
        const rateEl = document.getElementById('pdRate');
        const hoursEl = document.getElementById('pdHours');
        const grossEl = document.getElementById('pdGross');
        const dedEl = document.getElementById('pdDed');
        const netEl = document.getElementById('pdNet');
        const periodEl = document.getElementById('pdPeriod');
        const periodLabel = <?= json_encode($periodSummaryLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const payslipBase = new URLSearchParams({
          period: <?= json_encode($period, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
          week: <?= json_encode($weekStartStr, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
          month: <?= json_encode($monthValueForInput, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
          office_id: String(<?= (int) $officeFilter ?>),
          employees: <?= json_encode($payslipEmployeeIdList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
          show_deductions: <?= json_encode($showDeductions ? '1' : '0', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        });
        const pdPrintPayslip = document.getElementById('pdPrintPayslip');
        const emptyRow =
          '<tr><td colspan="6" class="text-muted text-center">No logs for this period.</td></tr>';

        tableBody.addEventListener('click', function (e) {
          const btn = e.target.closest('.js-open-payroll-detail');
          if (!btn) return;
          const id = String(btn.getAttribute('data-employee-id') || '');
          if (pdPrintPayslip) {
            payslipBase.set('employee_id', id);
            pdPrintPayslip.setAttribute('data-payslip-url', 'payslip_print.php?' + payslipBase.toString());
          }
          const html = detailRowsHtml[id];
          nameEl.textContent = btn.getAttribute('data-employee-name') || 'Employee';
          const office = btn.getAttribute('data-office') || '';
          officeEl.textContent = office ? '(' + office + ')' : '';
          if (periodEl) periodEl.textContent = 'Period: ' + periodLabel;
          rateEl.textContent = btn.getAttribute('data-hourly-rate') || '0.00';
          hoursEl.textContent = btn.getAttribute('data-hours') || '0.00';
          grossEl.textContent = btn.getAttribute('data-gross') || '0.00';
          dedEl.textContent = btn.getAttribute('data-deductions') || '0.00';
          netEl.textContent = btn.getAttribute('data-net') || '0.00';
          rowsEl.innerHTML = html && html.length ? html : emptyRow;
          modal.show();
        });
      })();

      // Payslip iframe modal logic
      (function () {
        var iframeModalEl = document.getElementById('payslipIframeModal');
        var iframe = document.getElementById('payslipIframe');
        if (!iframeModalEl || !iframe) return;
        var iframeModal = bootstrap.Modal.getOrCreateInstance(iframeModalEl);

        document.addEventListener('click', function (e) {
          var btn = e.target.closest('.js-open-payslip-modal');
          if (!btn) return;
          e.preventDefault();
          var url = btn.getAttribute('data-payslip-url');
          if (!url || url === '#') return;
          iframe.src = url;
          // Close any other open modals first
          var openModals = document.querySelectorAll('.modal.show');
          openModals.forEach(function (m) {
            var inst = bootstrap.Modal.getInstance(m);
            if (inst) inst.hide();
          });
          setTimeout(function () { iframeModal.show(); }, 200);
        });

        iframeModalEl.addEventListener('hidden.bs.modal', function () {
          iframe.src = 'about:blank';
        });
      })();

      // Department Summary iframe modal logic
      (function () {
        var modalEl = document.getElementById('deptSummaryIframeModal');
        var iframe = document.getElementById('deptSummaryIframe');
        if (!modalEl || !iframe) return;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        document.addEventListener('click', function (e) {
          var btn = e.target.closest('.js-open-dept-summary-modal');
          if (!btn) return;
          e.preventDefault();
          var url = btn.getAttribute('data-summary-url');
          if (!url || url === '#') return;
          iframe.src = url;
          modal.show();
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
          iframe.src = 'about:blank';
        });
      })();

      // Office Summary iframe modal logic
      (function () {
        var modalEl = document.getElementById('officeSummaryIframeModal');
        var iframe = document.getElementById('officeSummaryIframe');
        if (!modalEl || !iframe) return;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        document.addEventListener('click', function (e) {
          var btn = e.target.closest('.js-open-office-summary-modal');
          if (!btn) return;
          e.preventDefault();
          var url = btn.getAttribute('data-office-summary-url');
          if (!url || url === '#') return;
          iframe.src = url;
          modal.show();
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
          iframe.src = 'about:blank';
        });
      })();
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>
