<?php

$config = require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$file = basename((string)($_GET['file'] ?? ''));
if ($file === '' || !preg_match('/^wochenplan-print-\d{8}-\d{6}(?:-[a-f0-9]{6})?\.(png|jpg|webp)$/', $file)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ungültiger Dateiname']);
    exit;
}

$path = $config['data_dir'] . '/prints/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Datei nicht gefunden']);
    exit;
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($path);
