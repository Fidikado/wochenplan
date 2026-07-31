<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require __DIR__ . '/gemini.php';

$input = json_decode(file_get_contents('php://input'), true);
$text = trim($input['text'] ?? '');

if (empty($text)) {
    http_response_code(400);
    echo json_encode(['error' => 'Kein Text angegeben']);
    exit;
}

$prompt = get_recipe_prompt() . "\n\nRezepttext:\n" . $text;
$result = gemini_request($config, [['text' => $prompt]]);

echo json_encode($result);
