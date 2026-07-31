<?php

if (!function_exists('codex_init_logging')) {
    function codex_init_logging(string $dataDir): void {
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;

        $logDir = rtrim($dataDir, '/\\') . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $phpErrorLog = $logDir . '/php-error.log';
        ini_set('log_errors', '1');
        ini_set('display_errors', '0');
        ini_set('error_log', $phpErrorLog);

        // Für API-Requests Response puffern, damit JSON-Fehler zentral geloggt werden können.
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $isApiRequest = str_starts_with(ltrim($requestUri, '/'), 'api/');
        if (PHP_SAPI !== 'cli' && $isApiRequest && ob_get_level() === 0) {
            ob_start();
        }

        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
            $ctx = [
                'type' => 'php_error',
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ];
            codex_log('error', $ctx);
            return true;
        });

        set_exception_handler(static function (Throwable $e): void {
            $ctx = [
                'type' => 'uncaught_exception',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
            codex_log('error', $ctx);
            http_response_code(500);
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['error' => 'Interner Fehler']);
        });

        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err === null) {
                return;
            }
            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array($err['type'] ?? 0, $fatal, true)) {
                return;
            }
            $ctx = [
                'type' => 'fatal_shutdown',
                'severity' => $err['type'] ?? 0,
                'message' => $err['message'] ?? '',
                'file' => $err['file'] ?? '',
                'line' => $err['line'] ?? 0,
            ];
            codex_log('error', $ctx);
        });

        register_shutdown_function(static function (): void {
            if (PHP_SAPI === 'cli') {
                return;
            }
            $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
            $isApiRequest = str_starts_with(ltrim($requestUri, '/'), 'api/');
            if (!$isApiRequest) {
                return;
            }

            $status = http_response_code();
            $body = ob_get_level() > 0 ? (string)ob_get_contents() : '';
            if ($body === '') {
                return;
            }

            $contentType = '';
            foreach (headers_list() as $headerLine) {
                if (stripos($headerLine, 'Content-Type:') === 0) {
                    $contentType = trim(substr($headerLine, strlen('Content-Type:')));
                    break;
                }
            }
            $isJson = stripos($contentType, 'application/json') !== false;
            if (!$isJson) {
                return;
            }

            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['error'])) {
                codex_log('error', [
                    'type' => 'api_error_response',
                    'status' => $status,
                    'message' => (string)$decoded['error'],
                    'endpoint' => $requestUri,
                ]);
                return;
            }

            if ($status >= 400) {
                codex_log('error', [
                    'type' => 'api_http_error',
                    'status' => $status,
                    'endpoint' => $requestUri,
                    'body_preview' => mb_substr($body, 0, 400),
                ]);
            }
        });
    }
}

if (!function_exists('codex_log')) {
    function codex_log(string $level, array $context): void {
        $dataDir = __DIR__ . '/data';
        $logDir = $dataDir . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $entry = [
            'ts' => date('Y-m-d H:i:s'),
            'level' => $level,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'context' => $context,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        @file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
    }
}
