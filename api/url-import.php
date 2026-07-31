<?php

function http_fetch_html(string $url, int $timeoutSeconds = 15): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
        ],
    ]);
    $html = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => "cURL Fehler: $error"];
    }
    if (!$html || $httpCode !== 200) {
        return ['error' => "Seite konnte nicht geladen werden (HTTP $httpCode)"];
    }

    return ['html' => (string)$html, 'http_code' => $httpCode];
}

function import_recipe_from_url(array $config, string $url): array {
    require_once __DIR__ . '/gemini.php';

    $fetch = http_fetch_html($url, 15);
    if (isset($fetch['error'])) {
        return ['error' => $fetch['error']];
    }

    $html = (string)($fetch['html'] ?? '');
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = mb_substr((string)$text, 0, 10000);

    $prompt = get_recipe_prompt() . "\n\nWebseiten-Inhalt von $url:\n" . $text;
    $result = gemini_request($config, [['text' => $prompt]]);

    if (!isset($result['error'])) {
        $result['anmerkung'] = $url;
    }

    return $result;
}

