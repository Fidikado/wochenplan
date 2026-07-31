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
$id = (int)($input['id'] ?? 0);
$rating = (int)($input['rating'] ?? 0);

if ($id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Rezept-ID']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Bewertung muss zwischen 1 und 5 liegen']);
    exit;
}

try {
    $recipe = sqlite_rate_recipe($config, $id, $rating);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode(['message' => 'Bewertung gespeichert', 'recipe' => $recipe]);
