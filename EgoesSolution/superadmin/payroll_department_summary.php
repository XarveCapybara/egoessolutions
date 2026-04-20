<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payroll_deduction_types.php';
require_once __DIR__ . '/../includes/eg_worked_minutes.php';

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

if ($period === 'month') {
    $rangeStart = $monthStartDay;
    $rangeEnd = $monthEndDay;
    $periodLabel = $monthPicked->format('F Y');
} else {
    $rangeStart = $weekStartStr;
    $rangeEnd = $weekEndStr;
    $periodLabel = $weekMonday->format('M j') . ' - ' . $weekFriday->format('M j, Y');
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
$offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$officeName = 'All Offices';
foreach ($offices as $o) {
    if ((int) ($o['id'] ?? 0) === $officeFilter) {
        $officeName = (string) ($o['name'] ?? 'Office');
        break;
    }
}

$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
$hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
$hasOfficeTimeInColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_in'")->rowCount() > 0;
$hasOfficeTimeOutColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_out'")->rowCount() > 0;
$hasRateAmountColumn = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;
$hasPositionColumn = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'position'")->rowCount() > 0;
$hasEmpCodeColumn = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'employee_code'")->rowCount() > 0;
$hasLateMinutesColumn = $hasAttendanceLogs && $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
$hasDeductionAmountColumn = $hasAttendanceLogs && $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;

$defaultHourly = 0.0;
$deductionPerMinute = 0.0;
try {
    $rateStmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $rateStmt->execute(['hourly_rate_default']);
    $rv = $rateStmt->fetchColumn();
    if ($rv !== false && $rv !== null && is_numeric($rv)) {
        $defaultHourly = (float) $rv;
    }
    $dedStmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
    $dedStmt->execute(['deduction_per_minute']);
    $dv = $dedStmt->fetchColumn();
    if ($dv !== false && $dv !== null && is_numeric($dv)) {
        $deductionPerMinute = (float) $dv;
    }
} catch (Throwable $e) {
    // settings optional
}

$dedTypeMap = ['sss' => 0.0, 'philhealth' => 0.0, 'pagibig' => 0.0, 'loan' => 0.0, 'other' => 0.0];
try {
    eg_ensure_payroll_deduction_types($pdo);
    $dRows = $pdo->query('SELECT label, default_amount FROM payroll_deduction_types ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dRows as $dr) {
        $label = strtolower(trim((string) ($dr['label'] ?? '')));
        $amount = (float) ($dr['default_amount'] ?? 0);
        if (strpos($label, 'sss') !== false) {
            $dedTypeMap['sss'] += $amount;
        } elseif (strpos($label, 'phil') !== false) {
            $dedTypeMap['philhealth'] += $amount;
        } elseif (strpos($label, 'pag') !== false || strpos($label, 'ibig') !== false) {
            $dedTypeMap['pagibig'] += $amount;
        } elseif (strpos($label, 'loan') !== false) {
            $dedTypeMap['loan'] += $amount;
        } else {
            $dedTypeMap['other'] += $amount;
        }
    }
} catch (Throwable $e) {
    // optional
}

$employees = [];
if ($hasEmployeesTable && $hasAttendanceLogs) {
    $rateSelect = $hasRateAmountColumn ? 'e.rate_amount' : 'NULL AS rate_amount';
    $positionSelect = $hasPositionColumn ? 'e.position' : 'NULL AS position';
    $codeSelect = $hasEmpCodeColumn ? 'e.employee_code' : 'CAST(e.id AS CHAR) AS employee_code';
    $lateSelect = $hasLateMinutesColumn ? 'al.late_minutes' : '0 AS late_minutes';
    $dedSelect = $hasDeductionAmountColumn ? 'al.deduction_amount' : '0.00 AS deduction_amount';
    $officeTimeInSelect = $hasOfficeTimeInColumn ? 'o.time_in AS office_start' : 'NULL AS office_start';
    $officeTimeOutSelect = $hasOfficeTimeOutColumn ? 'o.time_out AS office_end' : 'NULL AS office_end';

    $sql = "
        SELECT
            al.employee_id,
            al.log_date,
            {$lateSelect},
            {$dedSelect},
            al.time_in,
            al.time_out,
            {$officeTimeInSelect},
            {$officeTimeOutSelect},
            u.full_name,
            u.role,
            {$positionSelect},
            {$codeSelect},
            {$rateSelect}
        FROM attendance_logs al
        JOIN employees e ON e.id = al.employee_id
        JOIN users u ON u.id = e.user_id
        JOIN offices o ON o.id = al.office_id
        WHERE al.log_date BETWEEN ? AND ?
    ";
    $params = [$rangeStart, $rangeEnd];
    if ($officeFilter > 0) {
        $sql .= ' AND al.office_id = ?';
        $params[] = $officeFilter;
    }
    $sql .= ' ORDER BY u.full_name, al.id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $byEmployee = [];
    foreach ($rows as $row) {
        $eid = (int) ($row['employee_id'] ?? 0);
        if ($eid <= 0) {
            continue;
        }
        if (!isset($byEmployee[$eid])) {
            $role = (string) ($row['role'] ?? 'employee');
            $pos = trim((string) ($row['position'] ?? ''));
            if ($pos === '') {
                $pos = $role === 'admin' ? 'Team Leader' : 'Employee';
            }
            $byEmployee[$eid] = [
                'name' => (string) ($row['full_name'] ?? ''),
                'code' => (string) ($row['employee_code'] ?? (string) $eid),
                'position' => $pos,
                'rate' => (float) ($row['rate_amount'] ?? 0),
                'worked_minutes' => 0,
                'late_deduction' => 0.0,
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
        $wm = (int) floor($rawWm / 60) * 60;
        $byEmployee[$eid]['worked_minutes'] += $wm;

        $d = (float) ($row['deduction_amount'] ?? 0);
        $late = (int) ($row['late_minutes'] ?? 0);
        if ($d <= 0 && $deductionPerMinute > 0 && $hasLateMinutesColumn) {
            $d = max(0, $late) * $deductionPerMinute;
        }
        $byEmployee[$eid]['late_deduction'] += $d;
    }

    // Include deducted cash advances for this period (same rule as payroll page).
    if ($pdo->query("SHOW TABLES LIKE 'cash_advances'")->rowCount() > 0 && !empty($byEmployee)) {
        $employeeIds = array_values(array_unique(array_map('intval', array_keys($byEmployee))));
        $ph = implode(',', array_fill(0, count($employeeIds), '?'));
        $loanStmt = $pdo->prepare("
            SELECT employee_id, amount, advance_date, status
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
            $byEmployee[$eid]['late_deduction'] += $amt;
        }
    }

    foreach ($byEmployee as $eid => $r) {
        $rate = (float) $r['rate'];
        if ($rate <= 0) {
            $rate = $defaultHourly;
        }
        $gross = ((float) $r['worked_minutes'] / 60.0) * $rate;
        $contrib = $dedTypeMap['sss'] + $dedTypeMap['philhealth'] + $dedTypeMap['pagibig'] + $dedTypeMap['loan'];
        $otherConfig = $dedTypeMap['other'];
        $lateOnly = (float) $r['late_deduction'];
        $others = $otherConfig + $lateOnly;
        $totalDed = $contrib + $others;
        $net = $gross - $totalDed;
        $employees[] = [
            'name' => $r['name'],
            'code' => $r['code'],
            'position' => $r['position'],
            'gross' => $gross,
            'sss' => $dedTypeMap['sss'],
            'philhealth' => $dedTypeMap['philhealth'],
            'pagibig' => $dedTypeMap['pagibig'],
            'loan' => $dedTypeMap['loan'],
            'contrib' => $contrib,
            'other_config' => $otherConfig,
            'late_only' => $lateOnly,
            'others' => $others,
            'net' => $net,
        ];
    }
}

usort($employees, static function (array $a, array $b): int {
    return strcmp((string) $a['name'], (string) $b['name']);
});

$memberCount = count($employees);
$grossTotal = 0.0;
$contribTotal = 0.0;
$otherDedTotal = 0.0;
$netTotal = 0.0;
$otherConfigTotal = 0.0;
$lateOnlyTotal = 0.0;
$netLateOnlyTotal = 0.0;
foreach ($employees as $e) {
    $grossTotal += (float) $e['gross'];
    $contribTotal += (float) $e['contrib'];
    $otherConfigTotal += (float) $e['other_config'];
    $lateOnlyTotal += (float) $e['late_only'];
    $otherDedTotal += (float) $e['others'];
    $netTotal += (float) $e['net'];
    $netLateOnlyTotal += (float) $e['gross'] - (float) $e['late_only'];
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-GOES Solutions</title>
    <link rel="stylesheet" href="../assets/css/payslip-print.css?v=20" />
    <style>
      .eg-toolbar-btn {
        display: inline-block;
        padding: 8px 14px;
        border-radius: 6px;
        border: 1px solid #4f5a66;
        text-decoration: none;
        font-family: Arial, sans-serif;
        font-size: 14px;
      }
      .eg-toolbar-btn--primary { background: #1f2730; color: #fff; border-color: #1f2730; }
      .eg-toolbar-btn--secondary { background: #f3f4f6; color: #333; }
      .eg-payslip-sheet {
        background: #fff !important;
      }
      .eg-dept-title { font-size: 22px; font-weight: 700; }
      .eg-summary-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0; border: 1px solid #000; }
      .eg-summary-grid > div { border-right: 1px solid #000; padding: 6px 5px; text-align: center; }
      .eg-summary-grid > div:last-child { border-right: 0; }
      .eg-summary-head { background: #e6d4ff; color: #111; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.03em; }
      .eg-summary-val { font-size: 14px; font-weight: 700; }
      .eg-detail th, .eg-detail td { font-size: 10px; }
      .eg-detail th,
      .eg-detail thead th,
      .eg-payslip-deductions-inner.eg-detail thead th {
        background: #e6d4ff !important;
        color: #111;
      }
      .eg-payslip-deductions-inner.eg-detail tbody tr:nth-child(odd) td {
        background: #ffffff !important;
      }
      .eg-payslip-deductions-inner.eg-detail tbody tr:nth-child(even) td {
        background: #f1e6ff !important;
      }
      .eg-payslip-header {
        background: #6f42c1 !important;
        color: #fff;
        border-bottom: 0 !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px !important;
        min-height: 56px;
      }
      .eg-payslip-header .eg-payslip-company-name,
      .eg-payslip-header .eg-payslip-address,
      .eg-payslip-header .eg-payslip-title {
        color: #fff !important;
      }
      .eg-payslip-header .eg-payslip-company-name { font-size: 22px; font-weight: 700; line-height: 1.1; }
      .eg-payslip-header .eg-payslip-address { font-size: 12px; line-height: 1.1; margin-top: 2px; }
      .eg-payslip-header .eg-payslip-title { font-size: 46px; font-weight: 800; line-height: 1; }
      .eg-payslip-header .eg-payslip-brand,
      .eg-payslip-header .eg-payslip-title {
        background: transparent !important;
      }
      .eg-detail th:last-child,
      .eg-detail td:last-child {
        width: 14%;
        min-width: 130px;
      }
      .eg-detail td:last-child {
        padding-top: 12px;
        padding-bottom: 12px;
      }
      .eg-detail tbody tr:nth-child(odd),
      .eg-detail tbody tr:nth-child(even) {
        background: #fff;
      }
      @media print {
        .eg-payslip-toolbar { display: none !important; }
        body.eg-payslip-body { display: block !important; padding: 0 !important; }
        .eg-payslip-sheet {
          width: 100% !important;
          max-width: none !important;
          zoom: 0.86 !important;
          transform: none !important;
          margin: 0 !important;
          font-size: 10px !important;
        }
        .eg-payslip-box { padding: 8px 10px !important; margin-bottom: 8px !important; }
        .eg-dept-title { font-size: 17px !important; }
        .eg-summary-head { font-size: 9px; }
        .eg-summary-val { font-size: 13px; }
        .eg-detail th, .eg-detail td { font-size: 9px; line-height: 1.2; padding: 4px 5px !important; }
        .eg-detail th:last-child,
        .eg-detail td:last-child { min-width: 100px; padding-top: 8px !important; padding-bottom: 8px !important; }
        @page { size: A4 landscape; margin: 4mm; }
      }
    </style>
  </head>
  <body class="eg-payslip-body">
    <div class="eg-payslip-toolbar d-flex flex-wrap align-items-center gap-2">
      <a href="#" class="eg-toolbar-btn eg-toolbar-btn--primary" onclick="window.print(); return false;">Print</a>

      <label style="font-family: Arial, sans-serif; font-size: 13px; margin-left: 8px;">
        <input type="checkbox" id="dsShowDeductions"<?= $showDeductions ? ' checked' : '' ?> />
        Show deductions on summary
      </label>
      <span style="font-family: Arial, sans-serif; font-size: 12px; color: #555;">Uncheck to set configurable deductions to 0.00 (late/attendance still applied).</span>
    </div>

    <div class="eg-payslip-sheet">
      <header class="eg-payslip-header">
        <div class="eg-payslip-brand">
          <div class="eg-payslip-company-name">E-Goes Solutions</div>
          <div class="eg-payslip-address">Luna Tiradpass, Bello Building, 2nd floor, Digos City</div>
        </div>
        <div class="eg-payslip-title">Summary</div>
      </header>

      <div class="eg-payslip-box">
        <div class="eg-dept-title">Department Payroll Summary &amp; Compliance Dashboard</div>
      </div>

      <div class="eg-payslip-box eg-payslip-box--spaced">
        <div class="eg-summary-grid">
          <div class="eg-summary-head">Totals</div>
          <div class="eg-summary-head">Department</div>
          <div class="eg-summary-head">Total Member</div>
          <div class="eg-summary-head">Gross Total</div>
          <div class="eg-summary-head">Contribution Total</div>
          <div class="eg-summary-head">Other Deductions</div>
          <div class="eg-summary-head">Net Pay Total</div>
          <div class="eg-summary-val"><?= htmlspecialchars($periodLabel) ?></div>
          <div class="eg-summary-val"><?= htmlspecialchars($officeName) ?></div>
          <div class="eg-summary-val"><?= (int) $memberCount ?></div>
          <div class="eg-summary-val"><?= number_format($grossTotal, 2) ?></div>
          <div class="eg-summary-val"><span id="dsContribTotal"><?= number_format($contribTotal, 2) ?></span></div>
          <div class="eg-summary-val"><span id="dsOtherTotal"><?= number_format($otherDedTotal, 2) ?></span></div>
          <div class="eg-summary-val"><span id="dsNetTotal"><?= number_format($netTotal, 2) ?></span></div>
        </div>
      </div>

      <div class="eg-payslip-box eg-payslip-box--spaced">
        <table class="eg-payslip-deductions-inner eg-detail">
          <thead>
            <tr>
              <th style="width:16%;">EmployeeName</th>
              <th style="width:10%;">Employee ID</th>
              <th style="width:11%;">Position</th>
              <th style="width:9%;">Gross Pay</th>
              <th style="width:8%;">SSS</th>
              <th style="width:9%;">PhilHealth</th>
              <th style="width:9%;">Pag-IBIG</th>
              <th style="width:7%;">Loan</th>
              <th style="width:8%;">Others</th>
              <th style="width:7%;">NetPay</th>
              <th style="width:14%;">Signature</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($employees)): ?>
              <tr><td colspan="11">#N/A</td></tr>
            <?php else: ?>
              <?php foreach ($employees as $e): ?>
                <tr>
                  <td><?= htmlspecialchars((string) $e['name']) ?></td>
                  <td><?= htmlspecialchars((string) $e['code']) ?></td>
                  <td><?= htmlspecialchars((string) $e['position']) ?></td>
                  <td class="num"><?= number_format((float) $e['gross'], 2) ?></td>
                  <td class="num"><span class="ds-contrib-cell" data-full="<?= htmlspecialchars(number_format((float) $e['sss'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"><?= number_format((float) $e['sss'], 2) ?></span></td>
                  <td class="num"><span class="ds-contrib-cell" data-full="<?= htmlspecialchars(number_format((float) $e['philhealth'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"><?= number_format((float) $e['philhealth'], 2) ?></span></td>
                  <td class="num"><span class="ds-contrib-cell" data-full="<?= htmlspecialchars(number_format((float) $e['pagibig'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"><?= number_format((float) $e['pagibig'], 2) ?></span></td>
                  <td class="num"><span class="ds-contrib-cell" data-full="<?= htmlspecialchars(number_format((float) $e['loan'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"><?= number_format((float) $e['loan'], 2) ?></span></td>
                  <td class="num">
                    <span
                      class="ds-others-cell"
                      data-full="<?= htmlspecialchars(number_format((float) $e['others'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-late="<?= htmlspecialchars(number_format((float) $e['late_only'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                    ><?= number_format((float) $e['others'], 2) ?></span>
                  </td>
                  <td class="num">
                    <span
                      class="ds-net-cell"
                      data-full="<?= htmlspecialchars(number_format((float) $e['net'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-late="<?= htmlspecialchars(number_format(((float) $e['gross'] - (float) $e['late_only']), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                    ><?= number_format((float) $e['net'], 2) ?></span>
                  </td>
                  <td></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <script>
      (function () {
        var cb = document.getElementById('dsShowDeductions');
        if (!cb) return;
        var fmt = function (n) { return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        var totals = {
          contribFull: <?= json_encode(number_format($contribTotal, 2, '.', ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
          otherFull: <?= json_encode(number_format($otherDedTotal, 2, '.', ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
          otherLate: <?= json_encode(number_format($lateOnlyTotal, 2, '.', ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
          netFull: <?= json_encode(number_format($netTotal, 2, '.', ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
          netLate: <?= json_encode(number_format($netLateOnlyTotal, 2, '.', ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
        };
        var contribEl = document.getElementById('dsContribTotal');
        var otherEl = document.getElementById('dsOtherTotal');
        var netEl = document.getElementById('dsNetTotal');
        function sync() {
          var show = cb.checked;
          document.querySelectorAll('.ds-contrib-cell').forEach(function (el) {
            var full = Number(el.getAttribute('data-full') || '0');
            el.textContent = fmt(show ? full : 0);
          });
          document.querySelectorAll('.ds-others-cell').forEach(function (el) {
            var full = Number(el.getAttribute('data-full') || '0');
            var late = Number(el.getAttribute('data-late') || '0');
            el.textContent = fmt(show ? full : late);
          });
          document.querySelectorAll('.ds-net-cell').forEach(function (el) {
            var full = Number(el.getAttribute('data-full') || '0');
            var late = Number(el.getAttribute('data-late') || '0');
            el.textContent = fmt(show ? full : late);
          });
          if (contribEl) contribEl.textContent = fmt(show ? totals.contribFull : 0);
          if (otherEl) otherEl.textContent = fmt(show ? totals.otherFull : totals.otherLate);
          if (netEl) netEl.textContent = fmt(show ? totals.netFull : totals.netLate);
        }
        cb.addEventListener('change', sync);
        sync();
      })();
    </script>
  </body>
</html>
