<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
eg_session_start();
session_unset();
session_destroy();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool) $params['secure'],
        (bool) $params['httponly']
    );
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="refresh" content="0;url=../index.php" />
    <title>Logging out...</title>
  </head>
  <body>
    <script>
      try {
        localStorage.setItem('eg_logged_out_at', String(Date.now()));
      } catch (e) {}
      window.location.replace('../index.php');
    </script>
  </body>
</html>


