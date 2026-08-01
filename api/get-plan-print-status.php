<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/plan-print-lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$jobId = trim((string)($_GET['job_id'] ?? ''));
if (!plan_print_is_valid_job_id($jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Job-ID']);
    exit;
}

$job = plan_print_read_job($config, $jobId);
if ($job === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Print-Job nicht gefunden']);
    exit;
}

// Hier wird der Job auch bearbeitet, nicht nur abgefragt: auf Shared Hosting
// ist exec() meist gesperrt, ein Hintergrundprozess laesst sich nicht starten.
// Der erste Poll, der den Job beansprucht, macht die Arbeit und antwortet erst
// danach - alle weiteren Polls sehen ihn als running und warten normal weiter.
if (($job['status'] ?? '') === 'queued' && plan_print_claim_job($config, $jobId)) {
    // Ein geschlossener Tab darf den Job nicht auf halbem Weg abschneiden.
    ignore_user_abort(true);
    @set_time_limit(0);

    try {
        plan_print_process_job($config, $jobId);
    } catch (Throwable $e) {
        // plan_print_process_job() hat den Fehler bereits in die Jobdatei
        // geschrieben; sie wird gleich neu gelesen und ausgeliefert.
        if (function_exists('codex_log')) {
            codex_log('error', [
                'type' => 'print_job_failed',
                'job_id' => $jobId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    $job = plan_print_read_job($config, $jobId) ?? $job;
}

$response = [
    'success' => true,
    'job_id' => $jobId,
    'status' => (string)($job['status'] ?? 'unknown'),
];

if (($job['status'] ?? '') === 'completed' && is_array($job['result'] ?? null)) {
    $response['filename'] = $job['result']['filename'] ?? '';
    $response['image'] = $job['result']['image'] ?? '';
    $response['model'] = $job['result']['model'] ?? '';
}

if (($job['status'] ?? '') === 'failed') {
    $response['error'] = (string)($job['error'] ?? 'Print-Job fehlgeschlagen');
}

echo json_encode($response);
