<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside id="eg-sidebar" class="col-12 col-md-3 col-lg-2 eg-sidebar eg-sidebar-superadmin py-4">
  <div class="eg-sidebar-brand d-flex align-items-center justify-content-between px-3 mb-3">
    <span class="eg-sidebar-role">Superadmin</span>
    <button id="eg-sidebar-toggle" class="eg-sidebar-toggle-btn eg-sidebar-toggle-btn-dark" aria-label="Toggle sidebar" title="Toggle sidebar">
      <span class="bi bi-layout-sidebar"></span>
    </button>
  </div>
  <nav class="nav flex-column gap-1">
    <a href="dashboard.php" class="eg-sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i>
      <span>Dashboard</span>
    </a>
    <a href="offices.php" class="eg-sidebar-link <?= in_array($currentPage, ['offices.php', 'office_overview.php'], true) ? 'active' : '' ?>">
      <i class="bi bi-building"></i>
      <span>Offices</span>
    </a>
    <a href="employees.php" class="eg-sidebar-link <?= $currentPage === 'employees.php' ? 'active' : '' ?>">
      <i class="bi bi-people"></i>
      <span>Employees</span>
    </a>
    <a href="payroll.php" class="eg-sidebar-link <?= $currentPage === 'payroll.php' ? 'active' : '' ?>">
      <i class="bi bi-currency-dollar"></i>
      <span>Payroll</span>
    </a>
    <a href="loans.php" class="eg-sidebar-link <?= $currentPage === 'loans.php' ? 'active' : '' ?>">
      <i class="bi bi-cash-coin"></i>
      <span>Cash Advances</span>
    </a>
    <a href="barcodes.php" class="eg-sidebar-link <?= $currentPage === 'barcodes.php' ? 'active' : '' ?>">
      <i class="bi bi-upc-scan"></i>
      <span>Employee Barcodes</span>
    </a>
    <a href="attendance.php" class="eg-sidebar-link <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">
      <i class="bi bi-calendar-check"></i>
      <span>Office Attendance</span>
    </a>
    <a href="settings.php" class="eg-sidebar-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
      <i class="bi bi-gear"></i>
      <span>Settings</span>
    </a>
    <a href="profile.php" class="eg-sidebar-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
      <i class="bi bi-person"></i>
      <span>Profile</span>
    </a>
    <a href="../auth/logout.php" class="eg-sidebar-link eg-sidebar-link-danger mt-3">
      <i class="bi bi-box-arrow-right"></i>
      <span>Logout</span>
    </a>
  </nav>
</aside>
