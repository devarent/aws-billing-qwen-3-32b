<?php
/**
 * Front controller AWS Billing Manager.
 * Semua halaman diakses melalui index.php?page=...
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/** Render view di dalam layout. */
function render(string $view, array $data = []): void
{
    global $pageTitle, $pageSubtitle, $currentPage, $unreadCount;
    $user = current_user();

    extract($data, EXTR_SKIP);
    $viewFile = __DIR__ . '/views/' . $view . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        exit('View tidak ditemukan: ' . e($view));
    }

    ob_start();
    require $viewFile;
    $content = (string) ob_get_clean();

    require __DIR__ . '/views/layout.php';
}

$page = strtolower((string) ($_GET['page'] ?? 'dashboard'));
$page = preg_replace('/[^a-z_]/', '', $page) ?: 'dashboard';

// ---------------------------------------------------------------------
// Login / logout
// ---------------------------------------------------------------------
if ($page === 'logout') {
    logout_user();
    redirect(url('login'));
}

if ($page === 'login') {
    if (current_user()) {
        redirect(url('dashboard'));
    }
    if (is_post()) {
        verify_csrf();
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (login_user($email, $password)) {
            flash('success', 'Selamat datang kembali, ' . current_user()['name'] . '!');
            redirect(url('dashboard'));
        }
        flash('error', 'Email atau password salah. Coba lagi.');
        redirect(url('login'));
    }

    $pageTitle = 'Masuk';
    require __DIR__ . '/views/login.php';
    exit;
}

require_login();
$user = current_user();

// ---------------------------------------------------------------------
// Export CSV (GET, sebelum output apa pun)
// ---------------------------------------------------------------------
if ($page === 'costs' && isset($_GET['export'])) {
    $filters = [
        'account_id' => safe_int($_GET['account_id'] ?? 0) ?: null,
        'service_id' => safe_int($_GET['service_id'] ?? 0) ?: null,
        'from'       => valid_date($_GET['from'] ?? '') ? $_GET['from'] : null,
        'to'         => valid_date($_GET['to'] ?? '') ? $_GET['to'] : null,
        'q'          => trim((string) ($_GET['q'] ?? '')),
    ];
    export_costs_csv($filters);
}

// ---------------------------------------------------------------------
// Aksi POST
// ---------------------------------------------------------------------
if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'cost_import') {
        handle_cost_import();
    } else {
        handle_action($action);
    }
}

// Pemeriksaan alert otomatis (dibatasi sekali per 5 menit per sesi).
if (in_array($page, ['dashboard', 'alerts'], true)) {
    maybe_check_budgets();
}

$unreadCount = q_unread_alert_count();
$currentPage = $page;
$pageSubtitle = '';
$pageTitle = 'Dashboard';

switch ($page) {
    // =================================================================
    // Dashboard
    // =================================================================
    case 'dashboard':
        $pageTitle = 'Dashboard Billing';
        $pageSubtitle = 'Ringkasan pengeluaran AWS, budget, dan kesehatan kredit.';

        $mtd = q_mtd_spend();
        $prevKey = date('Y-m', strtotime('first day of -1 month'));
        $prevMonth = q_month_total($prevKey);
        $dayOfMonth = (int) date('j');
        $daysInMonth = (int) date('t');
        $projected = $dayOfMonth > 0 ? $mtd / $dayOfMonth * $daysInMonth : $mtd;

        $prevSameStart = date('Y-m-01', strtotime('first day of -1 month'));
        $prevSameEnd = date('Y-m-d', strtotime($prevSameStart . ' +' . max(0, $dayOfMonth - 1) . ' days'));
        $prevSame = (float) db_value(
            'SELECT COALESCE(SUM(cost_usd), 0) FROM cost_records WHERE usage_date BETWEEN ? AND ?',
            [$prevSameStart, $prevSameEnd]
        );
        $deltaPct = $prevSame > 0 ? (($mtd - $prevSame) / $prevSame) * 100 : null;

        $creditTotal = (float) db_value('SELECT COALESCE(SUM(credit_balance), 0) FROM aws_accounts');
        $budgets = q_budget_status();
        $budgetLimit = array_sum(array_column($budgets, 'limit_usd'));
        $budgetSpent = array_sum(array_column($budgets, 'spent'));
        $budgetPct = $budgetLimit > 0 ? $budgetSpent / $budgetLimit * 100 : 0;
        $budgetsAtRisk = count(array_filter($budgets, static fn ($b) => $b['level'] !== 'ok'));

        $accounts = q_accounts_with_spend();
        $series = q_monthly_series(6);
        $daily = q_daily_series(14);
        $byService = q_spend_by_service(month_start(), today(), null, 7);
        $alerts = q_alerts(5);
        $topServices = q_top_services(month_start(), today(), 5);
        $imports = q_recent_imports(3);

        $chart = [
            'months'       => $series['labels'],
            'monthTotals'  => $series['totals'],
            'dailyLabels'  => $daily['labels'],
            'dailyTotals'  => $daily['totals'],
            'serviceNames' => array_map(static fn ($s) => $s['name'], $byService),
            'serviceTotals'=> array_map(static fn ($s) => round((float) $s['total'], 2), $byService),
        ];

        render('dashboard', compact(
            'mtd', 'prevMonth', 'projected', 'deltaPct', 'creditTotal', 'budgets',
            'budgetLimit', 'budgetSpent', 'budgetPct', 'budgetsAtRisk', 'accounts',
            'series', 'byService', 'alerts', 'topServices', 'imports', 'chart', 'user'
        ));
        break;

    // =================================================================
    // Biaya & usage
    // =================================================================
    case 'costs':
        $pageTitle = 'Biaya & Usage';
        $pageSubtitle = 'Rincian biaya harian per akun dan layanan AWS.';

        $from = valid_date($_GET['from'] ?? '') ? (string) $_GET['from'] : month_start();
        $to = valid_date($_GET['to'] ?? '') ? (string) $_GET['to'] : today();
        $filters = [
            'account_id' => safe_int($_GET['account_id'] ?? 0) ?: null,
            'service_id' => safe_int($_GET['service_id'] ?? 0) ?: null,
            'from'       => $from,
            'to'         => $to,
            'q'          => trim((string) ($_GET['q'] ?? '')),
        ];

        $perPage = 20;
        $currentPageNo = max(1, safe_int($_GET['p'] ?? 1));
        $totalRows = q_costs_count($filters);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $currentPageNo = min($currentPageNo, $totalPages);
        $rows = q_costs($filters, $perPage, ($currentPageNo - 1) * $perPage);
        $totals = q_cost_totals($filters);
        $accounts = q_accounts();
        $services = q_services();
        $imports = q_recent_imports(4);

        render('costs', compact(
            'filters', 'rows', 'totals', 'accounts', 'services', 'imports',
            'perPage', 'currentPageNo', 'totalPages', 'totalRows', 'from', 'to'
        ));
        break;

    // =================================================================
    // Akun AWS
    // =================================================================
    case 'accounts':
        $pageTitle = 'Akun AWS';
        $pageSubtitle = 'Kelola akun, kredit, dan batas budget per environment.';

        $accounts = q_accounts_with_spend();
        $editId = safe_int($_GET['edit'] ?? 0);
        $editAccount = $editId > 0 ? q_account($editId) : null;
        $defaultRegion = q_setting('default_region', 'us-east-1');

        render('accounts', compact('accounts', 'editAccount', 'defaultRegion'));
        break;

    // =================================================================
    // Budget
    // =================================================================
    case 'budgets':
        $pageTitle = 'Budget & Guardrail';
        $pageSubtitle = 'Pantau pemakaian terhadap limit dan proyeksi akhir periode.';

        $budgets = q_budget_status();
        $accounts = q_accounts();
        $defaultThreshold = q_setting('budget_alert_threshold', '80');
        $totalLimit = array_sum(array_column($budgets, 'limit_usd'));
        $totalSpent = array_sum(array_column($budgets, 'spent'));

        render('budgets', compact('budgets', 'accounts', 'defaultThreshold', 'totalLimit', 'totalSpent'));
        break;

    // =================================================================
    // Invoice
    // =================================================================
    case 'invoices':
        $pageTitle = 'Invoice & Tagihan';
        $pageSubtitle = 'Terbitkan tagihan bulanan dan catat pembayaran.';

        $status = (string) ($_GET['status'] ?? '');
        $invoices = q_invoices($status ?: null);
        $summary = q_invoice_summary();
        $accounts = q_accounts();
        $defaultStart = date('Y-m-01', strtotime('first day of -1 month'));
        $defaultEnd = date('Y-m-t', strtotime('first day of -1 month'));

        render('invoices', compact('invoices', 'summary', 'accounts', 'status', 'defaultStart', 'defaultEnd'));
        break;

    // =================================================================
    // Alert
    // =================================================================
    case 'alerts':
        $pageTitle = 'Alert Center';
        $pageSubtitle = 'Notifikasi budget, kredit, anomali, dan invoice jatuh tempo.';

        $unreadOnly = ($_GET['filter'] ?? '') === 'unread';
        $alerts = q_alerts(100, $unreadOnly);
        $unreadCount = q_unread_alert_count();

        render('alerts', compact('alerts', 'unreadOnly', 'unreadCount'));
        break;

    // =================================================================
    // Layanan
    // =================================================================
    case 'services':
        $pageTitle = 'Katalog Layanan';
        $pageSubtitle = 'Harga satuan AWS yang dipakai untuk menghitung biaya.';

        $services = q_services();
        $grouped = [];
        foreach ($services as $service) {
            $grouped[$service['category']][] = $service;
        }

        render('services', compact('services', 'grouped'));
        break;

    // =================================================================
    // Pengaturan
    // =================================================================
    case 'settings':
        $pageTitle = 'Pengaturan';
        $pageSubtitle = 'Identitas aplikasi, ambang alert, dan status sistem.';

        $settings = q_settings();
        $imports = q_recent_imports(6);
        $awsConfigured = aws_is_configured();
        $awsHasKey = aws_config()['access_key'] !== '';
        $aws = [
            'configured'  => $awsConfigured,
            'access_key'  => $awsConfigured ? aws_masked_access_key() : ($awsHasKey ? 'Placeholder terdeteksi' : '—'),
            'region'      => aws_config()['region'],
            'has_token'   => aws_config()['session_token'] !== '',
            'last_test'   => $settings['aws_last_test_at'] ?? '',
            'test_ok'     => ($settings['aws_last_test_ok'] ?? '') === '1',
            'test_message'=> $settings['aws_last_test_message'] ?? '',
            'account'     => $settings['aws_identity_account'] ?? '',
            'arn'         => $settings['aws_identity_arn'] ?? '',
            'last_sync'   => $settings['aws_last_sync_at'] ?? '',
            'sync_rows'   => $settings['aws_last_sync_rows'] ?? '',
            'sync_total'  => $settings['aws_last_sync_total'] ?? '',
            'sync_days'   => aws_config()['sync_days'],
        ];
        $dbInfo = [
            'host'     => (string) config('db.host'),
            'database' => (string) config('db.name'),
            'version'  => (string) db_value('SELECT VERSION()'),
            'php'      => PHP_VERSION,
            'timezone' => date_default_timezone_get(),
            'records'  => (int) db_value('SELECT COUNT(*) FROM cost_records'),
        ];

        render('settings', compact('settings', 'imports', 'dbInfo', 'aws'));
        break;

    default:
        http_response_code(404);
        $pageTitle = 'Halaman tidak ditemukan';
        render('notfound', []);
        break;
}
