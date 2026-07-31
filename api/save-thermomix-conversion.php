<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';

$input = json_decode((string)file_get_contents('php://input'), true);
$sourceId = (int)($input['source_id'] ?? 0);
$recipeInput = is_array($input['recipe'] ?? null) ? $input['recipe'] : [];

if ($sourceId < 1 || empty($recipeInput)) {
    http_response_code(400);
    echo json_encode(['error' => 'Quelldaten fehlen']);
    exit;
}

$sourceRecipe = sqlite_recipe_find_by_id($config, $sourceId);
if ($sourceRecipe === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Originalrezept nicht gefunden']);
    exit;
}

$title = trim((string)($recipeInput['title'] ?? ''));
if ($title === '') {
    $title = trim((string)($sourceRecipe['title'] ?? '')) . ' (Thermomix)';
}

$ingredients = trim((string)($recipeInput['zutaten'] ?? ''));
$instructions = trim((string)($recipeInput['zubereitung'] ?? ''));
if ($ingredients === '' || $instructions === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Die Thermomix-Vorschau ist unvollständig']);
    exit;
}

$sourceNote = trim((string)($sourceRecipe['anmerkung'] ?? ''));
$conversionNote = 'Thermomix-Version per KI aus "' . trim((string)($sourceRecipe['title'] ?? '')) . '" erstellt am ' . date('d.m.Y');
$note = $sourceNote !== '' ? $sourceNote . ' | ' . $conversionNote : $conversionNote;

try {
    $recipe = sqlite_recipe_create($config, [
        'title' => $title,
        'kategorie' => (string)($recipeInput['kategorie'] ?? ($sourceRecipe['kategorie'] ?? 'hauptspeise')),
        'typ' => 'thermomix',
        'kochzeit' => max(1, (int)($recipeInput['kochzeit'] ?? ($sourceRecipe['kochzeit'] ?? 30))),
        'portionen' => max(1, (int)($recipeInput['portionen'] ?? ($sourceRecipe['portionen'] ?? 4))),
        'tags' => $recipeInput['tags'] ?? ($sourceRecipe['tags'] ?? []),
        'anmerkung' => $note,
        'image' => (string)($sourceRecipe['image'] ?? ''),
        'zutaten' => $ingredients,
        'zubereitung' => $instructions,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'success' => true,
    'id' => $recipe['id'],
    'message' => "Thermomix-Rezept '" . $recipe['title'] . "' gespeichert",
]);
