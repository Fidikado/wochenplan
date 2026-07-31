<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $plan = sqlite_get_confirmed_plan($config, $id);
        if ($plan === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Plan nicht gefunden']);
            exit;
        }
        echo json_encode($plan);
        exit;
    }

    echo json_encode(sqlite_list_confirmed_plans($config));
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
