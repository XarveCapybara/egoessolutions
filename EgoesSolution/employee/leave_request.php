<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/eg_employee_suspension_guard.php';

$name = $_SESSION['display_name'] ?? 'Employee';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$officeId = (int) ($_SESSION['office_id'] ?? 0);
$todayYmd = date('Y-m-d');

$error = null;
$message = null;
$employeeId = 0;
$employeeCode = '';
$employeeFullName = $name;
$positionLabel = 'Employee';
$defaultSupervisor = '';
$defaultCampaign = '';

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
        'leave_other_specify' => 'ALTER TABLE leave_requests ADD COLUMN leave_other_specify VARCHAR(150) NULL AFTER leave_type',
        'employment_status' => 'ALTER TABLE leave_requests ADD COLUMN employment_status VARCHAR(30) NULL AFTER leave_other_specify',
        'campaign' => 'ALTER TABLE leave_requests ADD COLUMN campaign VARCHAR(120) NULL AFTER employment_status',
        'supervisor_name' => 'ALTER TABLE leave_requests ADD COLUMN supervisor_name VARCHAR(120) NULL AFTER campaign',
        'filing_date' => "ALTER TABLE leave_requests ADD COLUMN filing_date DATE NOT NULL DEFAULT '1970-01-01' AFTER supervisor_name",
        'total_days' => 'ALTER TABLE leave_requests ADD COLUMN total_days INT NOT NULL DEFAULT 0 AFTER end_date',
        'day_types' => 'ALTER TABLE leave_requests ADD COLUMN day_types VARCHAR(60) NULL AFTER total_days',
        'shift_schedule' => 'ALTER TABLE leave_requests ADD COLUMN shift_schedule VARCHAR(80) NULL AFTER day_types',
        'half_day_option' => 'ALTER TABLE leave_requests ADD COLUMN half_day_option VARCHAR(20) NULL AFTER shift_schedule',
        'supporting_documents' => 'ALTER TABLE leave_requests ADD COLUMN supporting_documents VARCHAR(255) NULL AFTER half_day_option',
        'supporting_document_image' => 'ALTER TABLE leave_requests ADD COLUMN supporting_document_image VARCHAR(255) NULL AFTER supporting_documents',
        'supporting_other_text' => 'ALTER TABLE leave_requests ADD COLUMN supporting_other_text VARCHAR(150) NULL AFTER supporting_documents',
        'coverage_arrangement' => 'ALTER TABLE leave_requests ADD COLUMN coverage_arrangement VARCHAR(80) NULL AFTER supporting_other_text',
        'coverage_other_text' => 'ALTER TABLE leave_requests ADD COLUMN coverage_other_text VARCHAR(150) NULL AFTER coverage_arrangement',
        'covering_employee' => 'ALTER TABLE leave_requests ADD COLUMN covering_employee VARCHAR(120) NULL AFTER coverage_other_text',
        'contact_during_leave' => 'ALTER TABLE leave_requests ADD COLUMN contact_during_leave VARCHAR(80) NULL AFTER covering_employee',
    ];
    foreach ($extraColumns as $columnName => $sql) {
        $existsStmt = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE " . $pdo->quote($columnName));
        if ($existsStmt && $existsStmt->rowCount() === 0) {
            $pdo->exec($sql);
        }
    }
} catch (Throwable $e) {
    $error = 'Unable to prepare leave request records right now.';
}

if ($userId > 0) {
    try {
        $hasPositionCol = $pdo->query("SHOW COLUMNS FROM employees LIKE 'position'")->rowCount() > 0;
        $hasCodeCol = $pdo->query("SHOW COLUMNS FROM employees LIKE 'employee_code'")->rowCount() > 0;
        $positionField = $hasPositionCol ? 'e.position' : 'NULL AS position';
        $codeField = $hasCodeCol ? 'e.employee_code' : 'NULL AS employee_code';
        $empStmt = $pdo->prepare("
            SELECT e.id, {$positionField}, {$codeField}, u.full_name, o.name AS office_name
            FROM employees e
            JOIN users u ON u.id = e.user_id
            LEFT JOIN offices o ON o.id = u.office_id
            WHERE e.user_id = ?
            LIMIT 1
        ");
        $empStmt->execute([$userId]);
        $empRow = $empStmt->fetch(PDO::FETCH_ASSOC);
        if ($empRow) {
            $employeeId = (int) ($empRow['id'] ?? 0);
            $employeeCode = trim((string) ($empRow['employee_code'] ?? ''));
            $employeeFullName = trim((string) ($empRow['full_name'] ?? '')) ?: $name;
            $positionLabel = trim((string) ($empRow['position'] ?? '')) ?: 'Employee';
            $defaultCampaign = trim((string) ($empRow['office_name'] ?? ''));
        }

        if ($officeId > 0) {
            $supStmt = $pdo->prepare("
                SELECT full_name
                FROM users
                WHERE office_id = ? AND role = 'admin' AND is_active = 1
                ORDER BY id ASC
                LIMIT 1
            ");
            $supStmt->execute([$officeId]);
            $defaultSupervisor = trim((string) ($supStmt->fetchColumn() ?: ''));
        }
    } catch (Throwable $e) {
        // Keep page usable even if employee profile lookup fails.
    }
}

$leaveType = trim((string) ($_POST['leave_type'] ?? 'Sick Leave'));
$leaveOtherSpecify = trim((string) ($_POST['leave_other_specify'] ?? ''));
$employmentStatus = trim((string) ($_POST['employment_status'] ?? 'Probationary'));
$campaign = trim((string) ($_POST['campaign'] ?? $defaultCampaign));
$supervisorName = trim((string) ($_POST['supervisor_name'] ?? $defaultSupervisor));
$filingDate = trim((string) ($_POST['filing_date'] ?? $todayYmd));
$startDate = trim((string) ($_POST['start_date'] ?? ''));
$endDate = trim((string) ($_POST['end_date'] ?? ''));
$shiftSchedule = trim((string) ($_POST['shift_schedule'] ?? ''));
$halfDayOption = trim((string) ($_POST['half_day_option'] ?? 'No'));
$supportingDocuments = $_POST['supporting_documents'] ?? [];
if (!is_array($supportingDocuments)) {
    $supportingDocuments = [];
}
$supportingOtherText = trim((string) ($_POST['supporting_other_text'] ?? ''));
$supportingDocumentImagePath = '';
$coverageArrangement = trim((string) ($_POST['coverage_arrangement'] ?? 'No coverage needed upon return'));
$coverageOtherText = trim((string) ($_POST['coverage_other_text'] ?? ''));
$coveringEmployee = trim((string) ($_POST['covering_employee'] ?? ''));
$contactDuringLeave = trim((string) ($_POST['contact_during_leave'] ?? ''));
$reason = trim((string) ($_POST['reason'] ?? ''));
$dayTypes = $_POST['day_types'] ?? [];
if (!is_array($dayTypes)) {
    $dayTypes = [];
}

$selectedTotalDays = 0;
if (
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)
    && $startDate <= $endDate
) {
    try {
        $startObj = new DateTimeImmutable($startDate);
        $endObj = new DateTimeImmutable($endDate);
        $selectedTotalDays = (int) $startObj->diff($endObj)->days + 1;
    } catch (Throwable $e) {
        $selectedTotalDays = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    $allowedTypes = [
        'Sick Leave',
        'Vacation Leave',
        'Emergency Leave',
        'Maternity / Paternity Leave',
        'Solo Parent Leave',
        'Bereavement Leave',
        'Other',
    ];
    $allowedEmploymentStatus = ['Probationary', 'Regular'];
    $allowedDayTypes = ['Working Days', 'Rest Days', 'Holidays'];
    $allowedHalfDayOptions = ['Yes - AM', 'Yes - PM', 'No'];
    $allowedSupportingDocs = ['Medical Certificate', 'Death Certificate / Obituary', 'Solo Parent ID', 'None', 'Other'];
    $allowedCoverage = ['No coverage needed upon return', 'Covered by teammate', 'Tasks will be completed', 'Other'];

    $cleanDayTypes = [];
    foreach ($dayTypes as $type) {
        $t = trim((string) $type);
        if (in_array($t, $allowedDayTypes, true) && !in_array($t, $cleanDayTypes, true)) {
            $cleanDayTypes[] = $t;
        }
    }
    $cleanSupportingDocs = [];
    foreach ($supportingDocuments as $doc) {
        $d = trim((string) $doc);
        if (in_array($d, $allowedSupportingDocs, true) && !in_array($d, $cleanSupportingDocs, true)) {
            $cleanSupportingDocs[] = $d;
        }
    }
    if (in_array('None', $cleanSupportingDocs, true) && count($cleanSupportingDocs) > 1) {
        $cleanSupportingDocs = ['None'];
    }

    if ($userId <= 0) {
        $error = 'Session expired. Please login again.';
    } elseif (!in_array($leaveType, $allowedTypes, true)) {
        $error = 'Please select a valid leave type.';
    } elseif ($leaveType === 'Other' && $leaveOtherSpecify === '') {
        $error = 'Please specify the leave type for "Other".';
    } elseif (!in_array($employmentStatus, $allowedEmploymentStatus, true)) {
        $error = 'Please select a valid employment status.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filingDate)) {
        $error = 'Please set a valid filing date.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $error = 'Please select valid leave dates.';
    } elseif ($startDate > $endDate) {
        $error = 'Date of leave to cannot be earlier than date from.';
    } elseif ($selectedTotalDays <= 0) {
        $error = 'Unable to calculate total leave days.';
    } elseif (empty($cleanDayTypes)) {
        $error = 'Please select at least one day type.';
    } elseif (!in_array($halfDayOption, $allowedHalfDayOptions, true)) {
        $error = 'Please select a valid half-day option.';
    } elseif (!in_array($coverageArrangement, $allowedCoverage, true)) {
        $error = 'Please select a valid coverage arrangement.';
    } elseif ($coverageArrangement === 'Other' && $coverageOtherText === '') {
        $error = 'Please specify the coverage arrangement for "Other".';
    } elseif (in_array('Other', $cleanSupportingDocs, true) && $supportingOtherText === '') {
        $error = 'Please specify the supporting document for "Other".';
    } elseif ($reason === '') {
        $error = 'Please provide your reason for leave.';
    } elseif (mb_strlen($reason) > 3000) {
        $error = 'Reason is too long. Keep it under 3000 characters.';
    } else {
        if (isset($_FILES['supporting_document_image']) && is_uploaded_file($_FILES['supporting_document_image']['tmp_name'])) {
            $uploadErr = (int) ($_FILES['supporting_document_image']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadErr === UPLOAD_ERR_OK) {
                $tmpFile = (string) $_FILES['supporting_document_image']['tmp_name'];
                $fileSize = (int) ($_FILES['supporting_document_image']['size'] ?? 0);
                $imgInfo = @getimagesize($tmpFile);
                $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
                if ($imgInfo === false || !isset($allowedTypes[$imgInfo[2]])) {
                    $error = 'Supporting document must be an image (JPG, PNG, WEBP, GIF).';
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $error = 'Supporting document image must be 5MB or less.';
                } else {
                    $ext = $allowedTypes[$imgInfo[2]];
                    $uploadDir = __DIR__ . '/../assets/images/leave_docs';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        $error = 'Unable to create document upload folder.';
                    } else {
                        $filename = 'leave_doc_' . $userId . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
                        $dest = $uploadDir . '/' . $filename;
                        if (!move_uploaded_file($tmpFile, $dest)) {
                            $error = 'Unable to upload supporting document image.';
                        } else {
                            $supportingDocumentImagePath = '../assets/images/leave_docs/' . $filename;
                        }
                    }
                }
            } elseif ($uploadErr !== UPLOAD_ERR_NO_FILE) {
                $error = 'Supporting document image upload failed.';
            }
        }
    }

    if ($error === null) {
        try {
            $insertStmt = $pdo->prepare(
                'INSERT INTO leave_requests (
                    user_id, employee_id, office_id, leave_type, leave_other_specify, employment_status, campaign, supervisor_name,
                    filing_date, start_date, end_date, total_days, day_types, shift_schedule, half_day_option,
                    supporting_documents, supporting_document_image, supporting_other_text, coverage_arrangement, coverage_other_text, covering_employee, contact_during_leave,
                    reason, status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $insertStmt->execute([
                $userId,
                $employeeId > 0 ? $employeeId : null,
                $officeId > 0 ? $officeId : null,
                $leaveType,
                $leaveOtherSpecify !== '' ? $leaveOtherSpecify : null,
                $employmentStatus,
                $campaign !== '' ? $campaign : null,
                $supervisorName !== '' ? $supervisorName : null,
                $filingDate,
                $startDate,
                $endDate,
                $selectedTotalDays,
                implode(', ', $cleanDayTypes),
                $shiftSchedule !== '' ? $shiftSchedule : null,
                $halfDayOption,
                !empty($cleanSupportingDocs) ? implode(', ', $cleanSupportingDocs) : null,
                $supportingDocumentImagePath !== '' ? $supportingDocumentImagePath : null,
                $supportingOtherText !== '' ? $supportingOtherText : null,
                $coverageArrangement,
                $coverageOtherText !== '' ? $coverageOtherText : null,
                $coveringEmployee !== '' ? $coveringEmployee : null,
                $contactDuringLeave !== '' ? $contactDuringLeave : null,
                $reason,
            ]);
            header('Location: leave_request.php?submitted=1');
            exit;
        } catch (Throwable $e) {
            $error = 'Unable to submit your leave request right now. Please try again.';
        }
    }
}

if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
    $message = 'Leave request submitted successfully.';
    $leaveType = 'Sick Leave';
    $leaveOtherSpecify = '';
    $employmentStatus = 'Probationary';
    $campaign = $defaultCampaign;
    $supervisorName = $defaultSupervisor;
    $filingDate = $todayYmd;
    $startDate = '';
    $endDate = '';
    $selectedTotalDays = 0;
    $dayTypes = [];
    $shiftSchedule = '';
    $halfDayOption = 'No';
    $supportingDocuments = [];
    $supportingOtherText = '';
    $coverageArrangement = 'No coverage needed upon return';
    $coverageOtherText = '';
    $coveringEmployee = '';
    $contactDuringLeave = '';
    $reason = '';
}

$requests = [];
if ($userId > 0) {
    try {
        $listStmt = $pdo->prepare(
            'SELECT id, leave_type, leave_other_specify, filing_date, start_date, end_date, total_days, day_types, half_day_option,
                    supporting_documents, supporting_document_image, supporting_other_text, coverage_arrangement, coverage_other_text, covering_employee, contact_during_leave,
                    reason, status, admin_notes, created_at
             FROM leave_requests
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC'
        );
        $listStmt->execute([$userId]);
        $requests = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Fail silently and keep form usable.
    }
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
    <link rel="stylesheet" href="../assets/css/style.css" />
    <style>
      .eg-leave-paper {
        background: #fff;
        border: 1px solid #d6d2e8;
        border-radius: 12px;
        padding: 1rem;
      }
      .eg-leave-section-title {
        margin: 0 0 0.6rem;
        padding: 0.4rem 0.65rem;
        background: #4c3b91;
        color: #fff;
        border-radius: 6px;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
      }
      .eg-leave-field-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #4c3b91;
        margin-bottom: 0.25rem;
      }
      .eg-leave-paper .form-control,
      .eg-leave-paper .form-select {
        border-color: #cfc8e8;
        min-height: 40px;
      }
      .eg-leave-paper .form-control:focus,
      .eg-leave-paper .form-select:focus {
        border-color: #6c5ab2;
        box-shadow: 0 0 0 0.15rem rgba(76, 59, 145, 0.15);
      }
      .eg-leave-choice-grid {
        border: 1px solid #e5e1f2;
        border-radius: 8px;
        padding: 0.7rem;
        background: #fcfbff;
      }
      .eg-leave-paper .form-check-input:checked {
        background-color: #4c3b91;
        border-color: #4c3b91;
      }
      .eg-leave-paper textarea.form-control {
        min-height: 130px;
      }
      .eg-leave-submit {
        background: #4c3b91;
        border-color: #4c3b91;
        font-weight: 600;
      }
      .eg-leave-submit:hover {
        background: #3e3077;
        border-color: #3e3077;
      }
    </style>
  </head>
  <body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_employee.php'; ?>
        <main class="col-12 col-md-9 col-lg-10 py-4">
      <div class="row g-3">
        

        <div class="col-12">
          <div class="eg-panel">
            <h5 class="mb-3">My Leave Requests</h5>
            <?php if (empty($requests)): ?>
              <p class="text-muted mb-0">No leave requests yet.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Filed</th>
                      <th>Type / Duration</th>
                      <th>Date Range</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($requests as $row): ?>
                      <?php
                      $status = strtolower((string) ($row['status'] ?? 'pending'));
                      $badgeClass = 'bg-secondary';
                      if ($status === 'approved') {
                          $badgeClass = 'bg-success';
                      } elseif ($status === 'approved_with_conditions') {
                          $badgeClass = 'bg-info text-dark';
                      } elseif ($status === 'disapproved' || $status === 'rejected') {
                          $badgeClass = 'bg-danger';
                      } elseif ($status === 'deferred') {
                          $badgeClass = 'bg-secondary';
                      } elseif ($status === 'pending') {
                          $badgeClass = 'bg-warning text-dark';
                      }
                      $statusLabel = 'Pending';
                      if ($status === 'approved_with_conditions') {
                          $statusLabel = 'Approved with Conditions';
                      } elseif ($status === 'disapproved') {
                          $statusLabel = 'Disapproved';
                      } elseif ($status === 'rejected') {
                          $statusLabel = 'Rejected';
                      } elseif ($status === 'deferred') {
                          $statusLabel = 'Deferred / Pending Review';
                      } elseif ($status === 'approved') {
                          $statusLabel = 'Approved';
                      }
                      ?>
                      <tr>
                        <td class="small text-muted"><?= htmlspecialchars(date('M j, Y h:i A', strtotime((string) $row['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                          <div class="fw-semibold">
                            <?= htmlspecialchars((string) ($row['leave_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($row['leave_other_specify'])): ?>
                              (<?= htmlspecialchars((string) $row['leave_other_specify'], ENT_QUOTES, 'UTF-8') ?>)
                            <?php endif; ?>
                          </div>
                          <div class="small text-muted"><?= htmlspecialchars((string) ($row['total_days'] ?? 0), ENT_QUOTES, 'UTF-8') ?> day(s) • <?= htmlspecialchars((string) ($row['half_day_option'] ?? 'No'), ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small text-muted text-truncate" style="max-width: 260px;" title="<?= htmlspecialchars((string) ($row['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($row['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                          </div>
                        </td>
                        <td class="small">
                          <?= htmlspecialchars(date('M j, Y', strtotime((string) $row['start_date'])), ENT_QUOTES, 'UTF-8') ?>
                          -
                          <?= htmlspecialchars(date('M j, Y', strtotime((string) $row['end_date'])), ENT_QUOTES, 'UTF-8') ?>
                          <div class="text-muted">Filed: <?= htmlspecialchars(date('M j, Y', strtotime((string) ($row['filing_date'] ?? $row['created_at']))), ENT_QUOTES, 'UTF-8') ?></div>
                          <?php if (!empty($row['day_types'])): ?>
                            <div class="text-muted">Type of Day(s): <?= htmlspecialchars((string) $row['day_types'], ENT_QUOTES, 'UTF-8') ?></div>
                          <?php endif; ?>
                          <?php if (!empty($row['supporting_document_image'])): ?>
                            <div class="mt-1">
                              <a href="<?= htmlspecialchars((string) $row['supporting_document_image'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">View attached document</a>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                          <?php if (!empty($row['admin_notes'])): ?>
                            <div class="small text-muted mt-1"><?= htmlspecialchars((string) $row['admin_notes'], ENT_QUOTES, 'UTF-8') ?></div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-12">
          <div class="eg-panel eg-leave-paper">
            <h5 class="mb-2">Leave Request Form</h5>
            <p class="text-muted small mb-3">Form format aligned with your sample leave request sheet.</p>

            <?php if ($message !== null): ?>
              <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($error !== null): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="row g-3">
              <div class="col-12">
                <h6 class="eg-leave-section-title">Section 1 - Employee Information</h6>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label eg-leave-field-label">Employee Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($employeeFullName, ENT_QUOTES, 'UTF-8') ?>" readonly />
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label eg-leave-field-label">Employee ID</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($employeeCode !== '' ? $employeeCode : (string) $employeeId, ENT_QUOTES, 'UTF-8') ?>" readonly />
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label eg-leave-field-label">Position / Role</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($positionLabel, ENT_QUOTES, 'UTF-8') ?>" readonly />
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label eg-leave-field-label" for="employment_status">Employment Status</label>
                <div class="d-flex gap-4">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="employment_status" id="statusProb" value="Probationary"<?= $employmentStatus === 'Probationary' ? ' checked' : '' ?>>
                    <label class="form-check-label" for="statusProb">Probationary</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="employment_status" id="statusReg" value="Regular"<?= $employmentStatus === 'Regular' ? ' checked' : '' ?>>
                    <label class="form-check-label" for="statusReg">Regular</label>
                  </div>
                </div>
              </div>
              <div class="col-12 col-md-4">
                <label for="campaign" class="form-label eg-leave-field-label">Campaign</label>
                <input type="text" id="campaign" name="campaign" class="form-control" value="<?= htmlspecialchars($campaign, ENT_QUOTES, 'UTF-8') ?>" />
              </div>
              <div class="col-12 col-md-4">
                <label for="supervisor_name" class="form-label eg-leave-field-label">Supervisor</label>
                <input type="text" id="supervisor_name" name="supervisor_name" class="form-control" value="<?= htmlspecialchars($supervisorName, ENT_QUOTES, 'UTF-8') ?>" />
              </div>
              <div class="col-12 col-md-4">
                <label for="filing_date" class="form-label eg-leave-field-label">Date of Filing</label>
                <input type="date" id="filing_date" name="filing_date" class="form-control" value="<?= htmlspecialchars($filingDate, ENT_QUOTES, 'UTF-8') ?>" required />
              </div>

              <div class="col-12 mt-2">
                <h6 class="eg-leave-section-title">Section 2 - Type of Leave</h6>
              </div>
              <div class="col-12">
                <div class="row g-2 eg-leave-choice-grid">
                  <?php
                  $types = ['Sick Leave', 'Vacation Leave', 'Emergency Leave', 'Maternity / Paternity Leave', 'Solo Parent Leave', 'Bereavement Leave', 'Other'];
                  foreach ($types as $type):
                    $id = 'leave_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($type));
                  ?>
                    <div class="col-12 col-lg-6">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="leave_type" id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"<?= $leaveType === $type ? ' checked' : '' ?> required>
                        <label class="form-check-label" for="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?></label>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <label for="leave_other_specify" class="form-label eg-leave-field-label">If Other, please specify</label>
                <input type="text" id="leave_other_specify" name="leave_other_specify" class="form-control" value="<?= htmlspecialchars($leaveOtherSpecify, ENT_QUOTES, 'UTF-8') ?>" />
              </div>

              <div class="col-12 mt-2">
                <h6 class="eg-leave-section-title">Section 3 - Leave Duration &amp; Schedule</h6>
              </div>
              <div class="col-12 col-md-4">
                <label for="start_date" class="form-label eg-leave-field-label">Date of Leave From</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>" required />
              </div>
              <div class="col-12 col-md-4">
                <label for="end_date" class="form-label eg-leave-field-label">Date of Leave To</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>" required />
              </div>
              <div class="col-12 col-md-4">
                <label for="total_days" class="form-label eg-leave-field-label">Total No. of Days</label>
                <input type="number" id="total_days" class="form-control" value="<?= (int) $selectedTotalDays ?>" readonly />
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label eg-leave-field-label d-block">Type of Day(s)</label>
                <div class="d-flex flex-wrap gap-3">
                  <?php
                  $dayTypeList = ['Working Days', 'Rest Days', 'Holidays'];
                  foreach ($dayTypeList as $dayType):
                    $dayId = 'day_type_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($dayType));
                    $checked = in_array($dayType, $dayTypes, true);
                  ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="day_types[]" id="<?= htmlspecialchars($dayId, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($dayType, ENT_QUOTES, 'UTF-8') ?>"<?= $checked ? ' checked' : '' ?>>
                      <label class="form-check-label" for="<?= htmlspecialchars($dayId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dayType, ENT_QUOTES, 'UTF-8') ?></label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <label for="shift_schedule" class="form-label eg-leave-field-label">Shift Schedule</label>
                <input type="text" id="shift_schedule" name="shift_schedule" class="form-control" placeholder="Example: 9:00 PM - 6:00 AM" value="<?= htmlspecialchars($shiftSchedule, ENT_QUOTES, 'UTF-8') ?>" />
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label eg-leave-field-label d-block">Half Day?</label>
                <?php
                $halfDayChoices = ['Yes - AM', 'Yes - PM', 'No'];
                foreach ($halfDayChoices as $choice):
                  $halfId = 'half_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($choice));
                ?>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="half_day_option" id="<?= htmlspecialchars($halfId, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?>"<?= $halfDayOption === $choice ? ' checked' : '' ?> required>
                    <label class="form-check-label" for="<?= htmlspecialchars($halfId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($choice, ENT_QUOTES, 'UTF-8') ?></label>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="col-12 mt-2">
                <h6 class="eg-leave-section-title">Section 4 - Reason for Leave</h6>
                <textarea
                  id="reason"
                  name="reason"
                  class="form-control"
                  rows="4"
                  maxlength="3000"
                  placeholder="State your reason in detail."
                  required
                ><?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?></textarea>
              </div>
              <div class="col-12 mt-2">
                <h6 class="eg-leave-section-title">Section 5 - Supporting Documents</h6>
                <div class="eg-leave-choice-grid">
                  <?php
                  $docOptions = ['Medical Certificate', 'Death Certificate / Obituary', 'Solo Parent ID', 'None', 'Other'];
                  foreach ($docOptions as $docOpt):
                    $docId = 'doc_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($docOpt));
                    $docChecked = in_array($docOpt, $supportingDocuments, true);
                  ?>
                    <div class="form-check form-check-inline mb-2">
                      <input class="form-check-input" type="checkbox" name="supporting_documents[]" id="<?= htmlspecialchars($docId, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($docOpt, ENT_QUOTES, 'UTF-8') ?>"<?= $docChecked ? ' checked' : '' ?>>
                      <label class="form-check-label" for="<?= htmlspecialchars($docId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($docOpt, ENT_QUOTES, 'UTF-8') ?></label>
                    </div>
                  <?php endforeach; ?>
                  <div class="mt-2">
                    <label for="supporting_other_text" class="form-label eg-leave-field-label">If Other, please specify</label>
                    <input type="text" id="supporting_other_text" name="supporting_other_text" class="form-control" value="<?= htmlspecialchars($supportingOtherText, ENT_QUOTES, 'UTF-8') ?>" />
                  </div>
                  <div class="mt-2">
                    <label for="supporting_document_image" class="form-label eg-leave-field-label">Attach Supporting Document Image</label>
                    <input type="file" id="supporting_document_image" name="supporting_document_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" />
                    <div class="small text-muted mt-1">Accepted: JPG, PNG, WEBP, GIF (max 5MB)</div>
                  </div>
                </div>
              </div>

              <div class="col-12 mt-2">
                <h6 class="eg-leave-section-title">Section 6 - Work Arrangement During Absence</h6>
              </div>
              <div class="col-12">
                <label class="form-label eg-leave-field-label d-block">Coverage Arrangement</label>
                <?php
                $coverageOptions = ['No coverage needed upon return', 'Covered by teammate', 'Tasks will be completed', 'Other'];
                foreach ($coverageOptions as $covOpt):
                  $covId = 'coverage_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($covOpt));
                ?>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="coverage_arrangement" id="<?= htmlspecialchars($covId, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($covOpt, ENT_QUOTES, 'UTF-8') ?>"<?= $coverageArrangement === $covOpt ? ' checked' : '' ?> required>
                    <label class="form-check-label" for="<?= htmlspecialchars($covId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($covOpt, ENT_QUOTES, 'UTF-8') ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="col-12 col-md-4">
                <label for="coverage_other_text" class="form-label eg-leave-field-label">Coverage Other (Specify)</label>
                <input type="text" id="coverage_other_text" name="coverage_other_text" class="form-control" value="<?= htmlspecialchars($coverageOtherText, ENT_QUOTES, 'UTF-8') ?>" />
              </div>
              <div class="col-12 col-md-4">
                <label for="covering_employee" class="form-label eg-leave-field-label">Covering Employee</label>
                <input type="text" id="covering_employee" name="covering_employee" class="form-control" value="<?= htmlspecialchars($coveringEmployee, ENT_QUOTES, 'UTF-8') ?>" />
              </div>
              <div class="col-12 col-md-4">
                <label for="contact_during_leave" class="form-label eg-leave-field-label">Contact No. During Leave</label>
                <input type="text" id="contact_during_leave" name="contact_during_leave" class="form-control" value="<?= htmlspecialchars($contactDuringLeave, ENT_QUOTES, 'UTF-8') ?>" />
              </div>

              <div class="col-12 mt-2">
                <h6 class="eg-leave-section-title">Section 7 - Approval Status</h6>
                <?php
                $decisionLabel = 'Deferred / Pending Review';
                if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
                    $decisionLabel = 'Deferred / Pending Review';
                }
                ?>
                <div class="eg-leave-choice-grid">
                  <div class="small mb-2">
                    Decision:
                    <strong><?= htmlspecialchars($decisionLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                  </div>
                  <div class="small text-muted">Approval decision and notes will be updated by HR/Admin after review.</div>
                </div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary eg-leave-submit">Submit Leave Request</button>
              </div>
            </form>
          </div>
        </div>
      </div></main>
      </div>
    </div>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <script>
      (function () {
        var startInput = document.getElementById('start_date');
        var endInput = document.getElementById('end_date');
        var totalEl = document.getElementById('total_days');
        if (!startInput || !endInput || !totalEl) return;

        function toDate(value) {
          if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
          var date = new Date(value + 'T00:00:00');
          return Number.isNaN(date.getTime()) ? null : date;
        }

        function updateTotalDays() {
          var s = toDate(startInput.value);
          var e = toDate(endInput.value);
          if (!s || !e || e < s) {
            totalEl.value = '0';
            return;
          }
          var msPerDay = 24 * 60 * 60 * 1000;
          var diffDays = Math.floor((e.getTime() - s.getTime()) / msPerDay) + 1;
          totalEl.value = String(diffDays);
        }

        startInput.addEventListener('change', updateTotalDays);
        endInput.addEventListener('change', updateTotalDays);
        updateTotalDays();
      })();
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>
