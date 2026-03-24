<?php
// Landing page prototype for attendance-payroll-system
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-GOES Solutions - Attendance & Payroll</title>
    <!-- Bootstrap CSS -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" href="assets/css/style.css" />
  </head>
  <body class="bg-light">
    <!-- Landing Page -->
    <div class="min-vh-100 d-flex flex-column">
      <!-- Top Bar / Branding -->
      <header class="eg-topbar">
        <div class="d-flex align-items-center">
          <img src="assets/images/egoes-logo.png?v=3" alt="E-GOES Solutions" class="eg-system-logo" />
        </div>
      </header>

      <!-- Hero Section -->
      <main class="flex-fill d-flex align-items-center justify-content-center">
        <div class="text-center px-3 px-md-0" style="max-width: 520px;">
          <h1 class="mb-3 eg-hero-title">
            Unlock Business Growth with
            <span class="eg-hero-highlight">Expert Virtual Solutions</span>
          </h1>
          <p class="text-muted mb-4 eg-hero-subtitle">
            Professional virtual assistance, cutting-edge design, and
            development services to accelerate your business success.
          </p>
          <a
            href="auth/login.php"
            class="btn btn-lg px-5 eg-hero-login-btn"
          >
            LOGIN
          </a>
        </div>
      </main>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
  </body>
</html>






