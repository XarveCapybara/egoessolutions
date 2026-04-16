<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
$name = $_SESSION['display_name'] ?? 'Admin';
$scanStatus = $_SESSION['scan_status'] ?? null;
$scanMessage = $_SESSION['scan_message'] ?? null;
unset($_SESSION['scan_status'], $_SESSION['scan_message']);

require_once __DIR__ . '/../config/database.php';
$officeId = (int) ($_SESSION['office_id'] ?? 0);
$allowedWindowLabel = 'Not configured';
if ($officeId > 0) {
    $hasTimeInColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_in'")->rowCount() > 0;
    $hasTimeOutColumn = $pdo->query("SHOW COLUMNS FROM offices LIKE 'time_out'")->rowCount() > 0;
    if ($hasTimeInColumn && $hasTimeOutColumn) {
        $stmt = $pdo->prepare('SELECT time_in, time_out FROM offices WHERE id = ? LIMIT 1');
        $stmt->execute([$officeId]);
        $office = $stmt->fetch();
        if (!empty($office['time_in']) && !empty($office['time_out'])) {
            $timeIn = date('h:i A', strtotime($office['time_in']));
            $timeOut = date('h:i A', strtotime($office['time_out']));
            $allowedStart = date('h:i A', strtotime($office['time_in'] . ' -1 hour'));
            $allowedWindowLabel = $allowedStart . ' to ' . $timeOut . ' | Shift: ' . $timeIn . ' to ' . $timeOut;
        }
    }
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
    <style>
      .eg-scan-float-panel {
        position: fixed;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        width: min(94vw, 420px);
        z-index: 1080;
        background: #ffffff;
        border: 1px solid #dbeafe;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        padding: 0.75rem;
      }
      .eg-scan-float-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.4rem;
      }
      .eg-scan-float-body video,
      .eg-scan-float-body #scannerReader {
        width: 100%;
        max-width: 100%;
      }
    </style>
  </head>
  <body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid">
      <div class="row">
        <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-3 fw-bold">Attendance Scanning</h3>
          <p class="text-muted mb-4">
            Scan employee barcodes to record time in and time out.
          </p>
          <div class="alert alert-info py-2">
            <div><strong>Allowed attendance time:</strong> <?= htmlspecialchars($allowedWindowLabel) ?></div>
            <div class="mt-1"><strong>Current time:</strong> <span id="currentClock">--:--:--</span></div>
          </div>
          <?php if (!empty($scanMessage)): ?>
            <div class="alert <?= $scanStatus === 'success' ? 'alert-success' : 'alert-danger' ?> py-2">
              <?= htmlspecialchars($scanMessage) ?>
            </div>
          <?php endif; ?>

          <div class="eg-panel p-4">
            <form action="scan_submit.php" method="post" id="scanForm">
              <div class="mb-3">
                <label class="form-label fw-semibold d-block">Scan Mode</label>
                <div class="btn-group" role="group" aria-label="Scan mode switch">
                  <button type="button" class="btn btn-outline-primary active" id="modeInBtn">Time In</button>
                  <button type="button" class="btn btn-outline-primary" id="modeOutBtn">Time Out</button>
                </div>
                <input type="hidden" name="scan_type" id="scan_type" value="in" />
                <input type="hidden" name="client_now" id="client_now" value="" />
              </div>
              <div class="mb-3">
                <label for="barcode-input" class="form-label fw-semibold">Barcode Number</label>
                <input
                  type="text"
                  id="barcode-input"
                  name="barcode_id"
                  class="form-control"
                  placeholder="Scan or type barcode ID"
                  autocomplete="off"
                />
              </div>
              <div class="mb-3">
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-outline-secondary" id="startCameraBtn">
                    Use Camera to Scan
                  </button>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary" id="submitScanBtn">Submit Time In</button>
                <button
                  type="submit"
                  class="btn btn-outline-danger"
                  id="bulkTimeoutBtn"
                  name="bulk_timeout"
                  value="1"
                >
                  Time Out All (Today)
                </button>
              </div>
              <p class="text-muted small mt-2 mb-0">
                <strong>Time Out All</strong> will set time-out for all employees in this office who have a time-in but no time-out yet for this workday.
              </p>
            </form>
          </div>

          <div id="scannerFloatPanel" class="eg-scan-float-panel d-none">
            <div class="eg-scan-float-header">
              <strong class="small mb-0">Camera Scanner</strong>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="stopCameraBtn">Close</button>
            </div>
            <div id="cameraStatus" class="text-muted small mb-2">Camera is off.</div>
            <div class="eg-scan-float-body">
              <video id="scannerVideo" class="border rounded d-none" playsinline muted></video>
              <div id="scannerReader" class="border rounded d-none"></div>
            </div>
          </div>
        </main>
      </div>
    </div>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
      const modeInBtn = document.getElementById('modeInBtn');
      const modeOutBtn = document.getElementById('modeOutBtn');
      const scanTypeInput = document.getElementById('scan_type');
      const submitScanBtn = document.getElementById('submitScanBtn');
      const scanForm = document.getElementById('scanForm');
      const clientNowInput = document.getElementById('client_now');
      const barcodeInput = document.getElementById('barcode-input');
      const startCameraBtn = document.getElementById('startCameraBtn');
      const bulkTimeoutBtn = document.getElementById('bulkTimeoutBtn');
      const stopCameraBtn = document.getElementById('stopCameraBtn');
      const scannerFloatPanel = document.getElementById('scannerFloatPanel');
      const scannerVideo = document.getElementById('scannerVideo');
      const scannerReader = document.getElementById('scannerReader');
      const cameraStatus = document.getElementById('cameraStatus');

      let cameraStream = null;
      let scanInterval = null;
      let fallbackScanner = null;
      let fallbackRunning = false;

      function formatLocalDateTime(date) {
        const pad = (n) => String(n).padStart(2, '0');
        const y = date.getFullYear();
        const m = pad(date.getMonth() + 1);
        const d = pad(date.getDate());
        const h = pad(date.getHours());
        const min = pad(date.getMinutes());
        const s = pad(date.getSeconds());
        return `${y}-${m}-${d} ${h}:${min}:${s}`;
      }

      function refreshClientNow() {
        if (!clientNowInput) return;
        clientNowInput.value = formatLocalDateTime(new Date());
      }
      refreshClientNow();

      function secondsSinceMidnight(date) {
        return date.getHours() * 3600 + date.getMinutes() * 60 + date.getSeconds();
      }

      function updateCurrentClock() {
        const el = document.getElementById('currentClock');
        if (!el) return;
        const now = new Date();
        const timeText = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        el.textContent = timeText;

        // Restrict bulk Time Out All button to office hours window (server also validates).
        if (bulkTimeoutBtn) {
          const officeIn = '<?= isset($office['time_in']) ? substr((string) $office['time_in'], 0, 8) : '' ?>';
          const officeOut = '<?= isset($office['time_out']) ? substr((string) $office['time_out'], 0, 8) : '' ?>';

          if (!officeIn || !officeOut) {
            bulkTimeoutBtn.disabled = true;
            bulkTimeoutBtn.title = 'Office schedule not configured.';
            return;
          }

          const toSec = (hhmmss) => {
            const parts = hhmmss.split(':').map(Number);
            if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) return null;
            return parts[0] * 3600 + parts[1] * 60 + parts[2];
          };

          const nowSec = secondsSinceMidnight(now);
          const inSec = toSec(officeIn);
          const outSec = toSec(officeOut);
          if (inSec === null || outSec === null) {
            bulkTimeoutBtn.disabled = true;
            bulkTimeoutBtn.title = 'Office schedule is invalid.';
            return;
          }

          const isGraveyard = inSec > outSec;
          const allowedStart = (inSec - 3600 + 86400) % 86400; // 1h before time-in
          let bulkStart;
          let bulkEnd;
          let allowed = false;

          if (isGraveyard) {
            // Graveyard: only from time-out until 1h after time-out (e.g. 05:00–06:00)
            bulkStart = outSec;
            bulkEnd = (outSec + 3600) % 86400;
            allowed = nowSec >= bulkStart && nowSec <= bulkEnd;
          } else {
            // Normal shift: 1h before in until 1h after out
            bulkStart = allowedStart;
            bulkEnd = Math.min(outSec + 3600, 86399);
            allowed = nowSec >= bulkStart && nowSec <= bulkEnd;
          }

          bulkTimeoutBtn.disabled = !allowed;

          bulkTimeoutBtn.title = bulkTimeoutBtn.disabled
            ? 'Time Out All is only available from 1 hour before shift start until 1 hour after office end.'
            : '';
        }
      }
      updateCurrentClock();
      setInterval(updateCurrentClock, 1000);

      modeInBtn.addEventListener('click', function () {
        refreshClientNow();
        scanTypeInput.value = 'in';
        modeInBtn.classList.add('active');
        modeOutBtn.classList.remove('active');
        submitScanBtn.textContent = 'Submit Time In';
      });

      modeOutBtn.addEventListener('click', function () {
        refreshClientNow();
        scanTypeInput.value = 'out';
        modeOutBtn.classList.add('active');
        modeInBtn.classList.remove('active');
        submitScanBtn.textContent = 'Submit Time Out';
      });

      function submitDetectedBarcode(rawValue) {
        const value = (rawValue || '').trim();
        if (value === '' || !scanForm) return;
        refreshClientNow();
        barcodeInput.value = value;
        submitScanBtn.disabled = true;
        submitScanBtn.textContent = scanTypeInput.value === 'out' ? 'Submitting Time Out...' : 'Submitting Time In...';
        setTimeout(function () {
          scanForm.requestSubmit();
        }, 120);
      }

      scanForm.addEventListener('submit', function (event) {
        refreshClientNow();
        if (document.activeElement === bulkTimeoutBtn) {
          const confirmed = window.confirm('Time out all employees who have a time-in but no time-out yet for this workday?');
          if (!confirmed) {
            event.preventDefault();
            return;
          }
          scanTypeInput.value = 'out';
          submitScanBtn.textContent = 'Timing Out All...';
        }
      });

      function stopCamera(statusMessage) {
        if (scanInterval) {
          clearInterval(scanInterval);
          scanInterval = null;
        }
        if (cameraStream) {
          cameraStream.getTracks().forEach(function (track) { track.stop(); });
          cameraStream = null;
        }
        if (fallbackScanner && fallbackRunning) {
          fallbackScanner.stop().catch(function () {}).finally(function () {
            fallbackScanner.clear().catch(function () {});
          });
          fallbackRunning = false;
        }
        scannerVideo.classList.add('d-none');
        scannerReader.classList.add('d-none');
        if (scannerFloatPanel) scannerFloatPanel.classList.add('d-none');
        cameraStatus.textContent = statusMessage || 'Camera is off.';
      }

      stopCameraBtn.addEventListener('click', stopCamera);

      startCameraBtn.addEventListener('click', async function () {
        if (!window.isSecureContext) {
          cameraStatus.textContent = 'Camera requires HTTPS or localhost. Open this page with a secure URL.';
          return;
        }

        try {
          if (scannerFloatPanel) scannerFloatPanel.classList.remove('d-none');
          cameraStatus.textContent = 'Requesting camera permission...';

          // Try fallback first for broader browser support.
          scannerReader.classList.remove('d-none');
          fallbackScanner = new Html5Qrcode('scannerReader');
          await fallbackScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 280, height: 140 } },
            function (decodedText) {
              const rawValue = (decodedText || '').trim();
              if (rawValue !== '') {
                stopCamera('Barcode detected: ' + rawValue);
                submitDetectedBarcode(rawValue);
              }
            },
            function () {}
          );
          fallbackRunning = true;
          cameraStatus.textContent = 'Camera is on. Point it to the barcode.';
          return;
        } catch (fallbackError) {
          if (fallbackScanner) {
            fallbackScanner.clear().catch(function () {});
            fallbackScanner = null;
          }
          fallbackRunning = false;
          scannerReader.classList.add('d-none');
        }

        try {
          if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('getUserMedia is unavailable');
          }

          // Native fallback if Html5Qrcode cannot initialize.
          cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
            audio: false
          });

          scannerVideo.srcObject = cameraStream;
          await scannerVideo.play();
          scannerVideo.classList.remove('d-none');
          cameraStatus.textContent = 'Camera is on. Point it to the barcode.';

          if ('BarcodeDetector' in window) {
            const detector = new BarcodeDetector({
              formats: ['code_128', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'qr_code']
            });

            scanInterval = setInterval(async function () {
              if (!cameraStream) return;
              try {
                const barcodes = await detector.detect(scannerVideo);
                if (barcodes.length > 0) {
                  const rawValue = (barcodes[0].rawValue || '').trim();
                  if (rawValue !== '') {
                    stopCamera('Barcode detected: ' + rawValue);
                    submitDetectedBarcode(rawValue);
                  }
                }
              } catch (err) {
                cameraStatus.textContent = 'Unable to read barcode from camera.';
              }
            }, 350);
            return;
          }

          throw new Error('No barcode scanner API available');
        } catch (nativeError) {
          stopCamera('Camera not available. Use Chrome/Edge on HTTPS or localhost, then allow camera permission.');
        }
      });
    </script>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
  </body>
</html>





