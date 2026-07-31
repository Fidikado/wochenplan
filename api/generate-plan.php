<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';
require_once __DIR__ . '/url-import.php';

function rezeptwelt_category_urls(): array {
    return [
        'https://www.rezeptwelt.de/kategorien/hauptgerichte-mit-fleisch',
        'https://www.rezeptwelt.de/kategorien/hauptgerichte-mit-fisch-meeresfruchten',
        'https://www.rezeptwelt.de/kategorien/hauptgerichte-mit-gemuse',
        'https://www.rezeptwelt.de/kategorien/sonstige-hauptgerichte',
    ];
}

function rezeptwelt_pick_random_recipe_url(): array {
    $base = 'https://www.rezeptwelt.de';
    $categories = rezeptwelt_category_urls();
    shuffle($categories);

    foreach ($categories as $categoryUrl) {
        $fetch = http_fetch_html($categoryUrl, 20);
        if (isset($fetch['error'])) {
            continue;
        }

        $html = (string)($fetch['html'] ?? '');
        if ($html === '') {
            continue;
        }

        $urls = [];
        preg_match_all('~https?://(?:www\.)?rezeptwelt\.de/([^"\'\\s<>?#]+)~i', $html, $matches);
        foreach ($matches[1] ?? [] as $path) {
            $path = trim((string)$path, '/');
            if ($path !== '') {
                $urls[] = $base . '/' . $path;
            }
        }

        preg_match_all('~href=["\']?(/([^"\'\\s<>?#]+))~i', $html, $matches);
        foreach ($matches[2] ?? [] as $path) {
            $path = trim((string)$path, '/');
            if ($path !== '') {
                $urls[] = $base . '/' . $path;
            }
        }

        $urls = array_values(array_unique(array_filter($urls, static function (string $url): bool {
            $url = strtolower($url);
            if (str_contains($url, '/kategorien/')) {
                return false;
            }
            if (str_contains($url, '/user/') || str_contains($url, '/suche') || str_contains($url, '/tracker')) {
                return false;
            }
            return (bool)preg_match('/-rezepte\/[^\/]+\/[^\/]+$/i', $url);
        })));

        if (!empty($urls)) {
            return ['url' => $urls[array_rand($urls)]];
        }
    }

    return ['error' => 'Konnte kein Rezept von rezeptwelt.de finden (Seite nicht erreichbar oder kein Rezept-Link gefunden)'];
}

function is_rezeptwelt_zufallsrezept(array $recipe): bool {
    $source = (string)($recipe['anmerkung'] ?? '');
    if (stripos($source, 'rezeptwelt.de/') === false) {
        return false;
    }

    foreach (recipe_tags_to_array($recipe['tags'] ?? []) as $tag) {
        if (mb_strtolower($tag, 'UTF-8') === 'zufallsrezept') {
            return true;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(sqlite_load_current_plan($config));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$recipes = sqlite_recipe_list_all($config, ['category_id' => 'hauptspeise']);
$rezeptweltCandidates = array_values(array_filter($recipes, 'is_rezeptwelt_zufallsrezept'));
$rezeptweltRecipe = !empty($rezeptweltCandidates) ? $rezeptweltCandidates[array_rand($rezeptweltCandidates)] : null;

if ($rezeptweltRecipe === null) {
    $pick = rezeptwelt_pick_random_recipe_url();
    if (isset($pick['error'])) {
        http_response_code(502);
        echo json_encode(['error' => $pick['error']]);
        exit;
    }

    $imported = import_recipe_from_url($config, (string)$pick['url']);
    if (isset($imported['error'])) {
        http_response_code(502);
        echo json_encode(['error' => $imported['error']]);
        exit;
    }

    $tags = recipe_tags_to_array($imported['tags'] ?? []);
    if (!in_array('Zufallsrezept', $tags, true)) {
        $tags[] = 'Zufallsrezept';
    }

    try {
        $rezeptweltRecipe = sqlite_recipe_create($config, [
            'title' => trim((string)($imported['title'] ?? '')),
            'kategorie' => 'hauptspeise',
            'typ' => 'thermomix',
            'kochzeit' => (int)($imported['kochzeit'] ?? 30),
            'portionen' => (int)($imported['portionen'] ?? 4),
            'tags' => $tags,
            'anmerkung' => (string)$pick['url'],
            'image' => '',
            'datum' => date('Y-m-d'),
            'zutaten' => trim((string)($imported['zutaten'] ?? '')),
            'zubereitung' => trim((string)($imported['zubereitung'] ?? '')),
        ]);
        $recipes[] = $rezeptweltRecipe;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

if (empty($recipes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Keine Hauptspeisen für den Wochenplan vorhanden']);
    exit;
}

// Gewicht basierend auf Bewertung: 0 (unbewertet) = 3, 1 = 1, 2 = 2, 3 = 3, 4 = 5, 5 = 8
// Niedrig bewertete Rezepte kommen seltener, aber nie gar nicht (Sicherheitsnetz)
function recipe_weight(array $recipe): int {
    $rating = (int)($recipe['rating'] ?? 0);
    return match ($rating) {
        0 => 3,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 5,
        5 => 8,
        default => 3,
    };
}

function weighted_random_pick(array $pool, array $excludeIndexes): int {
    $totalWeight = 0;
    $weights = [];
    foreach ($pool as $i => $item) {
        if (in_array($i, $excludeIndexes, true)) {
            $weights[$i] = 0;
            continue;
        }
        $w = recipe_weight($item);
        $weights[$i] = $w;
        $totalWeight += $w;
    }

    if ($totalWeight === 0) {
        // Alle ausgeschlossen: Reset, alle verfügbar
        foreach ($pool as $i => $item) {
            $w = recipe_weight($item);
            $weights[$i] = $w;
            $totalWeight += $w;
        }
    }

    $rand = random_int(1, max(1, $totalWeight));
    $cumulative = 0;
    foreach ($weights as $i => $w) {
        $cumulative += $w;
        if ($rand <= $cumulative) {
            return (int)$i;
        }
    }
    return (int)array_key_last($pool);
}

$schnell = array_values(array_filter($recipes, static fn(array $recipe): bool => (int)($recipe['kochzeit'] ?? 30) <= 45));
$tage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
$usedFast = [];
$usedAll = [];
$plan = [];

$forcedDay = random_int(0, 1) === 0 ? 'Samstag' : 'Sonntag';
$forcedIndex = null;
if ($rezeptweltRecipe !== null) {
    foreach ($recipes as $index => $recipe) {
        if ((int)($recipe['id'] ?? 0) === (int)($rezeptweltRecipe['id'] ?? 0)) {
            $forcedIndex = $index;
            break;
        }
    }
}

foreach ($tage as $i => $tag) {
    $isWeekday = $i < 5;

    if ($tag === $forcedDay && $forcedIndex !== null) {
        $usedAll[] = $forcedIndex;
        $plan[] = [
            'tag' => $tag,
            'recipe' => sqlite_plan_snapshot_from_recipe($recipes[$forcedIndex]),
        ];
        continue;
    }

    if ($isWeekday && !empty($schnell)) {
        $pool = $schnell;
        $pickIndex = weighted_random_pick($pool, $usedFast);
        $usedFast[] = $pickIndex;
        // Auch in usedAll eintragen, falls Pool auf recipes wechselt
        foreach ($recipes as $ri => $r) {
            if ((int)($r['id'] ?? -1) === (int)($pool[$pickIndex]['id'] ?? -2)) {
                $usedAll[] = $ri;
                break;
            }
        }
    } else {
        $pool = $recipes;
        $pickIndex = weighted_random_pick($pool, $usedAll);
        $usedAll[] = $pickIndex;
    }

    $plan[] = [
        'tag' => $tag,
        'recipe' => sqlite_plan_snapshot_from_recipe($pool[$pickIndex]),
    ];
}

if ($rezeptweltRecipe !== null) {
    $hasRandomRecipe = false;
    foreach ($plan as $day) {
        if (is_rezeptwelt_zufallsrezept($day['recipe'] ?? [])) {
            $hasRandomRecipe = true;
            break;
        }
    }

    if (!$hasRandomRecipe) {
        foreach ($plan as $index => $day) {
            if (($day['tag'] ?? '') === 'Sonntag') {
                $plan[$index]['recipe'] = sqlite_plan_snapshot_from_recipe($rezeptweltRecipe);
                break;
            }
        }
    }
}

$result = sqlite_save_current_plan($config, $plan, date('Y-m-d H:i:s'));
echo json_encode($result);
