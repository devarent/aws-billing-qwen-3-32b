<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

echo json_encode([
    'configured' => aws_is_configured(),
    'masked_key' => aws_masked_access_key(),
    'region'     => aws_config()['region'],
    'has_secret' => aws_config()['secret_key'] !== '',
    'has_token'  => aws_config()['session_token'] !== '',
    'sync_days'  => aws_config()['sync_days'],
    'php'        => PHP_VERSION,
    'curl'       => function_exists('curl_init'),
    'simplexml'  => function_exists('simplexml_load_string'),
    'openssl'    => extension_loaded('openssl'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (in_array('--test', $argv ?? [], true)) {
    echo "== test connection ==\n";
    try {
        $identity = aws_test_connection();
        echo json_encode($identity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } catch (Throwable $e) {
        echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    }
}
