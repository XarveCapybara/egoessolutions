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

// --- Fetch Issued Memos ---
$issuedMemos = [];
try {
    $issuedMemos = $pdo->query("
        SELECT m.*, u.full_name, o.name AS office_name
        FROM employee_memos m
        JOIN users u ON m.user_id = u.id
        LEFT JOIN offices o ON m.office_id = o.id
        ORDER BY m.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

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

                <!-- Global Issued Memos Oversight -->
                <div class="eg-panel p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-journal-text me-2"></i>Global Issued Memos Oversight</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Office</th>
                                    <th>Violation</th>
                                    <th>Offense</th>
                                    <th>Consequence</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($issuedMemos)): ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted">No issued memos found across any office.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($issuedMemos as $m): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($m['full_name']) ?></div>
                                            </td>
                                            <td><span class="badge bg-secondary opacity-75"><?= htmlspecialchars($m['office_name'] ?? 'General') ?></span></td>
                                            <td><?= htmlspecialchars($m['violation_code']) ?></td>
                                            <td>#<?= (int)$m['offense_number'] ?></td>
                                            <td>
                                                <div class="small fw-semibold"><?= htmlspecialchars($m['consequence']) ?></div>
                                                <?php if (($m['consequence_type'] ?? '') === 'suspension'): ?>
                                                    <div class="text-primary" style="font-size: 0.7rem;">
                                                        <i class="bi bi-calendar-x"></i> <?= (int)$m['suspension_days'] ?> days (<?= $m['suspension_start'] ?> to <?= $m['suspension_end'] ?>)
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= $m['status'] === 'active' ? 'bg-danger' : 'bg-success' ?>">
                                                    <?= ucfirst($m['status']) ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?= date('M j, Y', strtotime($m['created_at'])) ?></td>
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
      });
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
