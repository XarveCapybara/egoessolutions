<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside id="eg-sidebar" class="col-12 col-md-3 col-lg-2 eg-sidebar eg-sidebar-admin py-4">
  <div class="eg-sidebar-brand d-flex align-items-center justify-content-between px-3 mb-3">
    <span class="eg-sidebar-role">Employee</span>
    <button id="eg-sidebar-toggle" class="eg-sidebar-toggle-btn eg-sidebar-toggle-btn-dark" aria-label="Toggle sidebar" title="Toggle sidebar">
      <span class="bi bi-layout-sidebar"></span>
    </button>
  </div>
  <nav class="nav flex-column gap-1">
    <a href="dashboard.php" class="eg-sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i>
      <span>Dashboard</span>
    </a>
    <a href="leave_request.php" class="eg-sidebar-link <?= $currentPage === 'leave_request.php' ? 'active' : '' ?>">
      <i class="bi bi-calendar2-plus"></i>
      <span>Leave Request</span>
    </a>
    <a href="cash_advance.php" class="eg-sidebar-link <?= $currentPage === 'cash_advance.php' ? 'active' : '' ?>">
      <i class="bi bi-cash-coin"></i>
      <span>Cash Advance</span>
    </a>
    <a href="memorandum.php" class="eg-sidebar-link <?= $currentPage === 'memorandum.php' ? 'active' : '' ?>">
      <i class="bi bi-file-earmark-text"></i>
      <span>Memorandum</span>
    </a>
    <a href="payslip.php" class="eg-sidebar-link <?= $currentPage === 'payslip.php' ? 'active' : '' ?>">
      <i class="bi bi-receipt"></i>
      <span>Payslip Archive</span>
    </a>
    <a href="../auth/logout.php" class="eg-sidebar-link eg-sidebar-link-danger">
      <i class="bi bi-box-arrow-right"></i>
      <span>Logout</span>
    </a>
  </nav>
</aside>
