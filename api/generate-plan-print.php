<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/plan-print-lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

try {
    $context = plan_print_collect_context($config);
    $job = plan_print_create_job($config, $context);
    $spawned = plan_print_spawn_background_job((string)$job['id']);
    if (!$spawned) {
        throw new RuntimeException('Print-Job konnte nicht gestartet werden');
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'success' => true,
    'job_id' => $job['id'],
    'status' => $job['status'],
]);
