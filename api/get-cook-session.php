<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';
require_once __DIR__ . '/../cook-utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

try {
    $progress = sqlite_load_cook_progress($config);

    // Ohne id liefern wir nur den laufenden Fortschritt - das braucht der
    // Fortsetzen-Hinweis auf den Uebersichtsseiten.
    if ($id < 1) {
        if ($progress === null) {
            echo json_encode(['progress' => null]);
            exit;
        }
        $row = sqlite_recipe_find_row_by_id($config, $progress['recipe_id']);
        echo json_encode([
            'progress' => $progress,
            'title' => $row === null ? '' : (string)$row['title'],
        ]);
        exit;
    }

    $row = sqlite_recipe_find_row_by_id($config, $id);
    if ($row === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Rezept nicht gefunden']);
        exit;
    }

    $recipe = sqlite_recipe_from_row($row);
    $recipe['zutaten'] = (string)($row['ingredients_text'] ?? '');
    $recipe['zubereitung'] = (string)($row['instructions_text'] ?? '');

    // Aeltere Rezepte koennen die Spalten leer haben; dann aus dem Markdown holen.
    if ($recipe['zutaten'] === '' || $recipe['zubereitung'] === '') {
        $sections = recipe_extract_sections_from_body((string)($row['body_markdown'] ?? ''));
        if ($recipe['zutaten'] === '') {
            $recipe['zutaten'] = $sections['zutaten'];
        }
        if ($recipe['zubereitung'] === '') {
            $recipe['zubereitung'] = $sections['zubereitung'];
        }
    }

    $session = cook_build_session($recipe);
    $session['progress'] = ($progress !== null && $progress['recipe_id'] === $id) ? $progress : null;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

echo json_encode($session);
