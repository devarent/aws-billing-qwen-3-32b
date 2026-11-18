<?php
/**
 * REST API ringkas untuk AWS Billing Manager.
 *
 * Contoh:
 *   /billing/api.php?action=status
 *   /billing/api.php?action=summary
 *   /billing/api.php?action=monthly&months=6
 *   /billing/api.php?action=services&from=2026-11-01&to=2026-11-30
 *   /billing/api.php?action=accounts
 *   /billing/api.php?action=budgets
 *   /billing/api.php?action=alerts&limit=20
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$action = strtolower((string) ($_GET['action'] ?? 'status'));

if ($action === 'status') {
    json_response([
        'status'      => 'ok',
        'app'         => config('app.name') . ' — ' . config('app.subtitle'),
        'version'     => config('app.version'),
        'timezone'    => date_default_timezone_get(),
        'server_time' => date('c'),
        'database'    => [
            'host'  => config('db.host'),
            'name'  => config('db.name'),
            'mysql' => db_value('SELECT VERSION()'),
            'users' => (int) db_value('SELECT COUNT(*) FROM users'),
        ],
        'data' => [
            'accounts'     => (int) db_value('SELECT COUNT(*) FROM aws_accounts'),
            'cost_records' => (int) db_value('SELECT COUNT(*) FROM cost_records'),
            'budgets'      => (int) db_value('SELECT COUNT(*) FROM budgets'),
            'invoices'     => (int) db_value('SELECT COUNT(*) FROM invoices'),
            'alerts'       => (int) db_value('SELECT COUNT(*) FROM alerts'),
        ],
        'aws' => [
            'configured'    => aws_is_configured(),
            'region'        => aws_config()['region'],
            'last_test_at'  => q_setting('aws_last_test_at', ''),
            'last_test_ok'  => q_setting('aws_last_test_ok', '') === '1',
            'last_sync_at'  => q_setting('aws_last_sync_at', ''),
            'last_sync_rows'=> (int) q_setting('aws_last_sync_rows', '0'),
        ],
    ]);
}

if (!current_user()) {
    json_response(['status' => 'error', 'message' => 'Unauthorized. Login melalui /billing/ terlebih dahulu.'], 401);
}

switch ($action) {
    case 'summary':
        $mtd = q_mtd_spend();
        $day = max(1, (int) date('j'));
        $days = (int) date('t');
        $budgets = q_budget_status();
        json_response([
            'status' => 'ok',
            'period' => ['start' => month_start(), 'end' => today()],
            'mtd_spend'       => round($mtd, 2),
            'projected_spend' => round($mtd / $day * $days, 2),
            'previous_month'  => round(q_month_total(date('Y-m', strtotime('first day of -1 month'))), 2),
            'credit_remaining'=> round((float) db_value('SELECT COALESCE(SUM(credit_balance), 0) FROM aws_accounts'), 2),
            'budgets'         => array_map(static fn ($b) => [
                'name'          => $b['name'],
                'account'       => $b['alias'],
                'limit_usd'     => (float) $b['limit_usd'],
                'spent_usd'     => $b['spent'],
                'usage_pct'     => $b['usage_pct'],
                'projected_usd' => $b['projected'],
                'level'         => $b['level'],
            ], $budgets),
            'unread_alerts' => q_unread_alert_count(),
        ]);

    case 'monthly':
        $months = min(24, max(1, safe_int($_GET['months'] ?? 6, 6)));
        $series = q_monthly_series($months);
        json_response([
            'status' => 'ok',
            'months' => $series['keys'],
            'labels' => $series['labels'],
            'totals' => $series['totals'],
            'total'  => round(array_sum($series['totals']), 2),
        ]);

    case 'services':
        $from = valid_date($_GET['from'] ?? '') ? (string) $_GET['from'] : month_start();
        $to = valid_date($_GET['to'] ?? '') ? (string) $_GET['to'] : today();
        $rows = q_spend_by_service($from, $to, null, 100);
        json_response([
            'status' => 'ok',
            'from'   => $from,
            'to'     => $to,
            'items'  => array_map(static fn ($row) => [
                'code'     => $row['code'],
                'name'     => $row['name'],
                'category' => $row['category'],
                'total'    => round((float) $row['total'], 2),
            ], $rows),
        ]);

    case 'accounts':
        $rows = q_accounts_with_spend();
        json_response([
            'status' => 'ok',
            'items'  => array_map(static fn ($row) => [
                'account_id'     => $row['account_id'],
                'alias'          => $row['alias'],
                'environment'    => $row['environment'],
                'region'         => $row['region'],
                'status'         => $row['status'],
                'credit_balance' => (float) $row['credit_balance'],
                'monthly_budget' => (float) $row['monthly_budget'],
                'mtd_spend'      => (float) $row['mtd_spend'],
                'prev_spend'     => (float) $row['prev_spend'],
            ], $rows),
        ]);

    case 'budgets':
        json_response(['status' => 'ok', 'items' => q_budget_status()]);

    case 'alerts':
        $limit = min(200, max(1, safe_int($_GET['limit'] ?? 20, 20)));
        $rows = q_alerts($limit, ($_GET['unread'] ?? '') === '1');
        json_response([
            'status' => 'ok',
            'unread' => q_unread_alert_count(),
            'items'  => $rows,
        ]);

    case 'invoices':
        json_response(['status' => 'ok', 'items' => q_invoices($_GET['status'] ?? null)]);
}

json_response(['status' => 'error', 'message' => 'Action tidak dikenal: ' . $action], 404);
