<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';

$offices = [];
try {
    $offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $offices = [];
}

$rawOfficeId = $_GET['office_id'] ?? null;
if ($rawOfficeId === null || $rawOfficeId === '') {
    $officeChoiceMade = false;
    $officeFilter = 0;
} else {
    $officeChoiceMade = true;
    $officeFilter = (int) $rawOfficeId;
}
$employeeFilter = trim((string) ($_GET['employee'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$limitInput = 25;
$pageInput = (int) ($_GET['page'] ?? 1);
if ($pageInput < 1) {
    $pageInput = 1;
}

$today = date('Y-m-d');
if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = $today;
}
if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = $today;
}
if ($dateFrom > $dateTo) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}

$attendanceQuickQuery = function (array $base, string $from, string $to): string {
    $base['date_from'] = $from;
    $base['date_to'] = $to;

    return '?' . http_build_query($base);
};

$quickBase = [
    'employee' => $employeeFilter,
];
if ($officeChoiceMade) {
    $quickBase['office_id'] = $officeFilter;
}
$yesterday = date('Y-m-d', strtotime('-1 day'));
$last7From = date('Y-m-d', strtotime('-6 days'));

$logs = [];
$totalMatchingRows = 0;
$totalPages = 1;
$pageOffset = ($pageInput - 1) * $limitInput;
if ($officeChoiceMade && $pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount()) {
    $baseSql = '
        SELECT
            al.*,
            u.full_name,
            o.name AS office_name
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        JOIN users u ON e.user_id = u.id
        JOIN offices o ON al.office_id = o.id
        WHERE al.log_date BETWEEN ? AND ?
    ';
    $countSql = '
        SELECT COUNT(*)
        FROM attendance_logs al
        JOIN employees e ON al.employee_id = e.id
        JOIN users u ON e.user_id = u.id
        JOIN offices o ON al.office_id = o.id
        WHERE al.log_date BETWEEN ? AND ?
    ';
    $params = [$dateFrom, $dateTo];
    if ($officeFilter > 0) {
        $baseSql .= ' AND al.office_id = ?';
        $countSql .= ' AND al.office_id = ?';
        $params[] = $officeFilter;
    }
    if ($employeeFilter !== '') {
        $baseSql .= ' AND u.full_name LIKE ?';
        $countSql .= ' AND u.full_name LIKE ?';
        $params[] = '%' . $employeeFilter . '%';
    }
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalMatchingRows = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalMatchingRows / $limitInput));
    if ($pageInput > $totalPages) {
        $pageInput = $totalPages;
        $pageOffset = ($pageInput - 1) * $limitInput;
    }

    $sql = $baseSql . ' ORDER BY al.log_date DESC, al.time_in DESC LIMIT ' . $limitInput . ' OFFSET ' . $pageOffset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
}

$paginationQuery = function (int $targetPage) use ($rawOfficeId, $employeeFilter, $dateFrom, $dateTo): string {
    $q = [
        'office_id' => $rawOfficeId,
        'employee' => $employeeFilter,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'page' => max(1, $targetPage),
    ];
    return '?' . http_build_query($q);
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
    <link rel="stylesheet" href="../assets/css/style.css?v=blue1" />
  </head>
  <body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="fw-bold mb-3">All Offices Attendance Records</h3>
          <p class="text-muted small mb-2">Choose <strong>All offices</strong> or a specific office, then Apply. Dates default to <strong>today</strong>.</p>
          <form method="get" class="eg-panel p-3 mb-3">
            <div class="row g-3 align-items-end">
              <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label" for="office_id">Office</label>
                <select class="form-select" id="office_id" name="office_id">
                  <option value="" <?= !$officeChoiceMade ? ' selected' : '' ?>>Select office</option>
                  <option value="0" <?= $officeChoiceMade && $officeFilter === 0 ? ' selected' : '' ?>>All offices</option>
                  <?php foreach ($offices as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= $officeFilter === (int) $o['id'] ? ' selected' : '' ?>>
                      <?= htmlspecialchars((string) $o['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label" for="date_from">Date from</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" />
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label" for="date_to">Date to</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" />
              </div>
              <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label" for="employee">Employee</label>
                <input type="text" class="form-control" id="employee" name="employee" placeholder="Search name" value="<?= htmlspecialchars($employeeFilter, ENT_QUOTES, 'UTF-8') ?>" />
              </div>
              <div class="col-12 col-sm-6 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">Apply filters</button>
              </div>
              <div class="col-12 col-sm-6 col-md-3 d-grid">
                <a class="btn btn-outline-secondary" href="attendance.php" title="Clear to today’s date range">Reset to today</a>
              </div>
              <div class="col-12">
                <span class="text-muted small me-2">Quick:</span>
                <a class="btn btn-sm btn-outline-primary me-1 mb-1" href="<?= htmlspecialchars($attendanceQuickQuery($quickBase, $today, $today), ENT_QUOTES, 'UTF-8') ?>">Today</a>
                <a class="btn btn-sm btn-outline-primary me-1 mb-1" href="<?= htmlspecialchars($attendanceQuickQuery($quickBase, $yesterday, $yesterday), ENT_QUOTES, 'UTF-8') ?>">Yesterday</a>
                <a class="btn btn-sm btn-outline-primary mb-1" href="<?= htmlspecialchars($attendanceQuickQuery($quickBase, $last7From, $today), ENT_QUOTES, 'UTF-8') ?>">Last 7 days</a>
              </div>
            </div>
          </form>
          <div class="table-responsive bg-white rounded-3 shadow-sm p-3" id="attendanceTable">
            <table class="table table-bordered table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Office</th>
                  <th>Employee</th>
                  <th>Date</th>
                  <th>Time In</th>
                  <th>Time Out</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$officeChoiceMade): ?>
                  <tr><td colspan="6" class="text-muted text-center py-4">Select <strong>All offices</strong> or an office, then click <strong>Apply filters</strong> to load records.</td></tr>
                <?php elseif (empty($logs)): ?>
                  <tr><td colspan="6" class="text-muted text-center py-4">No attendance records found for the selected filters.</td></tr>
                <?php else: ?>
                  <?php foreach ($logs as $log): ?>
                    <tr>
                      <td><?= htmlspecialchars($log['office_name']) ?></td>
                      <td><?= htmlspecialchars($log['full_name']) ?></td>
                      <td><?= date('M j, Y', strtotime($log['log_date'])) ?></td>
                      <td><?= $log['time_in'] ? date('h:i A', strtotime($log['time_in'])) : '—' ?></td>
                      <td><?= $log['time_out'] ? date('h:i A', strtotime($log['time_out'])) : '—' ?></td>
                      <td><?= htmlspecialchars($log['status']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
            <?php if ($officeChoiceMade && $totalMatchingRows > 0): ?>
              <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">
                  Showing <?= (int) ($pageOffset + 1) ?>–<?= (int) min($pageOffset + $limitInput, $totalMatchingRows) ?> of <?= (int) $totalMatchingRows ?> rows
                </div>
                <div class="btn-group" role="group" aria-label="Attendance table pagination">
                  <?php if ($pageInput <= 1): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-disabled="true">
                      <i class="bi bi-arrow-left"></i>
                    </button>
                  <?php else: ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($paginationQuery($pageInput - 1), ENT_QUOTES, 'UTF-8') ?>">
                      <i class="bi bi-arrow-left"></i>
                    </a>
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary disabled">
                    Page <?= (int) $pageInput ?> / <?= (int) $totalPages ?>
                  </button>
                  <?php if ($pageInput >= $totalPages): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-disabled="true">
                      <i class="bi bi-arrow-right"></i>
                    </button>
                  <?php else: ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($paginationQuery($pageInput + 1), ENT_QUOTES, 'UTF-8') ?>">
                      <i class="bi bi-arrow-right"></i>
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </main>
      </div>
    </div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>






