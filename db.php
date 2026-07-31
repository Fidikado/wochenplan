<?php

function db_sqlite_is_available(): bool {
    if (!class_exists('PDO')) {
        return false;
    }

    return in_array('sqlite', PDO::getAvailableDrivers(), true);
}

function db_backend_uses_sqlite(array $config): bool {
    $backend = trim((string)($config['data_backend'] ?? 'auto'));
    if ($backend === 'files') {
        return false;
    }

    return db_sqlite_is_available();
}

function db(array $config): PDO {
    static $connections = [];

    $path = (string)($config['db_path'] ?? '');
    if ($path === '') {
        throw new RuntimeException('SQLite-Pfad fehlt in der Konfiguration');
    }
    if (!db_sqlite_is_available()) {
        throw new RuntimeException('PDO SQLite ist in PHP nicht aktiviert');
    }

    if (isset($connections[$path])) {
        return $connections[$path];
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('SQLite-Datenordner konnte nicht erstellt werden');
    }

    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    db_initialize_schema($pdo);

    $connections[$path] = $pdo;
    return $pdo;
}

function db_initialize_schema(PDO $pdo): void {
    static $initialized = [];

    $key = spl_object_id($pdo);
    if (isset($initialized[$key])) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS categories (
            id TEXT PRIMARY KEY,
            label TEXT NOT NULL,
            is_default INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS recipes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            legacy_filename TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL,
            title TEXT NOT NULL,
            category_id TEXT NOT NULL,
            type TEXT NOT NULL,
            cook_time INTEGER NOT NULL DEFAULT 30,
            servings INTEGER NOT NULL DEFAULT 4,
            tags_cache TEXT NOT NULL DEFAULT "",
            note TEXT NOT NULL DEFAULT "",
            image_path TEXT NOT NULL DEFAULT "",
            source_url TEXT NOT NULL DEFAULT "",
            is_random_recipe INTEGER NOT NULL DEFAULT 0,
            body_markdown TEXT NOT NULL DEFAULT "",
            ingredients_text TEXT NOT NULL DEFAULT "",
            instructions_text TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS recipe_tags (
            recipe_id INTEGER NOT NULL,
            tag TEXT NOT NULL,
            PRIMARY KEY (recipe_id, tag),
            FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS current_plan_days (
            position INTEGER PRIMARY KEY,
            day_name TEXT NOT NULL,
            recipe_id INTEGER NULL,
            recipe_snapshot TEXT NOT NULL,
            is_disabled INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE SET NULL
        )'
    );

    // Migration: Spalte is_disabled nachrüsten falls nicht vorhanden
    $cols = $pdo->query('PRAGMA table_info(current_plan_days)')->fetchAll();
    $colNames = array_column($cols, 'name');
    if (!in_array('is_disabled', $colNames, true)) {
        $pdo->exec('ALTER TABLE current_plan_days ADD COLUMN is_disabled INTEGER NOT NULL DEFAULT 0');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS current_shopping_sections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            position INTEGER NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS current_shopping_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            section_id INTEGER NOT NULL,
            item_text TEXT NOT NULL,
            position INTEGER NOT NULL,
            FOREIGN KEY (section_id) REFERENCES current_shopping_sections(id) ON DELETE CASCADE
        )'
    );

    db_seed_default_categories($pdo);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS confirmed_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            week_label TEXT NOT NULL,
            plan_json TEXT NOT NULL,
            confirmed_at TEXT NOT NULL
        )'
    );

    // Kochmodus: genau eine laufende Session, passend zum current_*-Muster.
    // timer_ends_at ist ein absoluter Zeitpunkt, damit ein Reload im Browser
    // die zwischenzeitlich vergangene Zeit nicht verschluckt.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cook_progress (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            recipe_id INTEGER NOT NULL,
            step_index INTEGER NOT NULL DEFAULT 0,
            servings INTEGER NOT NULL DEFAULT 0,
            timer_seconds INTEGER NULL,
            timer_ends_at TEXT NULL,
            timer_remaining INTEGER NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
        )'
    );

    // Migrations für recipes-Tabelle
    $recipeCols = array_column($pdo->query('PRAGMA table_info(recipes)')->fetchAll(), 'name');
    if (!in_array('rating', $recipeCols, true)) {
        $pdo->exec('ALTER TABLE recipes ADD COLUMN rating INTEGER NOT NULL DEFAULT 0');
    }
    if (!in_array('plan_appearances', $recipeCols, true)) {
        $pdo->exec('ALTER TABLE recipes ADD COLUMN plan_appearances INTEGER NOT NULL DEFAULT 0');
    }

    $initialized[$key] = true;
}

function db_seed_default_categories(PDO $pdo): void {
    $defaults = [
        ['id' => 'hauptspeise', 'label' => 'Hauptspeise'],
        ['id' => 'vorspeise', 'label' => 'Vorspeise'],
        ['id' => 'suppe', 'label' => 'Suppe'],
        ['id' => 'salat', 'label' => 'Salat'],
        ['id' => 'beilage', 'label' => 'Beilage'],
        ['id' => 'fruehstueck', 'label' => 'Frühstück'],
        ['id' => 'brot', 'label' => 'Brot'],
        ['id' => 'kuchen', 'label' => 'Kuchen'],
        ['id' => 'dessert', 'label' => 'Dessert'],
        ['id' => 'getraenk', 'label' => 'Getränk'],
        ['id' => 'snack', 'label' => 'Snack'],
    ];

    $existingStmt = $pdo->query('SELECT id FROM categories WHERE is_default = 1');
    $existingDefaultIds = [];
    foreach ($existingStmt->fetchAll() as $row) {
        $existingDefaultIds[(string)($row['id'] ?? '')] = true;
    }

    if (count($existingDefaultIds) >= count($defaults)) {
        return;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO categories (id, label, is_default, created_at)
         VALUES (:id, :label, 1, :created_at)'
    );

    $now = date('Y-m-d H:i:s');
    foreach ($defaults as $item) {
        if (isset($existingDefaultIds[$item['id']])) {
            continue;
        }
        $insertStmt->execute([
            ':id' => $item['id'],
            ':label' => $item['label'],
            ':created_at' => $now,
        ]);
    }
}

function db_setting_get(array $config, string $key, ?string $default = null): ?string {
    $stmt = db($config)->prepare('SELECT value FROM app_settings WHERE key = :key');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function db_setting_set(array $config, string $key, string $value): void {
    $stmt = db($config)->prepare(
        'INSERT INTO app_settings (key, value)
         VALUES (:key, :value)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value'
    );
    $stmt->execute([
        ':key' => $key,
        ':value' => $value,
    ]);
}

function db_setting_delete(array $config, string $key): void {
    $stmt = db($config)->prepare('DELETE FROM app_settings WHERE key = :key');
    $stmt->execute([':key' => $key]);
}
