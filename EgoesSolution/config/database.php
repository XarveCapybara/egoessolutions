<?php

// Central PDO connection for EgoesSolution.
// Uses environment variables first, with local-dev fallbacks.
date_default_timezone_set('Asia/Manila');

$appEnv = strtolower((string) (getenv('APP_ENV') ?: 'development'));
$isProduction = in_array($appEnv, ['prod', 'production'], true);

// Environment-aware PHP error policy.
if ($isProduction) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

$dbHost = (string) (getenv('DB_HOST') ?: '127.0.0.1');
$dbPort = (string) (getenv('DB_PORT') ?: '3306');
$dbName = (string) (getenv('DB_NAME') ?: 'egoessolution');
$dbUser = (string) (getenv('DB_USER') ?: 'root');
$dbPass = (string) (getenv('DB_PASS') ?: 'fieryblaze1');

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // Keep DB date/time functions aligned with PH time.
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    // Never expose DB details to users in production.
    error_log('Database connection failed: ' . $e->getMessage());
    if ($isProduction) {
        http_response_code(500);
        echo 'Service temporarily unavailable.';
        exit;
    }
    echo 'Database connection failed. Check your DB config.';
    exit;
}

