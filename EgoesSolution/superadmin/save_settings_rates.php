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
        // DB columns may be NOT NULL: store 0.00 and a non-null type when "use global default".
        // Use 'hourly' so ENUM/VARCHAR schemas that only allow real types still accept the row; pay logic uses rate_amount > 0.
        $rateUseDefault = '0.00';
        $rateTypeUseDefault = 'hourly';

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
                    $stmt->execute([$rateUseDefault, $rateTypeUseDefault, $empId]);
                } else {
                    $stmt->execute([$rateUseDefault, $empId]);
                }
                continue;
            }
            $normalized = str_replace(["\xC2\xA0", ' '], '', $raw);
            $normalized = str_replace(',', '.', $normalized);
            if ($normalized === '' || !is_numeric($normalized)) {
                continue;
            }
            $amountFloat = (float) $normalized;
            if ($amountFloat <= 0) {
                if ($hasRateType) {
                    $stmt->execute([$rateUseDefault, $rateTypeUseDefault, $empId]);
                } else {
                    $stmt->execute([$rateUseDefault, $empId]);
                }
                continue;
            }
            $amount = number_format($amountFloat, 2, '.', '');
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
    error_log('[save_settings_rates] ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
    $_SESSION['settings_status'] = 'error';
    $_SESSION['settings_message'] = 'Could not save settings. Debug: ' . $e->getMessage()
        . ' (SQLSTATE ' . $e->getCode() . ')';
}

header('Location: settings.php');
exit;
