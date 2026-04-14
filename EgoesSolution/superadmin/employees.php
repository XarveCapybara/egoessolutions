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
$selectedOfficeId = (int) ($_GET['office_id'] ?? 0);

$sql = "
    SELECT u.id, u.full_name, u.email, u.role, u.office_id, o.name AS office_name{$positionSelect},
           up.nickname AS profile_nickname, up.avatar AS profile_avatar, u.profile_image
    FROM users u
    LEFT JOIN employees e ON e.user_id = u.id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    LEFT JOIN offices o ON u.office_id = o.id
    WHERE u.role IN ('employee', 'admin')
    ORDER BY u.full_name
";
$employees = $pdo->query($sql)->fetchAll();

$offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();
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
$roleUpdateStatus = $_SESSION['role_update_status'] ?? null;
$roleUpdateMessage = $_SESSION['role_update_message'] ?? null;
unset($_SESSION['role_update_status'], $_SESSION['role_update_message']);
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
          <?php if (!empty($roleUpdateMessage)): ?>
            <div class="alert <?= $roleUpdateStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2">
              <?= htmlspecialchars($roleUpdateMessage) ?>
            </div>
          <?php endif; ?>
          <div id="js-employee-ajax-alert" class="alert d-none py-2" role="alert"></div>
          <div class="eg-panel p-3 mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
              <h5 class="mb-0">Create Account</h5>
              <div class="btn-group" role="group" aria-label="Create account type">
                <button type="button" class="btn btn-outline-primary active" id="showEmployeeForm">Employee</button>
                <button type="button" class="btn btn-outline-primary" id="showTeamLeaderForm">Team Leader</button>
              </div>
            </div>

            <div id="employeeFormPanel">
              
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
              </form>
            </div>

            <div id="teamLeaderFormPanel" class="d-none">
              
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
              
              </form>
            </div>
          </div>

          <div class="eg-panel p-3 mb-4">
            <div class="row g-2 align-items-end flex-wrap">
              <div class="col-12 col-md-4 col-lg-3">
                <label for="js-filter-office" class="form-label small text-muted mb-1">Office</label>
                <select id="js-filter-office" class="form-select form-select-sm" aria-label="Filter by office">
                  <option value="0">All offices</option>
                  <option value="-1" selected>Unassigned</option>
                  <?php foreach ($offices as $o): ?>
                    <option value="<?= (int) $o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                <label for="js-filter-role" class="form-label small text-muted mb-1">Role</label>
                <select id="js-filter-role" class="form-select form-select-sm" aria-label="Filter by role">
                  <option value="">All</option>
                  <option value="employee">Employee</option>
                  <option value="admin">Team leader</option>
                </select>
              </div>
              <div class="col-12 col-md-5 col-lg-4">
                <label for="js-filter-search" class="form-label small text-muted mb-1">Search</label>
                <input
                  type="search"
                  id="js-filter-search"
                  class="form-control form-control-sm"
                  placeholder="Name or email (instant)…"
                  autocomplete="off"
                />
              </div>
              <div class="col-auto d-flex gap-2 align-items-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="js-filter-clear">Clear</button>
              </div>
            </div>
          </div>

          <div class="row g-3" id="employee-grid">
            <?php if (empty($employees)): ?>
              <div class="col-12">
                <p class="text-muted">No employees or team leaders yet. Create one above.</p>
              </div>
            <?php else: ?>
              <div class="col-12 d-none" id="employee-filter-no-matches">
                <p class="text-muted mb-0">No one matches these filters. Try clearing filters.</p>
              </div>
              <?php foreach ($employees as $emp): ?>
                <?php
                $roleLabel = (($emp['role'] ?? '') === 'admin') ? 'Team leader' : 'Employee';
                $positionText = ($hasPositionCol && !empty(trim((string) ($emp['position'] ?? '')))) ? trim($emp['position']) : '';
                $displayName = !empty(trim((string) ($emp['profile_nickname'] ?? ''))) ? trim((string) $emp['profile_nickname']) : trim((string) ($emp['full_name'] ?? ''));
                $rawHay = trim($displayName . ' ' . ($emp['email'] ?? ''));
                $searchHay = function_exists('mb_strtolower') ? mb_strtolower($rawHay, 'UTF-8') : strtolower($rawHay);
                $avatarSrc = '';
                if (!empty($emp['profile_image'])) {
                    $avatarSrc = $emp['profile_image'];
                } elseif (!empty($emp['profile_avatar'])) {
                    $avatarSrc = $emp['profile_avatar'];
                }
                ?>
                <div
                  class="col-6 col-md-4 col-lg-3 eg-employee-filter-item"
                  data-user-id="<?= (int) $emp['id'] ?>"
                  data-office-id="<?= (int) ($emp['office_id'] ?? 0) ?>"
                  data-role="<?= htmlspecialchars((string) ($emp['role'] ?? 'employee'), ENT_QUOTES, 'UTF-8') ?>"
                  data-search="<?= htmlspecialchars($searchHay, ENT_QUOTES, 'UTF-8') ?>"
                >
                  <div class="eg-employee-card position-relative">
                    <div class="position-absolute top-0 end-0 m-2 d-flex gap-1">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center justify-content-center"
                        style="width: 32px; height: 32px;"
                        data-bs-toggle="modal"
                        data-bs-target="#changeRoleModal"
                        data-user-id="<?= (int) $emp['id'] ?>"
                        data-user-name="<?= htmlspecialchars($displayName) ?>"
                        data-user-role="<?= htmlspecialchars($emp['role'] ?? 'employee') ?>"
                        title="Change Role"
                        aria-label="Change Role"
                      >
                        <i class="bi bi-person-badge"></i>
                      </button>
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center justify-content-center"
                        style="width: 32px; height: 32px;"
                        data-bs-toggle="modal"
                        data-bs-target="#changePasswordModal"
                        data-user-id="<?= (int) $emp['id'] ?>"
                        data-user-name="<?= htmlspecialchars($displayName) ?>"
                        data-user-role="<?= htmlspecialchars($roleLabel) ?>"
                        title="Change Password"
                        aria-label="Change Password"
                      >
                        <i class="bi bi-key"></i>
                      </button>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                      <div class="eg-avatar-circle me-2 overflow-hidden d-flex align-items-center justify-content-center">
                        <?php if (!empty($avatarSrc)): ?>
                          <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="" width="40" height="40" style="width: 100%; height: 100%; object-fit: cover;" />
                        <?php else: ?>
                          <span class="bi bi-person-fill text-secondary"></span>
                        <?php endif; ?>
                      </div>
                      <div class="flex-grow-1 min-w-0 pe-5">
                        <div class="fw-semibold"><?= htmlspecialchars($displayName) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($emp['email']) ?></div>
                        <div class="mt-1">
                          <span class="badge bg-secondary bg-opacity-25 text-dark small eg-js-role-badge"><?= htmlspecialchars($roleLabel) ?></span>
                          <?php if (!empty($emp['office_name'])): ?>
                            <span class="badge bg-light text-dark border small"><?= htmlspecialchars($emp['office_name']) ?></span>
                          <?php endif; ?>
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

    <div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="changeRoleModalLabel">Change User Role</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="formChangeRole" action="update_user_role.php" method="post">
            <div class="modal-body">
              <input type="hidden" name="user_id" id="roleModalUserId" value="" />
              <div class="mb-2 text-muted small" id="roleModalTargetText"></div>
              <div class="mb-0">
                <label for="roleModalSelect" class="form-label">Role</label>
                <select class="form-select" id="roleModalSelect" name="role" required>
                  <option value="employee">Employee</option>
                  <option value="admin">Team leader (admin)</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" id="formChangeRoleSubmit">Save Role</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="changePasswordModalLabel">Change User Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="formChangePassword" action="update_user_password.php" method="post">
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
              <button type="submit" class="btn btn-primary" id="formChangePasswordSubmit">Update Password</button>
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

      if (employeeBtn && teamLeaderBtn && employeePanel && teamLeaderPanel) {
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
      }

      const changeRoleModal = document.getElementById('changeRoleModal');
      if (changeRoleModal) {
        changeRoleModal.addEventListener('show.bs.modal', function (event) {
          const btn = event.relatedTarget;
          if (!btn) return;
          const userId = btn.getAttribute('data-user-id') || '';
          const userName = btn.getAttribute('data-user-name') || 'User';
          const roleRaw = (btn.getAttribute('data-user-role') || 'employee').toLowerCase();
          const userIdInput = document.getElementById('roleModalUserId');
          const targetText = document.getElementById('roleModalTargetText');
          const roleSelect = document.getElementById('roleModalSelect');
          if (userIdInput) userIdInput.value = userId;
          if (targetText) targetText.textContent = 'Changing role for: ' + userName;
          if (roleSelect) {
            roleSelect.value = roleRaw === 'admin' ? 'admin' : 'employee';
          }
        });
      }

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

      (function () {
        const officeSel = document.getElementById('js-filter-office');
        const roleSel = document.getElementById('js-filter-role');
        const searchInp = document.getElementById('js-filter-search');
        const clearBtn = document.getElementById('js-filter-clear');
        const items = document.querySelectorAll('.eg-employee-filter-item');
        const noMatches = document.getElementById('employee-filter-no-matches');
        if (!officeSel || !roleSel || !searchInp || items.length === 0) return;

        function applyEmployeeFilters() {
          const office = officeSel.value || '0';
          const roleWant = (roleSel.value || '').trim();
          const q = (searchInp.value || '').trim().toLowerCase();
          let visible = 0;
          items.forEach(function (el) {
            const oid = String(el.getAttribute('data-office-id') || '0');
            const roleRaw = (el.getAttribute('data-role') || 'employee').toLowerCase();
            const hay = (el.getAttribute('data-search') || '').toLowerCase();
            let okOffice = false;
            if (office === '0') {
              okOffice = true;
            } else if (office === '-1') {
              okOffice = oid === '0';
            } else {
              okOffice = oid === office;
            }
            const okRole = roleWant === '' || roleRaw === roleWant;
            const okSearch = q === '' || hay.indexOf(q) !== -1;
            const show = okOffice && okRole && okSearch;
            el.classList.toggle('d-none', !show);
            if (show) visible += 1;
          });
          if (noMatches) {
            noMatches.classList.toggle('d-none', visible !== 0);
          }
        }

        officeSel.addEventListener('change', applyEmployeeFilters);
        roleSel.addEventListener('change', applyEmployeeFilters);
        searchInp.addEventListener('input', applyEmployeeFilters);
        if (clearBtn) {
          clearBtn.addEventListener('click', function () {
            officeSel.value = '-1';
            roleSel.value = '';
            searchInp.value = '';
            applyEmployeeFilters();
            searchInp.focus();
          });
        }
        applyEmployeeFilters();
        window.applyEmployeeFilters = applyEmployeeFilters;
      })();

      (function () {
        const ajaxAlert = document.getElementById('js-employee-ajax-alert');
        function showAjaxAlert(message, isError) {
          if (!ajaxAlert) return;
          ajaxAlert.textContent = message;
          ajaxAlert.className = 'alert py-2 ' + (isError ? 'alert-danger' : 'alert-success');
          ajaxAlert.classList.remove('d-none');
          ajaxAlert.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        async function postEmployeeForm(url, form) {
          const res = await fetch(url, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            credentials: 'same-origin',
          });
          const ct = (res.headers.get('content-type') || '').toLowerCase();
          if (!ct.includes('application/json')) {
            throw new Error('Session may have expired. Refresh the page and try again.');
          }
          const data = await res.json();
          return { res, data };
        }

        const roleForm = document.getElementById('formChangeRole');
        const roleSubmit = document.getElementById('formChangeRoleSubmit');
        const roleModalEl = document.getElementById('changeRoleModal');
        if (roleForm && roleSubmit && roleModalEl) {
          roleForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const prev = roleSubmit.textContent;
            roleSubmit.disabled = true;
            roleSubmit.textContent = 'Saving…';
            try {
              const { res, data } = await postEmployeeForm('update_user_role.php', roleForm);
              if (!data || typeof data.ok === 'undefined') {
                showAjaxAlert('Invalid response from server.', true);
                return;
              }
              if (!data.ok) {
                showAjaxAlert(data.message || 'Could not update role.', true);
                return;
              }
              const uid = String(document.getElementById('roleModalUserId')?.value || '');
              const card = document.querySelector('.eg-employee-filter-item[data-user-id="' + uid + '"]');
              if (card && data.role) {
                card.setAttribute('data-role', data.role);
                const badge = card.querySelector('.eg-js-role-badge');
                if (badge && data.role_label) {
                  badge.textContent = data.role_label;
                }
                const rBtn = card.querySelector('[data-bs-target="#changeRoleModal"]');
                const pBtn = card.querySelector('[data-bs-target="#changePasswordModal"]');
                if (rBtn) rBtn.setAttribute('data-user-role', data.role);
                if (pBtn && data.role_label) pBtn.setAttribute('data-user-role', data.role_label);
              }
              if (typeof window.applyEmployeeFilters === 'function') {
                window.applyEmployeeFilters();
              }
              const inst = bootstrap.Modal.getInstance(roleModalEl);
              if (inst) inst.hide();
              showAjaxAlert(data.message || 'Role updated.', false);
            } catch (err) {
              showAjaxAlert(err.message || 'Network error.', true);
            } finally {
              roleSubmit.disabled = false;
              roleSubmit.textContent = prev;
            }
          });
        }

        const pwdForm = document.getElementById('formChangePassword');
        const pwdSubmit = document.getElementById('formChangePasswordSubmit');
        const pwdModalEl = document.getElementById('changePasswordModal');
        if (pwdForm && pwdSubmit && pwdModalEl) {
          pwdForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const prev = pwdSubmit.textContent;
            pwdSubmit.disabled = true;
            pwdSubmit.textContent = 'Updating…';
            try {
              const { data } = await postEmployeeForm('update_user_password.php', pwdForm);
              if (!data || typeof data.ok === 'undefined') {
                showAjaxAlert('Invalid response from server.', true);
                return;
              }
              if (!data.ok) {
                showAjaxAlert(data.message || 'Could not update password.', true);
                return;
              }
              const np = document.getElementById('new_password');
              const cp = document.getElementById('confirm_password');
              if (np) np.value = '';
              if (cp) cp.value = '';
              const inst = bootstrap.Modal.getInstance(pwdModalEl);
              if (inst) inst.hide();
              showAjaxAlert(data.message || 'Password updated.', false);
            } catch (err) {
              showAjaxAlert(err.message || 'Network error.', true);
            } finally {
              pwdSubmit.disabled = false;
              pwdSubmit.textContent = prev;
            }
          });
        }
      })();
    </script>
  </body>
</html>