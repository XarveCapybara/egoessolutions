<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside class="col-12 col-md-3 col-lg-2 eg-sidebar eg-sidebar-admin py-4">
  <div class="eg-sidebar-brand px-3 mb-3">
    <span class="eg-sidebar-role">Office Admin</span>
  </div>
  <nav class="nav flex-column gap-1">
    <a href="dashboard.php" class="eg-sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i>
      <span>Dashboard</span>
    </a>
    <a href="scan.php" class="eg-sidebar-link <?= in_array($currentPage, ['scan.php', 'attendance.php'], true) ? 'active' : '' ?>">
      <i class="bi bi-upc-scan"></i>
      <span>Scanning</span>
    </a>
    <a href="payroll.php" class="eg-sidebar-link <?= $currentPage === 'payroll.php' ? 'active' : '' ?>">
      <i class="bi bi-currency-dollar"></i>
      <span>Payroll</span>
    </a>
    <a href="employees.php" class="eg-sidebar-link <?= $currentPage === 'employees.php' ? 'active' : '' ?>">
      <i class="bi bi-people"></i>
      <span>Employees</span>
    </a>
    <a href="../auth/logout.php" class="eg-sidebar-link eg-sidebar-link-danger mt-3">
      <i class="bi bi-box-arrow-right"></i>
      <span>Logout</span>
    </a>
  </nav>
</aside>
