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
$error = $_SESSION['memo_flash_error'] ?? null;
$message = $_SESSION['memo_flash_message'] ?? null;
unset($_SESSION['memo_flash_error'], $_SESSION['memo_flash_message']);

function eg_detect_consequence_type(string $text): string
{
    $s = strtolower($text);
    if (strpos($s, 'termination') !== false) {
        return 'termination';
    }
    if (strpos($s, 'suspension') !== false) {
        return 'suspension';
    }
    return 'warning';
}

function eg_extract_suspension_days(string $text): int
{
    if (preg_match('/(\d+)\s*day/i', $text, $m)) {
        return max(1, (int) $m[1]);
    }
    return 0;
}

function eg_render_letter_template(string $subjectTemplate, string $bodyTemplate, string $employeeName, string $incidentDate): string
{
    $name = trim($employeeName) !== '' ? $employeeName : 'Employee';

    $dateOut = $incidentDate;
    if ($incidentDate !== '') {
        $tsDate = strtotime($incidentDate);
        if ($tsDate !== false) {
            $dateOut = date('F j, Y', $tsDate);
        }
    }
    if ($dateOut === '') {
        $dateOut = date('F j, Y');
    }

    $tokens = [
        '[Employee Name]' => $name,
        '[Date]' => $dateOut,
        '[Time]' => '',
    ];

    $subjectRaw = trim($subjectTemplate);
    $bodyRaw = trim($bodyTemplate);

    // Convert escaped newlines from DB (\n, \r\n) into real line breaks.
    $subjectRaw = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $subjectRaw);
    $bodyRaw = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $bodyRaw);

    $subject = strtr($subjectRaw, $tokens);
    $body = strtr($bodyRaw, $tokens);
    if ($subject === '' && $body === '') {
        return '';
    }

    return ($subject !== '' ? $subject : 'Subject: Memorandum Notice') . "\n\n" . ($body !== '' ? $body : '');
}

function eg_memo_redirect_self(): void
{
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = 'memorandum.php' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target);
    exit;
}

try {
    try {
        $existingColumns = $pdo->query("SHOW COLUMNS FROM violation_policies")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('refresh_months', $existingColumns, true)) {
            $pdo->exec("ALTER TABLE violation_policies ADD COLUMN refresh_months INT NOT NULL DEFAULT 0 AFTER violation_name");
        }
    } catch (Throwable $e) {
        // Table may not exist yet; created below.
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS violation_policies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            violation_name VARCHAR(255) NOT NULL,
            refresh_months INT NOT NULL DEFAULT 0,
            offense_1 VARCHAR(255) NOT NULL,
            offense_1_type VARCHAR(50) DEFAULT "warning",
            offense_1_days INT DEFAULT 0,
            offense_2 VARCHAR(255) NOT NULL,
            offense_2_type VARCHAR(50) DEFAULT "warning",
            offense_2_days INT DEFAULT 0,
            offense_3 VARCHAR(255) NOT NULL,
            offense_3_type VARCHAR(50) DEFAULT "warning",
            offense_3_days INT DEFAULT 0,
            offense_4 VARCHAR(255) NOT NULL,
            offense_4_type VARCHAR(50) DEFAULT "warning",
            offense_4_days INT DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS employee_memos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            employee_id INT NULL,
            office_id INT NOT NULL,
            issued_by INT NOT NULL,
            violation_code VARCHAR(50) NOT NULL,
            violation_name VARCHAR(255) NOT NULL,
            offense_number INT NOT NULL,
            consequence VARCHAR(255) NOT NULL,
            consequence_type VARCHAR(30) NOT NULL,
            suspension_days INT NULL,
            suspension_start DATE NULL,
            suspension_end DATE NULL,
            memo_notes TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT "active",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee_memos_user (user_id),
            INDEX idx_employee_memos_office (office_id),
            INDEX idx_employee_memos_violation (violation_code),
            INDEX idx_employee_memos_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    try {
        $idxRows = $pdo->query("SHOW INDEX FROM employee_memos")->fetchAll(PDO::FETCH_ASSOC);
        $hasOfficeCreatedIdx = false;
        foreach ($idxRows as $idx) {
            if (($idx['Key_name'] ?? '') === 'idx_employee_memos_office_created') {
                $hasOfficeCreatedIdx = true;
                break;
            }
        }
        if (!$hasOfficeCreatedIdx) {
            $pdo->exec('CREATE INDEX idx_employee_memos_office_created ON employee_memos (office_id, created_at, id)');
        }
    } catch (Throwable $e) {
        // Non-fatal optimization.
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS violation_letter_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            subject_template VARCHAR(255) NOT NULL,
            body_template TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $seedStmt = $pdo->prepare(
        'INSERT INTO violation_policies (code, violation_name, refresh_months, offense_1, offense_1_type, offense_2, offense_2_type, offense_3, offense_3_type, offense_4, offense_4_type, offense_4_days)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           violation_name = VALUES(violation_name),
           refresh_months = VALUES(refresh_months),
           offense_1 = VALUES(offense_1),
           offense_1_type = VALUES(offense_1_type),
           offense_2 = VALUES(offense_2),
           offense_2_type = VALUES(offense_2_type),
           offense_3 = VALUES(offense_3),
           offense_3_type = VALUES(offense_3_type),
           offense_4 = VALUES(offense_4),
           offense_4_type = VALUES(offense_4_type),
           offense_4_days = VALUES(offense_4_days)'
    );
    $seedStmt->execute([
        'A-01',
        'Late login / late arrival',
        3,
        'Verbal warning', 'warning',
        'Written warning', 'warning',
        'Final warning', 'warning',
        'Suspension up to 3 days', 'suspension', 3
    ]);

} catch (Throwable $e) {
    $error = 'Unable to initialize memorandum records.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'issue_memo') {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $violationCode = trim((string) ($_POST['violation_code'] ?? ''));
        $memoNotes = '';
        $manualSuspDays = (int) ($_POST['suspension_days'] ?? 0);
        $incidentDate = trim((string) ($_POST['incident_date'] ?? ''));

        if ($officeId <= 0) {
            $error = 'Your account has no office assignment.';
        } elseif ($targetUserId <= 0) {
            $error = 'Please select an employee.';
        } elseif ($violationCode === '') {
            $error = 'Please select a violation code.';
        } else {
            try {
                $employeeStmt = $pdo->prepare(
                    "SELECT u.id AS user_id, e.id AS employee_id, u.full_name
                     FROM users u
                     JOIN employees e ON e.user_id = u.id
                     WHERE u.id = ? AND u.office_id = ? AND u.role = 'employee' AND u.is_active = 1
                     LIMIT 1"
                );
                $employeeStmt->execute([$targetUserId, $officeId]);
                $emp = $employeeStmt->fetch(PDO::FETCH_ASSOC);
                if (!$emp) {
                    $error = 'Selected employee is not valid for your office.';
                } else {
                    $policyStmt = $pdo->prepare('SELECT * FROM violation_policies WHERE code = ? AND is_active = 1 LIMIT 1');
                    $policyStmt->execute([$violationCode]);
                    $policy = $policyStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$policy) {
                        $error = 'Violation policy not found.';
                    } else {
                        $refreshMonths = max(0, (int) ($policy['refresh_months'] ?? 0));
                        if ($refreshMonths > 0) {
                            $cutoff = (new DateTimeImmutable('now'))->modify("-{$refreshMonths} months")->format('Y-m-d H:i:s');
                            $countStmt = $pdo->prepare(
                                'SELECT COUNT(*) FROM employee_memos WHERE user_id = ? AND office_id = ? AND violation_code = ? AND created_at >= ?'
                            );
                            $countStmt->execute([$targetUserId, $officeId, $violationCode, $cutoff]);
                        } else {
                            $countStmt = $pdo->prepare(
                                'SELECT COUNT(*) FROM employee_memos WHERE user_id = ? AND office_id = ? AND violation_code = ?'
                            );
                            $countStmt->execute([$targetUserId, $officeId, $violationCode]);
                        }
                        $priorCount = (int) $countStmt->fetchColumn();
                        $offenseNumber = min(4, $priorCount + 1);
                        $consequenceCol = 'offense_' . $offenseNumber;
                        $consequence = trim((string) ($policy[$consequenceCol] ?? $policy['offense_4']));
                        if ($consequence === '') {
                            $consequence = 'Warning';
                        }
                        
                        // New structured logic from Superadmin
                        $typeCol = "offense_{$offenseNumber}_type";
                        $daysCol = "offense_{$offenseNumber}_days";
                        
                        $consequenceType = $policy[$typeCol] ?? null;
                        if (!$consequenceType) {
                            $consequenceType = eg_detect_consequence_type($consequence);
                        }
                        
                        $suspDays = null;
                        $suspStart = null;
                        $suspEnd = null;
                        $memoStatus = 'resolved';

                        if ($consequenceType === 'suspension') {
                            $definedDays = (int)($policy[$daysCol] ?? 0);
                            $parsedDays = eg_extract_suspension_days($consequence);
                            
                            // Priority: Manual Override > Defined in Policy > Parsed from Text
                            $days = $manualSuspDays > 0 ? $manualSuspDays : ($definedDays > 0 ? $definedDays : $parsedDays);
                            
                            if ($days <= 0) {
                                $days = 1;
                            }
                            $today = new DateTimeImmutable('today');
                            $suspDays = $days;
                            $suspStart = $today->format('Y-m-d');
                            $suspEnd = $today->modify('+' . ($days - 1) . ' day')->format('Y-m-d');
                            $memoStatus = 'active';
                        } elseif ($consequenceType === 'termination') {
                            $memoStatus = 'active';
                        }

                        $templateStmt = $pdo->prepare('SELECT subject_template, body_template FROM violation_letter_templates WHERE code = ? AND is_active = 1 LIMIT 1');
                        $templateStmt->execute([$violationCode]);
                        $template = $templateStmt->fetch(PDO::FETCH_ASSOC);
                        if ($template) {
                            $memoNotes = eg_render_letter_template(
                                (string) ($template['subject_template'] ?? ''),
                                (string) ($template['body_template'] ?? ''),
                                (string) ($emp['full_name'] ?? ''),
                                $incidentDate
                            );
                        }


                        $insertStmt = $pdo->prepare(
                            'INSERT INTO employee_memos (
                                user_id, employee_id, office_id, issued_by, violation_code, violation_name,
                                offense_number, consequence, consequence_type, suspension_days, suspension_start, suspension_end,
                                memo_notes, status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $insertStmt->execute([
                            $targetUserId,
                            (int) ($emp['employee_id'] ?? 0) ?: null,
                            $officeId,
                            $adminUserId > 0 ? $adminUserId : 0,
                            $violationCode,
                            (string) ($policy['violation_name'] ?? ''),
                            $offenseNumber,
                            $consequence,
                            $consequenceType,
                            $suspDays,
                            $suspStart,
                            $suspEnd,
                            $memoNotes !== '' ? $memoNotes : null,
                            $memoStatus,
                        ]);

                        if ($consequenceType === 'termination') {
                            $deactivateStmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role = 'employee'");
                            $deactivateStmt->execute([$targetUserId]);
                            $_SESSION['memo_flash_message'] = 'Termination memo issued and employee login has been disabled.';
                        } else {
                            $_SESSION['memo_flash_message'] = 'Memorandum issued successfully.';
                        }
                        eg_memo_redirect_self();
                    }
                }
            } catch (Throwable $e) {
                $error = 'Unable to issue memorandum.';
            }
        }
    } elseif ($action === 'resolve_memo') {
        $memoId = (int) ($_POST['memo_id'] ?? 0);
        if ($memoId > 0) {
            try {
                $memoLookupStmt = $pdo->prepare('SELECT user_id, consequence_type FROM employee_memos WHERE id = ? AND office_id = ? LIMIT 1');
                $memoLookupStmt->execute([$memoId, $officeId]);
                $memoRow = $memoLookupStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                $resolveStmt = $pdo->prepare('UPDATE employee_memos SET status = "resolved" WHERE id = ? AND office_id = ?');
                $resolveStmt->execute([$memoId, $officeId]);

                $_SESSION['memo_flash_message'] = 'Memo status updated.';
                if ($memoRow && (($memoRow['consequence_type'] ?? '') === 'termination')) {
                    $targetUserId = (int) ($memoRow['user_id'] ?? 0);
                    if ($targetUserId > 0) {
                        $activeTerminationStmt = $pdo->prepare(
                            "SELECT COUNT(*)
                             FROM employee_memos
                             WHERE user_id = ?
                               AND consequence_type = 'termination'
                               AND status = 'active'"
                        );
                        $activeTerminationStmt->execute([$targetUserId]);
                        $activeTerminationCount = (int) $activeTerminationStmt->fetchColumn();

                        if ($activeTerminationCount === 0) {
                            $reactivateStmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ? AND role = 'employee'");
                            $reactivateStmt->execute([$targetUserId]);
                            $_SESSION['memo_flash_message'] = 'Termination resolved and employee login has been re-enabled.';
                        }
                    }
                }
                eg_memo_redirect_self();
            } catch (Throwable $e) {
                $error = 'Unable to update memo status.';
            }
        }
    }
}

$employees = [];
$policies = [];
$memos = [];
$letterTemplates = [];
$memoPage = max(1, (int) ($_GET['memo_page'] ?? 1));
$memoPerPage = 25;
$memoTotal = 0;
$memoTotalPages = 1;
if ($officeId > 0 && $error === null) {
    try {
        $empStmt = $pdo->prepare(
            "SELECT u.id, u.full_name, e.employee_code
             FROM users u
             LEFT JOIN employees e ON e.user_id = u.id
             WHERE u.office_id = ? AND u.role = 'employee' AND u.is_active = 1
             ORDER BY u.full_name"
        );
        $empStmt->execute([$officeId]);
        $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $policyStmt = $pdo->query('SELECT code, violation_name, offense_1, offense_2, offense_3, offense_4 FROM violation_policies WHERE is_active = 1 ORDER BY code');
        $policies = $policyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $tplLoadStmt = $pdo->query('SELECT code, subject_template, body_template FROM violation_letter_templates WHERE is_active = 1');
        foreach (($tplLoadStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $tplRow) {
            $c = strtoupper(trim((string) ($tplRow['code'] ?? '')));
            if ($c !== '') {
                $letterTemplates[$c] = [
                    'subject' => (string) ($tplRow['subject_template'] ?? ''),
                    'body' => (string) ($tplRow['body_template'] ?? ''),
                ];
            }
        }

        $memoCountStmt = $pdo->prepare('SELECT COUNT(*) FROM employee_memos WHERE office_id = ?');
        $memoCountStmt->execute([$officeId]);
        $memoTotal = (int) $memoCountStmt->fetchColumn();
        $memoTotalPages = max(1, (int) ceil($memoTotal / $memoPerPage));
        if ($memoPage > $memoTotalPages) {
            $memoPage = $memoTotalPages;
        }
        $memoOffset = ($memoPage - 1) * $memoPerPage;

        $memoStmt = $pdo->prepare(
            "SELECT m.id, m.user_id, m.employee_id, m.office_id, m.issued_by, m.violation_code, m.violation_name,
                    m.offense_number, m.consequence, m.consequence_type, m.suspension_days, m.suspension_start,
                    m.suspension_end, m.memo_notes, m.status, m.created_at, u.full_name AS employee_name, e.employee_code
             FROM employee_memos m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN employees e ON e.user_id = m.user_id
             WHERE m.office_id = ?
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT $memoPerPage OFFSET $memoOffset"
        );
        $memoStmt->execute([$officeId]);
        $memos = $memoStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $error = 'Unable to load memorandum data.';
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
    <link rel="stylesheet" href="../assets/css/style.css?v=blue1" />
  </head>
  <body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-3 fw-bold">Memorandum Management</h3>
          <p class="text-muted small mb-3">Issue violation memos and disciplinary consequences per offense count.</p>

          <?php if ($message !== null): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
          <?php if ($error !== null): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>

          <div class="eg-panel mb-4">
            <h5 class="mb-3">Issue New Memo</h5>
            <form method="post" class="row g-3">
              <input type="hidden" name="action" value="issue_memo" />
              <div class="col-md-4">
                <label for="user_id" class="form-label">Employee</label>
                <select id="user_id" name="user_id" class="form-select" required>
                  <option value="">Select employee</option>
                  <?php foreach ($employees as $emp): ?>
                    <option
                      value="<?= (int) ($emp['id'] ?? 0) ?>"
                      data-employee-name="<?= htmlspecialchars((string) ($emp['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars((string) ($emp['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                      <?= !empty($emp['employee_code']) ? ' [' . htmlspecialchars((string) $emp['employee_code'], ENT_QUOTES, 'UTF-8') . ']' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label for="violation_code" class="form-label">Violation Policy</label>
                <select id="violation_code" name="violation_code" class="form-select" required>
                  <option value="">Select violation policy</option>
                  <?php foreach ($policies as $policy): ?>
                    <option value="<?= htmlspecialchars((string) ($policy['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars((string) ($policy['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string) ($policy['violation_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label for="suspension_days" class="form-label">Suspension Days (optional override)</label>
                <input type="number" min="0" id="suspension_days" name="suspension_days" class="form-control" placeholder="e.g. 3" />
              </div>
              <div class="col-md-4">
                <label for="incident_date" class="form-label">Incident Date</label>
                <input type="date" id="incident_date" name="incident_date" class="form-control" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" />
              </div>
              <div class="col-12 d-none" id="letterPreviewWrap">
                <label class="form-label mb-1">Letter Preview</label>
                <div class="border rounded bg-light p-3 small" style="white-space: pre-wrap;" id="letterPreview"></div>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Issue Memo</button>
              </div>
            </form>
          </div>

          <div class="eg-panel">
            <h5 class="mb-3">Issued Memorandums</h5>
            <?php if (empty($memos)): ?>
              <p class="text-muted mb-0">No memorandums issued yet.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Employee</th>
                      <th>Violation</th>
                      <th>Offense</th>
                      <th>Consequence</th>
                      <th>Suspension</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($memos as $memo): ?>
                      <?php
                      $status = strtolower((string) ($memo['status'] ?? 'resolved'));
                      $badgeClass = $status === 'active' ? 'bg-danger' : 'bg-secondary';
                      ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= htmlspecialchars((string) ($memo['employee_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small text-muted"><?= htmlspecialchars((string) ($memo['employee_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td>
                          <div class="fw-semibold"><?= htmlspecialchars((string) ($memo['violation_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small text-muted"><?= htmlspecialchars((string) ($memo['violation_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td><?= (int) ($memo['offense_number'] ?? 0) ?></td>
                        <td>
                          <div><?= htmlspecialchars((string) ($memo['consequence'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        </td>
                        <td>
                          <?php if (($memo['consequence_type'] ?? '') === 'suspension'): ?>
                            <?= htmlspecialchars((string) ($memo['suspension_start'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                            to
                            <?= htmlspecialchars((string) ($memo['suspension_end'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                            <div class="small text-muted"><?= (int) ($memo['suspension_days'] ?? 0) ?> day(s)</div>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td>
                          <?php if ($status === 'active'): ?>
                            <form method="post">
                              <input type="hidden" name="action" value="resolve_memo" />
                              <input type="hidden" name="memo_id" value="<?= (int) ($memo['id'] ?? 0) ?>" />
                              <button type="submit" class="btn btn-sm btn-outline-secondary">Mark Resolved</button>
                            </form>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php if ($memoTotalPages > 1): ?>
                <nav class="mt-3">
                  <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $memoPage <= 1 ? 'disabled' : '' ?>">
                      <a class="page-link" href="?memo_page=<?= max(1, $memoPage - 1) ?>">Prev</a>
                    </li>
                    <li class="page-item disabled"><span class="page-link">Page <?= $memoPage ?> of <?= $memoTotalPages ?></span></li>
                    <li class="page-item <?= $memoPage >= $memoTotalPages ? 'disabled' : '' ?>">
                      <a class="page-link" href="?memo_page=<?= min($memoTotalPages, $memoPage + 1) ?>">Next</a>
                    </li>
                  </ul>
                </nav>
              <?php endif; ?>
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
        const violationSelect = document.getElementById('violation_code');
        const employeeSelect = document.getElementById('user_id');
        const incidentDateInput = document.getElementById('incident_date');
        const previewWrap = document.getElementById('letterPreviewWrap');
        const preview = document.getElementById('letterPreview');
        if (!violationSelect || !employeeSelect || !incidentDateInput || !previewWrap || !preview) {
          return;
        }

        function fmtDate(ymd) {
          if (!ymd) return '[Date]';
          const d = new Date(ymd + 'T00:00:00');
          if (Number.isNaN(d.getTime())) return '[Date]';
          return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }

        function selectedEmployeeName() {
          const opt = employeeSelect.options[employeeSelect.selectedIndex];
          const raw = opt ? (opt.getAttribute('data-employee-name') || '') : '';
          return raw.trim() !== '' ? raw.trim() : '[Employee Name]';
        }

        const templateMap = <?= json_encode($letterTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function buildLetter(code) {
          const tpl = templateMap[code];
          if (!tpl) return '';

          const replacements = {
            '[Employee Name]': selectedEmployeeName(),
            '[Date]': fmtDate(incidentDateInput.value),
            '[Time]': ''
          };
          function replaceTokens(text) {
            let out = String(text || '');
            out = out.replace(/\\r\\n|\\n|\\r/g, '\n');
            Object.keys(replacements).forEach(function (token) {
              out = out.split(token).join(replacements[token]);
            });
            return out;
          }

          const subject = replaceTokens(tpl.subject).trim();
          const body = replaceTokens(tpl.body).trim();
          if (!subject && !body) return '';
          return (subject || 'Subject: Memorandum Notice') + '\n\n' + body;
        }

        function refreshLetter() {
          const code = (violationSelect.value || '').toUpperCase();
          const letter = buildLetter(code);
          if (!letter) {
            previewWrap.classList.add('d-none');
            return;
          }

          preview.textContent = letter;
          previewWrap.classList.remove('d-none');
        }

        [violationSelect, employeeSelect, incidentDateInput].forEach(function (el) {
          el.addEventListener('change', refreshLetter);
        });

        refreshLetter();
      })();
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>
