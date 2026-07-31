<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../category-utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$input = !empty($_POST) ? $_POST : json_decode(file_get_contents('php://input'), true);
$id = trim((string)($input['id'] ?? ''));

$result = delete_category($config, $id);
if (isset($result['error'])) {
    http_response_code(400);
    echo json_encode($result);
    exit;
}

echo json_encode([
    'message' => 'Kategorie gelöscht',
    'categories' => categories_with_meta($config),
]);
