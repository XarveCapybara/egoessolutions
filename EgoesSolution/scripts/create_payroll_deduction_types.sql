-- Payroll deduction lines for payslip (Superadmin → Settings → Payroll deduction lines)
-- Run against your application database (e.g. egoessolution).

CREATE TABLE IF NOT EXISTS payroll_deduction_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(128) NOT NULL,
    default_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: initial rows (run only once)
INSERT INTO payroll_deduction_types (label, default_amount) VALUES
('SSS Contribution', 0.00),
('PhilHealth', 0.00),
('Pag-IBIG', 0.00),
('Loan Deductions', 0.00);

-- Legacy upgrades (if your table still has these columns):
-- ALTER TABLE payroll_deduction_types DROP COLUMN is_active;
-- ALTER TABLE payroll_deduction_types DROP COLUMN sort_order;
