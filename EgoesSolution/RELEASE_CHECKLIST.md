# Release Checklist

Use this checklist before publishing to Hostinger.

## 1) Environment and Config

- [ ] `APP_ENV` is set correctly (`production` on live server).
- [ ] `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` are set for production.
- [ ] Production DB user is not `root`.
- [ ] Domain/DocumentRoot points to the correct project folder.

## 2) Security

- [ ] Login/session redirect behavior works (`index.php` and `auth/login.php` auto-redirect logged-in users).
- [ ] Logout works across tabs (other open tabs redirect to login).
- [ ] CSRF protection works on superadmin sensitive actions:
  - [ ] Role update
  - [ ] Password update
  - [ ] Assign employee to office
- [ ] Protected routes redirect unauthorized users to login.

## 3) Functional Smoke Test

- [ ] Superadmin can log in and access dashboard.
- [ ] Admin can log in and access dashboard.
- [ ] Employee can log in and access dashboard.
- [ ] Employee creation works.
- [ ] Team leader creation works.
- [ ] Office assignment works.
- [ ] Barcode page loads without flash/hide issue.
- [ ] Attendance and payroll pages load without errors.

## 4) Technical Verification

- [ ] Run pre-publish checker:
  - `powershell -ExecutionPolicy Bypass -File .\scripts\prepublish-check.ps1`
- [ ] All PHP files pass syntax lint.
- [ ] No obvious debug leftovers in app files (`var_dump`, `print_r`, `dd`).

## 5) Deployment Safety

- [ ] Database backup created.
- [ ] Current files backup created.
- [ ] Rollback plan documented.
- [ ] Post-deploy smoke test ready.
