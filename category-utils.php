<?php

require_once __DIR__ . '/db.php';

function category_default_items(): array {
    return [
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
}

function category_default_ids(): array {
    return array_map(static fn(array $item): string => $item['id'], category_default_items());
}

function category_slugify(string $label): string {
    $value = trim(mb_strtolower($label, 'UTF-8'));
    $map = [
        'ä' => 'ae',
        'ö' => 'oe',
        'ü' => 'ue',
        'ß' => 'ss',
    ];
    $value = strtr($value, $map);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim((string)$value, '-');
}

function load_categories(array $config): array {
    $stmt = db($config)->query('SELECT id, label FROM categories ORDER BY lower(label), id');
    return array_map(
        static fn(array $row): array => [
            'id' => (string)$row['id'],
            'label' => (string)$row['label'],
        ],
        $stmt->fetchAll()
    );
}

function category_usage_count(array $config, string $categoryId): int {
    $stmt = db($config)->prepare('SELECT COUNT(*) FROM recipes WHERE category_id = :category_id');
    $stmt->execute([':category_id' => $categoryId]);
    return (int)$stmt->fetchColumn();
}

function categories_with_meta(array $config): array {
    $stmt = db($config)->query(
        'SELECT c.id, c.label, c.is_default, COUNT(r.id) AS usage_count
         FROM categories c
         LEFT JOIN recipes r ON r.category_id = c.id
         GROUP BY c.id, c.label, c.is_default
         ORDER BY c.is_default DESC, lower(c.label), c.id'
    );

    return array_map(
        static fn(array $row): array => [
            'id' => (string)$row['id'],
            'label' => (string)$row['label'],
            'is_default' => (bool)$row['is_default'],
            'usage_count' => (int)$row['usage_count'],
        ],
        $stmt->fetchAll()
    );
}

function category_label_map(array $config): array {
    $map = [];
    foreach (load_categories($config) as $item) {
        $map[$item['id']] = $item['label'];
    }
    return $map;
}

function normalize_category(array $config, string $category): string {
    $category = trim($category);
    if ($category === '') {
        return 'hauptspeise';
    }

    $stmt = db($config)->prepare('SELECT 1 FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $category]);
    return $stmt->fetchColumn() ? $category : 'hauptspeise';
}

function add_category(array $config, string $label): array {
    $label = trim($label);
    if ($label === '') {
        return ['error' => 'Bitte einen Kategorienamen eingeben'];
    }

    $id = category_slugify($label);
    if ($id === '') {
        return ['error' => 'Kategoriename ist ungültig'];
    }

    $pdo = db($config);
    $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    if ($stmt->fetchColumn()) {
        return ['error' => 'Diese Kategorie existiert bereits'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO categories (id, label, is_default, created_at)
         VALUES (:id, :label, 0, :created_at)'
    );
    $insert->execute([
        ':id' => $id,
        ':label' => $label,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);

    return ['id' => $id, 'label' => $label];
}

function delete_category(array $config, string $id): array {
    $id = trim($id);
    if ($id === '') {
        return ['error' => 'Ungültige Kategorie'];
    }

    if (in_array($id, category_default_ids(), true)) {
        return ['error' => 'Standardkategorien können nicht gelöscht werden'];
    }

    if (category_usage_count($config, $id) > 0) {
        return ['error' => 'Kategorie wird noch in Rezepten verwendet und kann nicht gelöscht werden'];
    }

    $stmt = db($config)->prepare('DELETE FROM categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() < 1) {
        return ['error' => 'Kategorie nicht gefunden'];
    }

    return ['success' => true];
}
