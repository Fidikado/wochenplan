<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';
require_once __DIR__ . '/gemini.php';

$input = json_decode((string)file_get_contents('php://input'), true);
$recipeId = (int)($input['id'] ?? 0);

if ($recipeId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Rezept-ID fehlt']);
    exit;
}

$recipe = sqlite_recipe_find_by_id($config, $recipeId);
if ($recipe === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Rezept nicht gefunden']);
    exit;
}

if (($recipe['typ'] ?? 'normal') === 'thermomix') {
    http_response_code(400);
    echo json_encode(['error' => 'Das Rezept ist bereits als Thermomix-Rezept markiert']);
    exit;
}

$sections = recipe_extract_sections_from_body((string)($recipe['body'] ?? ''));
$prompt = get_thermomix_conversion_prompt([
    'title' => $recipe['title'] ?? '',
    'kategorie' => $recipe['kategorie'] ?? 'hauptspeise',
    'kochzeit' => $recipe['kochzeit'] ?? 30,
    'portionen' => $recipe['portionen'] ?? 4,
    'tags' => $recipe['tags'] ?? [],
    'zutaten' => $sections['zutaten'] !== '' ? $sections['zutaten'] : '',
    'zubereitung' => $sections['zubereitung'] !== '' ? $sections['zubereitung'] : '',
]);

$result = gemini_request($config, [['text' => $prompt]], [
    'temperature' => 0.2,
    'max_output_tokens' => 3200,
]);

if (isset($result['error'])) {
    http_response_code(500);
    echo json_encode($result);
    exit;
}

$title = trim((string)($result['title'] ?? ''));
if ($title === '') {
    $title = trim((string)($recipe['title'] ?? '')) . ' (Thermomix)';
}

$converted = [
    'title' => $title,
    'kategorie' => (string)($recipe['kategorie'] ?? 'hauptspeise'),
    'typ' => 'thermomix',
    'kochzeit' => max(1, (int)($result['kochzeit'] ?? ($recipe['kochzeit'] ?? 30))),
    'portionen' => max(1, (int)($result['portionen'] ?? ($recipe['portionen'] ?? 4))),
    'tags' => recipe_tags_to_array($result['tags'] ?? ($recipe['tags'] ?? [])),
    'zutaten' => trim((string)($result['zutaten'] ?? '')),
    'zubereitung' => trim((string)($result['zubereitung'] ?? '')),
    'anmerkung' => trim((string)($recipe['anmerkung'] ?? '')),
    'image' => trim((string)($recipe['image'] ?? '')),
];

if ($converted['zutaten'] === '' || $converted['zubereitung'] === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Die KI hat kein vollständiges Thermomix-Rezept zurückgegeben']);
    exit;
}

$converted['body'] = recipe_build_body($converted['zutaten'], $converted['zubereitung']);

echo json_encode([
    'success' => true,
    'source' => [
        'id' => (int)$recipe['id'],
        'title' => (string)($recipe['title'] ?? ''),
    ],
    'recipe' => $converted,
]);
