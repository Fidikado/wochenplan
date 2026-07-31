<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['plan']) || !is_array($input['plan'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiger Plan']);
    exit;
}
if (count($input['plan']) !== 7) {
    http_response_code(400);
    echo json_encode(['error' => 'Plan muss genau 7 Tage enthalten']);
    exit;
}

$existing = sqlite_load_current_plan($config);
$createdAt = (string)($existing['erstellt'] ?? date('Y-m-d H:i:s'));

try {
    $data = sqlite_save_current_plan($config, $input['plan'], $createdAt);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'message' => 'Plan aktualisiert',
    'data' => $data,
]);
