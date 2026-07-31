<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../category-utils.php';

echo json_encode([
    'categories' => categories_with_meta($config),
]);
