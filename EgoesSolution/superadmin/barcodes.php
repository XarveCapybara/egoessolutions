<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';
$barcodes = [];
if ($pdo->query("SHOW TABLES LIKE 'employees'")->rowCount()) {
    $stmt = $pdo->query('SELECT e.id, e.employee_code, u.full_name, o.name AS office_name FROM employees e JOIN users u ON e.user_id = u.id LEFT JOIN offices o ON u.office_id = o.id ORDER BY e.employee_code');
    $barcodes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Super Admin Employee Barcodes</title>
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
    <header class="eg-topbar d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <img src="../assets/images/egoes-logo.png?v=3" alt="E-GOES Solutions" class="eg-system-logo" />
      </div>
      <div class="d-flex align-items-center me-3">
        <div class="me-2 fw-bold fs-5">SuperAdmin-<?= htmlspecialchars($name) ?></div>
        <div class="eg-avatar-circle"></div>
      </div>
    </header>

    <div class="container-fluid">
      <div class="row">
        <aside class="col-12 col-md-3 col-lg-2 eg-sidebar eg-sidebar-superadmin py-4">
          <div class="eg-sidebar-brand px-3 mb-3">
            <span class="eg-sidebar-role">Superadmin</span>
          </div>
          <nav class="nav flex-column gap-1">
            <a href="dashboard.php" class="eg-sidebar-link">
              <i class="bi bi-speedometer2"></i>
              <span>Dashboard</span>
            </a>
            <a href="offices.php" class="eg-sidebar-link">
              <i class="bi bi-building"></i>
              <span>Offices</span>
            </a>
            <a href="employees.php" class="eg-sidebar-link">
              <i class="bi bi-people"></i>
              <span>Employees</span>
            </a>
            <a href="payroll.php" class="eg-sidebar-link">
              <i class="bi bi-currency-dollar"></i>
              <span>Payroll</span>
            </a>
            <a href="barcodes.php" class="eg-sidebar-link active">
              <i class="bi bi-upc-scan"></i>
              <span>Employee Barcodes</span>
            </a>
            <a href="attendance.php" class="eg-sidebar-link">
              <i class="bi bi-calendar-check"></i>
              <span>Office Attendance</span>
            </a>
            <a href="../auth/logout.php" class="eg-sidebar-link eg-sidebar-link-danger mt-3">
              <i class="bi bi-box-arrow-right"></i>
              <span>Logout</span>
            </a>
          </nav>
        </aside>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="fw-bold mb-3">All Employee Barcodes</h3>
          <p class="text-muted">
            View and manage generated barcodes for each employee across all offices.
          </p>
          <div class="table-responsive bg-white rounded-3 shadow-sm p-3">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Employee ID</th>
                  <th>Name</th>
                  <th>Office</th>
                  <th>Barcode Code</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($barcodes)): ?>
                  <tr><td colspan="4" class="text-muted text-center py-4">No barcodes yet. Employees need employee records with codes.</td></tr>
                <?php else: ?>
                  <?php foreach ($barcodes as $b): ?>
                    <tr>
                      <td><?= htmlspecialchars($b['id']) ?></td>
                      <td><?= htmlspecialchars($b['full_name']) ?></td>
                      <td><?= htmlspecialchars($b['office_name'] ?? '—') ?></td>
                      <td><?= htmlspecialchars($b['employee_code']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>






