<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';
require_once __DIR__ . '/plan-print-lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$input = !empty($_POST) ? $_POST : json_decode((string)file_get_contents('php://input'), true);
$model = trim((string)($input['model'] ?? ''));
$allowed = array_column(plan_print_available_models(), 'id');

if ($model === '' || !in_array($model, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiges Bildmodell']);
    exit;
}

try {
    $saved = sqlite_set_plan_print_model($config, $model);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'success' => true,
    'model' => $saved,
    'message' => 'Bildmodell gespeichert',
]);
