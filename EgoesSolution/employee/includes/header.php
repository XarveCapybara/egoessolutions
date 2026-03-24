<?php
/**
 * Employee app top header. Set $name (display name) before include.
 * Optional: $avatarUrl — profile image path, or reads from $_SESSION['employee_profile']['avatar'].
 */
if (!isset($name)) {
    $name = $_SESSION['display_name'] ?? 'Employee';
}
if (!isset($avatarUrl)) {
    $avatarUrl = !empty($_SESSION['employee_profile']['avatar'])
        ? $_SESSION['employee_profile']['avatar']
        : null;
}
$firstName = explode(' ', trim((string) $name), 2)[0] ?: 'there';
?>
<header class="eg-topbar eg-topbar--employee" role="banner">
  <div class="container-fluid eg-topbar-inner">
    <a href="dashboard.php" class="eg-topbar-brand">
      <img src="../assets/images/egoes-logo.png?v=3" alt="E-GOES Solutions" class="eg-system-logo" />
    </a>
    <a href="profile.php" class="eg-topbar-user">
      <span class="eg-topbar-greeting">Hi, <?= htmlspecialchars($firstName) ?></span>
      <div class="eg-avatar-circle eg-avatar-circle--header overflow-hidden d-flex align-items-center justify-content-center">
        <?php if (!empty($avatarUrl)): ?>
          <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="" width="40" height="40" style="width: 100%; height: 100%; object-fit: cover;" />
        <?php else: ?>
          <span class="bi bi-person-fill eg-topbar-avatar-fallback" aria-hidden="true"></span>
        <?php endif; ?>
      </div>
    </a>
  </div>
</header>



