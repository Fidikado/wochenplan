<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';
require_once __DIR__ . '/../cook-utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Anfrage']);
    exit;
}

$recipeId = (int)($input['recipe_id'] ?? 0);
if ($recipeId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Rezept-ID']);
    exit;
}

$timerEndsAt = null;
if (!empty($input['timer_ends_at'])) {
    $timestamp = strtotime((string)$input['timer_ends_at']);
    if ($timestamp !== false) {
        // Bewusst ISO-8601 in UTC statt des sonst üblichen lokalen Formats:
        // der Wert wird im Browser wieder eingelesen und muss dort ohne
        // Zeitzonen-Raten ankommen.
        $timerEndsAt = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}

try {
    $progress = sqlite_save_cook_progress($config, [
        'recipe_id' => $recipeId,
        'step_index' => (int)($input['step_index'] ?? 0),
        'servings' => cook_clamp_servings((int)($input['servings'] ?? COOK_MIN_SERVINGS)),
        'timer_seconds' => isset($input['timer_seconds']) ? (int)$input['timer_seconds'] : null,
        'timer_ends_at' => $timerEndsAt,
        'timer_remaining' => isset($input['timer_remaining']) ? (int)$input['timer_remaining'] : null,
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode(['message' => 'Fortschritt gespeichert', 'progress' => $progress]);
