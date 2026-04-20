<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$name = $_SESSION['display_name'] ?? 'Admin';
$adminUserId = (int) ($_SESSION['user_id'] ?? 0);
$officeId = (int) ($_SESSION['office_id'] ?? 0);
$error = null;
$message = null;

try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS leave_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            employee_id INT NULL,
            office_id INT NULL,
            leave_type VARCHAR(60) NOT NULL,
            leave_other_specify VARCHAR(150) NULL,
            employment_status VARCHAR(30) NULL,
            campaign VARCHAR(120) NULL,
            supervisor_name VARCHAR(120) NULL,
            filing_date DATE NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            total_days INT NOT NULL DEFAULT 0,
            day_types VARCHAR(60) NULL,
            shift_schedule VARCHAR(80) NULL,
            half_day_option VARCHAR(20) NULL,
            supporting_documents VARCHAR(255) NULL,
            supporting_document_image VARCHAR(255) NULL,
            supporting_other_text VARCHAR(150) NULL,
            coverage_arrangement VARCHAR(80) NULL,
            coverage_other_text VARCHAR(150) NULL,
            covering_employee VARCHAR(120) NULL,
            contact_during_leave VARCHAR(80) NULL,
            reason TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT "pending",
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            admin_notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_leave_requests_user (user_id),
            INDEX idx_leave_requests_status (status),
            INDEX idx_leave_requests_dates (start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $extraColumns = [
        'supporting_document_image' => 'ALTER TABLE leave_requests ADD COLUMN supporting_document_image VARCHAR(255) NULL AFTER supporting_documents',
    ];
    foreach ($extraColumns as $columnName => $sql) {
        $existsStmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE " . $pdo->quote($columnName));
        if ($existsStmt && $existsStmt->rowCount() === 0) {
            $pdo->exec($sql);
        }
    }
} catch (Throwable $e) {
    $error = 'Unable to access leave requests records.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = trim((string) ($_POST['decision'] ?? ''));
    $notes = trim((string) ($_POST['admin_notes'] ?? ''));
    $allowedDecisions = ['approved', 'approved_with_conditions', 'disapproved', 'deferred'];

    if ($officeId <= 0) {
        $error = 'Your account has no assigned office.';
    } elseif ($requestId <= 0) {
        $error = 'Invalid request selected.';
    } elseif (!in_array($decision, $allowedDecisions, true)) {
        $error = 'Please select a valid decision.';
    } else {
        try {
            $checkStmt = $pdo->prepare('SELECT id FROM leave_requests WHERE id = ? AND office_id = ? LIMIT 1');
            $checkStmt->execute([$requestId, $officeId]);
            if (!$checkStmt->fetchColumn()) {
                $error = 'Leave request not found for your office.';
            } else {
                $updateStmt = $pdo->prepare('
                    UPDATE leave_requests
                    SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ? AND office_id = ?
                ');
                $updateStmt->execute([
                    $decision,
                    $notes !== '' ? $notes : null,
                    $adminUserId > 0 ? $adminUserId : null,
                    $requestId,
                    $officeId,
                ]);
                $message = 'Leave request decision saved.';
            }
        } catch (Throwable $e) {
            $error = 'Unable to update leave request decision.';
        }
    }
}

$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$allowedFilter = ['all', 'pending', 'approved', 'approved_with_conditions', 'disapproved', 'deferred'];
if (!in_array($statusFilter, $allowedFilter, true)) {
    $statusFilter = 'all';
}

$requests = [];
if ($officeId > 0 && $error === null) {
    try {
        $sql = "
            SELECT
                lr.*,
                u.full_name AS employee_name,
                e.employee_code
            FROM leave_requests lr
            JOIN users u ON u.id = lr.user_id
            LEFT JOIN employees e ON e.user_id = lr.user_id
            WHERE lr.office_id = ?
        ";
        $params = [$officeId];
        if ($statusFilter !== 'all') {
            $sql .= ' AND lr.status = ?';
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY CASE lr.status
                    WHEN 'pending' THEN 0
                    WHEN 'deferred' THEN 1
                    WHEN 'approved_with_conditions' THEN 2
                    WHEN 'approved' THEN 3
                    WHEN 'disapproved' THEN 4
                    ELSE 5
                  END, lr.created_at DESC, lr.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $error = 'Unable to load leave requests.';
    }
}

function eg_status_badge(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === 'approved') {
        return 'bg-success';
    }
    if ($s === 'approved_with_conditions') {
        return 'bg-info text-dark';
    }
    if ($s === 'disapproved') {
        return 'bg-danger';
    }
    if ($s === 'deferred') {
        return 'bg-secondary';
    }
    return 'bg-warning text-dark';
}

function eg_status_label(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === 'approved_with_conditions') {
        return 'Approved with Conditions';
    }
    if ($s === 'disapproved') {
        return 'Disapproved';
    }
    if ($s === 'deferred') {
        return 'Deferred / Pending Review';
    }
    if ($s === 'approved') {
        return 'Approved';
    }
    return 'Pending';
}
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
        <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
              <h3 class="mb-1 fw-bold">Leave Requests</h3>
              <p class="text-muted small mb-0">Employee requests from your office.</p>
            </div>
            <form method="get" class="d-flex align-items-center gap-2">
              <label for="status" class="small text-muted">Filter:</label>
              <select id="status" name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>All</option>
                <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Pending</option>
                <option value="approved"<?= $statusFilter === 'approved' ? ' selected' : '' ?>>Approved</option>
                <option value="approved_with_conditions"<?= $statusFilter === 'approved_with_conditions' ? ' selected' : '' ?>>Approved w/ Conditions</option>
                <option value="deferred"<?= $statusFilter === 'deferred' ? ' selected' : '' ?>>Deferred</option>
                <option value="disapproved"<?= $statusFilter === 'disapproved' ? ' selected' : '' ?>>Disapproved</option>
              </select>
            </form>
          </div>

          <?php if ($message !== null): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
          <?php if ($error !== null): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <div class="eg-panel">
            <?php if (empty($requests)): ?>
              <p class="text-muted mb-0">No leave requests found for this office.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle table-sm mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Employee</th>
                      <th>Request</th>
                      <th>Details</th>
                      <th>Status</th>
                      <th style="min-width: 280px;">Decision</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($requests as $row): ?>
                      <?php
                      $status = (string) ($row['status'] ?? 'pending');
                      $badgeClass = eg_status_badge($status);
                      $statusLabel = eg_status_label($status);
                      ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= htmlspecialchars((string) ($row['employee_name'] ?? 'Employee'), ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small text-muted">ID: <?= htmlspecialchars((string) ($row['employee_code'] ?? ($row['employee_id'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td>
                          <div class="fw-semibold">
                            <?= htmlspecialchars((string) ($row['leave_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($row['leave_other_specify'])): ?>
                              (<?= htmlspecialchars((string) $row['leave_other_specify'], ENT_QUOTES, 'UTF-8') ?>)
                            <?php endif; ?>
                          </div>
                          <div class="small text-muted">
                            <?= htmlspecialchars((string) ($row['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?> to
                            <?= htmlspecialchars((string) ($row['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                          </div>
                          <div class="small text-muted"><?= (int) ($row['total_days'] ?? 0) ?> day(s)</div>
                        </td>
                        <td>
                          <div class="small"><strong>Reason:</strong> <?= htmlspecialchars((string) ($row['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                          <?php if (!empty($row['supporting_documents'])): ?>
                            <div class="small text-muted"><strong>Docs:</strong> <?= htmlspecialchars((string) $row['supporting_documents'], ENT_QUOTES, 'UTF-8') ?></div>
                          <?php endif; ?>
                          <?php if (!empty($row['supporting_document_image'])): ?>
                            <div class="small"><a href="<?= htmlspecialchars((string) $row['supporting_document_image'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">View attached document image</a></div>
                          <?php endif; ?>
                          <?php if (!empty($row['coverage_arrangement'])): ?>
                            <div class="small text-muted"><strong>Coverage:</strong> <?= htmlspecialchars((string) $row['coverage_arrangement'], ENT_QUOTES, 'UTF-8') ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                          <?php if (!empty($row['admin_notes'])): ?>
                            <div class="small text-muted mt-1"><?= htmlspecialchars((string) $row['admin_notes'], ENT_QUOTES, 'UTF-8') ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <form method="post" class="row g-2">
                            <input type="hidden" name="request_id" value="<?= (int) ($row['id'] ?? 0) ?>" />
                            <div class="col-12">
                              <select name="decision" class="form-select form-select-sm" required>
                                <option value="">Select decision</option>
                                <option value="approved"<?= $status === 'approved' ? ' selected' : '' ?>>Approved</option>
                                <option value="approved_with_conditions"<?= $status === 'approved_with_conditions' ? ' selected' : '' ?>>Approved with Conditions</option>
                                <option value="disapproved"<?= $status === 'disapproved' ? ' selected' : '' ?>>Disapproved</option>
                                <option value="deferred"<?= $status === 'deferred' ? ' selected' : '' ?>>Deferred / Pending</option>
                              </select>
                            </div>
                            <div class="col-12">
                              <textarea name="admin_notes" class="form-control form-control-sm" rows="2" placeholder="Notes / conditions"><?= htmlspecialchars((string) ($row['admin_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="col-12">
                              <button type="submit" class="btn btn-sm btn-primary w-100">Save Decision</button>
                            </div>
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
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>
