<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Super Admin';

require_once __DIR__ . '/../config/database.php';
$barcodes = [];
$offices = [];
try {
    $offices = $pdo->query('SELECT id, name FROM offices ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    $offices = [];
}
if ($pdo->query("SHOW TABLES LIKE 'employees'")->rowCount()) {
    $stmt = $pdo->query('
        SELECT e.id, e.employee_code, u.full_name, u.role, u.office_id, o.name AS office_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN offices o ON u.office_id = o.id
        ORDER BY e.employee_code
    ');
    $barcodes = $stmt->fetchAll();
}
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
          <div class="eg-panel p-3 mb-3">
            <div class="row g-2 align-items-end">
              <div class="col-12 col-md-4 col-lg-3">
                <label for="js-barcode-filter-office" class="form-label small text-muted mb-1">Office</label>
                <select id="js-barcode-filter-office" class="form-select form-select-sm">
                <option value="0">All offices</option>
                <option value="-1" selected>Unassigned</option>  <!-- add "selected" here -->
                <?php foreach ($offices as $o): ?>
                  <option value="<?= (int) $o['id'] ?>"><?= htmlspecialchars((string) $o['name']) ?></option>
                <?php endforeach; ?>
              </select>
              </div>
              <div class="col-12 col-md-4 col-lg-3">
                <label for="js-barcode-filter-role" class="form-label small text-muted mb-1">Role</label>
                <select id="js-barcode-filter-role" class="form-select form-select-sm">
                  <option value="">All</option>
                  <option value="employee">Employee</option>
                  <option value="admin">Team leader</option>
                </select>
              </div>
              <div class="col-12 col-md-4 col-lg-4">
                <label for="js-barcode-filter-search" class="form-label small text-muted mb-1">Search</label>
                <input id="js-barcode-filter-search" class="form-control form-control-sm" type="search" placeholder="Name, code, office..." />
              </div>
              <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="js-barcode-filter-clear">Clear</button>
              </div>
            </div>
          </div>
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
              <tbody id="barcodeTableBody">
                <?php if (empty($barcodes)): ?>
                  <tr><td colspan="5" class="text-muted text-center py-4">No barcodes yet. Employees need employee records with codes.</td></tr>
                <?php else: ?>
                  <?php foreach ($barcodes as $b): ?>
                    <?php
                    $officeId = (int) ($b['office_id'] ?? 0);
                    $roleRaw = strtolower((string) ($b['role'] ?? 'employee'));
                    $searchHay = strtolower(trim(
                        ((string) ($b['full_name'] ?? '')) . ' ' .
                        ((string) ($b['employee_code'] ?? '')) . ' ' .
                        ((string) ($b['office_name'] ?? ''))
                    ));
                    ?>
                    <tr
                      class="js-barcode-row"
                      data-office-id="<?= $officeId ?>"
                      data-role="<?= htmlspecialchars($roleRaw, ENT_QUOTES, 'UTF-8') ?>"
                      data-search="<?= htmlspecialchars($searchHay, ENT_QUOTES, 'UTF-8') ?>"
                    >
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
            <p id="barcodeNoMatches" class="text-muted small d-none mb-0 mt-2">No barcodes match these filters.</p>
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
            <div class="text-muted small mb-2 d-none" id="barcodePreviewEmployee"></div>
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
        const officeSel = document.getElementById('js-barcode-filter-office');
        const roleSel = document.getElementById('js-barcode-filter-role');
        const searchInp = document.getElementById('js-barcode-filter-search');
        const clearBtn = document.getElementById('js-barcode-filter-clear');
        const body = document.getElementById('barcodeTableBody');
        const noMatches = document.getElementById('barcodeNoMatches');
        function applyBarcodeFilters() {
          if (!officeSel || !roleSel || !searchInp || !body) return;
          const office = String(officeSel.value || '0');
          const role = String(roleSel.value || '');
          const q = String(searchInp.value || '').trim().toLowerCase();
          let visible = 0;
          body.querySelectorAll('tr.js-barcode-row').forEach(function (tr) {
            const oid = String(tr.getAttribute('data-office-id') || '0');
            const r = String(tr.getAttribute('data-role') || 'employee');
            const hay = String(tr.getAttribute('data-search') || '');
            const okOffice = office === '0' ? true : (office === '-1' ? oid === '0' : oid === office);
            const okRole = role === '' || role === r;
            const okSearch = q === '' || hay.indexOf(q) !== -1;
            const show = okOffice && okRole && okSearch;
            tr.classList.toggle('d-none', !show);
            if (show) visible++;
          });
          if (noMatches) noMatches.classList.toggle('d-none', visible > 0 || q === '' && office === '0' && role === '');
        }
        if (officeSel) officeSel.addEventListener('change', applyBarcodeFilters);
        if (roleSel) roleSel.addEventListener('change', applyBarcodeFilters);
        if (searchInp) searchInp.addEventListener('input', applyBarcodeFilters);
        if (clearBtn) {
          clearBtn.addEventListener('click', function () {
            if (officeSel) officeSel.value = '0';
            if (roleSel) roleSel.value = '';
            if (searchInp) searchInp.value = '';
            applyBarcodeFilters();
          });
        }
        applyBarcodeFilters();
      })();

      (function () {
        const modal = document.getElementById('barcodePreviewModal');
        const svg = document.getElementById('barcodePreviewSvg');
        const employeeText = document.getElementById('barcodePreviewEmployee');
        const downloadBtn = document.getElementById('downloadBarcodePreviewBtn');
        if (!modal || !svg || !employeeText || !downloadBtn) return;
        let currentCode = '';
        let currentName = '';

        function renderBarcodeWithName(code, name) {
          const SVG_NS = 'http://www.w3.org/2000/svg';
          const tempSvg = document.createElementNS(SVG_NS, 'svg');
          JsBarcode(tempSvg, code, {
            format: 'CODE128',
            width: 2,
            height: 80,
            displayValue: true,
            margin: 8
          });

          const rawW = parseFloat(tempSvg.getAttribute('width') || '420');
          const rawH = parseFloat(tempSvg.getAttribute('height') || '140');
          const nameBand = 26;
          const totalH = rawH + nameBand;

          svg.innerHTML = '';
          svg.setAttribute('width', String(rawW));
          svg.setAttribute('height', String(totalH));
          svg.setAttribute('viewBox', `0 0 ${rawW} ${totalH}`);

          const bg = document.createElementNS(SVG_NS, 'rect');
          bg.setAttribute('x', '0');
          bg.setAttribute('y', '0');
          bg.setAttribute('width', String(rawW));
          bg.setAttribute('height', String(totalH));
          bg.setAttribute('fill', '#ffffff');
          svg.appendChild(bg);

          if (name) {
            const nameText = document.createElementNS(SVG_NS, 'text');
            nameText.setAttribute('x', String(rawW / 2));
            nameText.setAttribute('y', '16');
            nameText.setAttribute('text-anchor', 'middle');
            nameText.setAttribute('font-size', '14');
            nameText.setAttribute('font-family', 'Arial, sans-serif');
            nameText.setAttribute('fill', '#111111');
            nameText.textContent = name;
            svg.appendChild(nameText);
          }

          const g = document.createElementNS(SVG_NS, 'g');
          g.setAttribute('transform', `translate(0, ${nameBand})`);
          Array.from(tempSvg.childNodes).forEach(function (node) {
            g.appendChild(node.cloneNode(true));
          });
          svg.appendChild(g);
        }

        modal.addEventListener('show.bs.modal', function (event) {
          const btn = event.relatedTarget;
          if (!btn) return;
          const code = (btn.getAttribute('data-barcode-code') || '').trim();
          currentCode = code;
          const name = (btn.getAttribute('data-barcode-name') || '').trim();
          currentName = name;
          employeeText.textContent = '';

          if (code === '') {
            svg.innerHTML = '';
            return;
          }
          renderBarcodeWithName(code, name);
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
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>






