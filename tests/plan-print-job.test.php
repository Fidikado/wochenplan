<?php

require_once __DIR__ . '/../api/plan-print-lib.php';

/**
 * Legt ein Wegwerf-Datenverzeichnis mit genau einem Job an.
 */
function plan_print_test_config(string $status = 'queued'): array {
    $dir = sys_get_temp_dir() . '/plan-print-test-' . bin2hex(random_bytes(6));
    mkdir($dir . '/print-jobs', 0775, true);
    $config = ['data_dir' => $dir];

    plan_print_write_job($config, plan_print_test_job_id(), [
        'id' => plan_print_test_job_id(),
        'status' => $status,
        'created_at' => '2026-08-01 12:00:00',
        'updated_at' => '2026-08-01 12:00:00',
        'context' => ['plan_text' => 'Montag: Suppe'],
        'result' => null,
        'error' => null,
    ]);

    return $config;
}

function plan_print_test_job_id(): string {
    return str_repeat('ab', 16);
}

test('claim übernimmt einen wartenden Job und setzt ihn auf running', function () {
    $config = plan_print_test_config();

    assert_true(plan_print_claim_job($config, plan_print_test_job_id()), 'wartender Job wird übernommen');

    $job = plan_print_read_job($config, plan_print_test_job_id());
    assert_same('running', $job['status'], 'Status nach dem Übernehmen');
    assert_true($job['updated_at'] !== '2026-08-01 12:00:00', 'updated_at wird fortgeschrieben');
    assert_same('Montag: Suppe', $job['context']['plan_text'], 'Kontext bleibt erhalten');
});

test('ein zweiter Aufruf übernimmt denselben Job nicht noch einmal', function () {
    $config = plan_print_test_config();

    plan_print_claim_job($config, plan_print_test_job_id());
    // Sonst liefe der Gemini-Aufruf doppelt und kostete zweimal Quote.
    assert_false(plan_print_claim_job($config, plan_print_test_job_id()), 'bereits laufender Job');
});

test('bereits fertige oder fehlgeschlagene Jobs werden nicht übernommen', function () {
    foreach (['completed', 'failed', 'running'] as $status) {
        $config = plan_print_test_config($status);
        assert_false(plan_print_claim_job($config, plan_print_test_job_id()), 'Status ' . $status);
    }
});

test('unbekannte und ungültige Job-IDs liefern false', function () {
    $config = plan_print_test_config();

    assert_false(plan_print_claim_job($config, str_repeat('cd', 16)), 'unbekannte ID');
    assert_false(plan_print_claim_job($config, 'nicht-hex'), 'ungültige ID');
    assert_false(plan_print_claim_job($config, ''), 'leere ID');
});
