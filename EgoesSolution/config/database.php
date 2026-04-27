<?php

// Central PDO connection for EgoesSolution.
// Uses environment variables first.
date_default_timezone_set('Asia/Manila');

if (!function_exists('eg_env')) {
    function eg_env(string $key, string $default = ''): string
    {
        $v = getenv($key);
        if ($v !== false && $v !== null && $v !== '') {
            return (string) $v;
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        return $default;
    }
}

$appEnv = strtolower(eg_env('APP_ENV', 'development'));
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

$dbHost = eg_env('DB_HOST', '127.0.0.1');
$dbPort = eg_env('DB_PORT', '3306');
$dbName = eg_env('DB_NAME', 'egoessolution');
$dbUser = eg_env('DB_USER', 'root');
$dbPass = eg_env('DB_PASS', '');

if ($dbPass === '') {
    error_log('Missing DB_PASS environment value.');
    http_response_code(500);
    if ($isProduction) {
        echo 'Service temporarily unavailable.';
    } else {
        echo 'Database connection failed. Missing DB_PASS in .htaccess/env.';
    }
    exit;
}

if ($isProduction) {
    $missingVars = [];
    if (eg_env('DB_HOST', '') === '') $missingVars[] = 'DB_HOST';
    if (eg_env('DB_NAME', '') === '') $missingVars[] = 'DB_NAME';
    if (eg_env('DB_USER', '') === '') $missingVars[] = 'DB_USER';
    if (eg_env('DB_PASS', '') === '') $missingVars[] = 'DB_PASS';
    if (!empty($missingVars)) {
        error_log('Missing required production DB env vars: ' . implode(', ', $missingVars));
        http_response_code(500);
        echo 'Service temporarily unavailable.';
        exit;
    }
}

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

