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
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_superadmin.php'; ?>

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
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($barcodes)): ?>
                  <tr><td colspan="5" class="text-muted text-center py-4">No barcodes yet. Employees need employee records with codes.</td></tr>
                <?php else: ?>
                  <?php foreach ($barcodes as $b): ?>
                    <tr>
                      <td><?= htmlspecialchars($b['id']) ?></td>
                      <td><?= htmlspecialchars($b['full_name']) ?></td>
                      <td><?= htmlspecialchars($b['office_name'] ?? '—') ?></td>
                      <td><?= htmlspecialchars($b['employee_code']) ?></td>
                      <td class="text-end">
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#barcodePreviewModal"
                          data-barcode-code="<?= htmlspecialchars($b['employee_code']) ?>"
                          data-barcode-name="<?= htmlspecialchars($b['full_name']) ?>"
                        >
                          View Barcode
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </main>
      </div>
    </div>
    <div class="modal fade" id="barcodePreviewModal" tabindex="-1" aria-labelledby="barcodePreviewModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="barcodePreviewModalLabel">Employee Barcode</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center">
            <div class="text-muted small mb-2" id="barcodePreviewEmployee"></div>
            <div class="bg-white p-3 border rounded d-inline-block">
              <svg id="barcodePreviewSvg"></svg>
            </div>
            <div class="mt-3">
              <button type="button" class="btn btn-primary btn-sm" id="downloadBarcodePreviewBtn">Download Barcode</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <script>
      (function () {
        const modal = document.getElementById('barcodePreviewModal');
        const svg = document.getElementById('barcodePreviewSvg');
        const employeeText = document.getElementById('barcodePreviewEmployee');
        const downloadBtn = document.getElementById('downloadBarcodePreviewBtn');
        if (!modal || !svg || !employeeText || !downloadBtn) return;
        let currentCode = '';

        modal.addEventListener('show.bs.modal', function (event) {
          const btn = event.relatedTarget;
          if (!btn) return;
          const code = (btn.getAttribute('data-barcode-code') || '').trim();
          currentCode = code;
          const name = (btn.getAttribute('data-barcode-name') || '').trim();
          employeeText.textContent = name !== '' ? name + ' — ' + code : code;

          if (code === '') {
            svg.innerHTML = '';
            return;
          }

          JsBarcode(svg, code, {
            format: 'CODE128',
            width: 2,
            height: 80,
            displayValue: true,
            margin: 8
          });
        });

        downloadBtn.addEventListener('click', function () {
          if (!currentCode) return;
          const serializer = new XMLSerializer();
          const svgData = serializer.serializeToString(svg);
          const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
          const svgUrl = URL.createObjectURL(svgBlob);
          const img = new Image();

          img.onload = function () {
            const canvas = document.createElement('canvas');
            const svgRect = svg.getBoundingClientRect();
            const width = Math.max(1, Math.round(svgRect.width || 400));
            const height = Math.max(1, Math.round(svgRect.height || 140));
            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext('2d');
            if (!ctx) {
              URL.revokeObjectURL(svgUrl);
              return;
            }

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
            ctx.drawImage(img, 0, 0, width, height);

            const pngUrl = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.href = pngUrl;
            link.download = currentCode + '-barcode.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(svgUrl);
          };

          img.onerror = function () {
            URL.revokeObjectURL(svgUrl);
          };

          img.src = svgUrl;
        });
      })();
    </script>
  </body>
</html>






