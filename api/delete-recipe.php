<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';

$input = json_decode((string)file_get_contents('php://input'), true);
$recipeId = (int)($input['id'] ?? 0);

if ($recipeId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Rezept-ID ist erforderlich']);
    exit;
}

try {
    $deleted = sqlite_recipe_delete_by_id($config, $recipeId);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

if ($deleted === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Rezept nicht gefunden']);
    exit;
}

$imagePath = trim((string)($deleted['image'] ?? ''));
if ($imagePath !== '') {
    $full = __DIR__ . '/../' . ltrim($imagePath, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

echo json_encode([
    'success' => true,
    'id' => $recipeId,
    'message' => "Rezept '" . ($deleted['title'] ?: 'Unbekannt') . "' gelöscht",
]);
