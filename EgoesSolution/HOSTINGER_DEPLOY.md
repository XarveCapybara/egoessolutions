# Hostinger Deployment Guide

## 1) Configure environment in `.htaccess`

Edit project root `.htaccess` and set real values:

- `SetEnv APP_ENV production`
- `SetEnv DB_HOST ...`
- `SetEnv DB_PORT 3306`
- `SetEnv DB_NAME ...`
- `SetEnv DB_USER ...`
- `SetEnv DB_PASS ...`

Do not leave production DB values hardcoded in PHP files.

## 2) Upload project files

- Upload all project files to your target Hostinger directory.
- Ensure `index.php` is in the web root for your domain/subdomain.

## 3) Import database

- Create DB + DB user in Hostinger hPanel.
- Import your SQL dump in phpMyAdmin.
- Confirm table `employees` exists with `employee_code` column.

## 4) Remove/disable setup utilities

- Delete `setup_admin.php` after initial setup.
- It is now blocked in production mode, but deleting it is still recommended.

## 5) Pre-publish checks

Run locally before upload:

`powershell -ExecutionPolicy Bypass -File .\scripts\prepublish-check.ps1`

Expected:

- PHP lint pass
- No debug leftovers
- No production config warnings

## 6) Post-deploy smoke test

- Open `/` and verify role-based redirect when logged in.
- Test login/logout.
- Test superadmin employee creation.
- Confirm new employee code format is `E-00000`.
- Test role/password updates (CSRF-protected endpoints).
- Test office assignment.
