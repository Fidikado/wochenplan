<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';
require_once __DIR__ . '/plan-print-lib.php';

$template = sqlite_get_plan_template($config);
$currentModel = plan_print_resolve_image_model($config)[0];
$availableModels = plan_print_available_models();
if ($template === null || empty($template['path'])) {
    echo json_encode([
        'template' => null,
        'print_model' => [
            'current' => $currentModel,
            'options' => $availableModels,
        ],
    ]);
    exit;
}

$relativePath = ltrim((string)$template['path'], '/');
$fullPath = __DIR__ . '/../' . $relativePath;
if (!is_file($fullPath)) {
    echo json_encode(['template' => null]);
    exit;
}

$size = @getimagesize($fullPath);
echo json_encode([
    'template' => [
        'path' => $relativePath,
        'uploaded_at' => $template['uploaded_at'] ?? '',
        'width' => $size[0] ?? null,
        'height' => $size[1] ?? null,
        'size_kb' => round(filesize($fullPath) / 1024, 1),
    ],
    'print_model' => [
        'current' => $currentModel,
        'options' => $availableModels,
    ],
]);
