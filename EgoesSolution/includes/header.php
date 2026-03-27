<?php
// Shared admin/superadmin top header.
if (!isset($name) || $name === '') {
    $name = $_SESSION['display_name'] ?? 'User';
}

$role = $_SESSION['role'] ?? '';
$prefix = 'User-';
if ($role === 'superadmin') {
    $prefix = 'SuperAdmin-';
} elseif ($role === 'admin') {
    $prefix = 'Admin-';
}
$isProfileHeader = in_array($role, ['admin', 'superadmin'], true);
?>
<header class="eg-topbar d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center">
    <img src="../assets/images/egoes-logo.png?v=3" alt="E-GOES Solutions" class="eg-system-logo" />
  </div>
  <?php if ($isProfileHeader): ?>
    <a href="profile.php" class="eg-topbar-user me-3">
      <span class="eg-topbar-greeting"><?= htmlspecialchars($prefix . $name) ?></span>
      <div class="eg-avatar-circle eg-avatar-circle--header overflow-hidden d-flex align-items-center justify-content-center">
        <span class="bi bi-person-fill eg-topbar-avatar-fallback" aria-hidden="true"></span>
      </div>
    </a>
  <?php else: ?>
    <div class="d-flex align-items-center me-3">
      <div class="me-2 fw-bold fs-5"><?= htmlspecialchars($prefix . $name) ?></div>
      <div class="eg-avatar-circle"></div>
    </div>
  <?php endif; ?>
</header>