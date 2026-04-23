<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

// --- Migration: Ensure structured columns exist ---
try {
    $existingColumns = $pdo->query("SHOW COLUMNS FROM violation_policies")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('offense_1_type', $existingColumns)) {
        $pdo->exec("ALTER TABLE violation_policies 
            ADD COLUMN offense_1_type VARCHAR(50) DEFAULT 'warning' AFTER offense_1,
            ADD COLUMN offense_1_days INT DEFAULT 0 AFTER offense_1_type,
            ADD COLUMN offense_2_type VARCHAR(50) DEFAULT 'warning' AFTER offense_2,
            ADD COLUMN offense_2_days INT DEFAULT 0 AFTER offense_2_type,
            ADD COLUMN offense_3_type VARCHAR(50) DEFAULT 'warning' AFTER offense_3,
            ADD COLUMN offense_3_days INT DEFAULT 0 AFTER offense_3_type,
            ADD COLUMN offense_4_type VARCHAR(50) DEFAULT 'warning' AFTER offense_4,
            ADD COLUMN offense_4_days INT DEFAULT 0 AFTER offense_4_type
        ");
    }
    if (!in_array('refresh_months', $existingColumns, true)) {
        $pdo->exec("ALTER TABLE violation_policies ADD COLUMN refresh_months INT NOT NULL DEFAULT 0 AFTER violation_name");
    }
} catch (PDOException $e) {
    // Tables might not exist yet; admin/memorandum init will handle basic creation
}

$status = $_SESSION['status'] ?? null;
$message = $_SESSION['message'] ?? null;
unset($_SESSION['status'], $_SESSION['message']);

// --- Handle Add Violation Policy ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_policy') {
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['violation_name'] ?? '');
        $refreshMonths = max(0, (int)($_POST['refresh_months'] ?? 0));
        
        $o1 = trim($_POST['offense_1'] ?? '');
        $o1t = $_POST['offense_1_type'] ?? 'warning';
        $o1d = (int)($_POST['offense_1_days'] ?? 0);

        $o2 = trim($_POST['offense_2'] ?? '');
        $o2t = $_POST['offense_2_type'] ?? 'warning';
        $o2d = (int)($_POST['offense_2_days'] ?? 0);

        $o3 = trim($_POST['offense_3'] ?? '');
        $o3t = $_POST['offense_3_type'] ?? 'warning';
        $o3d = (int)($_POST['offense_3_days'] ?? 0);

        $o4 = trim($_POST['offense_4'] ?? '');
        $o4t = $_POST['offense_4_type'] ?? 'warning';
        $o4d = (int)($_POST['offense_4_days'] ?? 0);

        if ($code === '' || $name === '') {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Code and Violation Name are required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO violation_policies 
                    (code, violation_name, refresh_months, offense_1, offense_1_type, offense_1_days, 
                     offense_2, offense_2_type, offense_2_days, offense_3, offense_3_type, 
                     offense_3_days, offense_4, offense_4_type, offense_4_days) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $name, $refreshMonths, $o1, $o1t, $o1d, $o2, $o2t, $o2d, $o3, $o3t, $o3d, $o4, $o4t, $o4d]);
                $_SESSION['status'] = 'success';
                $_SESSION['message'] = 'Violation policy added successfully.';
            } catch (PDOException $e) {
                $_SESSION['status'] = 'error';
                $_SESSION['message'] = 'Error adding policy: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'update_policy') {
        $policyId = (int)($_POST['id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['violation_name'] ?? '');
        $refreshMonths = max(0, (int)($_POST['refresh_months'] ?? 0));

        $o1 = trim($_POST['offense_1'] ?? '');
        $o1t = $_POST['offense_1_type'] ?? 'warning';
        $o1d = (int)($_POST['offense_1_days'] ?? 0);

        $o2 = trim($_POST['offense_2'] ?? '');
        $o2t = $_POST['offense_2_type'] ?? 'warning';
        $o2d = (int)($_POST['offense_2_days'] ?? 0);

        $o3 = trim($_POST['offense_3'] ?? '');
        $o3t = $_POST['offense_3_type'] ?? 'warning';
        $o3d = (int)($_POST['offense_3_days'] ?? 0);

        $o4 = trim($_POST['offense_4'] ?? '');
        $o4t = $_POST['offense_4_type'] ?? 'warning';
        $o4d = (int)($_POST['offense_4_days'] ?? 0);

        if ($policyId <= 0 || $code === '' || $name === '') {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Policy ID, Code, and Violation Name are required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE violation_policies
                    SET code = ?, violation_name = ?, refresh_months = ?,
                        offense_1 = ?, offense_1_type = ?, offense_1_days = ?,
                        offense_2 = ?, offense_2_type = ?, offense_2_days = ?,
                        offense_3 = ?, offense_3_type = ?, offense_3_days = ?,
                        offense_4 = ?, offense_4_type = ?, offense_4_days = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $name, $refreshMonths, $o1, $o1t, $o1d, $o2, $o2t, $o2d, $o3, $o3t, $o3d, $o4, $o4t, $o4d, $policyId]);
                $_SESSION['status'] = 'success';
                $_SESSION['message'] = 'Violation policy updated.';
            } catch (PDOException $e) {
                $_SESSION['status'] = 'error';
                $_SESSION['message'] = 'Error updating policy: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'delete_policy') {
        $policyId = (int) $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM violation_policies WHERE id = ?");
        $stmt->execute([$policyId]);
        $_SESSION['status'] = 'success';
        $_SESSION['message'] = 'Policy deleted.';
    }
    header('Location: memos.php');
    exit;
}

// --- Fetch Violation Policies ---
$policies = [];
try {
    $policies = $pdo->query("SELECT * FROM violation_policies ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// --- Fetch Employees With Issued Memos ---
$memoEmployees = [];
try {
    $memoEmployees = $pdo->query("
        SELECT 
            u.id AS user_id,
            u.full_name,
            COALESCE(o.name, 'General') AS office_name,
            COUNT(m.id) AS total_memos,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(m.status, ''))) IN ('active', 'unresolved') THEN 1 ELSE 0 END) AS active_memos,
            MAX(m.created_at) AS latest_memo_date
        FROM employee_memos m
        JOIN users u ON m.user_id = u.id
        LEFT JOIN offices o ON u.office_id = o.id
        GROUP BY u.id, u.full_name, o.name
        HAVING COUNT(m.id) > 0
        ORDER BY latest_memo_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$memoEmployeesMain = [];
$memoEmployeesArchive = [];
foreach ($memoEmployees as $employeeMemo) {
    $activeMemos = (int) ($employeeMemo['active_memos'] ?? 0);
    if ($activeMemos > 0) {
        $memoEmployeesMain[] = $employeeMemo;
    } else {
        $memoEmployeesArchive[] = $employeeMemo;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Violation Policies & Memos | E-GOES Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="../assets/css/style.css?v=blue2" />
    <style>
        .consequence-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
            margin-top: 2px;
        }
        .cb-warning { background-color: #fff3cd; color: #856404; }
        .cb-suspension { background-color: #cfe2ff; color: #084298; }
        .cb-termination { background-color: #f8d7da; color: #842029; }
        .memo-signature-line {
            border-bottom: 1px solid #1f2937;
            min-height: 34px;
            width: 260px;
            margin: 20px auto 0;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 4px;
        }
        .memo-signature-block {
            width: 260px;
            margin-left: auto;
            text-align: center;
        }
        .memo-signature-name {
            font-size: 13px;
            font-weight: 700;
        }
        .memo-signature-role {
            font-size: 12px;
            color: #475569;
            margin-top: 4px;
        }
        #printMemoSheet {
            display: none;
        }
        @media print {
            body.printing-memo > *:not(#printMemoSheet) {
                display: none !important;
            }
            body.printing-memo #printMemoSheet {
                display: block !important;
                position: static;
                background: #fff;
                padding: 24px;
                font-family: Arial, Helvetica, sans-serif;
                color: #1f2937;
                line-height: 1.45;
            }
            body.printing-memo #printMemoSheet .print-title {
                font-size: 20px;
                font-weight: 700;
                margin: 0 0 8px;
            }
            body.printing-memo #printMemoSheet .print-brand {
                text-align: center;
                margin-bottom: 14px;
            }
            body.printing-memo #printMemoSheet .print-brand-name {
                font-size: 18px;
                font-weight: 800;
                margin: 0;
                letter-spacing: 0.4px;
            }
            body.printing-memo #printMemoSheet .print-brand-sub {
                font-size: 12px;
                color: #475569;
                margin: 0;
            }
            body.printing-memo #printMemoSheet .print-meta {
                font-size: 12px;
                color: #64748b;
                margin: 0 0 10px;
            }
            body.printing-memo #printMemoSheet .print-letter {
                border: 1px solid #d1d5db;
                border-radius: 6px;
                background: #f8fafc;
                padding: 12px;
                white-space: pre-wrap;
                font-size: 13px;
            }
            body.printing-memo #printMemoSheet .print-sign-wrap {
                width: 260px;
                margin: 24px 0 0 auto;
                text-align: center;
            }
            body.printing-memo #printMemoSheet .print-sign-line {
                border-bottom: 1px solid #1f2937;
                min-height: 34px;
                display: flex;
                align-items: flex-end;
                justify-content: center;
                padding-bottom: 4px;
                font-size: 13px;
                font-weight: 700;
            }
            body.printing-memo #printMemoSheet .print-sign-role {
                font-size: 12px;
                color: #475569;
                margin-top: 4px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>

            <main class="col-12 col-md-9 col-lg-10 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold mb-1">Violation Policies & Memos</h3>
                        <p class="text-muted small mb-0">Dynamic configuration for company rules and disciplinary actions.</p>
                    </div>
                    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addPolicyModal">
                        <i class="bi bi-plus-lg me-1"></i> Add Dynamic Policy
                    </button>
                </div>
                <!-- Global Issued Memos Oversight -->
                <div class="eg-panel p-4 mb-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-journal-text me-2"></i>Global Issued Memos Oversight</h5>
                    <ul class="nav nav-tabs mb-3" id="memoOversightTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="memo-main-tab" data-bs-toggle="tab" data-bs-target="#memo-main-pane" type="button" role="tab" aria-controls="memo-main-pane" aria-selected="true">
                                Main <span class="badge bg-danger ms-1"><?= count($memoEmployeesMain) ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="memo-archive-tab" data-bs-toggle="tab" data-bs-target="#memo-archive-pane" type="button" role="tab" aria-controls="memo-archive-pane" aria-selected="false">
                                Archive <span class="badge bg-secondary ms-1"><?= count($memoEmployeesArchive) ?></span>
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" id="memoOversightTabsContent">
                        <div class="tab-pane fade show active" id="memo-main-pane" role="tabpanel" aria-labelledby="memo-main-tab" tabindex="0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Office</th>
                                            <th>Total Issued Memos</th>
                                            <th>Active / Unresolved</th>
                                            <th>Latest Memorandum Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($memoEmployeesMain)): ?>
                                            <tr><td colspan="5" class="text-center py-4 text-muted">No employees with active or unresolved memorandums.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($memoEmployeesMain as $employeeMemo): ?>
                                                <tr>
                                                    <td>
                                                        <a href="#" class="fw-bold text-decoration-none js-employee-memo-history-btn"
                                                           data-bs-toggle="modal"
                                                           data-bs-target="#employeeMemoHistoryModal"
                                                           data-user-id="<?= (int)$employeeMemo['user_id'] ?>"
                                                           data-employee-name="<?= htmlspecialchars((string)$employeeMemo['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                           title="View employee memorandums">
                                                            <?= htmlspecialchars($employeeMemo['full_name']) ?>
                                                        </a>
                                                    </td>
                                                    <td><span class="badge bg-secondary opacity-75"><?= htmlspecialchars($employeeMemo['office_name']) ?></span></td>
                                                    <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= (int)$employeeMemo['total_memos'] ?></span></td>
                                                    <td><span class="badge bg-danger"><?= (int)$employeeMemo['active_memos'] ?></span></td>
                                                    <td class="small text-muted">
                                                        <?= !empty($employeeMemo['latest_memo_date']) ? date('M j, Y', strtotime($employeeMemo['latest_memo_date'])) : '—' ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="memo-archive-pane" role="tabpanel" aria-labelledby="memo-archive-tab" tabindex="0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Office</th>
                                            <th>Total Issued Memos</th>
                                            <th>Active / Unresolved</th>
                                            <th>Latest Memorandum Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($memoEmployeesArchive)): ?>
                                            <tr><td colspan="5" class="text-center py-4 text-muted">No archived employees yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($memoEmployeesArchive as $employeeMemo): ?>
                                                <tr>
                                                    <td>
                                                        <a href="#" class="fw-bold text-decoration-none js-employee-memo-history-btn"
                                                           data-bs-toggle="modal"
                                                           data-bs-target="#employeeMemoHistoryModal"
                                                           data-user-id="<?= (int)$employeeMemo['user_id'] ?>"
                                                           data-employee-name="<?= htmlspecialchars((string)$employeeMemo['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                           title="View employee memorandums">
                                                            <?= htmlspecialchars($employeeMemo['full_name']) ?>
                                                        </a>
                                                    </td>
                                                    <td><span class="badge bg-secondary opacity-75"><?= htmlspecialchars($employeeMemo['office_name']) ?></span></td>
                                                    <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= (int)$employeeMemo['total_memos'] ?></span></td>
                                                    <td><span class="badge bg-success"><?= (int)$employeeMemo['active_memos'] ?></span></td>
                                                    <td class="small text-muted">
                                                        <?= !empty($employeeMemo['latest_memo_date']) ? date('M j, Y', strtotime($employeeMemo['latest_memo_date'])) : '—' ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                

                <?php if ($status && $message): ?>
                    <div class="alert alert-<?= $status === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Violation Policies Table -->
                <div class="eg-panel p-4 mb-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-shield-exclamation me-2"></i>Active Violation Policies</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Violation Name</th>
                                    <th>Refresh</th>
                                    <th>1st Offense</th>
                                    <th>2nd Offense</th>
                                    <th>3rd Offense</th>
                                    <th>4th Offense</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($policies)): ?>
                                    <tr><td colspan="8" class="text-center py-4 text-muted">No policies found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($policies as $p): ?>
                                        <tr>
                                            <td class="fw-bold text-primary"><?= htmlspecialchars($p['code']) ?></td>
                                            <td><?= htmlspecialchars($p['violation_name']) ?></td>
                                            <td>
                                                <?php $rm = (int)($p['refresh_months'] ?? 0); ?>
                                                <span class="small <?= $rm > 0 ? 'text-primary' : 'text-muted' ?>">
                                                    <?= $rm > 0 ? ($rm . ' month' . ($rm > 1 ? 's' : '')) : 'No reset' ?>
                                                </span>
                                            </td>
                                            <?php for($i=1; $i<=4; $i++): 
                                                $desc = trim((string)($p["offense_$i"] ?? ''));
                                                $type = $p["offense_{$i}_type"] ?? 'warning';
                                                $days = (int)($p["offense_{$i}_days"] ?? 0);
                                            ?>
                                                <td>
                                                    <?php if ($desc !== ''): ?>
                                                        <div class="small fw-semibold"><?= htmlspecialchars($desc) ?></div>
                                                        <div class="consequence-badge cb-<?= $type ?>">
                                                            <?= ucfirst($type) ?><?= ($type === 'suspension' && $days > 0) ? " ({$days}d)" : "" ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-muted small">—</div>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>

                                            <td class="text-end">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary me-1 js-edit-policy-btn"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editPolicyModal"
                                                    data-policy-id="<?= (int)$p['id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this policy?');">
                                                    <input type="hidden" name="action" value="delete_policy">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                
            </main>
        </div>
    </div>

    <!-- Add Dynamic Policy Modal -->
    <div class="modal" id="addPolicyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0">
                        <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus me-2"></i>Configure Dynamic Violation</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_policy">
                        <div class="row g-4 mb-4 pb-4 border-bottom">
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Policy Code</label>
                                <input type="text" name="code" class="form-control" placeholder="e.g. A-02" required>
                                <div class="form-text mt-1">Unique alphanumeric code for this rule.</div>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label fw-bold small">Violation Name</label>
                                <input type="text" name="violation_name" class="form-control" placeholder="e.g. Unauthorized Use of Company Resources" required>
                                <div class="form-text mt-1">A clear title describing the violation.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small">Refresh Duration (months)</label>
                                <input type="number" name="refresh_months" class="form-control" min="0" value="0" />
                                <div class="form-text mt-1">Set 0 for no reset. Example: 3 means offense count resets after 3 months.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <?php for($i=1; $i<=4; $i++): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light border-0 shadow-sm">
                                        <div class="card-body">
                                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2"><?= $i ?><?= ($i==1?'st':($i==2?'nd':($i==3?'rd':'th'))) ?> Offense Configuration</h6>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Consequence Label</label>
                                                <input type="text" name="offense_<?= $i ?>" class="form-control" placeholder="e.g. <?= $i == 1 ? 'Verbal Warning' : ($i == 4 ? 'Termination' : 'Written Warning') ?>">
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-7">
                                                    <label class="form-label small fw-semibold">Action Type</label>
                                                    <select name="offense_<?= $i ?>_type" class="form-select action-selector">
                                                        <option value="warning" <?= $i < 3 ? 'selected' : '' ?>>Warning</option>
                                                        <option value="suspension">Suspension</option>
                                                        <option value="termination" <?= $i == 4 ? 'selected' : '' ?>>Termination</option>
                                                    </select>
                                                </div>
                                                <div class="col-5">
                                                    <label class="form-label small fw-semibold">Days (if Susp.)</label>
                                                    <input type="number" name="offense_<?= $i ?>_days" class="form-control" min="0" value="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary border-0" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Create Policy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reusable Edit Policy Modal -->
    <div class="modal" id="editPolicyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST">
                    <div class="modal-header bg-primary text-white border-0">
                        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Policy</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_policy">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="row g-4 mb-4 pb-4 border-bottom">
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Policy Code</label>
                                <input type="text" name="code" id="edit_code" class="form-control" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold small">Violation Name</label>
                                <input type="text" name="violation_name" id="edit_violation_name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small">Refresh Duration (months)</label>
                                <input type="number" name="refresh_months" id="edit_refresh_months" class="form-control" min="0" value="0" />
                            </div>
                        </div>
                        <div class="row g-3">
                            <?php for($i=1; $i<=4; $i++): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2"><?= $i ?><?= ($i==1?'st':($i==2?'nd':($i==3?'rd':'th'))) ?> Offense Configuration</h6>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Consequence Label</label>
                                                <input type="text" name="offense_<?= $i ?>" id="edit_offense_<?= $i ?>" class="form-control">
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-7">
                                                    <label class="form-label small fw-semibold">Action Type</label>
                                                    <select name="offense_<?= $i ?>_type" id="edit_offense_<?= $i ?>_type" class="form-select">
                                                        <option value="warning">Warning</option>
                                                        <option value="suspension">Suspension</option>
                                                        <option value="termination">Termination</option>
                                                    </select>
                                                </div>
                                                <div class="col-5">
                                                    <label class="form-label small fw-semibold">Days (if Susp.)</label>
                                                    <input type="number" name="offense_<?= $i ?>_days" id="edit_offense_<?= $i ?>_days" class="form-control" min="0" value="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary border-0" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="employeeMemoHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="employeeMemoHistoryTitle">
                        <i class="bi bi-person-lines-fill me-2"></i>Issued Memorandums
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="table-responsive border rounded">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Office</th>
                                    <th>Issued Memo</th>
                                    <th>Offense</th>
                                    <th>Consequence</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody id="employeeMemoHistoryBody">
                                <tr><td colspan="6" class="text-center py-4 text-muted">No memorandums found.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="issuedMemoDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="issuedMemoDetailTitle">Issued Memorandum</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 small text-muted" id="issuedMemoDetailMeta"></p>
                    <p class="mb-2"><strong>Consequence:</strong> <span id="issuedMemoDetailConsequence"></span></p>
                    <hr />
                    <div class="small border rounded bg-light p-3" style="white-space: pre-wrap;" id="issuedMemoDetailLetter">No letter content available for this memorandum.</div>

                    <div class="memo-signature-block">
                        <div class="memo-signature-line">
                            <div class="memo-signature-name" id="issuedMemoDetailEmployee"></div>
                        </div>
                        <div class="memo-signature-role" id="issuedMemoDetailRole">Employee</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printIssuedMemoBtn">
                        <i class="bi bi-printer me-1"></i> Print Memo
                    </button>
                </div>
            </div>
        </div>
    </div>
    <section id="printMemoSheet" aria-hidden="true"></section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const policyMap = <?= json_encode(array_reduce($policies, function($carry, $p) {
            $id = (int)($p['id'] ?? 0);
            if ($id > 0) {
                $carry[$id] = [
                    'id' => $id,
                    'code' => (string)($p['code'] ?? ''),
                    'violation_name' => (string)($p['violation_name'] ?? ''),
                    'refresh_months' => (int)($p['refresh_months'] ?? 0),
                    'offense_1' => (string)($p['offense_1'] ?? ''),
                    'offense_1_type' => (string)($p['offense_1_type'] ?? 'warning'),
                    'offense_1_days' => (int)($p['offense_1_days'] ?? 0),
                    'offense_2' => (string)($p['offense_2'] ?? ''),
                    'offense_2_type' => (string)($p['offense_2_type'] ?? 'warning'),
                    'offense_2_days' => (int)($p['offense_2_days'] ?? 0),
                    'offense_3' => (string)($p['offense_3'] ?? ''),
                    'offense_3_type' => (string)($p['offense_3_type'] ?? 'warning'),
                    'offense_3_days' => (int)($p['offense_3_days'] ?? 0),
                    'offense_4' => (string)($p['offense_4'] ?? ''),
                    'offense_4_type' => (string)($p['offense_4_type'] ?? 'warning'),
                    'offense_4_days' => (int)($p['offense_4_days'] ?? 0),
                ];
            }
            return $carry;
        }, []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const memoHistoryCacheByUser = Object.create(null);

        const editButtons = document.querySelectorAll('.js-edit-policy-btn');
        editButtons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            const policyId = Number(btn.getAttribute('data-policy-id') || '0');
            const policy = policyMap[policyId] || {};
            const setVal = (id, val) => {
              const el = document.getElementById(id);
              if (el) el.value = val ?? '';
            };
            setVal('edit_id', policy.id);
            setVal('edit_code', policy.code);
            setVal('edit_violation_name', policy.violation_name);
            setVal('edit_refresh_months', policy.refresh_months);
            for (let i = 1; i <= 4; i++) {
              setVal('edit_offense_' + i, policy['offense_' + i]);
              setVal('edit_offense_' + i + '_type', policy['offense_' + i + '_type']);
              setVal('edit_offense_' + i + '_days', policy['offense_' + i + '_days']);
            }
          });
        });

        const historyTitle = document.getElementById('employeeMemoHistoryTitle');
        const historyBody = document.getElementById('employeeMemoHistoryBody');
        const historyButtons = document.querySelectorAll('.js-employee-memo-history-btn');
        const issuedMemoDetailModalEl = document.getElementById('issuedMemoDetailModal');
        const issuedMemoDetailModal = issuedMemoDetailModalEl ? new bootstrap.Modal(issuedMemoDetailModalEl) : null;
        const issuedMemoDetailTitle = document.getElementById('issuedMemoDetailTitle');
        const issuedMemoDetailMeta = document.getElementById('issuedMemoDetailMeta');
        const issuedMemoDetailConsequence = document.getElementById('issuedMemoDetailConsequence');
        const issuedMemoDetailLetter = document.getElementById('issuedMemoDetailLetter');
        const issuedMemoDetailEmployee = document.getElementById('issuedMemoDetailEmployee');
        const printIssuedMemoBtn = document.getElementById('printIssuedMemoBtn');
        const printMemoSheet = document.getElementById('printMemoSheet');
        let selectedHistoryEmployeeName = '';
        const esc = (str) => String(str ?? '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
        const formatDate = (value) => {
          if (!value) return '—';
          const d = new Date(value);
          if (Number.isNaN(d.getTime())) return '—';
          return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        };

        const renderMemoRows = function (userId, rows) {
          if (!historyBody) return;
          if (!rows.length) {
            historyBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No memorandums found for this employee.</td></tr>';
            return;
          }
          historyBody.innerHTML = rows.map(function (memo, memoIndex) {
            const isSuspension = (memo.consequence_type || '') === 'suspension';
            const suspensionHtml = isSuspension
              ? '<div class="text-primary" style="font-size: 0.7rem;"><i class="bi bi-calendar-x"></i> ' + Number(memo.suspension_days || 0) + ' days (' + esc(memo.suspension_start || '') + ' to ' + esc(memo.suspension_end || '') + ')</div>'
              : '';
            const status = String(memo.status || 'resolved');
            const badgeClass = status === 'active' ? 'bg-danger' : 'bg-success';
            const memoLabel = (memo.violation_code || '') + ((memo.violation_name || '') ? (' - ' + memo.violation_name) : '');
            return '<tr>'
              + '<td><span class="badge bg-secondary opacity-75">' + esc(memo.office_name || 'General') + '</span></td>'
              + '<td><button type="button" class="btn btn-link p-0 text-start text-decoration-none js-issued-memo-btn" data-user-id="' + userId + '" data-memo-index="' + memoIndex + '">' + esc(memoLabel) + '</button></td>'
              + '<td>#' + Number(memo.offense_number || 0) + '</td>'
              + '<td><div class="small fw-semibold">' + esc(memo.consequence || '') + '</div>' + suspensionHtml + '</td>'
              + '<td><span class="badge ' + badgeClass + '">' + esc(status.charAt(0).toUpperCase() + status.slice(1)) + '</span></td>'
              + '<td class="small text-muted">' + esc(formatDate(memo.created_at || '')) + '</td>'
              + '</tr>';
          }).join('');
        };

        historyButtons.forEach(function (btn) {
          btn.addEventListener('click', async function () {
            const userId = Number(btn.getAttribute('data-user-id') || '0');
            const employeeName = btn.getAttribute('data-employee-name') || ('Employee #' + userId);
            selectedHistoryEmployeeName = employeeName;
            if (historyTitle) {
              historyTitle.innerHTML = '<i class="bi bi-person-lines-fill me-2"></i>Issued Memorandums: ' + esc(employeeName);
            }
            if (!historyBody) return;

            if (historyBody) {
              historyBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Loading memorandums...</td></tr>';
            }
            if (memoHistoryCacheByUser[userId]) {
              renderMemoRows(userId, memoHistoryCacheByUser[userId]);
              return;
            }
            try {
              const resp = await fetch('memo_history_api.php?user_id=' + encodeURIComponent(String(userId)), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
              });
              const payload = await resp.json();
              const rows = Array.isArray(payload.rows) ? payload.rows : [];
              memoHistoryCacheByUser[userId] = rows;
              renderMemoRows(userId, rows);
            } catch (err) {
              if (historyBody) {
                historyBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Unable to load memorandums right now.</td></tr>';
              }
            }
          });
        });

        if (historyBody) {
          historyBody.addEventListener('click', function (event) {
            const trigger = event.target.closest('.js-issued-memo-btn');
            if (!trigger) return;

            const userId = Number(trigger.getAttribute('data-user-id') || '0');
            const memoIndex = Number(trigger.getAttribute('data-memo-index') || '-1');
            const rows = memoHistoryCacheByUser[userId] || [];
            const memo = rows[memoIndex];
            if (!memo) return;

            const memoTitle = (memo.violation_code || 'Memo') + ((memo.violation_name || '') ? (' - ' + memo.violation_name) : '');
            if (issuedMemoDetailTitle) issuedMemoDetailTitle.textContent = memoTitle;
            if (issuedMemoDetailMeta) {
              issuedMemoDetailMeta.textContent = 'Issued: ' + formatDate(memo.created_at || '') + ' | Offense #' + Number(memo.offense_number || 0);
            }
            if (issuedMemoDetailConsequence) issuedMemoDetailConsequence.textContent = memo.consequence || '—';
            if (issuedMemoDetailLetter) {
              issuedMemoDetailLetter.textContent = (memo.memo_notes || '').trim() !== ''
                ? String(memo.memo_notes)
                : 'No letter content available for this memorandum.';
            }
            if (issuedMemoDetailEmployee) issuedMemoDetailEmployee.textContent = selectedHistoryEmployeeName || ('Employee #' + userId);

            if (issuedMemoDetailModal) issuedMemoDetailModal.show();
          });
        }

        if (printIssuedMemoBtn) {
          printIssuedMemoBtn.addEventListener('click', function () {
            if (!printMemoSheet) return;
            const title = issuedMemoDetailTitle ? issuedMemoDetailTitle.textContent || 'Issued Memorandum' : 'Issued Memorandum';
            const meta = issuedMemoDetailMeta ? issuedMemoDetailMeta.textContent || '' : '';
            const consequence = issuedMemoDetailConsequence ? issuedMemoDetailConsequence.textContent || '—' : '—';
            const letter = issuedMemoDetailLetter ? issuedMemoDetailLetter.textContent || 'No letter content available for this memorandum.' : 'No letter content available for this memorandum.';
            const employee = issuedMemoDetailEmployee ? issuedMemoDetailEmployee.textContent || '' : '';

            printMemoSheet.innerHTML = ''
              + '<div class="print-brand">'
              + '<p class="print-brand-name">E-GOES SOLUTIONS</p>'
              + '<p class="print-brand-sub">Employee Memorandum</p>'
              + '</div>'
              + '<h1 class="print-title">' + esc(title) + '</h1>'
              + '<p class="print-meta">' + esc(meta) + '</p>'
              + '<div><strong>Consequence:</strong> ' + esc(consequence) + '</div>'
              + '<hr>'
              + '<div class="print-letter">' + esc(letter) + '</div>'
              + '<div class="print-sign-wrap"><div class="print-sign-line">' + esc(employee) + '</div><div class="print-sign-role">Employee</div></div>';

            document.body.classList.add('printing-memo');
            window.setTimeout(function () {
              window.print();
            }, 50);
          });
        }

        window.addEventListener('afterprint', function () {
          document.body.classList.remove('printing-memo');
        });
      });
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
