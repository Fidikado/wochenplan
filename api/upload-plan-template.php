<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

if (empty($_FILES['template']) || !is_uploaded_file($_FILES['template']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Bitte eine Bildvorlage hochladen']);
    exit;
}

$file = $_FILES['template'];
if (!empty($file['error'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload fehlgeschlagen']);
    exit;
}

if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Datei ist zu groß (max. 15 MB)']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']) ?: '';
finfo_close($finfo);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$ext = $allowed[$mime] ?? null;
if ($ext === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiges Bildformat. Erlaubt: JPG, PNG, WebP.']);
    exit;
}

$templatesDir = rtrim($config['data_dir'], '/\\') . '/templates';
if (!is_dir($templatesDir) && !mkdir($templatesDir, 0775, true) && !is_dir($templatesDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Template-Ordner konnte nicht erstellt werden']);
    exit;
}

$existingTemplate = sqlite_get_plan_template($config);
if (!empty($existingTemplate['path'])) {
    $oldPath = __DIR__ . '/../' . ltrim((string)$existingTemplate['path'], '/');
    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

$filename = 'wochenplan-template-' . date('Ymd-His') . '.' . $ext;
$target = $templatesDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['error' => 'Vorlage konnte nicht gespeichert werden']);
    exit;
}

$relativePath = 'data/templates/' . $filename;
$uploadedAt = date('Y-m-d H:i:s');
sqlite_set_plan_template($config, $relativePath, $mime, $uploadedAt);

$size = @getimagesize($target);
echo json_encode([
    'success' => true,
    'message' => 'Vorlage gespeichert',
    'template' => [
        'path' => $relativePath,
        'uploaded_at' => $uploadedAt,
        'width' => $size[0] ?? null,
        'height' => $size[1] ?? null,
        'size_kb' => round(filesize($target) / 1024, 1),
    ],
]);
