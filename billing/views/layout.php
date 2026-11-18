<?php
/** @var string $content */
/** @var string $pageTitle */
/** @var string $pageSubtitle */
/** @var string $currentPage */
/** @var array $user */
/** @var int $unreadCount */

$appName = q_setting('company_name', 'Qween 3 32B');
$appTagline = q_setting('company_tagline', 'AWS Billing Manager');
$creditTotal = (float) db_value('SELECT COALESCE(SUM(credit_balance), 0) FROM aws_accounts');
$accountCount = (int) db_value('SELECT COUNT(*) FROM aws_accounts');
$initial = strtoupper(substr((string) ($user['name'] ?? 'Q'), 0, 1));
$awsConfigured = aws_is_configured();
$awsLive = $awsConfigured && q_setting('aws_last_test_ok', '') === '1';
$awsChipClass = $awsLive ? 'live' : 'offline';
$awsChipText = $awsLive ? 'AWS Live' : ($awsConfigured ? 'AWS Siap' : 'Data Demo');
$awsChipTitle = $awsLive
    ? 'Kredensial AWS valid. Klik untuk sinkronisasi.'
    : ($awsConfigured
        ? 'Kredensial AWS terdeteksi, belum diuji. Klik untuk tes koneksi.'
        : 'Belum ada kredensial AWS. Isi .env lalu tes koneksi.');

$navItems = [
    ['dashboard', 'Dashboard', 'dashboard'],
    ['costs', 'Biaya & Usage', 'costs'],
    ['accounts', 'Akun AWS', 'accounts'],
    ['budgets', 'Budget', 'budget'],
    ['invoices', 'Invoice', 'invoices'],
    ['alerts', 'Alert', 'alerts'],
    ['services', 'Layanan', 'services'],
    ['settings', 'Pengaturan', 'settings'],
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#070d18">
    <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='24' fill='%23ffb02e'/><text x='50' y='68' font-size='54' font-family='Arial' font-weight='bold' text-anchor='middle' fill='%231a1203'>Q</text></svg>">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark">Q</div>
            <div>
                <h1><?= e($appName) ?></h1>
                <p><?= e($appTagline) ?></p>
            </div>
        </div>

        <div class="nav-label">Workspace</div>
        <?php foreach ($navItems as [$key, $label, $icon]): ?>
            <?php if ($key === 'settings'): ?>
                <div class="nav-label">Sistem</div>
            <?php endif; ?>
            <a href="<?= e(url($key)) ?>" class="nav-link <?= $currentPage === $key ? 'active' : '' ?>">
                <?= icon($icon) ?>
                <span><?= e($label) ?></span>
                <?php if ($key === 'alerts' && $unreadCount > 0): ?>
                    <span class="nav-badge"><?= (int) $unreadCount ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <div class="sidebar-foot">
            <div class="credit-card">
                <div class="label">Total AWS Credit</div>
                <div class="value"><?= e(money($creditTotal)) ?></div>
                <div class="hint"><?= (int) $accountCount ?> akun terhubung</div>
                <div class="progress <?= $creditTotal <= 0 ? 'critical' : '' ?>">
                    <span style="width: <?= $creditTotal > 0 ? 100 : 8 ?>%"></span>
                </div>
            </div>
            <div class="row small muted" style="padding: 12px 4px 0; gap: 6px;">
                <?= icon('shield') ?>
                <span>AWS CE · MySQL · Docker / WSL 2</span>
            </div>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-toggle" id="menu-toggle" type="button" aria-label="Buka menu">
                <?= icon('menu') ?>
            </button>
            <div class="topbar-title">
                <h2><?= e($pageTitle) ?></h2>
                <?php if (!empty($pageSubtitle)): ?>
                    <p><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>

            <div class="topbar-actions">
                <a class="aws-chip <?= e($awsChipClass) ?>" href="<?= e(url('settings')) ?>#aws-connection" title="<?= e($awsChipTitle) ?>">
                    <span class="pulse"></span><?= e($awsChipText) ?>
                </a>
                <a class="btn btn-primary" href="<?= e(url('costs')) ?>#tambah-biaya">
                    <?= icon('plus') ?><span>Tambah Biaya</span>
                </a>
                <a class="icon-btn" href="<?= e(url('alerts')) ?>" title="Alert">
                    <?= icon('alerts') ?>
                    <?php if ($unreadCount > 0): ?><span class="dot"></span><?php endif; ?>
                </a>
                <div class="user-chip" title="<?= e($user['email'] ?? '') ?>">
                    <div class="avatar"><?= e($initial) ?></div>
                    <div class="meta">
                        <strong><?= e($user['name'] ?? 'User') ?></strong>
                        <span><?= e($user['role'] ?? 'viewer') ?></span>
                    </div>
                </div>
                <a class="icon-btn" href="<?= e(url('logout')) ?>" title="Keluar" aria-label="Keluar">
                    <?= icon('logout') ?>
                </a>
            </div>
        </header>

        <main class="page">
            <?php foreach (flashes() as $flash): ?>
                <div class="flash <?= e($flash['type']) ?>">
                    <?= icon($flash['type'] === 'error' ? 'warning' : ($flash['type'] === 'success' ? 'check' : 'info')) ?>
                    <div><?= e($flash['message']) ?></div>
                    <button type="button" class="close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endforeach; ?>

            <?= $content ?>

            <footer class="row-between tiny faint" style="padding-top: 10px; flex-wrap: wrap;">
                <span><?= e($appName) ?> · <?= e($appTagline) ?> v<?= e(config('app.version')) ?></span>
                <span>PHP <?= e(PHP_VERSION) ?> &middot; MySQL &middot; Docker / WSL 2 &middot; AWS Cost Explorer</span>
            </footer>
        </main>
    </div>
</div>

<script src="<?= e(asset('charts.js')) ?>"></script>
<script src="<?= e(asset('app.js')) ?>"></script>
</body>
</html>
