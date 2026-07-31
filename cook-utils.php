<?php

/**
 * Fachlogik des Kochmodus.
 *
 * Das Zerlegen von Schritten, Zeitangaben und Mengen passiert ausschliesslich
 * hier. Das Frontend bekommt die Zutaten bereits zerlegt geliefert und muss
 * zum Skalieren nur noch multiplizieren - so existiert die deutsche
 * Mengen-Grammatik an genau einer Stelle.
 *
 * Konzeptionell abgeleitet vom Kochmodus aus MorphCook (MIT), siehe
 * THIRD_PARTY_NOTICES.md.
 */

const COOK_MIN_SERVINGS = 1;
const COOK_MAX_SERVINGS = 24;
const COOK_LONG_STEP_CHARS = 180;
const COOK_LONG_STEP_SENTENCES = 3;
const COOK_MAX_TIMER_SECONDS = 86400;

/** Einheiten, die vor dem Zutatennamen stehen duerfen. Laengere zuerst. */
function cook_units(): array {
    return [
        'Esslöffel', 'Teelöffel', 'Messerspitze', 'Packungen', 'Packung', 'Päckchen',
        'Scheiben', 'Scheibe', 'Stangen', 'Stange', 'Zweige', 'Zweig', 'Blätter', 'Blatt',
        'Handvoll', 'Portionen', 'Portion', 'Tropfen', 'Prisen', 'Prise', 'Bündel', 'Bund',
        'Gläser', 'Glas', 'Dosen', 'Dose', 'Beutel', 'Becher', 'Tassen', 'Tasse',
        'Kugeln', 'Kugel', 'Würfel', 'Zehen', 'Zehe', 'Köpfe', 'Kopf', 'Stück', 'Stk',
        'Liter', 'Gramm', 'Msp', 'Pck', 'EL', 'TL', 'kg', 'ml', 'cl', 'cm', 'g', 'l',
    ];
}

/** Woerter, die als Zutatenname oder Schritt-Stichwort nichts aussagen. */
function cook_stopwords(): array {
    return [
        'zum', 'zur', 'oder', 'und', 'etwas', 'nach', 'fein', 'feine', 'frisch', 'frische',
        'frischer', 'frisches', 'braten', 'kochen', 'backen', 'garen', 'dann', 'alles', 'alle',
        'danach', 'wieder', 'kurz', 'geben', 'lassen', 'unter', 'dabei', 'sowie', 'mehr',
        'wenig', 'halbe', 'halber', 'ganze', 'ganzer', 'groß', 'große', 'großer', 'klein',
        'kleine', 'kleiner', 'gehackt', 'gehackte', 'gemahlen', 'gemahlene', 'getrocknet',
        'getrocknete', 'optional', 'belieben', 'geschmack', 'stück', 'stücke', 'wahl',
        'möglichst', 'eventuell', 'evtl', 'ggf', 'gemischt', 'gemischte', 'gemischtes',
        'abgeriebene', 'abgeriebener', 'bio', 'mittelgroße', 'mittelgroßer', 'garnieren',
        'servieren', 'zubereiten', 'menge', 'stets', 'jeweils',
    ];
}

// === Schritte ============================================================

/** Erkennt Platzhaltertexte, wie sie fehlgeschlagene KI-Importe hinterlassen. */
function cook_step_is_placeholder(string $text): bool {
    $normalized = mb_strtolower(trim($text), 'UTF-8');
    if ($normalized === '') {
        return true;
    }

    foreach (['nicht enthalten', 'keine zubereitung', 'keine schritte', 'nicht angegeben', 'liegen nicht vor'] as $needle) {
        if (str_contains($normalized, $needle)) {
            return true;
        }
    }

    return false;
}

/** Eine Zeile im Zubereitungstext ist ein Schritt. */
function cook_split_steps(string $text): array {
    $steps = [];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $line = trim((string)preg_replace('/^\s*(?:\d+\s*[.)]\s*|[-*•]\s+)/u', '', $line));
        if ($line === '' || cook_step_is_placeholder($line)) {
            continue;
        }

        $steps[] = $line;
    }

    return $steps;
}

/**
 * Zerlegt einen Absatz in Saetze. Deutsche Abkuerzungen wie "ca." oder "z. B."
 * duerfen dabei nicht als Satzende durchgehen.
 */
function cook_split_sentences(string $text): array {
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    $marker = "\x01";
    $guarded = $text;

    foreach (['z. B.', 'z.B.', 'd. h.', 'd.h.', 'u. a.', 'u.a.', 'o. Ä.', 'o.Ä.', 'i. d. R.'] as $abbreviation) {
        $guarded = str_replace($abbreviation, str_replace('.', $marker, $abbreviation), $guarded);
    }

    $single = [
        'ca', 'bzw', 'evtl', 'ggf', 'ggfs', 'usw', 'etc', 'inkl', 'exkl', 'max', 'min',
        'Min', 'Sek', 'Std', 'Nr', 'Dr', 'St', 'Pck', 'zzgl', 'vgl', 'TK', 'EL', 'TL', 'ml', 'Abb',
    ];
    $guarded = (string)preg_replace_callback(
        '/\b(' . implode('|', $single) . ')\./u',
        static fn(array $match): string => $match[1] . $marker,
        $guarded
    );

    // Dezimalzahlen wie "1.5" sind kein Satzende.
    $guarded = (string)preg_replace('/(\d)\.(\d)/u', '$1' . $marker . '$2', $guarded);

    $parts = preg_split('/(?<=[.!?…])\s+(?=[A-ZÄÖÜ])/u', $guarded) ?: [];

    $sentences = [];
    foreach ($parts as $part) {
        $part = trim(str_replace($marker, '.', $part));
        if ($part !== '') {
            $sentences[] = $part;
        }
    }

    return $sentences;
}

/** Lange Absaetze mit mehreren Saetzen darf der Nutzer feiner unterteilen. */
function cook_step_is_long(string $text): bool {
    if (mb_strlen(trim($text), 'UTF-8') <= COOK_LONG_STEP_CHARS) {
        return false;
    }

    return count(cook_split_sentences($text)) >= COOK_LONG_STEP_SENTENCES;
}

// === Zeitangaben =========================================================

/**
 * Findet alle Zeitangaben eines Schrittes. Bewusst eine Liste: ein Absatz
 * enthaelt haeufig mehrere ("5 Minuten braten ... 2 Minuten koecheln"), und
 * zu raten, welche gemeint ist, waere schlechter als beide anzubieten.
 */
function cook_detect_timers(string $text): array {
    $number = '\d+(?:[.,]\d+)?';
    $pattern = '/(' . $number . ')(?:\s*(?:bis|-|–|—)\s*(' . $number . '))?\s*'
        . '(Sekunden|Sekunde|Sek|Minuten|Minute|Min|Stunden|Stunde|Std)\b\.?'
        . '(?:\s+([a-zäöüß]+e?n))?/u';

    if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $timers = [];
    $seen = [];
    foreach ($matches as $match) {
        $value = cook_parse_number($match[2] !== '' ? $match[2] : $match[1]);
        if ($value === null || $value <= 0) {
            continue;
        }

        $seconds = (int)round($value * cook_time_unit_factor($match[3]));
        if ($seconds <= 0 || $seconds > COOK_MAX_TIMER_SECONDS) {
            continue;
        }

        $label = trim($match[0]);
        if (isset($seen[$label])) {
            continue;
        }
        $seen[$label] = true;

        $timers[] = [
            'seconds' => $seconds,
            'label' => $label,
        ];
    }

    return $timers;
}

function cook_time_unit_factor(string $unit): int {
    return match (mb_substr(mb_strtolower($unit, 'UTF-8'), 0, 3, 'UTF-8')) {
        'sek' => 1,
        'std' => 3600,
        'stu' => 3600,
        default => 60,
    };
}

// === Mengen und Zutaten ==================================================

/** Versteht "500", "1,5", "1½" und "½". */
function cook_parse_number(string $value): ?float {
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $fractions = ['½' => 0.5, '¼' => 0.25, '¾' => 0.75, '⅓' => 1 / 3, '⅔' => 2 / 3, '⅛' => 0.125];

    $total = 0.0;
    $found = false;
    foreach ($fractions as $character => $fraction) {
        if (str_contains($value, $character)) {
            $total += $fraction;
            $value = str_replace($character, '', $value);
            $found = true;
        }
    }

    $value = trim(str_replace(',', '.', $value));
    if ($value !== '') {
        if (!is_numeric($value)) {
            return $found ? $total : null;
        }
        $total += (float)$value;
        $found = true;
    }

    return $found ? $total : null;
}

/**
 * Zerlegt eine Zutatenzeile in Menge, Einheit und Name.
 *
 * Knapp ein Viertel des Bestands hat gar keine Menge ("Salz, Pfeffer") - diese
 * Zeilen bleiben unveraendert und werden spaeter nie skaliert.
 */
function cook_parse_ingredient(string $line): array {
    $raw = trim($line);
    $item = [
        'raw' => $raw,
        'qty' => null,
        'qty_max' => null,
        'unit' => '',
        'rest' => $raw,
        'ambiguous' => false,
    ];

    if ($raw === '') {
        return $item;
    }

    $number = '(?:\d+(?:[.,]\d+)?\s*[½¼¾⅓⅔⅛]|\d+(?:[.,]\d+)?|[½¼¾⅓⅔⅛])';
    if (!preg_match('/^(' . $number . ')(?:\s*(?:-|–|—|bis)\s*(' . $number . '))?\s*(.*)$/u', $raw, $match)) {
        return $item;
    }

    $quantity = cook_parse_number($match[1]);
    if ($quantity === null) {
        return $item;
    }

    $item['qty'] = $quantity;
    if (($match[2] ?? '') !== '') {
        $item['qty_max'] = cook_parse_number($match[2]);
    }

    $rest = trim($match[3]);
    foreach (cook_units() as $unit) {
        if (preg_match('/^' . preg_quote($unit, '/') . '\b\.?\s+(.*)$/iu', $rest, $unitMatch)) {
            $item['unit'] = $unit;
            $rest = trim($unitMatch[1]);
            break;
        }
    }

    $item['rest'] = $rest;
    $item['ambiguous'] = cook_rest_has_embedded_quantity($rest);

    return $item;
}

/**
 * Erkennt gebuendelte Zeilen wie "1 Ei, 2-3 TL Senf". Dort gehoert die fuehrende
 * Menge nur zum ersten Teil - solche Zeilen duerfen nie skaliert werden, sonst
 * stimmt hinterher die zweite Haelfte nicht mehr.
 */
function cook_rest_has_embedded_quantity(string $rest): bool {
    $units = implode('|', array_map(static fn(string $unit): string => preg_quote($unit, '/'), cook_units()));

    return (bool)preg_match('/\d+(?:[.,]\d+)?(?:\s*(?:-|–|—|bis)\s*\d+(?:[.,]\d+)?)?\s*(?:' . $units . ')\b/u', $rest);
}

/** Eine Zeile im Zutatentext ist eine Zutat. */
function cook_parse_ingredients(string $text): array {
    $items = [];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $line = trim((string)preg_replace('/^\s*[-*•]\s+/u', '', $line));
        if ($line === '') {
            continue;
        }

        $items[] = cook_parse_ingredient($line);
    }

    return $items;
}

// === Zuordnung Zutat zu Schritt ==========================================

/** Grobes Stemming, das deutsche Plural- und Beugungsendungen abschneidet. */
function cook_stem(string $word): string {
    $word = mb_strtolower($word, 'UTF-8');
    foreach (['en', 'er', 'e', 'n', 's'] as $suffix) {
        if (mb_strlen($word, 'UTF-8') > 4 && str_ends_with($word, $suffix)) {
            return mb_substr($word, 0, -mb_strlen($suffix), 'UTF-8');
        }
    }

    return $word;
}

/** Aussagekraeftige Wortstaemme eines Textes, kurze und generische raus. */
function cook_ingredient_keywords(string $text): array {
    $words = preg_split('/[^\p{L}]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stopwords = cook_stopwords();

    $keywords = [];
    foreach ($words as $word) {
        if (mb_strlen($word, 'UTF-8') < 4 || in_array($word, $stopwords, true)) {
            continue;
        }

        $stem = cook_stem($word);
        if (mb_strlen($stem, 'UTF-8') < 4) {
            continue;
        }

        $keywords[] = $stem;
    }

    return array_values(array_unique($keywords));
}

/**
 * Welche Zutaten ein Schritt vermutlich braucht - heuristisch ueber den Namen,
 * weil das Datenmodell keine Zuordnung kennt. Der Abgleich laeuft in beide
 * Richtungen, damit "Sahne" im Schritt auch die Zutat "Schlagsahne" trifft.
 * Ein Fehlgriff kostet hier einen Blick, keinen Fehler.
 */
function cook_match_ingredients(string $stepText, array $ingredients): array {
    $stepStems = cook_ingredient_keywords($stepText);
    if (empty($stepStems)) {
        return [];
    }

    $hits = [];
    foreach ($ingredients as $index => $item) {
        foreach (cook_ingredient_keywords((string)($item['rest'] ?? '')) as $keyword) {
            foreach ($stepStems as $stem) {
                if (str_contains($keyword, $stem) || str_contains($stem, $keyword)) {
                    $hits[] = (int)$index;
                    continue 3;
                }
            }
        }
    }

    return $hits;
}

// === Zusammenbau =========================================================

function cook_build_steps(string $instructionsText, array $ingredients): array {
    $steps = [];
    foreach (cook_split_steps($instructionsText) as $index => $text) {
        $sentences = cook_split_sentences($text);
        $steps[] = [
            'index' => $index,
            'text' => $text,
            'timers' => cook_detect_timers($text),
            'ingredients' => cook_match_ingredients($text, $ingredients),
            'splittable' => cook_step_is_long($text),
            'sentences' => count($sentences) > 1 ? $sentences : [],
        ];
    }

    return $steps;
}

/** Baut aus einem Rezept die komplette Kochsession fuer das Frontend. */
function cook_build_session(array $recipe): array {
    $ingredients = cook_parse_ingredients((string)($recipe['zutaten'] ?? ''));
    $steps = cook_build_steps((string)($recipe['zubereitung'] ?? ''), $ingredients);

    $baseServings = (int)($recipe['portionen'] ?? 0);
    if ($baseServings < COOK_MIN_SERVINGS) {
        $baseServings = 0;
    }

    return [
        'recipe' => [
            'id' => isset($recipe['id']) ? (int)$recipe['id'] : null,
            'title' => (string)($recipe['title'] ?? ''),
            'typ' => (string)($recipe['typ'] ?? 'normal'),
            'kategorie' => (string)($recipe['kategorie'] ?? ''),
            'kochzeit' => (int)($recipe['kochzeit'] ?? 0),
            'portionen' => $baseServings,
            'anmerkung' => (string)($recipe['anmerkung'] ?? ''),
            'image' => (string)($recipe['image'] ?? ''),
        ],
        'ingredients' => $ingredients,
        'steps' => $steps,
        'scalable' => $baseServings > 0,
    ];
}

function cook_clamp_servings(int $servings): int {
    return max(COOK_MIN_SERVINGS, min(COOK_MAX_SERVINGS, $servings));
}
