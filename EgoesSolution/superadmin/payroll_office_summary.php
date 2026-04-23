<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/eg_worked_minutes.php';
require_once __DIR__ . '/../includes/payroll_deduction_types.php';

function eg_payroll_monday(DateTimeImmutable $d): DateTimeImmutable
{
    $w = (int) $d->format('N');
    return $d->modify('-' . ($w - 1) . ' days')->setTime(0, 0, 0);
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
    $periodLabel = $weekMonday->format('M j') . '-' . $weekFriday->format('j, Y');
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
$offices = $pdo->query('SELECT id, name, time_in, time_out FROM offices ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$officeNameById = [];
foreach ($offices as $o) {
    $officeNameById[(int) ($o['id'] ?? 0)] = (string) ($o['name'] ?? '');
}

$hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
$hasRateAmountColumn = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;
$hasDeductionAmountColumn = $hasAttendanceLogs && $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;
$hasLateMinutesColumn = $hasAttendanceLogs && $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;

$defaultHourly = 0.0;
$deductionPerMinute = 0.0;
$configurableDeductionsPerEmployee = 0.0;
$hasCashAdvancesTable = $pdo->query("SHOW TABLES LIKE 'cash_advances'")->rowCount() > 0;
try {
    if ($pdo->query("SHOW TABLES LIKE 'app_settings'")->rowCount() > 0) {
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
} catch (Throwable $e) {
    $defaultHourly = 0.0;
    $deductionPerMinute = 0.0;
}

try {
    eg_ensure_payroll_deduction_types($pdo);
    $configurableDeductionsPerEmployee = (float) $pdo->query('SELECT COALESCE(SUM(default_amount), 0) FROM payroll_deduction_types')->fetchColumn();
} catch (Throwable $e) {
    $configurableDeductionsPerEmployee = 0.0;
}

$officeRows = [];
$grandTotalNet = 0.0;
if ($hasAttendanceLogs && $hasEmployeesTable) {
    $selectDeduction = $hasDeductionAmountColumn ? 'al.deduction_amount' : '0.00 AS deduction_amount';
    $selectLateMinutes = $hasLateMinutesColumn ? 'al.late_minutes' : '0 AS late_minutes';
    $selectRate = $hasRateAmountColumn ? 'e.rate_amount' : 'NULL AS rate_amount';

    $sql = "
        SELECT
            al.employee_id,
            al.office_id,
            al.log_date,
            al.time_in,
            al.time_out,
            {$selectDeduction},
            {$selectLateMinutes},
            {$selectRate},
            o.name AS office_name,
            o.time_in AS office_start,
            o.time_out AS office_end
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        JOIN offices o ON al.office_id = o.id
        WHERE al.log_date BETWEEN ? AND ?
    ";
    $params = [$rangeStart, $rangeEnd];
    if ($officeFilter > 0) {
        $sql .= ' AND al.office_id = ?';
        $params[] = $officeFilter;
    }
    $sql .= ' ORDER BY o.name, al.employee_id, al.log_date, al.id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $employeeOfficeAgg = [];
    $employeePrimaryOfficeKey = [];
    foreach ($rows as $row) {
        $employeeId = (int) ($row['employee_id'] ?? 0);
        $officeId = (int) ($row['office_id'] ?? 0);
        if ($employeeId <= 0 || $officeId <= 0) {
            continue;
        }
        $key = $officeId . ':' . $employeeId;
        if (!isset($employeeOfficeAgg[$key])) {
            $employeeOfficeAgg[$key] = [
                'office_id' => $officeId,
                'office_name' => (string) ($row['office_name'] ?? ($officeNameById[$officeId] ?? 'Office')),
                'worked_minutes' => 0,
                'working_days' => 0,
                'working_day_map' => [],
                'deductions' => 0.0,
                'hourly_rate' => 0.0,
                'employee_id' => $employeeId,
            ];
            $ra = $row['rate_amount'] ?? null;
            if ($ra !== null && $ra !== '' && (float) $ra > 0) {
                $employeeOfficeAgg[$key]['hourly_rate'] = (float) $ra;
            } else {
                $employeeOfficeAgg[$key]['hourly_rate'] = $defaultHourly;
            }
        }
        if (!isset($employeePrimaryOfficeKey[$employeeId])) {
            $employeePrimaryOfficeKey[$employeeId] = $key;
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
        $employeeOfficeAgg[$key]['worked_minutes'] += $workedMinutes;
        if ($workedMinutes > 0) {
            $logDate = (string) ($row['log_date'] ?? '');
            if ($logDate !== '' && !isset($employeeOfficeAgg[$key]['working_day_map'][$logDate])) {
                $employeeOfficeAgg[$key]['working_day_map'][$logDate] = true;
                $employeeOfficeAgg[$key]['working_days']++;
            }
        }
        $rowDeduction = (float) ($row['deduction_amount'] ?? 0);
        $lateMinutes = (int) ($row['late_minutes'] ?? 0);
        if ($rowDeduction <= 0 && $deductionPerMinute > 0 && $hasLateMinutesColumn) {
            $rowDeduction = max(0, $lateMinutes) * $deductionPerMinute;
        }
        $employeeOfficeAgg[$key]['deductions'] += $rowDeduction;
    }

    if ($hasCashAdvancesTable && !empty($employeePrimaryOfficeKey)) {
        $employeeIds = array_values(array_unique(array_map('intval', array_keys($employeePrimaryOfficeKey))));
        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $loanStmt = $pdo->prepare("
            SELECT employee_id, amount, advance_date, status
            FROM cash_advances
            WHERE employee_id IN ({$placeholders})
            ORDER BY advance_date, id
        ");
        $loanStmt->execute($employeeIds);
        $loanRows = $loanStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($loanRows as $lr) {
            if ((string) ($lr['status'] ?? '') !== 'deducted') {
                continue;
            }
            $employeeId = (int) ($lr['employee_id'] ?? 0);
            $officeKey = $employeePrimaryOfficeKey[$employeeId] ?? null;
            if ($officeKey === null || !isset($employeeOfficeAgg[$officeKey])) {
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
            $employeeOfficeAgg[$officeKey]['deductions'] += $amt;
        }
    }

    if ($showDeductions && $configurableDeductionsPerEmployee > 0 && !empty($employeePrimaryOfficeKey)) {
        foreach ($employeePrimaryOfficeKey as $employeeId => $officeKey) {
            if (!isset($employeeOfficeAgg[$officeKey])) {
                continue;
            }
            $employeeOfficeAgg[$officeKey]['deductions'] += $configurableDeductionsPerEmployee;
        }
    }

    $officeAgg = [];
    foreach ($employeeOfficeAgg as $r) {
        $oid = (int) $r['office_id'];
        if (!isset($officeAgg[$oid])) {
            $officeAgg[$oid] = [
                'office_name' => (string) $r['office_name'],
                'worked_minutes' => 0,
                'working_days' => 0,
                'net' => 0.0,
            ];
        }
        $workedMinutes = (int) ($r['worked_minutes'] ?? 0);
        $hourly = (float) ($r['hourly_rate'] ?? 0);
        $gross = ($workedMinutes / 60.0) * $hourly;
        $net = $gross - (float) ($r['deductions'] ?? 0);

        $officeAgg[$oid]['worked_minutes'] += $workedMinutes;
        $officeAgg[$oid]['working_days'] += (int) ($r['working_days'] ?? 0);
        $officeAgg[$oid]['net'] += $net;
    }

    foreach ($officeAgg as $r) {
        $officeRows[] = [
            'office_name' => (string) $r['office_name'],
            'days_metric' => (int) ($r['working_days'] ?? 0),
            'net' => (float) $r['net'],
        ];
        $grandTotalNet += (float) $r['net'];
    }
}

usort($officeRows, static function (array $a, array $b): int {
    return strcmp((string) $a['office_name'], (string) $b['office_name']);
});
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll Office Summary</title>
    <style>
      body {
        margin: 0;
        background: #f1f5f9;
        font-family: Arial, Helvetica, sans-serif;
        color: #1f2937;
      }
      .toolbar {
        padding: 10px 14px;
      }
      .btn {
        display: inline-block;
        padding: 8px 14px;
        border-radius: 6px;
        border: 1px solid #1f2937;
        background: #1f2937;
        color: #fff;
        text-decoration: none;
        font-size: 14px;
      }
      .sheet {
        width: 850px;
        margin: 0 auto 20px;
        background: #fff;
        border: 1px solid #cbd5e1;
        padding: 20px 24px 26px;
      }
      .title {
        text-align: center;
        font-weight: 700;
        font-size: 28px;
        color: #334155;
        margin: 0 0 2px;
      }
      .subtitle {
        text-align: center;
        margin: 0 0 14px;
        font-size: 14px;
        color: #475569;
      }
      .table-wrap {
        border: 1px solid #000;
      }
      table {
        width: 100%;
        border-collapse: collapse;
      }
      thead th {
        background: #e6d4ff;
        color: #111;
        font-size: 12px;
        font-weight: 700;
        padding: 7px 8px;
        border: 1px solid #000;
      }
      tbody td {
        font-size: 12px;
        border: 1px solid #000;
        padding: 6px 8px;
      }
      tbody tr:nth-child(even) {
        background: #f1e6ff;
      }
      .text-end {
        text-align: right;
      }
      .total-row td {
        background: #e6d4ff;
        font-weight: 700;
      }
      .sign-area {
        margin-top: 38px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 50px;
      }
      .sign-label {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 18px;
      }
      .sign-line {
        border-top: 1px solid #1f2937;
        height: 1px;
        width: 260px;
        margin: 20px auto 0;
      }
      .sign-name {
        text-align: center;
        font-size: 13px;
        font-weight: 700;
        margin-top: 4px;
      }
      .sign-role {
        text-align: center;
        font-size: 12px;
        color: #475569;
      }
      @media print {
        body { background: #fff; }
        .toolbar { display: none !important; }
        .sheet {
          border: 0;
          width: auto;
          margin: 0;
          padding: 0;
        }
        * {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
        thead th {
          background: #e6d4ff !important;
          color: #111 !important;
          border: 1px solid #000 !important;
        }
        tbody tr:nth-child(even) {
          background: #f1e6ff !important;
        }
        .total-row td {
          background: #e6d4ff !important;
        }
        @page { size: A4 portrait; margin: 10mm; }
      }
    </style>
  </head>
  <body>
    <div class="toolbar">
      <a href="#" class="btn" onclick="window.print(); return false;">Print</a>
    </div>

    <div class="sheet">
      <h1 class="title">PAYROLL SUMMARY REPORT</h1>
      <p class="subtitle">(<?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?>) · Deductions: <?= $showDeductions ? 'Shown' : 'Hidden' ?></p>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width: 39%;">ACCT</th>
              <th style="width: 20%;" class="text-end">NO. OF DAYS</th>
              <th style="width: 25%;" class="text-end">NET</th>
              <th style="width: 16%;">DATE RELEASED</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($officeRows)): ?>
              <tr>
                <td colspan="4" style="text-align:center; color:#64748b;">No payroll data for this period.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($officeRows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars((string) $row['office_name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-end"><?= number_format((float) $row['days_metric'], 0) ?></td>
                  <td class="text-end">P<?= number_format((float) $row['net'], 2) ?></td>
                  <td></td>
                </tr>
              <?php endforeach; ?>
              <tr class="total-row">
                <td>TOTAL</td>
                <td></td>
                <td class="text-end">P<?= number_format($grandTotalNet, 2) ?></td>
                <td></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="sign-area">
        <div>
          <div class="sign-label">Prepared by:</div>
          <div class="sign-line"></div>
          <div class="sign-name">ANNA MARIE VILLANUEVA</div>
          <div class="sign-role">(HR Head)</div>
        </div>
        <div>
          <div class="sign-label">Approved by:</div>
          <div class="sign-line"></div>
          <div class="sign-name">REGIN MATA</div>
          <div class="sign-role">(Chief Executive Officer)</div>
        </div>
      </div>
    </div>
  </body>
</html>
