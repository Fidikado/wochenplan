<?php

/**
 * Winziger, abhängigkeitsfreier Testrunner.
 *
 * Das Projekt hat bewusst keinen Paketmanager, deshalb kein PHPUnit.
 * Aufruf: php tests/run-php.php
 */

$state = [
    'pass' => 0,
    'fail' => 0,
    'current' => '',
    'failures' => [],
];

function test(string $name, callable $fn): void {
    global $state;
    $state['current'] = $name;
    try {
        $fn();
    } catch (Throwable $e) {
        $state['fail']++;
        $state['failures'][] = $name . ': unerwartete Exception ' . get_class($e) . ' - ' . $e->getMessage();
    }
}

function check(bool $ok, string $message): void {
    global $state;
    if ($ok) {
        $state['pass']++;
        return;
    }
    $state['fail']++;
    $state['failures'][] = $state['current'] . ': ' . $message;
}

function assert_same($expected, $actual, string $message = ''): void {
    $ok = $expected === $actual;
    check($ok, ($message !== '' ? $message . ' - ' : '')
        . 'erwartet ' . var_export($expected, true)
        . ', bekommen ' . var_export($actual, true));
}

function assert_true($value, string $message = ''): void {
    check($value === true, ($message !== '' ? $message . ' - ' : '') . 'erwartet true, bekommen ' . var_export($value, true));
}

function assert_false($value, string $message = ''): void {
    check($value === false, ($message !== '' ? $message . ' - ' : '') . 'erwartet false, bekommen ' . var_export($value, true));
}

$files = glob(__DIR__ . '/*.test.php') ?: [];
sort($files);
foreach ($files as $file) {
    require $file;
}

echo "\n";
foreach ($state['failures'] as $failure) {
    echo "FEHLGESCHLAGEN  " . $failure . "\n";
}

$total = $state['pass'] + $state['fail'];
echo sprintf("\n%d Prüfungen, %d bestanden, %d fehlgeschlagen\n", $total, $state['pass'], $state['fail']);

exit($state['fail'] === 0 ? 0 : 1);
