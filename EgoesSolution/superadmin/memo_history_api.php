<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (($_SESSION['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['rows' => [], 'error' => 'forbidden']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['rows' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(o.name, 'General') AS office_name,
            m.violation_code,
            m.violation_name,
            m.offense_number,
            m.consequence,
            m.consequence_type,
            m.suspension_days,
            m.suspension_start,
            m.suspension_end,
            m.status,
            m.created_at,
            m.memo_notes
        FROM employee_memos m
        LEFT JOIN offices o ON m.office_id = o.id
        WHERE m.user_id = ?
        ORDER BY m.created_at DESC, m.id DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['rows' => [], 'error' => 'query_failed']);
}

