<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';
$hasPositionCol = $pdo->query("SHOW COLUMNS FROM employees LIKE 'position'")->rowCount() > 0;
$positionSelect = $hasPositionCol ? ', e.position' : '';
$stmt = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.role{$positionSelect}
    FROM users u
    LEFT JOIN employees e ON e.user_id = u.id
    WHERE u.role IN ('employee', 'admin')
    ORDER BY u.full_name
");
$employees = $stmt->fetchAll();
$offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();
$selectedOfficeId = (int) ($_GET['office_id'] ?? 0);
$selectedOfficeName = null;
foreach ($offices as $office) {
    if ((int) $office['id'] === $selectedOfficeId) {
        $selectedOfficeName = $office['name'];
        break;
    }
}
$employeeCreateStatus = $_SESSION['employee_create_status'] ?? null;
$employeeCreateMessage = $_SESSION['employee_create_message'] ?? null;
unset($_SESSION['employee_create_status'], $_SESSION['employee_create_message']);
$teamLeaderCreateStatus = $_SESSION['team_leader_create_status'] ?? null;
$teamLeaderCreateMessage = $_SESSION['team_leader_create_message'] ?? null;
unset($_SESSION['team_leader_create_status'], $_SESSION['team_leader_create_message']);
$passwordUpdateStatus = $_SESSION['password_update_status'] ?? null;
$passwordUpdateMessage = $_SESSION['password_update_message'] ?? null;
unset($_SESSION['password_update_status'], $_SESSION['password_update_message']);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Super Admin - Employees</title>
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
          <h3 class="mb-3 fw-bold">Employees</h3>
          <p class="text-muted mb-4">Create and manage employee login accounts.</p>
          <?php if (!empty($employeeCreateMessage)): ?>
            <div class="alert <?= $employeeCreateStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2">
              <?= htmlspecialchars($employeeCreateMessage) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($teamLeaderCreateMessage)): ?>
            <div class="alert <?= $teamLeaderCreateStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2">
              <?= htmlspecialchars($teamLeaderCreateMessage) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($passwordUpdateMessage)): ?>
            <div class="alert <?= $passwordUpdateStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2">
              <?= htmlspecialchars($passwordUpdateMessage) ?>
            </div>
          <?php endif; ?>
          <div class="eg-panel p-3 mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
              <h5 class="mb-0">Create Account</h5>
              <div class="btn-group" role="group" aria-label="Create account type">
                <button type="button" class="btn btn-outline-primary active" id="showEmployeeForm">Employee</button>
                <button type="button" class="btn btn-outline-primary" id="showTeamLeaderForm">Team Leader</button>
              </div>
            </div>

            <div id="employeeFormPanel">
              <p class="text-muted small mb-3">Only SuperAdmin can create employee accounts.</p>
              <form action="createemployee.php" method="post" class="row g-3">
                <input type="hidden" name="office_id" value="<?= (int) $selectedOfficeId ?>" />
                <div class="col-md-4">
                  <input class="form-control" name="full_name" placeholder="Full Name" required />
                </div>
                <div class="col-md-3">
                  <input type="email" class="form-control" name="email" placeholder="Email" required />
                </div>
                <div class="col-md-3">
                  <input type="password" class="form-control" name="password" placeholder="Password" required minlength="8" />
                </div>
                <div class="col-md-2 d-grid">
                  <button type="submit" class="btn btn-primary">Create Employee</button>
                </div>
                <div class="col-12">
                  <?php if ($selectedOfficeName !== null): ?>
                    <div class="text-muted small">Assigning to office: <strong><?= htmlspecialchars($selectedOfficeName) ?></strong></div>
                  <?php else: ?>
                    <div class="text-muted small">Tip: open this page from an office overview to auto-assign office.</div>
                  <?php endif; ?>
                </div>
              </form>
            </div>

            <div id="teamLeaderFormPanel" class="d-none">
              <p class="text-muted small mb-3">Creates a team leader login with account type <strong>admin</strong>.</p>
              <form action="createteamleader.php" method="post" class="row g-3">
                <input type="hidden" name="office_id" value="<?= (int) $selectedOfficeId ?>" />
                <div class="col-md-4">
                  <input class="form-control" name="full_name" placeholder="Full Name" required />
                </div>
                <div class="col-md-3">
                  <input type="email" class="form-control" name="email" placeholder="Email" required />
                </div>
                <div class="col-md-3">
                  <input type="password" class="form-control" name="password" placeholder="Password" required minlength="8" />
                </div>
                <div class="col-md-2 d-grid">
                  <button type="submit" class="btn btn-primary">Create Team Leader</button>
                </div>
                <div class="col-12">
                  <?php if ($selectedOfficeName !== null): ?>
                    <div class="text-muted small">Assigning to office: <strong><?= htmlspecialchars($selectedOfficeName) ?></strong></div>
                  <?php else: ?>
                    <div class="text-muted small">Tip: open this page from an office overview to auto-assign office.</div>
                  <?php endif; ?>
                </div>
              </form>
            </div>
          </div>
          <div class="row g-3">
            <?php if (empty($employees)): ?>
              <div class="col-12">
                <p class="text-muted">No employees or team leaders yet. Create one above.</p>
              </div>
            <?php else: ?>
              <?php foreach ($employees as $emp): ?>
                <?php
                $roleLabel = (($emp['role'] ?? '') === 'admin') ? 'Team leader' : 'Employee';
                $positionText = ($hasPositionCol && !empty(trim((string) ($emp['position'] ?? '')))) ? trim($emp['position']) : '';
                ?>
                <div class="col-6 col-md-4 col-lg-3">
                  <div class="eg-employee-card position-relative">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary position-absolute top-0 end-0 m-2 d-inline-flex align-items-center justify-content-center"
                      style="width: 32px; height: 32px;"
                      data-bs-toggle="modal"
                      data-bs-target="#changePasswordModal"
                      data-user-id="<?= (int) $emp['id'] ?>"
                      data-user-name="<?= htmlspecialchars($emp['full_name']) ?>"
                      data-user-role="<?= htmlspecialchars($roleLabel) ?>"
                      title="Change Password"
                      aria-label="Change Password"
                    >
                      <i class="bi bi-key"></i>
                    </button>
                    <div class="d-flex align-items-center mb-2">
                      <div class="eg-avatar-circle me-2"></div>
                      <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold"><?= htmlspecialchars($emp['full_name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($emp['email']) ?></div>
                        <div class="mt-1">
                          <span class="badge bg-secondary bg-opacity-25 text-dark small"><?= htmlspecialchars($roleLabel) ?></span>
                          <?php if ($positionText !== ''): ?>
                            <span class="text-muted small ms-1"><?= htmlspecialchars($positionText) ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </main>
      </div>
    </div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="changePasswordModalLabel">Change User Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="update_user_password.php" method="post">
            <div class="modal-body">
              <input type="hidden" name="user_id" id="passwordModalUserId" value="" />
              <div class="mb-2 text-muted small" id="passwordModalTargetText"></div>
              <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required />
                <div class="form-text">Minimum 8 characters.</div>
              </div>
              <div class="mb-0">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required />
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <script>
      const employeeBtn = document.getElementById('showEmployeeForm');
      const teamLeaderBtn = document.getElementById('showTeamLeaderForm');
      const employeePanel = document.getElementById('employeeFormPanel');
      const teamLeaderPanel = document.getElementById('teamLeaderFormPanel');

      employeeBtn.addEventListener('click', function () {
        employeeBtn.classList.add('active');
        teamLeaderBtn.classList.remove('active');
        employeePanel.classList.remove('d-none');
        teamLeaderPanel.classList.add('d-none');
      });

      teamLeaderBtn.addEventListener('click', function () {
        teamLeaderBtn.classList.add('active');
        employeeBtn.classList.remove('active');
        teamLeaderPanel.classList.remove('d-none');
        employeePanel.classList.add('d-none');
      });

      const changePasswordModal = document.getElementById('changePasswordModal');
      if (changePasswordModal) {
        changePasswordModal.addEventListener('show.bs.modal', function (event) {
          const btn = event.relatedTarget;
          if (!btn) return;
          const userId = btn.getAttribute('data-user-id') || '';
          const userName = btn.getAttribute('data-user-name') || 'User';
          const userRole = btn.getAttribute('data-user-role') || '';
          const userIdInput = document.getElementById('passwordModalUserId');
          const targetText = document.getElementById('passwordModalTargetText');
          const newPasswordInput = document.getElementById('new_password');
          const confirmPasswordInput = document.getElementById('confirm_password');
          if (userIdInput) userIdInput.value = userId;
          if (targetText) targetText.textContent = 'Updating password for: ' + userName + (userRole ? ' (' + userRole + ')' : '');
          if (newPasswordInput) newPasswordInput.value = '';
          if (confirmPasswordInput) confirmPasswordInput.value = '';
        });
      }
    </script>
  </body>
</html>