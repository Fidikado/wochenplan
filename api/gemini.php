<?php
// Gemeinsame Gemini-API-Hilfsfunktionen

function gemini_generate_content(array $config, array $parts, array $options = []): array {
    $apiKey = trim((string)($config['gemini_api_key'] ?? ''));
    $modelConfig = $options['models'] ?? ($config['gemini_model'] ?? '');
    if (is_array($modelConfig)) {
        $models = array_values(array_filter(array_map('trim', $modelConfig)));
    } else {
        $modelConfig = trim((string)$modelConfig);
        $models = array_values(array_filter(array_map('trim', explode(',', $modelConfig))));
    }
    $temperature = isset($options['temperature']) ? (float)$options['temperature'] : 0.2;
    $responseMimeType = trim((string)($options['response_mime_type'] ?? 'application/json'));
    $timeoutSeconds = max(10, (int)($options['timeout'] ?? 60));
    $connectTimeoutSeconds = max(5, (int)($options['connect_timeout'] ?? 15));

    if ($apiKey === '') {
        if (function_exists('codex_log')) {
            codex_log('error', ['type' => 'gemini_config', 'message' => 'GEMINI_API_KEY fehlt']);
        }
        return ['error' => 'GEMINI_API_KEY fehlt in der .env'];
    }
    // Altes Format: AIza + 35 Zeichen; neues Google-Format (seit 2025): beginnt mit "AQ."
    if (!preg_match('/^(AIza[A-Za-z0-9_-]{35}|AQ\.[A-Za-z0-9._-]{20,})$/', $apiKey)) {
        if (function_exists('codex_log')) {
            codex_log('error', ['type' => 'gemini_config', 'message' => 'GEMINI_API_KEY ungültig']);
        }
        return ['error' => 'GEMINI_API_KEY hat ein ungültiges Format (prüfe auf Sonderzeichen/Leerzeichen)'];
    }
    if (empty($models)) {
        if (function_exists('codex_log')) {
            codex_log('error', ['type' => 'gemini_config', 'message' => 'GEMINI_MODEL fehlt']);
        }
        return ['error' => 'GEMINI_MODEL fehlt in der .env'];
    }

    $genCfg = array_filter([
        'temperature' => $temperature,
        'responseModalities' => $options['response_modalities'] ?? null,
        'imageConfig' => $options['image_config'] ?? null,
    ], static fn($v) => $v !== null);
    if ($responseMimeType !== '') {
        $genCfg['responseMimeType'] = $responseMimeType;
    }

    $payload = [
        'contents' => [
            ['parts' => $parts]
        ],
        'generationConfig' => $genCfg,
    ];
    if (isset($options['max_output_tokens'])) {
        $payload['generationConfig']['maxOutputTokens'] = (int)$options['max_output_tokens'];
    }

    $lastError = null;
    foreach ($models as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($model)
             . ':generateContent?key=' . rawurlencode($apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            if (function_exists('codex_log')) {
                codex_log('error', ['type' => 'gemini_curl', 'model' => $model, 'message' => $error]);
            }
            return ['error' => "cURL Fehler: $error"];
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $apiMsg = $decoded['error']['message'] ?? $response;
            $lastError = "Gemini API Fehler (Modell $model, HTTP $httpCode): $apiMsg";
            if (function_exists('codex_log')) {
                codex_log('error', [
                    'type' => 'gemini_http',
                    'model' => $model,
                    'status' => $httpCode,
                    'message' => $apiMsg
                ]);
            }

            // Bei Quota/Rate-Limit oder unbekanntem Modell mit nächstem Modell versuchen.
            if ($httpCode === 429 || $httpCode === 403 || $httpCode === 404) {
                continue;
            }
            return ['error' => $lastError];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $lastError = "Ungültige Gemini-Antwort (Modell $model)";
            if (function_exists('codex_log')) {
                codex_log('error', ['type' => 'gemini_parse', 'model' => $model, 'message' => $lastError]);
            }
            continue;
        }

        return ['data' => $data, 'model' => $model];
    }

    return ['error' => $lastError ?? 'Gemini-Anfrage fehlgeschlagen'];
}

function gemini_request(array $config, array $parts, array $options = []): array {
    $raw = gemini_generate_content($config, $parts, $options);
    if (isset($raw['error'])) {
        return $raw;
    }

    $data = $raw['data'] ?? [];
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$text) {
        return ['error' => "Keine Text-Antwort von Gemini erhalten (Modell " . ($raw['model'] ?? '?') . ")"];
    }

    $parsed = gemini_parse_json_text($text);
    if (!$parsed) {
        if (function_exists('codex_log')) {
            codex_log('error', [
                'type' => 'gemini_json_parse',
                'model' => $raw['model'] ?? '?',
                'preview' => mb_substr(trim($text), 0, 800),
            ]);
        }
        return ['error' => "Gemini-Antwort konnte nicht als JSON geparst werden (Modell " . ($raw['model'] ?? '?') . ")"];
    }
    return $parsed;
}

function gemini_parse_json_text(string $text): ?array {
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    $candidates = [$text];

    $withoutCodeFence = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
    if (is_string($withoutCodeFence) && trim($withoutCodeFence) !== $text) {
        $candidates[] = trim($withoutCodeFence);
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $candidates[] = trim(substr($text, $start, $end - $start + 1));
    }

    foreach ($candidates as $candidate) {
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function get_recipe_prompt(): string {
    return <<<PROMPT
Extrahiere aus dem folgenden Rezept die strukturierten Daten. Antworte ausschließlich mit einem JSON-Objekt in diesem Format:
{
  "title": "Rezeptname",
  "kategorie": "hauptspeise",
  "kochzeit": 30,
  "portionen": 4,
  "tags": ["tag1", "tag2"],
  "zutaten": "Zutat 1\\nZutat 2\\nZutat 3",
  "zubereitung": "Schritt 1\\nSchritt 2\\nSchritt 3"
}

Regeln:
- "kategorie" muss genau einer dieser Werte sein: hauptspeise, vorspeise, suppe, salat, beilage, fruehstueck, brot, kuchen, dessert, getraenk, snack
- "kochzeit" ist die geschätzte Gesamtzeit in Minuten (Zahl)
- "portionen" ist die Anzahl der Portionen (Zahl)
- "tags" sind passende Kategorien als Array
- "zutaten" als Klartext, eine Zutat pro Zeile, mit Mengenangaben
- "zubereitung" als Klartext, ein Schritt pro Zeile
- Alle Texte auf Deutsch
PROMPT;
}

function get_thermomix_conversion_prompt(array $recipe): string {
    $title = trim((string)($recipe['title'] ?? ''));
    $category = trim((string)($recipe['kategorie'] ?? 'hauptspeise'));
    $cookTime = (int)($recipe['kochzeit'] ?? 30);
    $servings = (int)($recipe['portionen'] ?? 4);
    $ingredients = trim((string)($recipe['zutaten'] ?? ''));
    $instructions = trim((string)($recipe['zubereitung'] ?? ''));
    $tags = recipe_tags_to_array($recipe['tags'] ?? []);
    $tagsText = $tags ? implode(', ', $tags) : 'keine';

    return <<<PROMPT
Wandle das folgende Rezept in eine Thermomix-geeignete Version um. Antworte ausschließlich mit einem JSON-Objekt in diesem Format:
{
  "title": "Rezeptname Thermomix",
  "kochzeit": 30,
  "portionen": 4,
  "tags": ["tag1", "tag2"],
  "zutaten": "Zutat 1\\nZutat 2\\nZutat 3",
  "zubereitung": "Schritt 1\\nSchritt 2\\nSchritt 3"
}

Regeln:
- Schreibe auf Deutsch.
- Gib eine echte Thermomix-Zubereitung mit passenden Angaben wie Stufe, Temperatur und Zeit, soweit sinnvoll und fachlich plausibel.
- Erfinde keine exotischen Zutaten. Halte dich möglichst nah am Original.
- Wenn ein Schritt nicht sinnvoll mit dem Thermomix gemacht werden kann, formuliere ihn als externen Schritt, z.B. "im Ofen backen" oder "in der Pfanne anbraten".
- "title" soll den Originaltitel beibehalten, aber klar als Thermomix-Version erkennbar sein.
- "kochzeit" ist die geschätzte Gesamtzeit in Minuten als Zahl.
- "portionen" ist die Anzahl der Portionen als Zahl.
- "tags" ist ein JSON-Array passender Schlagwörter. Übernimm sinnvolle vorhandene Tags.
- "zutaten" enthält eine Zutat pro Zeile.
- "zubereitung" enthält einen Arbeitsschritt pro Zeile.
- Gib nur valides JSON zurück, keine Markdown-Umrandung.

Originalrezept:
Titel: {$title}
Kategorie: {$category}
Kochzeit: {$cookTime}
Portionen: {$servings}
Tags: {$tagsText}

Zutaten:
{$ingredients}

Zubereitung:
{$instructions}
PROMPT;
}
