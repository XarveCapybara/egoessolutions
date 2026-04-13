<?php

// Central PDO connection for EgoesSolution.
// Adjust $dbName, $dbUser, $dbPass to match your MySQL setup.
date_default_timezone_set('Asia/Manila');

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'egoessolution';
$dbUser = 'root';
$dbPass = ''; // if your root user has a password, put it here

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // Keep DB date/time functions aligned with PH time.
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    // For now, just stop the script. Later you can log this instead.
    die('Database connection failed.');
}

