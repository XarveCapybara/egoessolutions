<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';
$stmt = $pdo->query('SELECT id, full_name, email FROM users WHERE role = "employee" ORDER BY full_name');
$employees = $stmt->fetchAll();
$offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();
$selectedOfficeId = (int) ($_GET['office_id'] ?? 0);
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
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Super Admin - Employees</title>
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
        <div class="me-2 fw-bold fs-5">
          SuperAdmin-<?= htmlspecialchars($name) ?>
        </div>
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
            <a href="employees.php" class="eg-sidebar-link active">
              <i class="bi bi-people"></i>
              <span>Employees</span>
            </a>
            <a href="payroll.php" class="eg-sidebar-link">
              <i class="bi bi-currency-dollar"></i>
              <span>Payroll</span>
            </a>
            <a href="barcodes.php" class="eg-sidebar-link">
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
          <h3 class="mb-3 fw-bold">Employees</h3>
          <p class="text-muted mb-4">Create and manage employee login accounts.</p>
          <?php if (!empty($employeeCreateMessage)): ?>
            <div class="alert <?= $employeeCreateStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2">
              <?= htmlspecialchars($employeeCreateMessage) ?>
            </div>
          <?php endif; ?>
          <div class="eg-panel p-3 mb-4">
            <h5 class="mb-3">Create Employee Account</h5>
            <p class="text-muted small mb-3">Only SuperAdmin can create accounts.</p>
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
              <div class="col-12">
                <?php if ($selectedOfficeName !== null): ?>
                  <div class="text-muted small">Assigning to office: <strong><?= htmlspecialchars($selectedOfficeName) ?></strong></div>
                <?php else: ?>
                  <div class="text-muted small">Tip: open this page from an office overview to auto-assign office.</div>
                <?php endif; ?>
              </div>
            </form>
          </div>
          <div class="row g-3">
            <?php if (empty($employees)): ?>
              <div class="col-12">
                <p class="text-muted">No Employees yet. Create one above when form is wired to database.</p>
              </div>
            <?php else: ?>
              <?php foreach ($employees as $emp): ?>
                <div class="col-6 col-md-4 col-lg-3">
                  <div class="eg-employee-card">
                    <div class="d-flex align-items-center mb-2">
                      <div class="eg-avatar-circle me-2"></div>
                      <div>
                        <div class="fw-semibold"><?= htmlspecialchars($emp['full_name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($emp['email']) ?></div>
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

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>