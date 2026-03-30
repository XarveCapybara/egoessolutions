<?php

declare(strict_types=1);

/**
 * Ensures payroll_deduction_types exists and seeds default rows when empty.
 */
function eg_ensure_payroll_deduction_types(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS payroll_deduction_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(128) NOT NULL,
            default_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');

    try {
        if ($pdo->query("SHOW COLUMNS FROM payroll_deduction_types LIKE 'is_active'")->rowCount() > 0) {
            $pdo->exec('ALTER TABLE payroll_deduction_types DROP COLUMN is_active');
        }
    } catch (PDOException $e) {
        // ignore if table missing or drop unsupported
    }

    try {
        if ($pdo->query("SHOW COLUMNS FROM payroll_deduction_types LIKE 'sort_order'")->rowCount() > 0) {
            $pdo->exec('ALTER TABLE payroll_deduction_types DROP COLUMN sort_order');
        }
    } catch (PDOException $e) {
        // ignore
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM payroll_deduction_types')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $ins = $pdo->prepare('
        INSERT INTO payroll_deduction_types (label, default_amount)
        VALUES (?, ?)
    ');
    $seed = [
        ['SSS Contribution', '0.00'],
        ['PhilHealth', '0.00'],
        ['Pag-IBIG', '0.00'],
        ['Loan Deductions', '0.00'],
    ];
    foreach ($seed as $row) {
        $ins->execute($row);
    }
}
