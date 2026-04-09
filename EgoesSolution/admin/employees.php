<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Admin';
$officeId = $_SESSION['office_id'] ?? null;

require_once __DIR__ . '/../config/database.php';
$employees = [];
$hasPositionCol = false;
if ($officeId) {
    $hasPositionCol = $pdo->query("SHOW COLUMNS FROM employees LIKE 'position'")->rowCount() > 0;
    $positionSelect = $hasPositionCol ? ', e.position' : '';
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.role{$positionSelect}
        FROM users u
        LEFT JOIN employees e ON e.user_id = u.id
        WHERE u.office_id = ? AND u.role IN ('employee', 'admin')
        ORDER BY u.full_name
    ");
    $stmt->execute([$officeId]);
    $employees = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EGoes Solutions</title>
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
        <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-4 fw-bold">Employees &amp; team leaders</h3>
          <p class="text-muted small mb-3">Staff for this office. Only SuperAdmin creates accounts.</p>
          <div class="row g-3">
            <?php if (empty($employees)): ?>
              <div class="col-12">
                <p class="text-muted">No employees yet. SuperAdmin creates employee accounts in Employee Accounts.</p>
              </div>
            <?php else: ?>
              <?php foreach ($employees as $emp): ?>
                <?php
                $roleLabel = (($emp['role'] ?? '') === 'admin') ? 'Team leader' : 'Employee';
                $positionText = ($hasPositionCol && !empty(trim((string) ($emp['position'] ?? '')))) ? trim($emp['position']) : '';
                ?>
                <div class="col-6 col-md-4 col-lg-3">
                  <div class="eg-employee-card">
                    <div class="d-flex align-items-center mb-2">
                      <div class="eg-avatar-circle me-2"></div>
                      <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold"><?= htmlspecialchars($emp['full_name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($emp['email']) ?></div>
                        <div class="mt-1">
                          <span class="badge bg-secondary bg-opacity-25 text-dark small"><?= htmlspecialchars($roleLabel) ?></span>
                          <?php if ($positionText !== ''): ?>
                            <span class="text-muted small ms-1"><?= htmlspecialchars($positionText) ?></span>
                          <?php endif; ?>
                        </div>
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







