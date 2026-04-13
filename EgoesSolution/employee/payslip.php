<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payroll_deduction_types.php';

$name = $_SESSION['display_name'] ?? 'Employee';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$officeId = (int) ($_SESSION['office_id'] ?? 0);

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

function eg_payslip_period_display(string $period, DateTimeImmutable $weekMonday, DateTimeImmutable $weekFriday, DateTimeImmutable $monthPicked): string
{
    if ($period === 'month') {
        return $monthPicked->format('F Y') . ' (full month)';
    }
    $m1 = $weekMonday->format('n');
    $m2 = $weekFriday->format('n');
    $y1 = $weekMonday->format('Y');
    $y2 = $weekFriday->format('Y');
    if ($m1 === $m2 && $y1 === $y2) {
        return $weekMonday->format('F j') . '-' . $weekFriday->format('j, Y');
    }
    return $weekMonday->format('M j') . ' – ' . $weekFriday->format('M j, Y');
}

$period = trim((string) ($_GET['period'] ?? 'week'));
if ($period !== 'month') {
    $period = 'week';
}

$weekInput = trim((string) ($_GET['week'] ?? ''));
$monthInput = trim((string) ($_GET['month'] ?? ''));

if ($period === 'month') {
    if ($monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
        $monthPicked = DateTimeImmutable::createFromFormat('Y-m-d', $monthInput . '-01') ?: new DateTimeImmutable('first day of this month');
    } else {
        $monthPicked = new DateTimeImmutable('first day of this month');
    }
    $monthPicked = $monthPicked->setTime(0, 0, 0);
    $rangeStart = $monthPicked->format('Y-m-d');
    $rangeEnd = $monthPicked->modify('last day of this month')->format('Y-m-d');
    $monthInput = $monthPicked->format('Y-m');
    $weekMonday = eg_payroll_monday($monthPicked);
    $weekFriday = $weekMonday->modify('+4 days');
} else {
    if ($weekInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekInput)) {
        $picked = DateTimeImmutable::createFromFormat('Y-m-d', $weekInput) ?: new DateTimeImmutable('today');
    } else {
        $picked = new DateTimeImmutable('today');
    }
    $weekMonday = eg_payroll_monday($picked);
    $weekFriday = $weekMonday->modify('+4 days');
    $rangeStart = $weekMonday->format('Y-m-d');
    $rangeEnd = $weekFriday->format('Y-m-d');
    $weekInput = $weekMonday->format('Y-m-d');
}

$payPeriodLabel = eg_payslip_period_display($period, $weekMonday, $weekFriday, $monthPicked ?? new DateTimeImmutable('first day of this month'));
$earningsPeriodCol = $period === 'month' ? 'Monthly' : 'Daily / Weekly';

$showDeductions = $period === 'month';
if ($period === 'week') {
    $monthEnd = $weekMonday->modify('last day of this month')->setTime(0, 0, 0);
    $showDeductions = $weekFriday >= $monthEnd;
}

$hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
$hasAppSettingsTable = $pdo->query("SHOW TABLES LIKE 'app_settings'")->rowCount() > 0;
$hasRateAmountColumn = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;
$hasPositionCol = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'position'")->rowCount() > 0;
$hasEmployeeCodeCol = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'employee_code'")->rowCount() > 0;

$defaultHourly = 0.0;
$deductionPerMinute = 0.0;
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

$error = null;
$gross = 0.0;
$deductionsTotal = 0.0;
$net = 0.0;
$hoursWorked = 0.0;
$hourlyRate = 0.0;
$presentDays = 0;
$officeName = '';
$fullName = $name;
$positionLabel = 'Employee';
$employeeCode = '';
$payrollDeductionLines = [];

if (!$hasEmployeesTable || !$hasAttendanceLogs || $userId <= 0) {
    $error = 'Employee or attendance data is not available.';
} else {
    $posField = $hasPositionCol ? 'e.position' : 'NULL AS position';
    $rateField = $hasRateAmountColumn ? 'e.rate_amount' : 'NULL AS rate_amount';
    $codeField = $hasEmployeeCodeCol ? 'e.employee_code' : 'NULL AS employee_code';
    $empStmt = $pdo->prepare("SELECT e.id AS employee_id, {$codeField}, {$rateField}, u.full_name, u.role, {$posField} FROM employees e JOIN users u ON e.user_id = u.id WHERE e.user_id = ? LIMIT 1");
    $empStmt->execute([$userId]);
    $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
    if (!$empRow) {
        $error = 'Employee account not found.';
    } else {
        $employeeId = (int) ($empRow['employee_id'] ?? 0);
        $fullName = trim((string) ($empRow['full_name'] ?? '')) ?: $name;
        $codeRaw = trim((string) ($empRow['employee_code'] ?? ''));
        $employeeCode = $codeRaw !== '' ? $codeRaw : (string) $employeeId;
        $pos = trim((string) ($empRow['position'] ?? ''));
        if ($pos !== '') {
            $positionLabel = $pos;
        } else {
            $positionLabel = (($empRow['role'] ?? '') === 'admin') ? 'Team Leader' : 'Employee';
        }

        if ($empRow['rate_amount'] !== null && $empRow['rate_amount'] !== '') {
            $candidate = (float) $empRow['rate_amount'];
            if ($candidate > 0) {
                $hourlyRate = $candidate;
            }
        }
        if ($hourlyRate <= 0) {
            $hourlyRate = $defaultHourly;
        }

        $hasDeductionAmountColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;
        $hasLateMinutesColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
        $selectDeduction = $hasDeductionAmountColumn ? 'al.deduction_amount' : '0.00 AS deduction_amount';
        $selectLateMinutes = $hasLateMinutesColumn ? 'al.late_minutes' : '0 AS late_minutes';
        $sql = "SELECT al.log_date, al.time_in, al.time_out, {$selectDeduction}, {$selectLateMinutes}, o.name AS office_name FROM attendance_logs al JOIN offices o ON al.office_id = o.id WHERE al.employee_id = ? AND al.log_date BETWEEN ? AND ?";
        $params = [$employeeId, $rangeStart, $rangeEnd];
        if ($officeId > 0) {
            $sql .= ' AND al.office_id = ?';
            $params[] = $officeId;
        }
        $sql .= ' ORDER BY al.log_date, al.id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($logRows)) {
            $error = 'No attendance for this employee in the selected period.';
        } else {
            $officeName = (string) ($logRows[0]['office_name'] ?? '');
            $workedMinutes = 0;
            $deductionSum = 0.0;
            $datesPresent = [];
            foreach ($logRows as $row) {
                $ld = (string) ($row['log_date'] ?? '');
                if ($ld !== '') {
                    $datesPresent[$ld] = true;
                }
                $wm = 0;
                if (!empty($row['time_in']) && !empty($row['time_out'])) {
                    $inTs = strtotime((string) $row['time_in']);
                    $outTs = strtotime((string) $row['time_out']);
                    if ($outTs > $inTs) {
                        $wm = (int) floor(($outTs - $inTs) / 60);
                    }
                }
                $workedMinutes += $wm;
                $rowDeduction = (float) ($row['deduction_amount'] ?? 0);
                $lateMinutes = (int) ($row['late_minutes'] ?? 0);
                if ($rowDeduction <= 0 && $deductionPerMinute > 0 && $hasLateMinutesColumn) {
                    $rowDeduction = max(0, $lateMinutes - 60) * $deductionPerMinute;
                }
                $deductionSum += $rowDeduction;
            }
            $presentDays = count($datesPresent);
            $hoursWorked = $workedMinutes / 60.0;
            $gross = $hoursWorked * $hourlyRate;
            $deductionsTotal = $deductionSum;
        }
    }
}

$otherDeductions = 0.0;
$tardinessAmount = $deductionsTotal;
$cashAdvanceDeduction = 0.0;
$regularHoliday = 0.0;
$specialHoliday = 0.0;
$allowance = 0.0;
$overtimePay = 0.0;

$totalConfigurableDeductions = 0.0;
if ($error === null) {
    try {
        eg_ensure_payroll_deduction_types($pdo);
        $stmt = $pdo->query('SELECT label, default_amount FROM payroll_deduction_types ORDER BY id ASC');
        $payrollDeductionLines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($payrollDeductionLines as $line) {
            $totalConfigurableDeductions += (float) ($line['default_amount'] ?? 0);
        }
    } catch (Throwable $e) {
        $payrollDeductionLines = [];
    }

    try {
        $loanStmt = $pdo->prepare("SELECT amount, advance_date, status FROM cash_advances WHERE employee_id = ? AND status = 'deducted' ORDER BY advance_date, id");
        $loanStmt->execute([$employeeId]);
        $loanRows = $loanStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($loanRows as $lr) {
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
            if ($amt > 0) {
                $cashAdvanceDeduction += $amt;
            }
        }
    } catch (Throwable $e) {
        $cashAdvanceDeduction = 0.0;
    }
}

$totalStatutoryAndOther = $totalConfigurableDeductions + $otherDeductions;
$totalDeductionsAll = $totalStatutoryAndOther + $tardinessAmount + $cashAdvanceDeduction;
$net = $gross - $totalDeductionsAll;
$totalDeductionsLateAttendanceOnly = $tardinessAmount + $cashAdvanceDeduction;
$netWithConfigurableLinesAsZero = $gross - $totalDeductionsLateAttendanceOnly;

$fmtMoney = static function (float $n): string {
    return number_format($n, 2, '.', ',');
};
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
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/payslip-print.css?v=29" />
  </head>
  <body class="bg-light">
    <?php
    $name = $_SESSION['display_name'] ?? 'Employee';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="container-fluid py-4">
      <nav class="eg-employee-nav mb-4">
        <a href="dashboard.php" class="eg-employee-nav-link">
          <i class="bi bi-house-door"></i>
          <span>Dashboard</span>
        </a>
        <a href="payslip.php" class="eg-employee-nav-link active">
          <i class="bi bi-receipt"></i>
          <span>Payslip Archive</span>
        </a>
        <a href="../auth/logout.php" class="eg-employee-nav-link eg-employee-nav-link-danger">
          <i class="bi bi-box-arrow-right"></i>
          <span>Logout</span>
        </a>
      </nav>

      <div class="eg-panel">
        <h5 class="mb-3">My Payslip Archive</h5>
        <p class="text-muted small mb-3">
          Payslip records will appear here once they are loaded from the database.
        </p>

        <form method="get" class="row g-3 align-items-end mb-4">
          <div class="col-12 col-sm-4 col-lg-3">
            <label class="form-label" for="period">Period</label>
            <select id="period" name="period" class="form-select">
              <option value="week"<?= $period === 'week' ? ' selected' : '' ?>>Week</option>
              <option value="month"<?= $period === 'month' ? ' selected' : '' ?>>Month</option>
            </select>
          </div>
          <div class="col-12 col-sm-4 col-lg-3<?= $period === 'month' ? ' d-none' : '' ?>" id="weekFieldWrap">
            <label class="form-label" for="week">Week</label>
            <input type="date" id="week" name="week" class="form-control" value="<?= htmlspecialchars($weekInput, ENT_QUOTES, 'UTF-8') ?>" />
          </div>
          <div class="col-12 col-sm-4 col-lg-3<?= $period === 'month' ? '' : ' d-none' ?>" id="monthFieldWrap">
            <label class="form-label" for="month">Month</label>
            <input type="month" id="month" name="month" class="form-control" value="<?= htmlspecialchars($monthInput, ENT_QUOTES, 'UTF-8') ?>" />
          </div>
          <div class="col-12 col-sm-4 col-lg-3">
            <button type="submit" class="btn btn-primary w-100">Show Payslip</button>
          </div>
        </form>

        <?php if ($error !== null): ?>
          <div class="alert alert-warning"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php else: ?>
          <div class="eg-payslip-print-pair" id="egPayslipPrintPair">
            <div class="eg-payslip-sheet" id="egPayslipSheet">
              <header class="eg-payslip-header">
                <div class="eg-payslip-brand">
                  <div>
                    <div class="eg-payslip-company-name">E-Goes Solutions</div>
                    <div class="eg-payslip-address">Luna Tiradpass, Bello Building, 2nd floor, Digos City</div>
                  </div>
                </div>
                <div class="eg-payslip-title">Payslip</div>
              </header>

              <div class="eg-payslip-grid">
                <div class="eg-payslip-left">
                  <div class="eg-payslip-box">
                    <div class="eg-payslip-box-title">EMPLOYEE INFORMATION</div>
                    <div class="eg-payslip-field">
                      <label for="ps-name">Employee Name:</label>
                      <div id="ps-name" class="eg-payslip-field-value eg-payslip-field-value--name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="eg-payslip-field">
                      <label for="ps-pos">Position:</label>
                      <div id="ps-pos" class="eg-payslip-field-value"><?= htmlspecialchars($positionLabel, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="eg-payslip-field">
                      <label for="ps-code">Employee Code:</label>
                      <div id="ps-code" class="eg-payslip-field-value"><?= htmlspecialchars($employeeCode, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="eg-payslip-field">
                      <label for="ps-period">Pay Period:</label>
                      <div id="ps-period" class="eg-payslip-field-value"><?= htmlspecialchars($payPeriodLabel, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </div>

                  <div class="eg-payslip-box eg-payslip-box--spaced">
                    <div class="eg-payslip-box-title">Attendance Summary</div>
                    <div class="eg-payslip-field" style="grid-template-columns: 1fr auto">
                      <label>Present Days</label>
                      <span class="eg-payslip-num eg-payslip-bold"><?= (int) $presentDays ?></span>
                    </div>
                  </div>

                  <div class="eg-payslip-box eg-payslip-box--spaced">
                    <div class="eg-payslip-box-title">Earnings</div>
                    <table class="eg-payslip-earnings">
                      <thead>
                        <tr>
                          <th>Earnings</th>
                          <th><?= htmlspecialchars($earningsPeriodCol, ENT_QUOTES, 'UTF-8') ?></th>
                          <th class="eg-payslip-num">Amount</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>Basic Salary</td>
                          <td class="eg-payslip-num"><?= $fmtMoney($hoursWorked) ?> hrs</td>
                          <td class="eg-payslip-num"><?= $fmtMoney($gross) ?></td>
                        </tr>
                        <tr>
                          <td><span class="eg-payslip-strike">Allowance</span></td>
                          <td class="eg-payslip-strike">—</td>
                          <td class="eg-payslip-num eg-payslip-strike"><?= $fmtMoney($allowance) ?></td>
                        </tr>
                        <tr>
                          <td><span class="eg-payslip-strike">Overtime Pay</span></td>
                          <td class="eg-payslip-strike">—</td>
                          <td class="eg-payslip-num eg-payslip-strike"><?= $fmtMoney($overtimePay) ?></td>
                        </tr>
                        <tr>
                          <td><span class="eg-payslip-strike">Regular Holiday</span></td>
                          <td class="eg-payslip-num eg-payslip-strike">—</td>
                          <td class="eg-payslip-num eg-payslip-strike"><?= $fmtMoney($regularHoliday) ?></td>
                        </tr>
                        <tr>
                          <td><span class="eg-payslip-strike">Special Holiday</span></td>
                          <td class="eg-payslip-num eg-payslip-strike">—</td>
                          <td class="eg-payslip-num eg-payslip-strike"><?= $fmtMoney($specialHoliday) ?></td>
                        </tr>
                        <tr class="eg-payslip-bold">
                          <td colspan="2">Gross Pay</td>
                          <td class="eg-payslip-num"><?= $fmtMoney($gross) ?></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <div class="eg-payslip-right">
                  <div class="eg-payslip-box eg-payslip-deductions-section">
                    <div class="eg-payslip-box-title">Deductions</div>
                    <table class="eg-payslip-deductions-inner">
                      <tbody>
                        <?php foreach ($payrollDeductionLines as $line): ?>
                          <?php $cfgAmt = $showDeductions ? (float) ($line['default_amount'] ?? 0) : 0.0; ?>
                          <tr>
                            <td><?= htmlspecialchars((string) ($line['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="eg-payslip-num">
                              <span class="eg-payslip-ded-cfg"><?= $fmtMoney($cfgAmt) ?></span>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                        <tr>
                          <td><span class="eg-payslip-strike">Tardiness</span></td>
                          <td class="eg-payslip-num eg-payslip-strike">—</td>
                        </tr>
                        <tr>
                          <td>Late / attendance deductions</td>
                          <td class="eg-payslip-num"><?= $fmtMoney($tardinessAmount) ?></td>
                        </tr>
                        <tr>
                          <td>Cash Advance Deduction</td>
                          <td class="eg-payslip-num"><?= $fmtMoney($cashAdvanceDeduction) ?></td>
                        </tr>
                        <tr>
                          <td>Other Deductions</td>
                          <td class="eg-payslip-num"><?= $fmtMoney($showDeductions ? $otherDeductions : 0.0) ?></td>
                        </tr>
                        <tr class="eg-payslip-bold">
                          <td>Total Deductions</td>
                          <td class="eg-payslip-num"><span class="eg-payslip-ded-table-total"><?= $fmtMoney($totalDeductionsAll) ?></span></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="eg-payslip-net-box">
                    <div class="eg-payslip-net-inner">
                      <div>
                        <div class="eg-payslip-bold">Gross Pay</div>
                        <div class="eg-payslip-num"><?= $fmtMoney($gross) ?></div>
                      </div>
                      <div class="eg-payslip-net-deductions-summary">
                        <div class="eg-payslip-bold">Total Deductions</div>
                        <div class="eg-payslip-num"><span class="eg-payslip-total-ded-amount"><?= $fmtMoney($totalDeductionsAll) ?></span></div>
                      </div>
                    </div>
                    <div class="eg-payslip-net-total">Net Pay: PHP <span class="eg-payslip-net-amount"><?= $fmtMoney($showDeductions ? $net : $netWithConfigurableLinesAsZero) ?></span></div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <script>
      (function () {
        var periodSelect = document.getElementById('period');
        var weekWrap = document.getElementById('weekFieldWrap');
        var monthWrap = document.getElementById('monthFieldWrap');
        function updateFields() {
          if (!periodSelect) return;
          var isMonth = periodSelect.value === 'month';
          weekWrap.classList.toggle('d-none', isMonth);
          monthWrap.classList.toggle('d-none', !isMonth);
        }
        if (periodSelect) {
          periodSelect.addEventListener('change', updateFields);
          updateFields();
        }
      })();
    </script>
  </body>
</html>


