<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Keep compatibility for old admin attendance links.
header('Location: scan.php');
exit;



