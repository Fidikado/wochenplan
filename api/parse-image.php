<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require __DIR__ . '/gemini.php';

$files = [];
if (!empty($_FILES['images'])) {
    $count = count($_FILES['images']['name'] ?? []);
    for ($i = 0; $i < $count; $i++) {
        if (!empty($_FILES['images']['tmp_name'][$i])) {
            $files[] = [
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'type' => $_FILES['images']['type'][$i] ?? 'image/jpeg',
                'error' => $_FILES['images']['error'][$i] ?? 0,
            ];
        }
    }
} elseif (!empty($_FILES['image'])) {
    $files[] = $_FILES['image'];
}

if (empty($files)) {
    http_response_code(400);
    echo json_encode(['error' => 'Kein Bild hochgeladen']);
    exit;
}

// Maximal 3 Bilder zulassen
$files = array_slice($files, 0, 3);

$parts = [
    ['text' => get_recipe_prompt() . "\n\nAnalysiere die folgenden Bilder gemeinsam und extrahiere das Rezept:"],
];

foreach ($files as $file) {
    if (!empty($file['error'])) {
        continue;
    }
    $mimeType = $file['type'] ?? 'image/jpeg';
    $imageData = base64_encode(file_get_contents($file['tmp_name']));
    $parts[] = [
        'inline_data' => [
            'mime_type' => $mimeType,
            'data' => $imageData
        ]
    ];
}

if (count($parts) === 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Keine gültigen Bilder empfangen']);
    exit;
}

$result = gemini_request($config, $parts);

echo json_encode($result);
