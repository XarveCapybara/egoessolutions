<?php
// Optional separate attendance page for employee prototype
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
    <title>E-GOES Solutions</title>
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
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_employee.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
      <h4 class="mb-3">Attendance History (Prototype)</h4>
      <div class="table-responsive bg-white rounded-3 shadow-sm p-3">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Clock-in</th>
              <th>Clock-out</th>
              <th>Total Hours</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Feb 2 (Monday)</td>
              <td>08:00 PM</td>
              <td>05:00 AM</td>
              <td>8h</td>
            </tr>
            <tr>
              <td>Feb 3 (Tuesday)</td>
              <td>08:00 PM</td>
              <td>05:00 AM</td>
              <td>8h</td>
            </tr>
          </tbody>
        </table>
      </div>
        </main>
      </div>
    </div>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>


