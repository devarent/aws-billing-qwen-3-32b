<?php
/**
 * Konfigurasi aplikasi AWS Billing Manager.
 *
 * Nilai dibaca dari environment (Docker Compose) dengan fallback yang aman
 * untuk pengembangan lokal (php -S / Laragon).
 */
return [
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => (int) (getenv('DB_PORT') ?: 3306),
        'name'    => getenv('DB_NAME') ?: 'aws_billing',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'      => 'Qween 3 32B',
        'subtitle'  => 'AWS Billing Manager',
        'timezone'  => getenv('TZ') ?: 'Asia/Jakarta',
        'version'   => '1.0.0',
    ],
    'aws' => [
        'access_key'    => env_value('AWS_ACCESS_KEY_ID'),
        'secret_key'    => env_value('AWS_SECRET_ACCESS_KEY'),
        'session_token' => env_value('AWS_SESSION_TOKEN'),
        'region'        => env_value('AWS_DEFAULT_REGION', env_value('AWS_REGION', 'us-east-1')),
        'sync_days'     => max(1, min(365, (int) env_value('AWS_SYNC_DAYS', '30'))),
    ],
];
