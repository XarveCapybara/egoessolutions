<?php
session_start();

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: settings.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$defaultRate = trim($_POST['default_hourly_rate'] ?? '');
$deductionPerMinute = trim($_POST['deduction_per_minute'] ?? '');
$employeeRates = $_POST['rate_amount'] ?? [];

try {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS app_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(64) NOT NULL UNIQUE,
            setting_value VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    if ($defaultRate !== '' && is_numeric($defaultRate)) {
        $v = number_format((float) $defaultRate, 2, '.', '');
        $upsert = $pdo->prepare('
            INSERT INTO app_settings (setting_key, setting_value) VALUES ("hourly_rate_default", ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');
        $upsert->execute([$v]);
    }

    if ($deductionPerMinute !== '' && is_numeric($deductionPerMinute)) {
        $v = number_format((float) $deductionPerMinute, 2, '.', '');
        $upsert = $pdo->prepare('
            INSERT INTO app_settings (setting_key, setting_value) VALUES ("deduction_per_minute", ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');
        $upsert->execute([$v]);
    }

    $hasRateAmount = $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_amount'")->rowCount() > 0;
    $hasRateType = $pdo->query("SHOW COLUMNS FROM employees LIKE 'rate_type'")->rowCount() > 0;

    if ($hasRateAmount && is_array($employeeRates)) {
        if ($hasRateType) {
            $stmt = $pdo->prepare('UPDATE employees SET rate_amount = ?, rate_type = ? WHERE id = ?');
        } else {
            $stmt = $pdo->prepare('UPDATE employees SET rate_amount = ? WHERE id = ?');
        }

        foreach ($employeeRates as $empId => $raw) {
            $empId = (int) $empId;
            if ($empId <= 0) {
                continue;
            }
            $raw = trim((string) $raw);
            if ($raw === '') {
                if ($hasRateType) {
                    $stmt->execute([null, null, $empId]);
                } else {
                    $stmt->execute([null, $empId]);
                }
                continue;
            }
            if (!is_numeric($raw)) {
                continue;
            }
            $amount = number_format((float) $raw, 2, '.', '');
            if ($hasRateType) {
                $stmt->execute([$amount, 'hourly', $empId]);
            } else {
                $stmt->execute([$amount, $empId]);
            }
        }
    }

    $_SESSION['settings_status'] = 'success';
    $_SESSION['settings_message'] = 'Rate settings saved.';
} catch (PDOException $e) {
    $_SESSION['settings_status'] = 'error';
    $_SESSION['settings_message'] = 'Could not save settings. Please try again.';
}

header('Location: settings.php');
exit;
