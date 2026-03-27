<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';
$logs = [];
if ($pdo->query("SHOW TABLES LIKE 'attendance_logs'")->rowCount()) {
    $stmt = $pdo->query('SELECT al.*, u.full_name, o.name AS office_name FROM attendance_logs al JOIN employees e ON al.employee_id = e.id JOIN users u ON e.user_id = u.id JOIN offices o ON al.office_id = o.id ORDER BY al.log_date DESC, al.time_in DESC LIMIT 100');
    $logs = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Super Admin Attendance</title>
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
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="fw-bold mb-3">All Offices Attendance Records</h3>
          <div class="table-responsive bg-white rounded-3 shadow-sm p-3">
            <table class="table table-bordered table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Office</th>
                  <th>Employee</th>
                  <th>Date</th>
                  <th>Time In</th>
                  <th>Time Out</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($logs)): ?>
                  <tr><td colspan="6" class="text-muted text-center py-4">No attendance records yet. Data from scans will appear here.</td></tr>
                <?php else: ?>
                  <?php foreach ($logs as $log): ?>
                    <tr>
                      <td><?= htmlspecialchars($log['office_name']) ?></td>
                      <td><?= htmlspecialchars($log['full_name']) ?></td>
                      <td><?= date('M j, Y', strtotime($log['log_date'])) ?></td>
                      <td><?= $log['time_in'] ? date('h:i A', strtotime($log['time_in'])) : '—' ?></td>
                      <td><?= $log['time_out'] ? date('h:i A', strtotime($log['time_out'])) : '—' ?></td>
                      <td><?= htmlspecialchars($log['status']) ?></td>
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






