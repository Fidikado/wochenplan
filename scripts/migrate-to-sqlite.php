<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/config.php';
require $root . '/db.php';
require $root . '/recipe-utils.php';
require $root . '/category-utils.php';
require $root . '/sqlite-store.php';

$config = require $root . '/config.php';

if (!db_sqlite_is_available()) {
    fwrite(STDERR, "PDO SQLite ist in dieser PHP-Laufzeit nicht aktiviert.\n");
    exit(1);
}

$pdo = db($config);

try {
    $pdo->exec('DELETE FROM current_shopping_items');
    $pdo->exec('DELETE FROM current_shopping_sections');
    $pdo->exec('DELETE FROM current_plan_days');
    $pdo->exec('DELETE FROM recipe_tags');
    $pdo->exec('DELETE FROM recipes');
    $pdo->exec('DELETE FROM categories WHERE is_default = 0');

    $customCategoriesFile = $config['data_dir'] . '/categories.json';
    if (is_file($customCategoriesFile)) {
        $customCategories = json_decode((string)file_get_contents($customCategoriesFile), true);
        if (is_array($customCategories)) {
            foreach ($customCategories as $item) {
                $label = trim((string)($item['label'] ?? ''));
                if ($label !== '') {
                    add_category($config, $label);
                }
            }
        }
    }

    $recipeIdsByFilename = [];
    $recipeFiles = glob(rtrim($config['rezepte_dir'], '/\\') . '/*.md') ?: [];
    sort($recipeFiles);
    foreach ($recipeFiles as $file) {
        $parsed = recipe_parse_markdown_content((string)file_get_contents($file));
        if ($parsed === null || $parsed['title'] === '') {
            continue;
        }

        $recipe = sqlite_recipe_create($config, [
            'filename' => basename($file),
            'title' => $parsed['title'],
            'kategorie' => $parsed['kategorie'],
            'typ' => $parsed['typ'],
            'kochzeit' => $parsed['kochzeit'],
            'portionen' => $parsed['portionen'],
            'tags' => $parsed['tags'],
            'anmerkung' => $parsed['anmerkung'],
            'image' => $parsed['image'],
            'datum' => $parsed['datum'],
            'body' => $parsed['body'],
            'zutaten' => $parsed['zutaten'],
            'zubereitung' => $parsed['zubereitung'],
        ]);
        $recipeIdsByFilename[$recipe['filename']] = $recipe['id'];
    }

    $planFile = $config['data_dir'] . '/wochenplan.json';
    if (is_file($planFile)) {
        $plan = json_decode((string)file_get_contents($planFile), true);
        if (is_array($plan) && !empty($plan['plan']) && is_array($plan['plan'])) {
            sqlite_save_current_plan($config, $plan['plan'], (string)($plan['erstellt'] ?? date('Y-m-d H:i:s')));
        }
    }

    $shoppingFile = $config['data_dir'] . '/einkaufsliste.json';
    if (is_file($shoppingFile)) {
        $shopping = json_decode((string)file_get_contents($shoppingFile), true);
        if (is_array($shopping) && isset($shopping['kategorien']) && is_array($shopping['kategorien'])) {
            sqlite_save_current_shopping_list($config, $shopping);
        }
    }

    $templateFile = $config['data_dir'] . '/wochenplan-template.json';
    if (is_file($templateFile)) {
        $template = json_decode((string)file_get_contents($templateFile), true);
        $path = trim((string)($template['path'] ?? ''));
        if ($path !== '') {
            sqlite_set_plan_template(
                $config,
                $path,
                trim((string)($template['mime'] ?? '')),
                trim((string)($template['uploaded_at'] ?? date('Y-m-d H:i:s')))
            );
        }
    }

} catch (Throwable $e) {
    fwrite(STDERR, "Migration fehlgeschlagen: " . $e->getMessage() . "\n");
    exit(1);
}

$recipeCount = (int)$pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn();
$categoryCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$planCount = (int)$pdo->query('SELECT COUNT(*) FROM current_plan_days')->fetchColumn();

fwrite(STDOUT, "SQLite-Migration abgeschlossen.\n");
fwrite(STDOUT, "Rezepte: {$recipeCount}\n");
fwrite(STDOUT, "Kategorien: {$categoryCount}\n");
fwrite(STDOUT, "Plan-Tage: {$planCount}\n");
