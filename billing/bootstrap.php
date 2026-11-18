<?php
/**
 * Bootstrap aplikasi: timezone, session, helper, koneksi database.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/env.php';
load_env_file(dirname(__DIR__) . '/.env');

$__config = require __DIR__ . '/config.php';
date_default_timezone_set((string) ($__config['app']['timezone'] ?? 'Asia/Jakarta'));

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', getenv('DEBUG') === 'true' ? '1' : '0');

if (session_status() === PHP_SESSION_NONE) {
    session_name('qween_billing_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/queries.php';
require_once __DIR__ . '/lib/alerts.php';
require_once __DIR__ . '/lib/aws.php';
require_once __DIR__ . '/lib/actions.php';

try {
    $pdo = db();
    ensure_database($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    $detail = e($e->getMessage());
    $debug = getenv('DEBUG') === 'true';
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Database belum siap</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#0b1220;color:#e6edf7;display:grid;place-items:center;min-height:100vh;margin:0}'
        . '.box{max-width:680px;background:#111a2b;border:1px solid #24314a;border-radius:16px;padding:32px}'
        . 'h1{margin:0 0 8px;font-size:22px;color:#ffb02e}code{background:#0b1220;padding:2px 6px;border-radius:6px;color:#ffd88a}'
        . 'pre{white-space:pre-wrap;background:#0b1220;padding:16px;border-radius:10px;color:#fca5a5;font-size:12px;overflow:auto}'
        . 'ul{line-height:1.8;color:#b9c7dc}</style></head><body><div class="box">'
        . '<h1>MySQL belum bisa dihubungi</h1>'
        . '<p>Aplikasi AWS Billing Manager membutuhkan container MySQL. Jalankan:</p>'
        . '<pre>docker compose up -d billing-db</pre>'
        . '<ul><li>Pastikan container <code>billing-db</code> berstatus healthy.</li>'
        . '<li>Host: <code>' . e((string) ($__config['db']['host'] ?? '')) . '</code> &middot; Database: <code>' . e((string) ($__config['db']['name'] ?? '')) . '</code></li>'
        . '<li>Jika menjalankan <code>php -S</code> di Windows, set <code>DB_HOST=127.0.0.1 DB_USER=root</code>.</li></ul>'
        . ($debug ? '<pre>' . $detail . '</pre>' : '<p style="color:#8fa3c0;font-size:13px">Aktifkan <code>DEBUG=true</code> untuk detail error.</p>')
        . '</div></body></html>';
    exit;
}
