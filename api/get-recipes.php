<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';

echo json_encode(sqlite_recipe_list_all($config));
