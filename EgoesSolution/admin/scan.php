<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Scanning</title>
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
        <div class="me-2 fw-bold fs-5">Admin-<?= htmlspecialchars($name) ?></div>
        <div class="eg-avatar-circle"></div>
      </div>
    </header>

    <div class="container-fluid">
      <div class="row">
        <aside class="col-12 col-md-3 col-lg-2 eg-sidebar eg-sidebar-admin py-4">
          <div class="eg-sidebar-brand px-3 mb-3">
            <span class="eg-sidebar-role">Office Admin</span>
          </div>
          <nav class="nav flex-column gap-1">
            <a href="dashboard.php" class="eg-sidebar-link">
              <i class="bi bi-speedometer2"></i>
              <span>Dashboard</span>
            </a>
            <a href="scan.php" class="eg-sidebar-link active">
              <i class="bi bi-upc-scan"></i>
              <span>Scanning</span>
            </a>
            <a href="employees.php" class="eg-sidebar-link">
              <i class="bi bi-people"></i>
              <span>Employees</span>
            </a>
            <a href="../auth/logout.php" class="eg-sidebar-link eg-sidebar-link-danger mt-3">
              <i class="bi bi-box-arrow-right"></i>
              <span>Logout</span>
            </a>
          </nav>
        </aside>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-3 fw-bold">Attendance Scanning</h3>
          <p class="text-muted mb-4">
            Scan employee barcodes to record time in and time out.
          </p>

          <div class="eg-panel p-4">
            <div class="mb-3">
              <label for="barcode-input" class="form-label fw-semibold">Barcode Number</label>
              <input
                type="text"
                id="barcode-input"
                class="form-control"
                placeholder="Scan or type barcode ID"
                autocomplete="off"
              />
            </div>
            <button class="btn btn-primary">Submit Scan</button>
          </div>
        </main>
      </div>
    </div>
  </body>
</html>





