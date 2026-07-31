<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/plan-print-lib.php';

$jobId = trim((string)($argv[1] ?? ''));
if (!plan_print_is_valid_job_id($jobId)) {
    fwrite(STDERR, "Ungültige Job-ID\n");
    exit(1);
}

try {
    plan_print_process_job($config, $jobId);
    exit(0);
} catch (Throwable $e) {
    if (function_exists('codex_log')) {
        codex_log('error', [
            'type' => 'print_job_failed',
            'job_id' => $jobId,
            'message' => $e->getMessage(),
        ]);
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
