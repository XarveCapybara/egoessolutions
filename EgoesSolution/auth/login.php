<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-GOES Solutions</title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="../assets/css/style.css" />
    <style>
      .eg-topbar-login .eg-system-wordmark-top,
      .eg-topbar-login .eg-system-wordmark-bottom {
        font-weight: 500 !important;
      }
      .eg-password-wrap {
        position: relative;
      }
      .eg-password-wrap .form-control {
        padding-right: 2.6rem;
      }
      .eg-password-toggle {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        border: 0;
        background: transparent;
        padding: 0;
        color: #6c757d;
        line-height: 1;
        z-index: 5;
      }
    </style>
  </head>
  <body class="bg-light">
    <?php $headerTopbarExtraClass = 'eg-topbar-login'; $headerAssetBase = '../'; ?>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
          <div class="eg-login-card">
            <h2 class="text-center mb-4 eg-login-title">Login</h2>
            <form
              action="authenticate.php"
              method="post"
              class="needs-validation"
              novalidate
            >
              <div class="mb-3">
                <label for="username" class="form-label fw-semibold"
                  >Email or Username</label
                >
                <div class="input-group">
                  <span class="input-group-text">
                    <span class="bi bi-person-fill"></span>
                  </span>
                  <input
                    type="text"
                    class="form-control"
                    id="username"
                    name="username"
                    placeholder="Enter email or username"
                    required
                    autocomplete="off"
                  />
                  <div class="invalid-feedback">
                    Please enter your email or username.
                  </div>
                </div>
              </div>
              <div class="mb-4">
                <label for="password" class="form-label fw-semibold"
                  >Password</label
                >
                <div class="input-group eg-password-wrap">
                  <span class="input-group-text">
                    <span class="bi bi-lock-fill"></span>
                  </span>
                  <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    required
                    autocomplete="off"
                  />
                  <button
                    type="button"
                    class="eg-password-toggle shadow-none"
                    id="togglePasswordBtn"
                    aria-label="Show password"
                    title="Show password"
                  >
                    <span class="bi bi-eye" id="togglePasswordIcon"></span>
                  </button>
                  <div class="invalid-feedback">
                    Please enter your password.
                  </div>
                </div>
              </div>
              <?php if (!empty($_SESSION['login_error'])): ?>
                <div class="alert alert-danger py-2 small" role="alert">
                  <?= htmlspecialchars($_SESSION['login_error']) ?>
                </div>
              <?php unset($_SESSION['login_error']); endif; ?>

              <button
                type="submit"
                class="btn w-100 eg-login-btn"
              >
                LOGIN
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
      crossorigin="anonymous"
    ></script>
    <script>
      // Simple client-side Bootstrap validation for the prototype
      (function () {
        'use strict';
        const forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function (form) {
          form.addEventListener(
            'submit',
            function (event) {
              if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
              }
              form.classList.add('was-validated');
            },
            false
          );
        });
      })();

      (function () {
        const pwd = document.getElementById('password');
        const btn = document.getElementById('togglePasswordBtn');
        const icon = document.getElementById('togglePasswordIcon');
        if (!pwd || !btn || !icon) return;
        btn.addEventListener('click', function () {
          const isHidden = pwd.type === 'password';
          pwd.type = isHidden ? 'text' : 'password';
          icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
          btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
          btn.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
        });
      })();
    </script>
  </body>
</html>






