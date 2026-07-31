<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/recipe-utils.php';
require_once __DIR__ . '/category-utils.php';

function sqlite_recipe_tags_from_row(array $row): array {
    $joined = (string)($row['tags_joined'] ?? $row['tags_cache'] ?? '');
    if ($joined === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode("\n", $joined)), static fn(string $tag): bool => $tag !== ''));
}

function sqlite_recipe_from_row(array $row): array {
    return recipe_to_api_array($row, sqlite_recipe_tags_from_row($row));
}

function sqlite_recipe_query_base(): string {
    return 'SELECT
            r.*,
            COALESCE(GROUP_CONCAT(rt.tag, CHAR(10)), "") AS tags_joined
        FROM recipes r
        LEFT JOIN recipe_tags rt ON rt.recipe_id = r.id';
}

function sqlite_recipe_list_all(array $config, array $options = []): array {
    $sql = sqlite_recipe_query_base();
    $params = [];
    $where = [];

    if (!empty($options['category_id'])) {
        $where[] = 'r.category_id = :category_id';
        $params[':category_id'] = $options['category_id'];
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' GROUP BY r.id ORDER BY lower(r.title), r.id';

    $stmt = db($config)->prepare($sql);
    $stmt->execute($params);
    return array_map('sqlite_recipe_from_row', $stmt->fetchAll());
}

function sqlite_recipe_find_by_filename(array $config, string $filename): ?array {
    $stmt = db($config)->prepare(
        sqlite_recipe_query_base() . '
        WHERE r.legacy_filename = :filename
        GROUP BY r.id
        LIMIT 1'
    );
    $stmt->execute([':filename' => $filename]);
    $row = $stmt->fetch();
    return $row ? sqlite_recipe_from_row($row) : null;
}

function sqlite_recipe_find_row_by_id(array $config, int $recipeId): ?array {
    $stmt = db($config)->prepare(
        sqlite_recipe_query_base() . '
        WHERE r.id = :id
        GROUP BY r.id
        LIMIT 1'
    );
    $stmt->execute([':id' => $recipeId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sqlite_recipe_find_row_by_filename(array $config, string $filename): ?array {
    $stmt = db($config)->prepare(
        sqlite_recipe_query_base() . '
        WHERE r.legacy_filename = :filename
        GROUP BY r.id
        LIMIT 1'
    );
    $stmt->execute([':filename' => $filename]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function sqlite_recipe_next_filename(array $config, string $title): string {
    $slug = recipe_slugify_for_filename($title);
    if ($slug === '') {
        $slug = 'rezept-' . date('Ymd-His');
    }

    $filename = $slug . '.md';
    $stmt = db($config)->prepare('SELECT 1 FROM recipes WHERE legacy_filename = :filename LIMIT 1');
    $stmt->execute([':filename' => $filename]);
    if (!$stmt->fetchColumn()) {
        return $filename;
    }

    $i = 2;
    do {
        $filename = $slug . '-' . $i . '.md';
        $stmt->execute([':filename' => $filename]);
        $i++;
    } while ($stmt->fetchColumn());

    return $filename;
}

function sqlite_recipe_write_tags(PDO $pdo, int $recipeId, array $tags): void {
    $delete = $pdo->prepare('DELETE FROM recipe_tags WHERE recipe_id = :recipe_id');
    $delete->execute([':recipe_id' => $recipeId]);

    if (empty($tags)) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO recipe_tags (recipe_id, tag) VALUES (:recipe_id, :tag)');
    foreach ($tags as $tag) {
        $insert->execute([
            ':recipe_id' => $recipeId,
            ':tag' => $tag,
        ]);
    }
}

function sqlite_recipe_payload(array $config, array $input, ?string $existingFilename = null): array {
    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Titel ist erforderlich');
    }

    $tags = recipe_tags_to_array($input['tags'] ?? []);
    $body = trim((string)($input['body'] ?? ''));
    $zutaten = trim((string)($input['zutaten'] ?? ''));
    $zubereitung = trim((string)($input['zubereitung'] ?? ''));

    if ($body === '') {
        $body = recipe_build_body($zutaten, $zubereitung);
    }
    if ($zutaten === '' || $zubereitung === '') {
        $sections = recipe_extract_sections_from_body($body);
        if ($zutaten === '') {
            $zutaten = $sections['zutaten'];
        }
        if ($zubereitung === '') {
            $zubereitung = $sections['zubereitung'];
        }
    }

    $note = trim((string)($input['anmerkung'] ?? ''));
    $filename = $existingFilename ?: trim((string)($input['filename'] ?? ''));
    if ($filename === '') {
        $filename = sqlite_recipe_next_filename($config, $title);
    }

    $categoryId = normalize_category($config, (string)($input['kategorie'] ?? 'hauptspeise'));
    $type = trim((string)($input['typ'] ?? 'normal'));
    if (!in_array($type, ['normal', 'thermomix', 'airfryer'], true)) {
        $type = 'normal';
    }

    $isRandom = 0;
    foreach ($tags as $tag) {
        if (mb_strtolower($tag, 'UTF-8') === 'zufallsrezept') {
            $isRandom = 1;
            break;
        }
    }

    $createdAt = trim((string)($input['created_at'] ?? ''));
    if ($createdAt === '') {
        $createdAt = trim((string)($input['datum'] ?? ''));
    }
    if ($createdAt === '') {
        $createdAt = date('Y-m-d H:i:s');
    } elseif (!str_contains($createdAt, ':')) {
        $createdAt .= ' 00:00:00';
    }

    return [
        'legacy_filename' => basename($filename),
        'slug' => recipe_slugify_for_filename($title),
        'title' => $title,
        'category_id' => $categoryId,
        'type' => $type,
        'cook_time' => (int)($input['kochzeit'] ?? 30),
        'servings' => (int)($input['portionen'] ?? 4),
        'tags' => $tags,
        'tags_cache' => implode(', ', $tags),
        'note' => $note,
        'image_path' => trim((string)($input['image'] ?? '')),
        'source_url' => preg_match('/^https?:\/\//', $note) ? $note : '',
        'is_random_recipe' => $isRandom,
        'body_markdown' => $body,
        'ingredients_text' => $zutaten,
        'instructions_text' => $zubereitung,
        'created_at' => $createdAt,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function sqlite_recipe_create(array $config, array $input): array {
    $pdo = db($config);
    $payload = sqlite_recipe_payload($config, $input);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO recipes (
                legacy_filename, slug, title, category_id, type, cook_time, servings,
                tags_cache, note, image_path, source_url, is_random_recipe,
                body_markdown, ingredients_text, instructions_text, created_at, updated_at
            ) VALUES (
                :legacy_filename, :slug, :title, :category_id, :type, :cook_time, :servings,
                :tags_cache, :note, :image_path, :source_url, :is_random_recipe,
                :body_markdown, :ingredients_text, :instructions_text, :created_at, :updated_at
            )'
        );
        $stmt->execute([
            ':legacy_filename' => $payload['legacy_filename'],
            ':slug' => $payload['slug'],
            ':title' => $payload['title'],
            ':category_id' => $payload['category_id'],
            ':type' => $payload['type'],
            ':cook_time' => $payload['cook_time'],
            ':servings' => $payload['servings'],
            ':tags_cache' => $payload['tags_cache'],
            ':note' => $payload['note'],
            ':image_path' => $payload['image_path'],
            ':source_url' => $payload['source_url'],
            ':is_random_recipe' => $payload['is_random_recipe'],
            ':body_markdown' => $payload['body_markdown'],
            ':ingredients_text' => $payload['ingredients_text'],
            ':instructions_text' => $payload['instructions_text'],
            ':created_at' => $payload['created_at'],
            ':updated_at' => $payload['updated_at'],
        ]);

        $recipeId = (int)$pdo->lastInsertId();
        sqlite_recipe_write_tags($pdo, $recipeId, $payload['tags']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $recipe = sqlite_recipe_find_by_filename($config, $payload['legacy_filename']);
    if ($recipe === null) {
        throw new RuntimeException('Gespeichertes Rezept konnte nicht geladen werden');
    }
    return $recipe;
}

function sqlite_recipe_update_by_id(array $config, int $recipeId, array $input): array {
    $existing = sqlite_recipe_find_row_by_id($config, $recipeId);
    if ($existing === null) {
        throw new RuntimeException('Rezept nicht gefunden');
    }

    $payload = sqlite_recipe_payload($config, array_merge([
        'image' => $existing['image_path'] ?? '',
        'created_at' => $existing['created_at'] ?? date('Y-m-d H:i:s'),
    ], $input), (string)$existing['legacy_filename']);

    $pdo = db($config);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE recipes SET
                slug = :slug,
                title = :title,
                category_id = :category_id,
                type = :type,
                cook_time = :cook_time,
                servings = :servings,
                tags_cache = :tags_cache,
                note = :note,
                image_path = :image_path,
                source_url = :source_url,
                is_random_recipe = :is_random_recipe,
                body_markdown = :body_markdown,
                ingredients_text = :ingredients_text,
                instructions_text = :instructions_text,
                updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => (int)$existing['id'],
            ':slug' => $payload['slug'],
            ':title' => $payload['title'],
            ':category_id' => $payload['category_id'],
            ':type' => $payload['type'],
            ':cook_time' => $payload['cook_time'],
            ':servings' => $payload['servings'],
            ':tags_cache' => $payload['tags_cache'],
            ':note' => $payload['note'],
            ':image_path' => $payload['image_path'],
            ':source_url' => $payload['source_url'],
            ':is_random_recipe' => $payload['is_random_recipe'],
            ':body_markdown' => $payload['body_markdown'],
            ':ingredients_text' => $payload['ingredients_text'],
            ':instructions_text' => $payload['instructions_text'],
            ':updated_at' => $payload['updated_at'],
        ]);
        sqlite_recipe_write_tags($pdo, (int)$existing['id'], $payload['tags']);
        sqlite_plan_refresh_recipe_snapshot($config, (int)$existing['id']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $recipe = sqlite_recipe_find_by_id($config, (int)$existing['id']);
    if ($recipe === null) {
        throw new RuntimeException('Aktualisiertes Rezept konnte nicht geladen werden');
    }
    return $recipe;
}

function sqlite_plan_snapshot_from_recipe(array $recipe): array {
    return [
        'id' => isset($recipe['id']) ? (int)$recipe['id'] : null,
        'title' => $recipe['title'] ?? 'Unbekannt',
        'kategorie' => $recipe['kategorie'] ?? 'hauptspeise',
        'typ' => $recipe['typ'] ?? 'normal',
        'kochzeit' => (int)($recipe['kochzeit'] ?? 30),
        'portionen' => (int)($recipe['portionen'] ?? 4),
        'tags' => recipe_tags_to_array($recipe['tags'] ?? []),
        'anmerkung' => $recipe['anmerkung'] ?? '',
        'image' => $recipe['image'] ?? '',
        'body' => $recipe['body'] ?? '',
    ];
}

function sqlite_plan_resolve_recipe(array $config, array $recipe): ?array {
    $recipeId = isset($recipe['id']) ? (int)$recipe['id'] : 0;
    if ($recipeId > 0) {
        $resolved = sqlite_recipe_find_by_id($config, $recipeId);
        if ($resolved !== null) {
            return $resolved;
        }
    }

    $filename = trim((string)($recipe['filename'] ?? ''));
    if ($filename !== '') {
        // Legacy fallback for older plan snapshots that still only carry a filename.
        return sqlite_recipe_find_by_filename($config, $filename);
    }

    return null;
}

function sqlite_plan_normalize_snapshot(array $config, $snapshot): array {
    if (!is_array($snapshot)) {
        return recipe_plan_placeholder();
    }

    $resolved = sqlite_plan_resolve_recipe($config, $snapshot);
    if ($resolved !== null) {
        return sqlite_plan_snapshot_from_recipe($resolved);
    }

    return sqlite_plan_snapshot_from_recipe($snapshot);
}

function sqlite_load_current_plan(array $config): ?array {
    $stmt = db($config)->query('SELECT position, day_name, recipe_snapshot, is_disabled FROM current_plan_days ORDER BY position');
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        return null;
    }

    $plan = [];
    foreach ($rows as $row) {
        $snapshot = json_decode((string)$row['recipe_snapshot'], true);
        $plan[] = [
            'tag' => (string)$row['day_name'],
            'disabled' => (bool)(int)($row['is_disabled'] ?? 0),
            'recipe' => sqlite_plan_normalize_snapshot($config, $snapshot),
        ];
    }

    return [
        'erstellt' => db_setting_get($config, 'plan_created_at', ''),
        'plan' => $plan,
    ];
}

function sqlite_save_current_plan(array $config, array $plan, ?string $createdAt = null): array {
    $pdo = db($config);
    $createdAt = $createdAt ?: date('Y-m-d H:i:s');

    $resolvedRows = [];
    foreach (array_values($plan) as $index => $day) {
        $recipe = $day['recipe'] ?? [];
        $dbRecipe = sqlite_plan_resolve_recipe($config, is_array($recipe) ? $recipe : []);
        $snapshot = $dbRecipe ? sqlite_plan_snapshot_from_recipe($dbRecipe) : sqlite_plan_snapshot_from_recipe($recipe);
        $resolvedRecipeId = $dbRecipe['id'] ?? null;

        $resolvedRows[] = [
            'position' => $index,
            'day_name' => (string)($day['tag'] ?? 'Unbekannt'),
            'recipe_id' => $resolvedRecipeId,
            'recipe_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_disabled' => isset($day['disabled']) && $day['disabled'] ? 1 : 0,
        ];
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM current_plan_days');
        $insert = $pdo->prepare(
            'INSERT INTO current_plan_days (position, day_name, recipe_id, recipe_snapshot, is_disabled)
             VALUES (:position, :day_name, :recipe_id, :recipe_snapshot, :is_disabled)'
        );
        foreach ($resolvedRows as $row) {
            $insert->execute([
                ':position' => $row['position'],
                ':day_name' => $row['day_name'],
                ':recipe_id' => $row['recipe_id'],
                ':recipe_snapshot' => $row['recipe_snapshot'],
                ':is_disabled' => $row['is_disabled'],
            ]);
        }
        db_setting_set($config, 'plan_created_at', $createdAt);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return sqlite_load_current_plan($config) ?? ['erstellt' => $createdAt, 'plan' => []];
}

function sqlite_plan_refresh_recipe_snapshot(array $config, int $recipeId): void {
    $recipe = sqlite_recipe_find_by_id($config, $recipeId);
    if ($recipe === null) {
        return;
    }

    $snapshot = json_encode(
        sqlite_plan_snapshot_from_recipe($recipe),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $stmt = db($config)->prepare(
        'UPDATE current_plan_days
         SET recipe_snapshot = :recipe_snapshot
         WHERE recipe_id = :recipe_id'
    );
    $stmt->execute([
        ':recipe_snapshot' => $snapshot,
        ':recipe_id' => $recipeId,
    ]);
}

function sqlite_recipe_find_by_id(array $config, int $recipeId): ?array {
    $stmt = db($config)->prepare(
        sqlite_recipe_query_base() . '
        WHERE r.id = :id
        GROUP BY r.id
        LIMIT 1'
    );
    $stmt->execute([':id' => $recipeId]);
    $row = $stmt->fetch();
    return $row ? sqlite_recipe_from_row($row) : null;
}

function sqlite_plan_replace_recipe_with_placeholder(array $config, int $recipeId): void {
    $stmt = db($config)->prepare(
        'UPDATE current_plan_days
         SET recipe_id = NULL, recipe_snapshot = :recipe_snapshot
         WHERE recipe_id = :recipe_id'
    );
    $stmt->execute([
        ':recipe_snapshot' => json_encode(recipe_plan_placeholder(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':recipe_id' => $recipeId,
    ]);
}

function sqlite_recipe_delete_by_id(array $config, int $recipeId): ?array {
    $existing = sqlite_recipe_find_row_by_id($config, $recipeId);
    if ($existing === null) {
        return null;
    }

    $pdo = db($config);
    $pdo->beginTransaction();
    try {
        sqlite_plan_replace_recipe_with_placeholder($config, (int)$existing['id']);
        $stmt = $pdo->prepare('DELETE FROM recipes WHERE id = :id');
        $stmt->execute([':id' => (int)$existing['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'id' => (int)$existing['id'],
        'title' => (string)($existing['title'] ?? ''),
        'image' => (string)($existing['image_path'] ?? ''),
    ];
}

function sqlite_load_current_shopping_list(array $config): ?array {
    $sectionsStmt = db($config)->query('SELECT id, name, position FROM current_shopping_sections ORDER BY position, id');
    $sections = $sectionsStmt->fetchAll();
    if (empty($sections)) {
        return null;
    }

    $itemsStmt = db($config)->prepare(
        'SELECT item_text FROM current_shopping_items WHERE section_id = :section_id ORDER BY position, id'
    );

    $resultSections = [];
    foreach ($sections as $section) {
        $itemsStmt->execute([':section_id' => (int)$section['id']]);
        $items = array_map(
            static fn(array $row): string => (string)$row['item_text'],
            $itemsStmt->fetchAll()
        );
        $resultSections[] = [
            'name' => (string)$section['name'],
            'zutaten' => $items,
        ];
    }

    return [
        'erstellt' => db_setting_get($config, 'shopping_list_created_at', ''),
        'wochenplan_erstellt' => db_setting_get($config, 'shopping_list_plan_created_at', ''),
        'kategorien' => $resultSections,
    ];
}

function sqlite_save_current_shopping_list(array $config, array $data): array {
    $pdo = db($config);
    $sections = $data['kategorien'] ?? [];
    $createdAt = (string)($data['erstellt'] ?? date('Y-m-d H:i:s'));
    $planCreatedAt = (string)($data['wochenplan_erstellt'] ?? '');

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM current_shopping_items');
        $pdo->exec('DELETE FROM current_shopping_sections');

        $insertSection = $pdo->prepare(
            'INSERT INTO current_shopping_sections (name, position) VALUES (:name, :position)'
        );
        $insertItem = $pdo->prepare(
            'INSERT INTO current_shopping_items (section_id, item_text, position)
             VALUES (:section_id, :item_text, :position)'
        );

        foreach (array_values($sections) as $sectionIndex => $section) {
            $insertSection->execute([
                ':name' => (string)($section['name'] ?? 'Sonstiges'),
                ':position' => $sectionIndex,
            ]);
            $sectionId = (int)$pdo->lastInsertId();

            foreach (array_values($section['zutaten'] ?? []) as $itemIndex => $itemText) {
                $insertItem->execute([
                    ':section_id' => $sectionId,
                    ':item_text' => (string)$itemText,
                    ':position' => $itemIndex,
                ]);
            }
        }

        db_setting_set($config, 'shopping_list_created_at', $createdAt);
        db_setting_set($config, 'shopping_list_plan_created_at', $planCreatedAt);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return sqlite_load_current_shopping_list($config) ?? [
        'erstellt' => $createdAt,
        'wochenplan_erstellt' => $planCreatedAt,
        'kategorien' => [],
    ];
}

function sqlite_remove_shopping_list_item(array $config, int $sectionIndex, int $itemIndex): array {
    $data = sqlite_load_current_shopping_list($config);
    if ($data === null || !isset($data['kategorien'][$sectionIndex]['zutaten'][$itemIndex])) {
        throw new RuntimeException('Zutat nicht gefunden');
    }

    array_splice($data['kategorien'][$sectionIndex]['zutaten'], $itemIndex, 1);
    $data['kategorien'] = array_values(array_filter(
        $data['kategorien'],
        static fn(array $section): bool => !empty($section['zutaten'])
    ));

    return sqlite_save_current_shopping_list($config, $data);
}

function sqlite_get_plan_template(array $config): ?array {
    $path = trim((string)db_setting_get($config, 'plan_template_path', ''));
    if ($path === '') {
        return null;
    }

    return [
        'path' => $path,
        'mime' => trim((string)db_setting_get($config, 'plan_template_mime', '')),
        'uploaded_at' => trim((string)db_setting_get($config, 'plan_template_uploaded_at', '')),
    ];
}

function sqlite_set_plan_template(array $config, string $path, string $mime, string $uploadedAt): array {
    db_setting_set($config, 'plan_template_path', $path);
    db_setting_set($config, 'plan_template_mime', $mime);
    db_setting_set($config, 'plan_template_uploaded_at', $uploadedAt);

    return [
        'path' => $path,
        'mime' => $mime,
        'uploaded_at' => $uploadedAt,
    ];
}

function sqlite_get_plan_print_model(array $config): string {
    return trim((string)db_setting_get($config, 'plan_print_model', ''));
}

function sqlite_set_plan_print_model(array $config, string $model): string {
    db_setting_set($config, 'plan_print_model', trim($model));
    return sqlite_get_plan_print_model($config);
}

function sqlite_rate_recipe(array $config, int $recipeId, int $rating): array {
    if ($rating < 1 || $rating > 5) {
        throw new InvalidArgumentException('Bewertung muss zwischen 1 und 5 liegen');
    }
    $stmt = db($config)->prepare('UPDATE recipes SET rating = :rating WHERE id = :id');
    $stmt->execute([':rating' => $rating, ':id' => $recipeId]);

    $recipe = sqlite_recipe_find_by_id($config, $recipeId);
    if ($recipe === null) {
        throw new RuntimeException('Rezept nicht gefunden');
    }
    return $recipe;
}

function sqlite_confirm_plan(array $config): array {
    $plan = sqlite_load_current_plan($config);
    if ($plan === null || empty($plan['plan'])) {
        throw new RuntimeException('Kein Wochenplan vorhanden');
    }

    $now = date('Y-m-d H:i:s');
    $weekNum = (int)date('W');
    $year = (int)date('Y');
    $weekLabel = "KW $weekNum / $year";

    $pdo = db($config);

    // plan_appearances für aktive Tage erhöhen
    $ids = [];
    foreach ($plan['plan'] as $day) {
        if (!empty($day['disabled'])) {
            continue;
        }
        $recipeId = isset($day['recipe']['id']) ? (int)$day['recipe']['id'] : 0;
        if ($recipeId > 0) {
            $ids[] = $recipeId;
        }
    }
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE recipes SET plan_appearances = plan_appearances + 1 WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }

    // Bestätigten Plan speichern
    $stmt = $pdo->prepare(
        'INSERT INTO confirmed_plans (week_label, plan_json, confirmed_at)
         VALUES (:week_label, :plan_json, :confirmed_at)'
    );
    $stmt->execute([
        ':week_label' => $weekLabel,
        ':plan_json' => json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':confirmed_at' => $now,
    ]);
    $confirmedId = (int)$pdo->lastInsertId();

    return [
        'id' => $confirmedId,
        'week_label' => $weekLabel,
        'confirmed_at' => $now,
        'recipe_count' => count($ids),
    ];
}

function sqlite_list_confirmed_plans(array $config): array {
    $stmt = db($config)->query(
        'SELECT id, week_label, confirmed_at FROM confirmed_plans ORDER BY confirmed_at DESC'
    );
    return array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'week_label' => (string)$row['week_label'],
        'confirmed_at' => (string)$row['confirmed_at'],
    ], $stmt->fetchAll());
}

function sqlite_get_confirmed_plan(array $config, int $id): ?array {
    $stmt = db($config)->prepare(
        'SELECT id, week_label, plan_json, confirmed_at FROM confirmed_plans WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $plan = json_decode((string)$row['plan_json'], true);
    return [
        'id' => (int)$row['id'],
        'week_label' => (string)$row['week_label'],
        'confirmed_at' => (string)$row['confirmed_at'],
        'plan' => is_array($plan) ? $plan : [],
    ];
}

// === Kochmodus ===========================================================

/**
 * Der Kochfortschritt der laufenden Session. Immer hoechstens eine Zeile -
 * die App kennt keine Benutzerverwaltung, es gibt genau einen Koch.
 */
function sqlite_load_cook_progress(array $config): ?array {
    $stmt = db($config)->query('SELECT * FROM cook_progress WHERE id = 1');
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    // Zeigt der Fortschritt auf ein geloeschtes Rezept, ist er wertlos.
    $recipeId = (int)$row['recipe_id'];
    if (sqlite_recipe_find_row_by_id($config, $recipeId) === null) {
        sqlite_clear_cook_progress($config);
        return null;
    }

    return [
        'recipe_id' => $recipeId,
        'step_index' => (int)$row['step_index'],
        'servings' => (int)$row['servings'],
        'timer_seconds' => $row['timer_seconds'] === null ? null : (int)$row['timer_seconds'],
        'timer_ends_at' => $row['timer_ends_at'] === null ? null : (string)$row['timer_ends_at'],
        'timer_remaining' => $row['timer_remaining'] === null ? null : (int)$row['timer_remaining'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function sqlite_save_cook_progress(array $config, array $progress): array {
    $recipeId = (int)($progress['recipe_id'] ?? 0);
    if ($recipeId <= 0) {
        throw new InvalidArgumentException('Rezept-ID fehlt');
    }
    if (sqlite_recipe_find_row_by_id($config, $recipeId) === null) {
        throw new InvalidArgumentException('Rezept nicht gefunden');
    }

    $stmt = db($config)->prepare(
        'INSERT INTO cook_progress (id, recipe_id, step_index, servings, timer_seconds, timer_ends_at, timer_remaining, updated_at)
         VALUES (1, :recipe_id, :step_index, :servings, :timer_seconds, :timer_ends_at, :timer_remaining, :updated_at)
         ON CONFLICT(id) DO UPDATE SET
            recipe_id = excluded.recipe_id,
            step_index = excluded.step_index,
            servings = excluded.servings,
            timer_seconds = excluded.timer_seconds,
            timer_ends_at = excluded.timer_ends_at,
            timer_remaining = excluded.timer_remaining,
            updated_at = excluded.updated_at'
    );

    $stmt->execute([
        ':recipe_id' => $recipeId,
        ':step_index' => max(0, (int)($progress['step_index'] ?? 0)),
        ':servings' => max(0, (int)($progress['servings'] ?? 0)),
        ':timer_seconds' => isset($progress['timer_seconds']) ? (int)$progress['timer_seconds'] : null,
        ':timer_ends_at' => $progress['timer_ends_at'] ?? null,
        ':timer_remaining' => isset($progress['timer_remaining']) ? (int)$progress['timer_remaining'] : null,
        ':updated_at' => date('Y-m-d H:i:s'),
    ]);

    return sqlite_load_cook_progress($config) ?? [];
}

function sqlite_clear_cook_progress(array $config): void {
    db($config)->exec('DELETE FROM cook_progress');
}
