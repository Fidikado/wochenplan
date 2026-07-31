<?php
require_once __DIR__ . '/bootstrap.php';

// .env Datei laden
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$dataDir = __DIR__ . '/data';
codex_init_logging($dataDir);

return [
    'gemini_api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
    'gemini_model'   => $_ENV['GEMINI_MODEL'] ?? 'gemini-2.5-flash,gemini-2.0-flash',
    'gemini_nano_banana_model' => $_ENV['GEMINI_NANO_BANANA_MODEL'] ?? '',
    'data_backend'   => $_ENV['DATA_BACKEND'] ?? 'auto',
    'db_path'        => $_ENV['SQLITE_PATH'] ?? ($dataDir . '/rezeptbuch.sqlite'),
    'rezepte_dir'    => __DIR__ . '/rezepte',
    'images_dir'     => __DIR__ . '/rezepte/images',
    'data_dir'       => $dataDir,
];
