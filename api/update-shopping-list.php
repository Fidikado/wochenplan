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
$sectionIndex = $input['kategorie'] ?? null;
$itemIndex = $input['zutat'] ?? null;

if ($sectionIndex === null || $itemIndex === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Kategorie und Zutat-Index erforderlich']);
    exit;
}

try {
    $data = sqlite_remove_shopping_list_item($config, (int)$sectionIndex, (int)$itemIndex);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'message' => 'Zutat entfernt',
    'data' => $data,
]);
