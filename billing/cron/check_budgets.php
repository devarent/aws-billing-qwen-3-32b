<?php
/**
 * Pemeriksa budget & invoice jatuh tempo (CLI).
 *
 * Jalankan dari host:
 *   docker compose exec ai-billing-hub php /var/www/html/billing/cron/check_budgets.php
 *
 * Atau tambahkan ke crontab container/host untuk pemeriksaan berkala.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya untuk CLI.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

$started = microtime(true);
$result = check_budgets();
$elapsed = round(microtime(true) - $started, 3);

if (in_array('--json', $argv ?? [], true)) {
    echo json_encode($result + ['elapsed_seconds' => $elapsed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

echo "=== Qween 3 32B — Pemeriksaan Budget ===" . PHP_EOL;
echo 'Waktu        : ' . date('Y-m-d H:i:s T') . PHP_EOL;
echo 'Budget dicek : ' . $result['checked'] . PHP_EOL;
echo 'Alert baru   : ' . $result['created'] . PHP_EOL;
echo 'Invoice lewat: ' . $result['overdue'] . PHP_EOL;
echo 'Durasi       : ' . $elapsed . ' detik' . PHP_EOL;
echo 'Status       : OK' . PHP_EOL;

exit(0);
