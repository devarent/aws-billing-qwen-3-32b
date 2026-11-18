<?php
/**
 * Sinkronisasi AWS dari CLI (cocok untuk cron / Task Scheduler).
 *
 * Contoh:
 *   php /var/www/html/billing/cron/sync_aws.php 30
 *   docker compose exec ai-billing-hub php /var/www/html/billing/cron/sync_aws.php 30
 *
 * Argumen opsional: jumlah hari (default AWS_SYNC_DAYS atau 30).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script ini hanya untuk CLI.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

$days = isset($argv[1]) ? max(1, min(365, (int) $argv[1])) : aws_config()['sync_days'];

try {
    $identity = aws_test_connection();
    echo 'AWS account: ' . $identity['account'] . ' (' . $identity['arn'] . ")\n";

    $costs = aws_sync_costs($days, $identity);
    printf(
        "Biaya: %d baris, %s s/d %s, total %s\n",
        $costs['rows'],
        $costs['start'],
        $costs['end'],
        money($costs['total'])
    );

    try {
        $budgets = aws_sync_budgets((string) $identity['account']);
        printf("Budget: %d baru, %d diperbarui, %d dilewati\n", $budgets['created'], $budgets['updated'], $budgets['skipped']);
    } catch (Throwable $e) {
        echo 'Budget dilewati: ' . $e->getMessage() . "\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Sync gagal: ' . $e->getMessage() . "\n");
    aws_record_test_failure($e);
    exit(1);
}
