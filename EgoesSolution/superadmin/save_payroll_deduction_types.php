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
require_once __DIR__ . '/../includes/payroll_deduction_types.php';

function eg_parse_money(string $raw): float
{
    $raw = trim(str_replace(["\xC2\xA0", ' '], '', $raw));
    $raw = str_replace(',', '.', $raw);
    if ($raw === '' || !is_numeric($raw)) {
        return 0.0;
    }
    return (float) $raw;
}

try {
    eg_ensure_payroll_deduction_types($pdo);

    $rows = $_POST['row'] ?? [];
    if (is_array($rows)) {
        $upd = $pdo->prepare('
            UPDATE payroll_deduction_types
            SET label = ?, default_amount = ?
            WHERE id = ?
        ');
        foreach ($rows as $id => $r) {
            $id = (int) $id;
            if ($id <= 0 || !is_array($r)) {
                continue;
            }
            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $amt = eg_parse_money((string) ($r['amount'] ?? '0'));
            $upd->execute([
                $label,
                number_format(max(0, $amt), 2, '.', ''),
                $id,
            ]);
        }
    }

    $newLabel = trim((string) ($_POST['new_label'] ?? ''));
    if ($newLabel !== '') {
        $newAmt = eg_parse_money((string) ($_POST['new_amount'] ?? '0'));
        $ins = $pdo->prepare('
            INSERT INTO payroll_deduction_types (label, default_amount)
            VALUES (?, ?)
        ');
        $ins->execute([
            $newLabel,
            number_format(max(0, $newAmt), 2, '.', ''),
        ]);
    }

    $_SESSION['settings_status'] = 'success';
    $_SESSION['settings_message'] = 'Payroll deduction lines saved.';
} catch (Throwable $e) {
    error_log('save_payroll_deduction_types: ' . $e->getMessage());
    $_SESSION['settings_status'] = 'error';
    $_SESSION['settings_message'] = 'Could not save deduction lines. ' . $e->getMessage();
}

header('Location: settings.php');
exit;
