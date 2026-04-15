<?php
// Shared admin/superadmin top header.
if (!isset($name) || $name === '') {
    $name = $_SESSION['display_name'] ?? 'User';
}

// Display name logic
$role = $_SESSION['role'] ?? '';

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
(function(){
  if(localStorage.getItem('eg_sidebar_collapsed')==='1'){
    document.body.classList.add('sidebar-collapsed','no-sidebar-transition');
    requestAnimationFrame(function(){
      requestAnimationFrame(function(){
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
    <a href="profile.php" class="eg-topbar-user me-3">
      <span class="eg-topbar-greeting"><?= htmlspecialchars($prefix . $displayName) ?></span>
      <div class="eg-avatar-circle eg-avatar-circle--header overflow-hidden d-flex align-items-center justify-content-center">
        <span class="bi bi-person-fill eg-topbar-avatar-fallback" aria-hidden="true"></span>
      </div>
    </a>
  <?php else: ?>
    <div class="d-flex align-items-center me-3">
      <div class="me-2 fw-bold fs-5"><?= htmlspecialchars($prefix . $displayName) ?></div>
      <div class="eg-avatar-circle"></div>
    </div>
  <?php endif; ?>
</header>