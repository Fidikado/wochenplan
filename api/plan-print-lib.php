<?php

require_once __DIR__ . '/../sqlite-store.php';
require_once __DIR__ . '/gemini.php';

function plan_print_jobs_dir(array $config): string {
    return rtrim((string)$config['data_dir'], '/\\') . '/print-jobs';
}

function plan_print_job_file(array $config, string $jobId): string {
    return plan_print_jobs_dir($config) . '/' . $jobId . '.json';
}

function plan_print_is_valid_job_id(string $jobId): bool {
    return (bool)preg_match('/^[a-f0-9]{24,64}$/', $jobId);
}

function plan_print_write_job(array $config, string $jobId, array $payload): void {
    $dir = plan_print_jobs_dir($config);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Job-Ordner konnte nicht erstellt werden');
    }

    $path = plan_print_job_file($config, $jobId);
    $tmp = $path . '.tmp';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($tmp, $json) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Jobdatei konnte nicht geschrieben werden');
    }
}

function plan_print_read_job(array $config, string $jobId): ?array {
    if (!plan_print_is_valid_job_id($jobId)) {
        return null;
    }

    $path = plan_print_job_file($config, $jobId);
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function plan_print_load_gd_image(string $path, string $mime) {
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function plan_print_encode_gd_image($image, string $mime): ?string {
    ob_start();
    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($image, null, 84),
        'image/png' => imagepng($image, null, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 84) : false,
        default => false,
    };
    $data = $ok ? ob_get_clean() : null;
    if (!$ok) {
        ob_end_clean();
        return null;
    }
    return $data === false ? null : $data;
}

function plan_print_prepare_template_inline_payload(string $templatePath): array {
    $mime = mime_content_type($templatePath) ?: 'image/png';
    $originalBytes = (string)file_get_contents($templatePath);
    $originalSize = strlen($originalBytes);
    $maxEdge = 1800;
    $size = @getimagesize($templatePath);
    $width = (int)($size[0] ?? 0);
    $height = (int)($size[1] ?? 0);

    if ($width < 1 || $height < 1 || max($width, $height) <= $maxEdge) {
        return [
            'mime' => $mime,
            'data' => base64_encode($originalBytes),
            'width' => $width,
            'height' => $height,
            'bytes' => $originalSize,
            'optimized' => false,
        ];
    }

    $source = plan_print_load_gd_image($templatePath, $mime);
    if (!$source) {
        return [
            'mime' => $mime,
            'data' => base64_encode($originalBytes),
            'width' => $width,
            'height' => $height,
            'bytes' => $originalSize,
            'optimized' => false,
        ];
    }

    $scale = $maxEdge / max($width, $height);
    $targetWidth = max(1, (int)round($width * $scale));
    $targetHeight = max(1, (int)round($height * $scale));
    $resized = imagecreatetruecolor($targetWidth, $targetHeight);

    if ($mime !== 'image/jpeg') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
    }

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    $encoded = plan_print_encode_gd_image($resized, $mime);
    imagedestroy($resized);
    imagedestroy($source);

    if ($encoded === null) {
        return [
            'mime' => $mime,
            'data' => base64_encode($originalBytes),
            'width' => $width,
            'height' => $height,
            'bytes' => $originalSize,
            'optimized' => false,
        ];
    }

    return [
        'mime' => $mime,
        'data' => base64_encode($encoded),
        'width' => $targetWidth,
        'height' => $targetHeight,
        'bytes' => strlen($encoded),
        'optimized' => true,
        'original_width' => $width,
        'original_height' => $height,
        'original_bytes' => $originalSize,
    ];
}

function plan_print_collect_context(array $config): array {
    $planData = sqlite_load_current_plan($config);
    if (!$planData || empty($planData['plan'])) {
        throw new RuntimeException('Kein Wochenplan vorhanden');
    }

    $template = sqlite_get_plan_template($config);
    if ($template === null || empty($template['path'])) {
        throw new RuntimeException('Keine Bildvorlage hinterlegt. Bitte im Admin-Bereich hochladen.');
    }

    $templatePathRel = ltrim((string)$template['path'], '/');
    $templatePath = __DIR__ . '/../' . $templatePathRel;
    if (!is_file($templatePath)) {
        throw new RuntimeException('Gespeicherte Bildvorlage wurde nicht gefunden.');
    }

    $templateSize = @getimagesize($templatePath);
    $templateWidth = (int)($templateSize[0] ?? 0);
    $templateHeight = (int)($templateSize[1] ?? 0);
    $isLandscape = $templateWidth > 0 && $templateHeight > 0 && $templateWidth > $templateHeight;
    $orientationLabel = $isLandscape ? 'Querformat (A4 Landscape)' : 'Hochformat (A4 Portrait)';

    $dayOrder = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $byTag = [];
    foreach ($planData['plan'] as $day) {
        $byTag[(string)($day['tag'] ?? '')] = $day;
    }

    $lines = [];
    $layoutLines = [];
    foreach ($dayOrder as $tag) {
        $day = $byTag[$tag] ?? null;

        // Freigestellte/blockierte Tage haben kein Gericht: Feld bleibt leer,
        // damit die KI hier nichts (auch kein altes/erfundenes Rezept) einträgt.
        if ($day !== null && !empty($day['disabled'])) {
            $lines[] = $tag . ': (frei – kein Gericht)';
            $layoutLines[] = $tag;
            $layoutLines[] = '  Zeile 1: (leer lassen)';
            $layoutLines[] = '  Meta: (leer lassen)';
            continue;
        }

        $recipe = $day['recipe'] ?? [];
        $title = trim((string)($recipe['title'] ?? 'Kein Eintrag'));
        $compactTitle = plan_print_compact_title($title, 34);
        $wrappedTitle = plan_print_wrap_title($compactTitle, 18, 2);
        $lines[] = $tag . ': '
            . $title
            . ' | ' . ($recipe['kochzeit'] ?? '?') . ' Min'
            . ' | ' . ($recipe['portionen'] ?? '?') . ' Portionen';
        $layoutLines[] = $tag;
        foreach ($wrappedTitle as $index => $line) {
            $layoutLines[] = '  Zeile ' . ($index + 1) . ': ' . $line;
        }
        $layoutLines[] = '  Meta: ' . ($recipe['kochzeit'] ?? '?') . ' Min · ' . ($recipe['portionen'] ?? '?') . ' Port.';
    }

    return [
        'template_path' => $templatePath,
        'template_relative_path' => $templatePathRel,
        'orientation_label' => $orientationLabel,
        'plan_text' => implode("\n", $lines),
        'layout_text' => implode("\n", $layoutLines),
    ];
}

function plan_print_compact_title(string $title, int $maxLength = 34): string {
    $title = trim(preg_replace('/\s+/u', ' ', $title));
    if ($title === '') {
        return 'Kein Eintrag';
    }

    if (mb_strlen($title, 'UTF-8') <= $maxLength) {
        return $title;
    }

    $shortened = mb_substr($title, 0, $maxLength - 1, 'UTF-8');
    $lastSpace = mb_strrpos($shortened, ' ', 0, 'UTF-8');
    if ($lastSpace !== false && $lastSpace > 10) {
        $shortened = mb_substr($shortened, 0, $lastSpace, 'UTF-8');
    }

    return rtrim($shortened, " \t\n\r\0\x0B,.-") . '…';
}

function plan_print_wrap_title(string $title, int $lineLength = 18, int $maxLines = 2): array {
    $words = preg_split('/\s+/u', trim($title)) ?: [];
    if (empty($words)) {
        return ['Kein Eintrag'];
    }

    $lines = [];
    $current = '';
    $consumedAllWords = true;
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if ($current !== '' && mb_strlen($candidate, 'UTF-8') > $lineLength) {
            $lines[] = $current;
            $current = $word;
            if (count($lines) >= $maxLines - 1) {
                $consumedAllWords = false;
                break;
            }
        } else {
            $current = $candidate;
        }
    }

    if ($current !== '' && count($lines) < $maxLines) {
        $lines[] = $current;
    }

    $lines = array_values(array_filter($lines, static fn($line) => trim($line) !== ''));
    if (empty($lines)) {
        $lines[] = $title;
    }

    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
    }

    if (!$consumedAllWords && count($lines) === $maxLines && !str_ends_with(end($lines), '…')) {
        $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], " \t\n\r\0\x0B,.-") . '…';
    }

    return $lines;
}

function plan_print_extract_image_part(array $raw): ?array {
    $parts = $raw['data']['candidates'][0]['content']['parts'] ?? [];
    foreach ($parts as $part) {
        $mime = $part['inlineData']['mimeType'] ?? $part['inline_data']['mime_type'] ?? '';
        $blob = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? '';
        if ($blob !== '' && str_starts_with($mime, 'image/')) {
            return ['mime' => $mime, 'data' => $blob];
        }
    }
    return null;
}

function plan_print_prompt(string $orientationLabel, string $planText, string $layoutText): string {
    return <<<PROMPT
Bearbeite die hochgeladene Wochenplan-Vorlage direkt als Bild.

Ziel:
- Nutze die Vorlage als Basis-Design.
- Trage die 7 Gerichte sauber in die vorhandenen Bereiche der Vorlage ein.
- Liefere EIN fertiges, druckbares A4-Bild in folgender Ausrichtung zurück: $orientationLabel.
- Behalte die Ausrichtung und das Seitenverhältnis der Vorlage bei.
- Keine zusätzliche Erklärung, kein Markdown, nur das generierte Bild.

Typografie/Lesbarkeit:
- Hoher Kontrast zwischen Text und Hintergrund.
- Nutze eine schlichte, saubere, gedruckte Sans-Serif-Schrift.
- Keine Handschrift, keine Kalligrafie, keine dekorative oder verspielte Schrift.
- Kein gebogener, verzerrter, perspektivischer oder unscharfer Text.
- Überschriften klar erkennbar.
- Gerichte gut lesbar.
- Kein Text außerhalb des Druckbereichs.
- Wenn die Vorlage schon feste Wochentags-Labels hat, diese NICHT neu gestalten, sondern nur die Essensfelder befüllen.
- Halte dich eng an das bestehende Layout der Vorlage. Nicht das gesamte Design neu erfinden.
- Verwende pro Tag maximal 2 kurze Zeilen für den Gerichtsnamen und 1 kleine Meta-Zeile.
- Falls ein Titel zu lang wäre, nutze genau die unten vorgegebenen Kurzfassungen.
- Tage, die als "(frei – kein Gericht)" markiert sind oder deren Layout "(leer lassen)" vorgibt, bleiben vollständig leer. Trage dort KEIN Gericht und KEINE Meta-Daten ein und erfinde nichts.

Wochenplan:
$planText

Layoutvorgabe fuer die Beschriftung:
$layoutText
PROMPT;
}

function plan_print_resolve_image_model(array $config): array {
    $configured = trim((string)sqlite_get_plan_print_model($config));
    if ($configured === '') {
        $configured = trim((string)($config['gemini_nano_banana_model'] ?? ''));
    }
    // Alte Preview-/eingestellte Modell-IDs auf die aktuellen GA-IDs abbilden
    $aliasMap = [
        'gemini-2.5-flash-image-preview'            => 'gemini-2.5-flash-image',
        'gemini-2.0-flash-preview-image-generation' => 'gemini-3.1-flash-image',
        'gemini-3-pro-image-preview'                => 'gemini-3-pro-image',
        'gemini-3.1-flash-image-preview'            => 'gemini-3.1-flash-image',
    ];

    $resolved = $configured === '' ? 'gemini-3.1-flash-image' : ($aliasMap[$configured] ?? $configured);

    // Fallback-Kette: bei 404/429/403 wird das nächste Modell versucht
    $models = [$resolved];
    if ($resolved !== 'gemini-2.5-flash-image') {
        $models[] = 'gemini-2.5-flash-image';
    }

    return $models;
}

function plan_print_available_models(): array {
    return [
        [
            'id' => 'gemini-3.1-flash-image',
            'label' => 'Gemini 3.1 Flash Image',
            'description' => 'Offiziell empfohlene Allround-Wahl fuer Bildgenerierung.',
        ],
        [
            'id' => 'gemini-3-pro-image',
            'label' => 'Gemini 3 Pro Image',
            'description' => 'Hoechste Qualitaet fuer komplexe Layouts, dafuer langsamer.',
        ],
        [
            'id' => 'gemini-2.5-flash-image',
            'label' => 'Gemini 2.5 Flash Image (Legacy)',
            'description' => 'Aelteres Modell, laut Google nur noch als Legacy-Option.',
        ],
    ];
}

function plan_print_image_config(string $orientationLabel, string $model): array {
    $isPortrait = stripos($orientationLabel, 'Portrait') !== false;
    $config = [
        'aspectRatio' => $isPortrait ? '3:4' : '4:3',
    ];

    $supportsImageSize = [
        'gemini-3.1-flash-image',
        'gemini-3.1-flash-image-preview',
        'gemini-3-pro-image',
        'gemini-3-pro-image-preview',
    ];
    if (in_array($model, $supportsImageSize, true)) {
        $config['imageSize'] = '2K';
    }

    return $config;
}

/**
 * Beansprucht einen wartenden Job fuer die Bearbeitung im laufenden Request.
 *
 * Frueher startete generate-plan-print.php per exec() einen eigenen
 * PHP-Prozess. Auf Shared Hosting ist exec() regelmaessig gesperrt - dann
 * wurde der Job zwar angelegt, aber nie bearbeitet. Stattdessen holt sich
 * jetzt der erste Status-Poll den Job hier ab.
 *
 * Der Lock haelt den Uebergang queued -> running exklusiv: zwei gleichzeitige
 * Polls duerfen denselben Job nicht doppelt starten, sonst kostet er zweimal
 * Gemini-Quote. Wer den Lock nicht bekommt, bekommt false und pollt weiter.
 */
function plan_print_claim_job(array $config, string $jobId): bool {
    if (!plan_print_is_valid_job_id($jobId)) {
        return false;
    }

    $handle = @fopen(plan_print_job_file($config, $jobId), 'r+');
    if ($handle === false) {
        return false;
    }

    try {
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            return false;
        }

        $job = json_decode((string)stream_get_contents($handle), true);
        if (!is_array($job) || ($job['status'] ?? '') !== 'queued') {
            return false;
        }

        $job['status'] = 'running';
        $job['updated_at'] = date('Y-m-d H:i:s');
        $json = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }

        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json) === false) {
            return false;
        }
        fflush($handle);

        if (function_exists('codex_log')) {
            codex_log('info', [
                'type' => 'print_job_claimed',
                'job_id' => $jobId,
            ]);
        }

        return true;
    } finally {
        @flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function plan_print_create_job(array $config, array $context): array {
    $jobId = bin2hex(random_bytes(16));
    $job = [
        'id' => $jobId,
        'status' => 'queued',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'context' => $context,
        'result' => null,
        'error' => null,
    ];
    plan_print_write_job($config, $jobId, $job);
    return $job;
}

function plan_print_process_job(array $config, string $jobId): array {
    $job = plan_print_read_job($config, $jobId);
    if ($job === null) {
        throw new RuntimeException('Print-Job nicht gefunden');
    }

    $job['status'] = 'running';
    $job['updated_at'] = date('Y-m-d H:i:s');
    plan_print_write_job($config, $jobId, $job);

    try {
        $context = $job['context'] ?? [];
        $templatePath = (string)($context['template_path'] ?? '');
        $orientationLabel = (string)($context['orientation_label'] ?? '');
        $planText = (string)($context['plan_text'] ?? '');
        if ($templatePath === '' || $orientationLabel === '' || $planText === '') {
            throw new RuntimeException('Print-Job enthält keinen gültigen Kontext');
        }
        if (!is_file($templatePath)) {
            throw new RuntimeException('Gespeicherte Bildvorlage wurde nicht gefunden.');
        }

        $templatePayload = plan_print_prepare_template_inline_payload($templatePath);
        $imageModels = plan_print_resolve_image_model($config);
        if (function_exists('codex_log')) {
            codex_log('info', [
                'type' => 'print_template_payload',
                'optimized' => (bool)($templatePayload['optimized'] ?? false),
                'width' => (int)($templatePayload['width'] ?? 0),
                'height' => (int)($templatePayload['height'] ?? 0),
                'bytes' => (int)($templatePayload['bytes'] ?? 0),
                'original_width' => (int)($templatePayload['original_width'] ?? ($templatePayload['width'] ?? 0)),
                'original_height' => (int)($templatePayload['original_height'] ?? ($templatePayload['height'] ?? 0)),
                'original_bytes' => (int)($templatePayload['original_bytes'] ?? ($templatePayload['bytes'] ?? 0)),
                'job_id' => $jobId,
                'image_model' => implode(',', $imageModels),
            ]);
        }

        $layoutText = (string)($context['layout_text'] ?? '');
        $prompt = plan_print_prompt($orientationLabel, $planText, $layoutText);
        $raw = gemini_generate_content(
            $config,
            [
                [
                    'inline_data' => [
                        'mime_type' => $templatePayload['mime'],
                        'data' => $templatePayload['data'],
                    ],
                ],
                ['text' => $prompt],
            ],
            [
                'models' => $imageModels,
                'response_mime_type' => '',
                'response_modalities' => ['IMAGE'],
                'image_config' => plan_print_image_config($orientationLabel, $imageModels[0]),
                'temperature' => 0.3,
                'timeout' => 240,
                'connect_timeout' => 20,
            ]
        );

        if (isset($raw['error'])) {
            throw new RuntimeException((string)$raw['error']);
        }

        $imgPart = plan_print_extract_image_part($raw);
        if ($imgPart === null) {
            $parts = $raw['data']['candidates'][0]['content']['parts'] ?? [];
            $details = trim((string)($parts[0]['text'] ?? ''));
            $message = 'Das gewählte Gemini-Modell hat kein Bild zurückgegeben.';
            if ($details !== '') {
                $message .= ' ' . $details;
            }
            throw new RuntimeException($message);
        }

        $extByMime = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        $ext = $extByMime[$imgPart['mime']] ?? 'png';
        $printsDir = rtrim((string)$config['data_dir'], '/\\') . '/prints';
        if (!is_dir($printsDir) && !mkdir($printsDir, 0775, true) && !is_dir($printsDir)) {
            throw new RuntimeException('Konnte Druckordner nicht erstellen');
        }

        $filename = 'wochenplan-print-' . date('Ymd-His') . '-' . substr($jobId, 0, 6) . '.' . $ext;
        $outPath = $printsDir . '/' . $filename;
        $binary = base64_decode((string)$imgPart['data'], true);
        if ($binary === false || file_put_contents($outPath, $binary) === false) {
            throw new RuntimeException('Konnte generiertes Bild nicht speichern');
        }

        $job['status'] = 'completed';
        $job['updated_at'] = date('Y-m-d H:i:s');
        $job['result'] = [
            'filename' => $filename,
            'image' => 'data/prints/' . $filename,
            'model' => (string)($raw['model'] ?? ''),
        ];
        $job['error'] = null;
        plan_print_write_job($config, $jobId, $job);
        return $job;
    } catch (Throwable $e) {
        $job['status'] = 'failed';
        $job['updated_at'] = date('Y-m-d H:i:s');
        $job['result'] = null;
        $job['error'] = $e->getMessage();
        plan_print_write_job($config, $jobId, $job);
        throw $e;
    }
}
