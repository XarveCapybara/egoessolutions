<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: payroll.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function eg_payroll_monday_sr(DateTimeImmutable $d): DateTimeImmutable
{
    $w = (int) $d->format('N');
    return $d->modify('-' . ($w - 1) . ' days')->setTime(0, 0, 0);
}

$employeeId = (int) ($_POST['employee_id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? ''));
if (!in_array($status, ['pending', 'received'], true)) {
    $status = 'pending';
}

$period = trim((string) ($_POST['period_type'] ?? 'week'));
if ($period !== 'month') {
    $period = 'week';
}

$weekInput = trim((string) ($_POST['week'] ?? ''));
if ($weekInput !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekInput)) {
    $picked = DateTimeImmutable::createFromFormat('Y-m-d', $weekInput) ?: new DateTimeImmutable('today');
} else {
    $picked = new DateTimeImmutable('today');
}
$weekMonday = eg_payroll_monday_sr($picked);
$weekSunday = $weekMonday->modify('+6 days');
$weekStartStr = $weekMonday->format('Y-m-d');
$weekEndStr = $weekSunday->format('Y-m-d');

$monthInput = trim((string) ($_POST['month'] ?? ''));
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

$officeId = (int) ($_POST['office_id'] ?? 0);

if ($employeeId <= 0) {
    header('Location: payroll.php');
    exit;
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

    $chk = $pdo->prepare('SELECT id FROM employees WHERE id = ? LIMIT 1');
    $chk->execute([$employeeId]);
    if (!$chk->fetch()) {
        header('Location: payroll.php');
        exit;
    }

    $upsert = $pdo->prepare('
        INSERT INTO payroll_receipts (employee_id, period_type, period_start, period_end, status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status)
    ');
    $upsert->execute([$employeeId, $period, $rangeStart, $rangeEnd, $status]);
} catch (PDOException $e) {
    // ignore; redirect back
}

$q = [
    'period' => $period,
    'office_id' => $officeId,
    'week' => $weekStartStr,
    'month' => $monthPicked->format('Y-m'),
];
header('Location: payroll.php?' . http_build_query($q));
exit;
