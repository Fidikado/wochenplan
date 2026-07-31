<?php

function recipe_slugify_for_filename(string $title): string {
    $slug = mb_strtolower(trim($title), 'UTF-8');
    $slug = preg_replace('/[^a-z0-9äöüß]+/u', '-', $slug);
    return trim((string)$slug, '-');
}

function recipe_tags_to_array($tags): array {
    if (is_array($tags)) {
        $out = [];
        foreach ($tags as $tag) {
            $tag = trim((string)$tag);
            if ($tag !== '') {
                $out[] = $tag;
            }
        }
        return array_values(array_unique($out));
    }

    if (is_string($tags)) {
        $parts = array_map('trim', explode(',', $tags));
        $parts = array_values(array_filter($parts, static fn(string $tag): bool => $tag !== ''));
        return array_values(array_unique($parts));
    }

    return [];
}

function recipe_build_body(string $zutaten, string $zubereitung): string {
    $zutaten = trim($zutaten);
    $zubereitung = trim($zubereitung);

    $md = "## Zutaten\n";
    foreach (explode("\n", $zutaten) as $zeile) {
        $zeile = trim($zeile);
        if ($zeile !== '') {
            $zeile = ltrim($zeile, '-* ');
            $md .= "- $zeile\n";
        }
    }

    $md .= "\n## Zubereitung\n";
    $schritt = 1;
    foreach (explode("\n", $zubereitung) as $zeile) {
        $zeile = trim($zeile);
        if ($zeile !== '') {
            $zeile = preg_replace('/^\d+[\.\)]\s*/', '', $zeile);
            $md .= "$schritt. $zeile\n";
            $schritt++;
        }
    }

    return trim($md);
}

function recipe_extract_sections_from_body(string $body): array {
    $zutaten = '';
    $zubereitung = '';

    if (preg_match('/## Zutaten\n([\s\S]*?)(?=\n## |$)/', $body, $match)) {
        $zutaten = trim(preg_replace('/^- /m', '', trim($match[1])));
    }
    if (preg_match('/## Zubereitung\n([\s\S]*?)$/', $body, $match)) {
        $zubereitung = trim(preg_replace('/^\d+[\.\)]\s*/m', '', trim($match[1])));
    }

    return [
        'zutaten' => $zutaten,
        'zubereitung' => $zubereitung,
    ];
}

function recipe_parse_markdown_content(string $content): ?array {
    if (!preg_match('/^---\s*\n(.+?)\n---\s*\n(.*)/s', $content, $matches)) {
        return null;
    }

    $frontmatter = [];
    foreach (explode("\n", $matches[1]) as $line) {
        if (!preg_match('/^(\w+):\s*(.+)$/', $line, $kv)) {
            continue;
        }

        $key = $kv[1];
        $val = trim($kv[2]);
        if (preg_match('/^\[(.*)\]$/', $val, $arr)) {
            $val = recipe_tags_to_array($arr[1]);
        } elseif (is_numeric($val)) {
            $val = (int)$val;
        } else {
            $val = trim($val, "\"'");
        }
        $frontmatter[$key] = $val;
    }

    $body = trim($matches[2]);
    $sections = recipe_extract_sections_from_body($body);

    return [
        'title' => trim((string)($frontmatter['title'] ?? '')),
        'kategorie' => trim((string)($frontmatter['kategorie'] ?? 'hauptspeise')) ?: 'hauptspeise',
        'typ' => trim((string)($frontmatter['typ'] ?? 'normal')) ?: 'normal',
        'kochzeit' => (int)($frontmatter['kochzeit'] ?? 30),
        'portionen' => (int)($frontmatter['portionen'] ?? 4),
        'tags' => recipe_tags_to_array($frontmatter['tags'] ?? []),
        'anmerkung' => trim((string)($frontmatter['anmerkung'] ?? '')),
        'image' => trim((string)($frontmatter['image'] ?? '')),
        'datum' => trim((string)($frontmatter['datum'] ?? date('Y-m-d'))),
        'body' => $body,
        'zutaten' => $sections['zutaten'],
        'zubereitung' => $sections['zubereitung'],
    ];
}

function recipe_plan_placeholder(): array {
    return [
        'id' => null,
        'title' => 'Rezept gelöscht – bitte ersetzen',
        'kategorie' => 'hauptspeise',
        'typ' => 'normal',
        'kochzeit' => 0,
        'portionen' => 0,
        'tags' => [],
        'anmerkung' => '',
        'image' => '',
        'body' => '',
    ];
}

function recipe_to_api_array(array $row, array $tags): array {
    return [
        'id' => isset($row['id']) ? (int)$row['id'] : null,
        'title' => (string)($row['title'] ?? ''),
        'kategorie' => (string)($row['category_id'] ?? 'hauptspeise'),
        'typ' => (string)($row['type'] ?? 'normal'),
        'kochzeit' => (int)($row['cook_time'] ?? 30),
        'portionen' => (int)($row['servings'] ?? 4),
        'tags' => array_values($tags),
        'anmerkung' => (string)($row['note'] ?? ''),
        'image' => (string)($row['image_path'] ?? ''),
        'datum' => substr((string)($row['created_at'] ?? date('Y-m-d H:i:s')), 0, 10),
        'body' => (string)($row['body_markdown'] ?? ''),
        'rating' => (int)($row['rating'] ?? 0),
        'plan_appearances' => (int)($row['plan_appearances'] ?? 0),
    ];
}
