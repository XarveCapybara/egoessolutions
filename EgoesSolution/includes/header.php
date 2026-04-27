<?php
// Shared admin/superadmin top header.
if (!isset($name) || $name === '') {
  $name = $_SESSION['display_name'] ?? 'User';
}
$headerTopbarExtraClass = isset($headerTopbarExtraClass) ? trim((string) $headerTopbarExtraClass) : '';
if (isset($headerAssetBase)) {
  $headerAssetBase = trim((string) $headerAssetBase);
  $headerAssetBase = $headerAssetBase === '' ? '' : rtrim($headerAssetBase, '/') . '/';
} else {
  $headerAssetBase = '../';
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
$isGuestHeader = $role === '';
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
  (function () {
    var iconCandidates = [
      '<?= htmlspecialchars($headerAssetBase, ENT_QUOTES, "UTF-8") ?>assets/images/logo-white.png?v=1'
    ];
    var existingIcon = document.querySelector('link[rel="icon"]');
    if (!existingIcon) {
      existingIcon = document.createElement('link');
      existingIcon.setAttribute('rel', 'icon');
      existingIcon.setAttribute('type', 'image/png');
      document.head.appendChild(existingIcon);
    }

    function applyIconAt(index) {
      if (index >= iconCandidates.length) {
        return;
      }
      var probe = new Image();
      probe.onload = function () {
        existingIcon.setAttribute('href', iconCandidates[index]);
      };
      probe.onerror = function () {
        applyIconAt(index + 1);
      };
      probe.src = iconCandidates[index];
    }

    applyIconAt(0);
  })();

  // Restore sidebar state BEFORE paint to eliminate flicker
  (function () {
    var isPhone = window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches;
    if (isPhone) {
      document.body.classList.add('sidebar-collapsed', 'no-sidebar-transition');
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          document.body.classList.remove('no-sidebar-transition');
        });
      });
      return;
    }
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
<style>
  .eg-system-brand {
    transition: transform 0.35s cubic-bezier(0.22, 0.61, 0.36, 1) !important;
    transform-origin: left center !important;
    cursor: pointer;
  }
  .eg-system-brand:hover {
    transform: scale(1.06) !important;
  }
</style>
<header class="eg-topbar<?= $headerTopbarExtraClass !== '' ? ' ' . htmlspecialchars($headerTopbarExtraClass, ENT_QUOTES, 'UTF-8') : '' ?> d-flex justify-content-between align-items-center">
  <div class="eg-system-brand d-inline-flex align-items-center" style="gap:.6rem;">
    <img src="<?= htmlspecialchars($headerAssetBase, ENT_QUOTES, 'UTF-8') ?>assets/images/logo-white.png" alt="E-Goes Solutions" class="eg-system-logo" style="width:36px;height:auto;display:block;" />
    <div class="eg-system-wordmark d-flex flex-column" style="line-height:1.02;">
      <span class="eg-system-wordmark-top" style="font-size:1rem;font-weight:900;color:#fff;text-transform:uppercase;">E-Goes</span>
      <span class="eg-system-wordmark-bottom" style="font-size:.76rem;font-weight:900;color:rgba(255,255,255,.95);text-transform:uppercase;">Solutions</span>
    </div>
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
  <?php elseif (!$isGuestHeader): ?>
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

  (function () {
    window.addEventListener('storage', function (event) {
      if (!event || event.key !== 'eg_logged_out_at') return;
      var path = window.location.pathname.toLowerCase();
      if (path.indexOf('/auth/login.php') !== -1 || path.endsWith('/index.php')) return;
      window.location.href = '../auth/login.php';
    });
  })();
</script>