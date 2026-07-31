<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';

$input = !empty($_POST) ? $_POST : json_decode((string)file_get_contents('php://input'), true);
$recipeId = (int)($input['id'] ?? 0);

if ($recipeId < 1 || empty($input['title'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Rezept-ID und Titel sind erforderlich']);
    exit;
}

$existing = sqlite_recipe_find_by_id($config, $recipeId);
if ($existing === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Rezept nicht gefunden']);
    exit;
}

$imagePath = (string)($existing['image'] ?? '');
$removeImage = !empty($input['remove_image']);

if (!empty($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $image = $_FILES['image'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $allowed[$image['type']] ?? null;
    if ($ext === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültiges Bildformat. Erlaubt: JPG, PNG, WebP.']);
        exit;
    }

    if (!is_dir($config['images_dir']) && !mkdir($config['images_dir'], 0755, true) && !is_dir($config['images_dir'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Bildordner konnte nicht erstellt werden']);
        exit;
    }

    $base = preg_replace('/[^a-z0-9äöüß]+/u', '-', strtolower((string)$input['title']));
    $base = trim((string)$base, '-');
    if ($base === '') {
        $base = 'rezeptbild';
    }
    $imageFilename = $base . '-' . substr(uniqid('', true), -6) . '.' . $ext;
    $target = rtrim($config['images_dir'], '/\\') . '/' . $imageFilename;
    if (!move_uploaded_file($image['tmp_name'], $target)) {
        http_response_code(500);
        echo json_encode(['error' => 'Bild konnte nicht gespeichert werden']);
        exit;
    }

    if ($imagePath !== '') {
        $oldPath = __DIR__ . '/../' . ltrim($imagePath, '/');
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    $imagePath = 'rezepte/images/' . $imageFilename;
} elseif ($removeImage && $imagePath !== '') {
    $oldPath = __DIR__ . '/../' . ltrim($imagePath, '/');
    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
    $imagePath = '';
}

try {
    $recipe = sqlite_recipe_update_by_id($config, $recipeId, [
        'title' => trim((string)$input['title']),
        'kategorie' => (string)($input['kategorie'] ?? ($existing['kategorie'] ?? 'hauptspeise')),
        'typ' => (string)($input['typ'] ?? 'normal'),
        'kochzeit' => (int)($input['kochzeit'] ?? 30),
        'portionen' => (int)($input['portionen'] ?? 4),
        'tags' => $input['tags'] ?? [],
        'anmerkung' => trim((string)($input['anmerkung'] ?? '')),
        'image' => $imagePath,
        'zutaten' => trim((string)($input['zutaten'] ?? '')),
        'zubereitung' => trim((string)($input['zubereitung'] ?? '')),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'success' => true,
    'id' => $recipe['id'],
    'message' => "Rezept '" . $recipe['title'] . "' aktualisiert",
]);
