<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'employee') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Employee';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Employee Payslip Archive</title>
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
    <link rel="stylesheet" href="../assets/css/style.css" />
  </head>
  <body class="bg-light">
    <?php
    $name = $_SESSION['display_name'] ?? 'Employee';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="container-fluid py-4">
      <nav class="eg-employee-nav mb-4">
        <a href="dashboard.php" class="eg-employee-nav-link">
          <i class="bi bi-house-door"></i>
          <span>Dashboard</span>
        </a>
        <a href="payslip.php" class="eg-employee-nav-link active">
          <i class="bi bi-receipt"></i>
          <span>Payslip Archive</span>
        </a>
        <a href="../auth/logout.php" class="eg-employee-nav-link eg-employee-nav-link-danger">
          <i class="bi bi-box-arrow-right"></i>
          <span>Logout</span>
        </a>
      </nav>

      <div class="eg-panel">
        <h5 class="mb-3">My Payslip Archive</h5>
        <p class="text-muted small mb-0">
          Payslip records will appear here once they are loaded from the database.
        </p>
      </div>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>


