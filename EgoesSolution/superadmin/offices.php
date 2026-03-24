<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';
$hasTeamLeaderColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'team_leader'")->rowCount() > 0;
$hasTeamLeaderUserIdColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'team_leader_user_id'")->rowCount() > 0;
if (!$hasTeamLeaderUserIdColumn) {
    $pdo->exec("ALTER TABLE offices ADD COLUMN team_leader_user_id INT NULL AFTER team_leader");
}
if ($hasTeamLeaderColumn) {
    $offices = $pdo->query('SELECT id, name, address, team_leader, team_leader_user_id FROM offices ORDER BY name')->fetchAll();
} else {
    $offices = $pdo->query('SELECT id, name, address, NULL AS team_leader, NULL AS team_leader_user_id FROM offices ORDER BY name')->fetchAll();
}
$admins = $pdo->query('SELECT id, full_name FROM users WHERE role = "admin" ORDER BY full_name')->fetchAll();
$assignedTeamLeaderRows = $pdo->query('SELECT team_leader_user_id FROM offices WHERE team_leader_user_id IS NOT NULL')->fetchAll();
$assignedTeamLeaderIds = [];
foreach ($assignedTeamLeaderRows as $row) {
    $assignedTeamLeaderIds[(int) $row['team_leader_user_id']] = true;
}
$officeCreateStatus = $_SESSION['office_create_status'] ?? null;
$officeCreateMessage = $_SESSION['office_create_message'] ?? null;
unset($_SESSION['office_create_status'], $_SESSION['office_create_message']);
$officeEditStatus = $_SESSION['office_edit_status'] ?? null;
$officeEditMessage = $_SESSION['office_edit_message'] ?? null;
unset($_SESSION['office_edit_status'], $_SESSION['office_edit_message']);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Super Admin Offices</title>
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
    <header class="eg-topbar d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <img src="../assets/images/egoes-logo.png?v=3" alt="E-GOES Solutions" class="eg-system-logo" />
      </div>
      <div class="d-flex align-items-center me-3">
        <div class="me-2 fw-bold fs-5">SuperAdmin-<?= htmlspecialchars($name) ?></div>
        <div class="eg-avatar-circle"></div>
      </div>
    </header>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="fw-bold mb-3">Office Management</h3>
          <p class="text-muted small mb-3">Only SuperAdmin can create offices.</p>
          <?php if (!empty($officeCreateMessage)): ?>
            <div class="alert <?= $officeCreateStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2">
              <?= htmlspecialchars($officeCreateMessage) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($officeEditMessage)): ?>
            <div class="alert <?= $officeEditStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2">
              <?= htmlspecialchars($officeEditMessage) ?>
            </div>
          <?php endif; ?>
          <div class="eg-panel p-3 mb-4">
            <h5 class="mb-3">Add New Office</h5>
            <form action="addoffice.php" method="post" class="row g-3">
              <div class="col-md-4"><input class="form-control" name="name" placeholder="Office Name" required /></div>
              <div class="col-md-4"><input class="form-control" name="address" placeholder="Location" required /></div>
              <div class="col-md-3">
                <select class="form-select" name="team_leader_user_id">
                  <option value="">Select Team Leader (optional)</option>
                  <?php foreach ($admins as $admin): ?>
                    <?php $isTaken = !empty($assignedTeamLeaderIds[(int) $admin['id']]); ?>
                    <option value="<?= (int) $admin['id'] ?>" <?= $isTaken ? 'disabled' : '' ?>>
                      <?= htmlspecialchars($admin['full_name']) ?><?= $isTaken ? ' (already team leader)' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-1 d-grid"><button type="submit" class="btn btn-primary">Add</button></div>
            </form>
          </div>
          <div class="eg-panel">
            <h5 class="mb-3">All Offices</h5>
            <?php if (empty($offices)): ?>
              <p class="text-muted small mb-0">No offices yet. Create one above when form is wired to database.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light"><tr><th>Name</th><th>Address</th><th>Team Leader</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($offices as $o): ?>
                      <tr>
                        <td>
                          <a href="office_overview.php?id=<?= (int) $o['id'] ?>" class="text-decoration-none fw-semibold">
                            <?= htmlspecialchars($o['name']) ?>
                          </a>
                        </td>
                        <td><?= htmlspecialchars($o['address'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($o['team_leader'] ?: '—') ?></td>
                        <td class="text-end">
                          <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal"
                            data-bs-target="#editOfficeModal<?= (int) $o['id'] ?>"
                          >
                            Edit
                          </button>
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

    <?php foreach ($offices as $o): ?>
      <div class="modal fade" id="editOfficeModal<?= (int) $o['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Edit Office</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="updateoffice.php" method="post">
              <div class="modal-body">
                <input type="hidden" name="office_id" value="<?= (int) $o['id'] ?>" />
                <div class="mb-3">
                  <label class="form-label">Office Name</label>
                  <input class="form-control" name="name" value="<?= htmlspecialchars($o['name']) ?>" required />
                </div>
                <div class="mb-3">
                  <label class="form-label">Address</label>
                  <input class="form-control" name="address" value="<?= htmlspecialchars($o['address'] ?? '') ?>" required />
                </div>
                <div class="mb-1">
                  <label class="form-label">Team Leader</label>
                  <select class="form-select" name="team_leader_user_id">
                    <option value="">No Team Leader</option>
                    <?php foreach ($admins as $admin): ?>
                      <?php
                        $adminId = (int) $admin['id'];
                        $selectedId = (int) ($o['team_leader_user_id'] ?? 0);
                        $isTaken = !empty($assignedTeamLeaderIds[$adminId]) && $selectedId !== $adminId;
                      ?>
                      <option value="<?= $adminId ?>" <?= $selectedId === $adminId ? 'selected' : '' ?> <?= $isTaken ? 'disabled' : '' ?>>
                        <?= htmlspecialchars($admin['full_name']) ?>
                        <?= $isTaken ? ' (already team leader)' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>






