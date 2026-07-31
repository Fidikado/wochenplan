<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/url-import.php';

$input = json_decode(file_get_contents('php://input'), true);
$url = trim($input['url'] ?? '');

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige URL']);
    exit;
}

if (stripos($url, 'https://www.rezeptwelt.de/') !== 0 && stripos($url, 'http://www.rezeptwelt.de/') !== 0) {
    // Wir erlauben weiterhin jede URL, aber Rezeptwelt-URLs sind der Ziel-Usecase.
}

$result = import_recipe_from_url($config, $url);

echo json_encode($result);
