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
        <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

        <main class="col-12 col-md-9 col-lg-10 py-4">
          <h3 class="mb-3 fw-bold">Attendance Scanning</h3>
          <p class="text-muted mb-4">
            Scan employee barcodes to record time in and time out.
          </p>
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
                  required
                />
              </div>
              <div class="mb-3">
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-outline-secondary" id="startCameraBtn">
                    Use Camera to Scan
                  </button>
                  <button type="button" class="btn btn-outline-secondary d-none" id="stopCameraBtn">
                    Stop Camera
                  </button>
                </div>
                <div id="cameraStatus" class="text-muted small mt-2">Camera is off.</div>
                <video id="scannerVideo" class="mt-2 border rounded d-none" style="max-width: 420px; width: 100%;" playsinline muted></video>
                <div id="scannerReader" class="mt-2 border rounded d-none" style="max-width: 420px; width: 100%;"></div>
              </div>
              <button type="submit" class="btn btn-primary" id="submitScanBtn">Submit Time In</button>
            </form>
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
      const barcodeInput = document.getElementById('barcode-input');
      const startCameraBtn = document.getElementById('startCameraBtn');
      const stopCameraBtn = document.getElementById('stopCameraBtn');
      const scannerVideo = document.getElementById('scannerVideo');
      const scannerReader = document.getElementById('scannerReader');
      const cameraStatus = document.getElementById('cameraStatus');

      let cameraStream = null;
      let scanInterval = null;
      let fallbackScanner = null;
      let fallbackRunning = false;

      modeInBtn.addEventListener('click', function () {
        scanTypeInput.value = 'in';
        modeInBtn.classList.add('active');
        modeOutBtn.classList.remove('active');
        submitScanBtn.textContent = 'Submit Time In';
      });

      modeOutBtn.addEventListener('click', function () {
        scanTypeInput.value = 'out';
        modeOutBtn.classList.add('active');
        modeInBtn.classList.remove('active');
        submitScanBtn.textContent = 'Submit Time Out';
      });

      function submitDetectedBarcode(rawValue) {
        const value = (rawValue || '').trim();
        if (value === '' || !scanForm) return;
        barcodeInput.value = value;
        submitScanBtn.disabled = true;
        submitScanBtn.textContent = scanTypeInput.value === 'out' ? 'Submitting Time Out...' : 'Submitting Time In...';
        setTimeout(function () {
          scanForm.requestSubmit();
        }, 120);
      }

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
        stopCameraBtn.classList.add('d-none');
        startCameraBtn.classList.remove('d-none');
        cameraStatus.textContent = statusMessage || 'Camera is off.';
      }

      stopCameraBtn.addEventListener('click', stopCamera);

      startCameraBtn.addEventListener('click', async function () {
        if (!window.isSecureContext) {
          cameraStatus.textContent = 'Camera requires HTTPS or localhost. Open this page with a secure URL.';
          return;
        }

        try {
          startCameraBtn.classList.add('d-none');
          stopCameraBtn.classList.remove('d-none');
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
  </body>
</html>





