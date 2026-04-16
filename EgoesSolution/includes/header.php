<?php
// Shared admin/superadmin top header.
if (!isset($name) || $name === '') {
  $name = $_SESSION['display_name'] ?? 'User';
}

// Display name logic
$role = $_SESSION['role'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? 0);
$headerAvatarUrl = '';

// Always prefer latest DB values so topbar reflects profile edits.
if ($userId > 0 && isset($pdo) && $pdo instanceof PDO) {
  try {
    $headerStmt = $pdo->prepare('SELECT full_name, profile_image FROM users WHERE id = ? LIMIT 1');
    $headerStmt->execute([$userId]);
    $headerUser = $headerStmt->fetch(PDO::FETCH_ASSOC);
    if ($headerUser) {
      $dbFullName = trim((string) ($headerUser['full_name'] ?? ''));
      if ($dbFullName !== '') {
        $name = $dbFullName;
      }
      $headerAvatarUrl = trim((string) ($headerUser['profile_image'] ?? ''));
    }
  } catch (Throwable $e) {
    // Keep header usable even if query fails.
  }
}

$role = $_SESSION['role'] ?? '';
$prefix = '';
if ($role === 'superadmin') {
  $prefix = 'SuperAdmin - ';
} elseif ($role === 'admin') {
  $prefix = 'Admin - ';
} elseif ($role === 'employee') {
  $prefix = 'Employee - ';
}
$isProfileHeader = in_array($role, ['admin', 'superadmin', 'employee'], true);
$isEmployeeProfileModalEnabled = $role === 'employee';
$isAdminProfileModalEnabled = $role === 'admin';
$isSuperadminProfileModalEnabled = $role === 'superadmin';
$profileModalEnabled = $isEmployeeProfileModalEnabled || $isAdminProfileModalEnabled || $isSuperadminProfileModalEnabled;
$profileModalId = $isAdminProfileModalEnabled
  ? 'adminProfileModal'
  : ($isSuperadminProfileModalEnabled ? 'superadminProfileModal' : 'employeeProfileModal');
$profileModalFrameId = $isAdminProfileModalEnabled
  ? 'adminProfileModalFrame'
  : ($isSuperadminProfileModalEnabled ? 'superadminProfileModalFrame' : 'employeeProfileModalFrame');
$profileModalTitle = $isAdminProfileModalEnabled
  ? 'Admin Profile'
  : ($isSuperadminProfileModalEnabled ? 'Superadmin Profile' : 'Employee Profile');

// Build display name: extract from full name
// Database format is "Lastname, Firstname" so first name is after the comma
$parts = explode(',', $name, 2);
if (count($parts) > 1) {
  $displayName = trim($parts[1]);
} else {
  $nameParts = explode(' ', trim($name));
  $displayName = $nameParts[0];
}
?>
<script>
  // Restore sidebar state BEFORE paint to eliminate flicker
  (function () {
    if (localStorage.getItem('eg_sidebar_collapsed') === '1') {
      document.body.classList.add('sidebar-collapsed', 'no-sidebar-transition');
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          document.body.classList.remove('no-sidebar-transition');
        });
      });
    }
  })();
</script>
<header class="eg-topbar d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center gap-3">
    <img src="../assets/images/egoes-logo.png?v=3" alt="E-GOES Solutions" class="eg-system-logo" />
  </div>
  <?php if ($isProfileHeader): ?>
    <a
      href="<?= $profileModalEnabled ? '#' : 'profile.php' ?>"
      class="eg-topbar-user me-3"
      <?php if ($profileModalEnabled): ?>
      data-bs-toggle="modal"
      data-bs-target="#<?= $profileModalId ?>"
      <?php endif; ?>>
      <span class="eg-topbar-greeting" id="egTopbarGreeting"><?= htmlspecialchars($prefix . $displayName) ?></span>
      <div
        class="eg-avatar-circle eg-avatar-circle--header overflow-hidden d-flex align-items-center justify-content-center">
        <?php if ($headerAvatarUrl !== ''): ?>
          <img
            id="egTopbarAvatarImg"
            src="<?= htmlspecialchars($headerAvatarUrl) ?>"
            alt="Profile"
            style="width:100%;height:100%;object-fit:cover;"
          />
          <span class="bi bi-person-fill eg-topbar-avatar-fallback d-none" id="egTopbarAvatarFallback" aria-hidden="true"></span>
        <?php else: ?>
          <img
            id="egTopbarAvatarImg"
            src=""
            alt="Profile"
            class="d-none"
            style="width:100%;height:100%;object-fit:cover;"
          />
          <span class="bi bi-person-fill eg-topbar-avatar-fallback" id="egTopbarAvatarFallback" aria-hidden="true"></span>
        <?php endif; ?>
      </div>
    </a>
  <?php else: ?>
    <div class="d-flex align-items-center me-3">
      <div class="me-2 fw-bold fs-5"><?= htmlspecialchars($prefix . $displayName) ?></div>
      <div class="eg-avatar-circle"></div>
    </div>
  <?php endif; ?>
</header>
<?php if ($profileModalEnabled): ?>
  <div class="modal fade" id="<?= $profileModalId ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" style="max-width: 860px;">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h5 class="modal-title">My Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0" style="min-height: 62vh;">
          <iframe
            id="<?= $profileModalFrameId ?>"
            src="profile.php?modal=1"
            title="<?= htmlspecialchars($profileModalTitle) ?>"
            style="width:100%; height:62vh; border:0;"></iframe>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<script>
  (function () {
    var greetingEl = document.getElementById('egTopbarGreeting');
    var avatarImg = document.getElementById('egTopbarAvatarImg');
    var avatarFallback = document.getElementById('egTopbarAvatarFallback');
    var prefix = <?= json_encode($prefix, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

    window.addEventListener('message', function (event) {
      if (!event || event.origin !== window.location.origin || !event.data || event.data.type !== 'eg_profile_updated') {
        return;
      }
      var profile = event.data.profile || {};
      var displayName = String(profile.displayName || '').trim();
      var avatarUrl = String(profile.avatarUrl || '').trim();

      if (greetingEl && displayName) {
        greetingEl.textContent = prefix + displayName;
      }
      if (avatarImg && avatarFallback) {
        if (avatarUrl) {
          avatarImg.src = avatarUrl;
          avatarImg.classList.remove('d-none');
          avatarFallback.classList.add('d-none');
        } else {
          avatarImg.src = '';
          avatarImg.classList.add('d-none');
          avatarFallback.classList.remove('d-none');
        }
      }
    });
  })();
</script>