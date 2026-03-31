<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payroll_deduction_types.php';

function eg_payroll_monday(DateTimeImmutable $d): DateTimeImmutable
{
    $w = (int) $d->format('N');
    return $d->modify('-' . ($w - 1) . ' days')->setTime(0, 0, 0);
}

function eg_payslip_period_display(string $period, DateTimeImmutable $weekMonday, DateTimeImmutable $weekSunday, DateTimeImmutable $monthPicked): string
{
    if ($period === 'month') {
        return $monthPicked->format('F Y') . ' (full month)';
    }
    $m1 = $weekMonday->format('n');
    $m2 = $weekSunday->format('n');
    $y1 = $weekMonday->format('Y');
    $y2 = $weekSunday->format('Y');
    if ($m1 === $m2 && $y1 === $y2) {
        return $weekMonday->format('F j') . '-' . $weekSunday->format('j, Y');
    }
    return $weekMonday->format('M j') . ' – ' . $weekSunday->format('M j, Y');
}

$employeeId = (int) ($_GET['employee_id'] ?? 0);
if ($employeeId <= 0) {
    http_response_code(400);
    echo 'Missing or invalid employee.';
    exit;
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
$weekSunday = $weekMonday->modify('+6 days');
$weekStartStr = $weekMonday->format('Y-m-d');
$weekEndStr = $weekSunday->format('Y-m-d');

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
} else {
    $rangeStart = $weekStartStr;
    $rangeEnd = $weekEndStr;
}

$officeFilter = (int) ($_GET['office_id'] ?? 0);
$payPeriodLabel = eg_payslip_period_display($period, $weekMonday, $weekSunday, $monthPicked);
$earningsPeriodCol = $period === 'month' ? 'Monthly' : 'Daily / Weekly';

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
$fullName = '';
$positionLabel = '';
$employeeCode = '';

if (!$hasAttendanceLogs || !$hasEmployeesTable) {
    $error = 'Attendance or employee data is not available.';
} else {
    $posField = $hasPositionCol ? 'e.position' : 'NULL AS position';
    $rateField = $hasRateAmountColumn ? 'e.rate_amount' : 'NULL AS rate_amount';
    $codeField = $hasEmployeeCodeCol ? 'e.employee_code' : 'NULL AS employee_code';
    $empStmt = $pdo->prepare("
        SELECT e.id, e.id AS employee_id, {$codeField}, {$rateField}, u.full_name, u.role, {$posField}
        FROM employees e
        JOIN users u ON e.user_id = u.id
        WHERE e.id = ?
        LIMIT 1
    ");
    $empStmt->execute([$employeeId]);
    $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
    if (!$empRow) {
        $error = 'Employee not found.';
    } else {
        $fullName = (string) ($empRow['full_name'] ?? '');
        $codeRaw = trim((string) ($empRow['employee_code'] ?? ''));
        $employeeCode = $codeRaw !== '' ? $codeRaw : (string) (int) ($empRow['employee_id'] ?? $employeeId);
        $pos = trim((string) ($empRow['position'] ?? ''));
        if ($pos !== '') {
            $positionLabel = $pos;
        } else {
            $positionLabel = (($empRow['role'] ?? '') === 'admin') ? 'Team Leader' : 'Employee';
        }

        $hasDeductionAmountColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;
        $hasLateMinutesColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
        $selectDeduction = $hasDeductionAmountColumn ? 'al.deduction_amount' : '0.00 AS deduction_amount';
        $selectLateMinutes = $hasLateMinutesColumn ? 'al.late_minutes' : '0 AS late_minutes';

        $sql = "
            SELECT
                al.log_date,
                al.time_in,
                al.time_out,
                {$selectDeduction},
                {$selectLateMinutes},
                o.name AS office_name
            FROM attendance_logs al
            JOIN offices o ON al.office_id = o.id
            WHERE al.employee_id = ?
              AND al.log_date BETWEEN ? AND ?
        ";
        $params = [$employeeId, $rangeStart, $rangeEnd];
        if ($officeFilter > 0) {
            $sql .= ' AND al.office_id = ?';
            $params[] = $officeFilter;
        }
        $sql .= ' ORDER BY al.log_date, al.id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logRows = $stmt->fetchAll();
        if (empty($logRows)) {
            $error = 'No attendance for this employee in the selected period and filters.';
        } else {
            $officeName = (string) ($logRows[0]['office_name'] ?? '');
            $workedMinutes = 0;
            $deductionSum = 0.0;
            $datesPresent = [];
            $ra = $empRow['rate_amount'] ?? null;
            if ($ra !== null && $ra !== '') {
                $c = (float) $ra;
                if ($c > 0) {
                    $hourlyRate = $c;
                }
            }
            if ($hourlyRate <= 0 && $hasAppSettingsTable) {
                $hourlyRate = $defaultHourly;
            }
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
$regularHoliday = 0.0;
$specialHoliday = 0.0;
$allowance = 0.0;
$overtimePay = 0.0;

$payrollDeductionLines = [];
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
}

$totalStatutoryAndOther = $totalConfigurableDeductions + $otherDeductions;
$totalDeductionsAll = $totalStatutoryAndOther + $tardinessAmount;
$net = $gross - $totalDeductionsAll;

/** Total deductions if settings lines + “Other” show as 0 but late/attendance still applies */
$totalDeductionsLateAttendanceOnly = $tardinessAmount;
/** Net pay for that same payslip view (gross minus late/attendance only; configurable & other treated as 0) */
$netWithConfigurableLinesAsZero = $gross - $tardinessAmount;

$fmtMoney = static function (float $n): string {
    return number_format($n, 2, '.', ',');
};

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payslip — <?= htmlspecialchars($fullName !== '' ? $fullName : 'Employee', ENT_QUOTES, 'UTF-8') ?></title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" href="../assets/css/payslip-print.css?v=20" />
  </head>
  <body class="eg-payslip-body">
    <div class="eg-payslip-toolbar d-flex flex-wrap align-items-center gap-2">
      <button type="button" class="btn btn-dark" onclick="window.print()">Print / Save as PDF</button>
      <a href="payroll.php?<?= htmlspecialchars(http_build_query([
          'period' => $period,
          'week' => $weekStartStr,
          'month' => $monthPicked->format('Y-m'),
          'office_id' => $officeFilter,
      ]), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary">Back to payroll</a>
      <?php if ($error === null): ?>
        <div class="form-check ms-md-2 mb-0">
          <input class="form-check-input" type="checkbox" id="psShowDeductions" checked aria-describedby="psShowDeductionsHelp" />
          <label class="form-check-label" for="psShowDeductions">Show deductions on payslip</label>
        </div>
        <span id="psShowDeductionsHelp" class="text-muted small d-none d-md-inline">Uncheck to show 0.00 on payroll lines (SSS, etc.); late/attendance deductions still apply.</span>
      <?php endif; ?>
    </div>

    <?php if ($error !== null): ?>
      <div class="eg-payslip-sheet">
        <p class="eg-payslip-error-msg"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    <?php else: ?>
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
                    <?php $cfgAmt = (float) ($line['default_amount'] ?? 0); ?>
                    <tr>
                      <td><?= htmlspecialchars((string) ($line['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                      <td class="eg-payslip-num">
                        <span class="eg-payslip-ded-cfg" data-full="<?= htmlspecialchars($fmtMoney($cfgAmt), ENT_QUOTES, 'UTF-8') ?>"><?= $fmtMoney($cfgAmt) ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr>
                    <td><span class="eg-payslip-strike">Tardiness</span></td>
                    <td class="eg-payslip-num eg-payslip-strike">—</td>
                  </tr>
                  <tr class="eg-payslip-ded-late-row">
                    <td>Late / attendance deductions</td>
                    <td class="eg-payslip-num"><?= $fmtMoney($tardinessAmount) ?></td>
                  </tr>
                  <tr>
                    <td>Other Deductions</td>
                    <td class="eg-payslip-num">
                      <span class="eg-payslip-ded-other" data-full="<?= htmlspecialchars($fmtMoney($otherDeductions), ENT_QUOTES, 'UTF-8') ?>"><?= $fmtMoney($otherDeductions) ?></span>
                    </td>
                  </tr>
                  <tr class="eg-payslip-bold">
                    <td>Total Deductions</td>
                    <td class="eg-payslip-num"><span id="egPayslipDedTableTotal" class="eg-payslip-ded-table-total"><?= $fmtMoney($totalDeductionsAll) ?></span></td>
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
                  <div class="eg-payslip-num"><span id="egPayslipTotalDedAmount" class="eg-payslip-total-ded-amount"><?= $fmtMoney($totalDeductionsAll) ?></span></div>
                </div>
              </div>
              <div class="eg-payslip-net-total">Net Pay: PHP <span id="egPayslipNetAmount" class="eg-payslip-net-amount"><?= $fmtMoney($net) ?></span></div>
            </div>
          </div>
        </div>

        <footer class="eg-payslip-footer">
          <div class="eg-payslip-sign-row">
            <div class="eg-payslip-sign-block">
              <div>Prepared by</div>
              <div class="eg-payslip-sign-line"></div>
              <div>HR Officer</div>
              <div style="margin-top: 8px">Date: _______________</div>
            </div>
            <div class="eg-payslip-sign-block">
              <div>Approved by</div>
              <div class="eg-payslip-sign-line"></div>
              <div>Management</div>
              <div style="margin-top: 8px">Date: _______________</div>
            </div>
          </div>
          <div class="eg-payslip-ack">
            <h4>EMPLOYEE ACKNOWLEDGEMENT</h4>
            <p>
              I acknowledge that I have received the amount of <strong>PHP <span id="egPayslipAckAmount" class="eg-payslip-ack-amount"><?= $fmtMoney($net) ?></span></strong> stated in this payslip.
            </p>
            <div class="eg-payslip-sign-line"></div>
            <div style="text-align: center">Employee Signature / Date</div>
          </div>
        </footer>
      </div>
    <?php endif; ?>

    <?php if ($error === null): ?>
      <script>
        window.__EG_PAYSLIP_AMOUNTS = <?= json_encode(
            [
                'netFull' => $fmtMoney($net),
                'netLateOnly' => $fmtMoney($netWithConfigurableLinesAsZero),
                'totalDedFull' => $fmtMoney($totalDeductionsAll),
                'totalDedLateOnly' => $fmtMoney($totalDeductionsLateAttendanceOnly),
                'zero' => $fmtMoney(0.0),
            ],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        ) ?>;
        (function () {
          var cb = document.getElementById('psShowDeductions');
          var sourceSheet = document.getElementById('egPayslipSheet');
          if (sourceSheet && !document.getElementById('egPayslipSheetCopy')) {
            var clone = sourceSheet.cloneNode(true);
            clone.id = 'egPayslipSheetCopy';
            clone.classList.add('eg-payslip-sheet-copy');
            clone.querySelectorAll('[id]').forEach(function (el) {
              el.removeAttribute('id');
            });
            sourceSheet.insertAdjacentElement('afterend', clone);
          }
          var A = window.__EG_PAYSLIP_AMOUNTS;
          if (!cb || !A) return;
          function sync() {
            var show = cb.checked;
            var z = A.zero;
            document.querySelectorAll('.eg-payslip-ded-cfg').forEach(function (el) {
              el.textContent = show ? (el.getAttribute('data-full') || z) : z;
            });
            document.querySelectorAll('.eg-payslip-ded-other').forEach(function (el) {
              el.textContent = show ? (el.getAttribute('data-full') || z) : z;
            });
            if (show) {
              document.querySelectorAll('.eg-payslip-net-amount').forEach(function (el) { el.textContent = A.netFull; });
              document.querySelectorAll('.eg-payslip-ack-amount').forEach(function (el) { el.textContent = A.netFull; });
              document.querySelectorAll('.eg-payslip-total-ded-amount').forEach(function (el) { el.textContent = A.totalDedFull; });
              document.querySelectorAll('.eg-payslip-ded-table-total').forEach(function (el) { el.textContent = A.totalDedFull; });
            } else {
              document.querySelectorAll('.eg-payslip-net-amount').forEach(function (el) { el.textContent = A.netLateOnly; });
              document.querySelectorAll('.eg-payslip-ack-amount').forEach(function (el) { el.textContent = A.netLateOnly; });
              document.querySelectorAll('.eg-payslip-total-ded-amount').forEach(function (el) { el.textContent = A.totalDedLateOnly; });
              document.querySelectorAll('.eg-payslip-ded-table-total').forEach(function (el) { el.textContent = A.totalDedLateOnly; });
            }
          }
          cb.addEventListener('change', sync);
          sync();
        })();
      </script>
    <?php endif; ?>
  </body>
</html>
