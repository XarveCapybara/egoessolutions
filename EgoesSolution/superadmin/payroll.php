<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';

$offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();

function eg_payroll_monday(DateTimeImmutable $d): DateTimeImmutable
{
    $w = (int) $d->format('N');
    return $d->modify('-' . ($w - 1) . ' days')->setTime(0, 0, 0);
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
$monthValueForInput = $monthPicked->format('Y-m');

if ($period === 'month') {
    $rangeStart = $monthStartDay;
    $rangeEnd = $monthEndDay;
    $periodSummaryLabel = $monthPicked->format('F Y') . ' (full month)';
} else {
    $rangeStart = $weekStartStr;
    $rangeEnd = $weekEndStr;
    $periodSummaryLabel = $weekMonday->format('M j') . ' – ' . $weekSunday->format('M j, Y') . ' (Mon–Sun)';
}

$officeFilter = (int) ($_GET['office_id'] ?? 0);

$hasAttendanceLogs = $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount() > 0;
$hasEmployeesTable = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
$hasAppSettingsTable = $pdo->query("SHOW TABLES LIKE 'app_settings'")->rowCount() > 0;
$hasRateAmountColumn = $hasEmployeesTable && $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;

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

$payrollRows = [];
$totalGross = 0.0;
$totalDeductions = 0.0;
$totalNet = 0.0;

if ($hasAttendanceLogs && $hasEmployeesTable) {
    $hasDeductionAmountColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'deduction_amount'")->rowCount() > 0;
    $hasLateMinutesColumn = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'late_minutes'")->rowCount() > 0;
    $selectDeduction = $hasDeductionAmountColumn ? 'al.deduction_amount' : '0.00 AS deduction_amount';
    $selectLateMinutes = $hasLateMinutesColumn ? 'al.late_minutes' : '0 AS late_minutes';
    $selectRate = $hasRateAmountColumn ? 'e.rate_amount' : 'NULL AS rate_amount';

    $sql = "
        SELECT
            al.employee_id,
            al.time_in,
            al.time_out,
            {$selectDeduction},
            {$selectLateMinutes},
            u.full_name,
            o.name AS office_name,
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
            ];
        }
        $workedMinutes = 0;
        if (!empty($row['time_in']) && !empty($row['time_out'])) {
            $inTs = strtotime((string) $row['time_in']);
            $outTs = strtotime((string) $row['time_out']);
            if ($outTs > $inTs) {
                $workedMinutes = (int) floor(($outTs - $inTs) / 60);
            }
        }
        $byEmployee[$eid]['worked_minutes'] += $workedMinutes;

        $rowDeduction = (float) ($row['deduction_amount'] ?? 0);
        if ($rowDeduction <= 0 && $deductionPerMinute > 0 && $hasLateMinutesColumn) {
            $lateMinutes = (int) ($row['late_minutes'] ?? 0);
            $rowDeduction = max(0, $lateMinutes - 60) * $deductionPerMinute;
        }
        $byEmployee[$eid]['deductions'] += $rowDeduction;
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
        $ded = $agg['deductions'];
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
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Super Admin Payroll</title>
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
          <p class="text-muted mb-4">
            Totals from attendance (time in/out) for the selected <strong>week</strong> (Mon–Sun) or <strong>calendar month</strong>. Rates: per-employee override if &gt; 0, otherwise global default from
            <a href="settings.php">Settings</a>. Deductions: stored amount or late minutes × deduction per minute (same rules as employee payslip).
            <strong>Receipt status</strong> (pending / received) is saved per employee for this period.
          </p>

          <form method="get" action="payroll.php" class="eg-panel p-3 mb-3" id="payrollFilterForm">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <label class="form-label" for="periodSelect">Period</label>
                <select class="form-select" name="period" id="periodSelect" aria-label="Week or month">
                  <option value="week" <?= $period === 'week' ? ' selected' : '' ?>>Week (Mon–Sun)</option>
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
                  <option value="0" <?= $officeFilter === 0 ? ' selected' : '' ?>>All offices</option>
                  <?php foreach ($offices as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= $officeFilter === (int) $o['id'] ? ' selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-3 col-lg-2 d-grid">
                <button type="submit" class="btn btn-primary">Apply</button>
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
                <div class="text-muted small"><?= $isMonthPeriod ? 'Monthly deductions' : 'Weekly deductions' ?></div>
                <div class="fw-bold fs-4"><?= number_format($totalDeductions, 2) ?></div>
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
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Employee</th>
                      <th>Office</th>
                      <th class="text-end">Hourly rate</th>
                      <th class="text-end">Hours</th>
                      <th class="text-end">Gross</th>
                      <th class="text-end">Deductions</th>
                      <th class="text-end">Net</th>
                      <th style="min-width: 11rem;">Receipt</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($payrollRows as $pr): ?>
                      <?php $recv = ($pr['receipt_status'] ?? 'pending') === 'received'; ?>
                      <tr>
                        <td><?= htmlspecialchars($pr['full_name']) ?></td>
                        <td><?= htmlspecialchars($pr['office_name']) ?></td>
                        <td class="text-end"><?= number_format($pr['hourly_rate'], 2) ?></td>
                        <td class="text-end"><?= number_format($pr['hours'], 2) ?></td>
                        <td class="text-end"><?= number_format($pr['gross'], 2) ?></td>
                        <td class="text-end"><?= number_format($pr['deductions'], 2) ?></td>
                        <td class="text-end fw-semibold"><?= number_format($pr['net'], 2) ?></td>
                        <td>
                          <form method="post" action="save_payroll_receipt.php" class="d-flex flex-wrap align-items-center gap-1">
                            <input type="hidden" name="period_type" value="<?= htmlspecialchars($period) ?>" />
                            <input type="hidden" name="week" value="<?= htmlspecialchars($weekStartStr) ?>" />
                            <input type="hidden" name="month" value="<?= htmlspecialchars($monthValueForInput) ?>" />
                            <input type="hidden" name="office_id" value="<?= (int) $officeFilter ?>" />
                            <input type="hidden" name="employee_id" value="<?= (int) $pr['employee_id'] ?>" />
                            <select name="status" class="form-select form-select-sm" style="width: auto; min-width: 7.5rem;" aria-label="Receipt status for <?= htmlspecialchars($pr['full_name']) ?>">
                              <option value="pending" <?= !$recv ? ' selected' : '' ?>>Pending</option>
                              <option value="received" <?= $recv ? ' selected' : '' ?>>Received</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                          </form>
                        </td>
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
    <script>
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
    </script>
  </body>
</html>
