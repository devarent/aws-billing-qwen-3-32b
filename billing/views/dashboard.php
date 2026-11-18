<?php
/**
 * @var float $mtd
 * @var float $prevMonth
 * @var float $projected
 * @var float|null $deltaPct
 * @var float $creditTotal
 * @var array $budgets
 * @var float $budgetLimit
 * @var float $budgetSpent
 * @var float $budgetPct
 * @var int $budgetsAtRisk
 * @var array $accounts
 * @var array $series
 * @var array $byService
 * @var array $alerts
 * @var array $topServices
 * @var array $imports
 * @var array $chart
 */
$sixMonthTotal = array_sum($series['totals']);
$dayOfMonth = (int) date('j');
$daysInMonth = (int) date('t');
$monthProgress = $daysInMonth > 0 ? $dayOfMonth / $daysInMonth * 100 : 0;
$projectedVsBudget = $budgetLimit > 0 ? array_sum(array_column($budgets, 'projected')) / $budgetLimit * 100 : 0;

$serviceTotal = 0.0;
foreach ($byService as $service) {
    $serviceTotal += (float) $service['total'];
}
$palette = ['#ffb02e', '#60a5fa', '#a78bfa', '#34d399', '#fb7185', '#22d3ee', '#f472b6', '#94a3b8'];
$donutStops = [];
$donutCursor = 0.0;
foreach ($byService as $index => $service) {
    $share = $serviceTotal > 0 ? ((float) $service['total'] / $serviceTotal) * 100 : 0;
    $start = $donutCursor;
    $donutCursor = min(100, $donutCursor + $share);
    $color = $palette[$index % count($palette)];
    $donutStops[] = $color . ' ' . round($start, 2) . '% ' . round($donutCursor, 2) . '%';
}
if ($donutStops) {
    $donutStops[count($donutStops) - 1] = preg_replace('/\s[\d.]+%$/', ' 100%', $donutStops[count($donutStops) - 1]);
}
$donutStyle = $donutStops ? 'conic-gradient(' . implode(', ', $donutStops) . ')' : 'conic-gradient(#1b2b45 0 100%)';
?>
<script>
window.BILLING_CHARTS = <?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<?php
$awsConfigured = aws_is_configured();
$awsAccount = q_setting('aws_identity_account', '');
$awsSyncAt = q_setting('aws_last_sync_at', '');
$awsTestOk = q_setting('aws_last_test_ok', '') === '1';
$awsRows = (int) q_setting('aws_last_sync_rows', '0');
?>
<section class="aws-banner <?= $awsSyncAt !== '' ? 'live' : ($awsConfigured ? 'ready' : 'demo') ?>">
    <span class="aws-banner-icon"><?= icon($awsSyncAt !== '' ? 'cloud' : ($awsConfigured ? 'key' : 'info')) ?></span>
    <div class="aws-banner-copy">
        <?php if ($awsSyncAt !== ''): ?>
            <strong>AWS Cost Explorer aktif.</strong>
            <span>
                Account <span class="mono"><?= e($awsAccount ?: '—') ?></span> &middot;
                sync terakhir <?= e(fmt_date($awsSyncAt, true)) ?> &middot;
                <?= e(num($awsRows)) ?> baris biaya.
            </span>
        <?php elseif ($awsConfigured && !$awsTestOk): ?>
            <strong>Kredensial AWS terdeteksi.</strong>
            <span>Jalankan <b>Tes Koneksi</b> lalu sinkronkan Cost Explorer untuk mengganti data demo.</span>
        <?php elseif ($awsConfigured): ?>
            <strong>Koneksi AWS siap.</strong>
            <span>Belum ada sinkronisasi. Tarik biaya nyata dari Cost Explorer sekarang.</span>
        <?php else: ?>
            <strong>Masih memakai data demo.</strong>
            <span>Isi <span class="mono">AWS_ACCESS_KEY_ID</span> dan <span class="mono">AWS_SECRET_ACCESS_KEY</span> di <span class="mono">.env</span> untuk menarik tagihan AWS asli.</span>
        <?php endif; ?>
    </div>
    <a class="btn btn-sm <?= $awsSyncAt === '' ? 'btn-primary' : '' ?>" href="<?= e(url('settings')) ?>#aws-connection">
        <?= icon($awsSyncAt === '' ? 'key' : 'refresh') ?> <?= $awsSyncAt === '' ? 'Hubungkan AWS' : 'Sync / Status' ?>
    </a>
</section>

<!-- KPI -->
<section class="grid kpi-grid">
    <article class="card kpi amber">
        <div class="kpi-top"><span class="kpi-label">Pengeluaran Bulan Ini</span></div>
        <div class="kpi-value"><?= e(money($mtd)) ?></div>
        <div class="kpi-sub">
            <?php if ($deltaPct !== null): ?>
                <span class="delta <?= $deltaPct > 0 ? 'up' : ($deltaPct < 0 ? 'down' : 'flat') ?>">
                    <?= $deltaPct > 0 ? '▲' : ($deltaPct < 0 ? '▼' : '•') ?> <?= e(pct(abs($deltaPct), 1)) ?>
                </span>
                <span>vs periode sama bulan lalu</span>
            <?php else: ?>
                <span>Sejak <?= e(fmt_date(month_start())) ?></span>
            <?php endif; ?>
        </div>
        <div class="kpi-icon"><?= icon('costs') ?></div>
        <div class="kpi-sub" style="margin-top: 10px;">
            <span class="tag">Proyeksi <?= e(money($projected)) ?></span>
            <span class="faint tiny"><?= (int) $dayOfMonth ?>/<?= (int) $daysInMonth ?> hari</span>
        </div>
    </article>

    <article class="card kpi blue">
        <div class="kpi-top"><span class="kpi-label">Bulan Lalu</span></div>
        <div class="kpi-value"><?= e(money($prevMonth)) ?></div>
        <div class="kpi-sub">
            <span><?= e(month_name((int) date('n', strtotime('first day of -1 month'))) . ' ' . date('Y', strtotime('first day of -1 month'))) ?></span>
        </div>
        <div class="kpi-icon"><?= icon('calendar') ?></div>
        <div class="kpi-sub" style="margin-top: 10px;">
            <span class="tag">Total 6 bulan <?= e(money($sixMonthTotal)) ?></span>
        </div>
    </article>

    <article class="card kpi green">
        <div class="kpi-top"><span class="kpi-label">Kredit AWS Tersisa</span></div>
        <div class="kpi-value"><?= e(money($creditTotal)) ?></div>
        <div class="kpi-sub">
            <span><?= count($accounts) ?> akun terhubung</span>
        </div>
        <div class="kpi-icon"><?= icon('wallet') ?></div>
        <div class="kpi-sub" style="margin-top: 10px;">
            <span class="tag"><?= $creditTotal > 0 ? 'Saldo aktif' : 'Kredit habis' ?></span>
        </div>
    </article>

    <article class="card kpi <?= $budgetsAtRisk > 0 ? 'red' : 'amber' ?>">
        <div class="kpi-top"><span class="kpi-label">Pemakaian Budget</span></div>
        <div class="kpi-value"><?= e(pct($budgetPct, 1)) ?></div>
        <div class="kpi-sub">
            <span><?= e(money($budgetSpent)) ?> / <?= e(money($budgetLimit)) ?></span>
        </div>
        <div class="kpi-icon"><?= icon('budget') ?></div>
        <div class="kpi-sub" style="margin-top: 10px;">
            <span class="tag"><?= (int) $budgetsAtRisk ?> budget perlu perhatian</span>
        </div>
    </article>
</section>

<!-- Grafik -->
<section class="grid cols-3">
    <article class="card">
        <div class="card-head">
            <div>
                <h3>Tren Pengeluaran 6 Bulan</h3>
                <p>Total <?= e(money($sixMonthTotal)) ?> · rata-rata <?= e(money($sixMonthTotal / max(1, count($series['totals'])))) ?>/bulan</p>
            </div>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('costs')) ?>">Rincian <?= icon('arrow-right') ?></a>
        </div>
        <div class="card-body">
            <div class="chart-box"><canvas id="chart-monthly"></canvas></div>
        </div>
    </article>

    <article class="card">
        <div class="card-head">
            <div>
                <h3>Biaya per Layanan</h3>
                <p>Bulan ini (<?= e(fmt_date(month_start())) ?> – <?= e(fmt_date(today())) ?>)</p>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($byService)): ?>
                <div class="empty"><?= icon('costs') ?><p>Belum ada biaya bulan ini.</p></div>
            <?php else: ?>
                <div class="donut" style="background: <?= e($donutStyle) ?>;">
                    <div class="donut-hole">
                        <strong><?= e(money($serviceTotal)) ?></strong>
                        <span>bulan ini</span>
                    </div>
                </div>
                <div class="donut-legend">
                    <?php foreach ($byService as $index => $service): ?>
                        <?php $share = $serviceTotal > 0 ? ((float) $service['total'] / $serviceTotal) * 100 : 0; ?>
                        <div class="donut-legend-item">
                            <span class="legend-swatch" style="background: <?= e($palette[$index % count($palette)]) ?>"></span>
                            <span class="name"><?= e($service['name']) ?></span>
                            <span class="value"><?= e(money($service['total'])) ?></span>
                            <span class="share"><?= e(pct($share, 0)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
</section>

<!-- Budget + alert -->
<section class="grid cols-2">
    <article class="card">
        <div class="card-head">
            <div>
                <h3>Kesehatan Budget</h3>
                <p>Proyeksi akhir periode berdasarkan burn-rate saat ini</p>
            </div>
            <a class="btn btn-sm" href="<?= e(url('budgets')) ?>">Kelola</a>
        </div>
        <div class="card-body" style="display: flex; flex-direction: column; gap: 15px;">
            <?php if (empty($budgets)): ?>
                <div class="empty"><?= icon('budget') ?><p>Belum ada budget aktif.</p></div>
            <?php else: ?>
                <?php foreach ($budgets as $budget): ?>
                    <div>
                        <div class="row-between" style="margin-bottom: 6px;">
                            <div>
                                <span class="strong small"><?= e($budget['alias']) ?></span>
                                <span class="faint tiny">· <?= e($budget['name']) ?></span>
                            </div>
                            <span class="badge <?= $budget['level'] === 'critical' ? 'badge-danger' : ($budget['level'] === 'warning' ? 'badge-warning' : 'badge-success') ?>">
                                <?= e(pct($budget['usage_pct'], 1)) ?>
                            </span>
                        </div>
                        <div class="progress <?= e($budget['level']) ?>">
                            <span style="width: <?= min(100, max(0, $budget['usage_pct'])) ?>%"></span>
                        </div>
                        <div class="row-between tiny faint" style="margin-top: 5px;">
                            <span><?= e(money($budget['spent'])) ?> dari <?= e(money($budget['limit_usd'])) ?></span>
                            <span>Proyeksi <?= e(money($budget['projected'])) ?> (<?= e(pct($budget['projected_pct'], 0)) ?>)</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </article>

    <article class="card">
        <div class="card-head">
            <div>
                <h3>Alert Terbaru</h3>
                <p><?= (int) q_unread_alert_count() ?> belum dibaca</p>
            </div>
            <a class="btn btn-sm" href="<?= e(url('alerts')) ?>">Buka pusat alert</a>
        </div>
        <div class="card-body tight">
            <?php if (empty($alerts)): ?>
                <div class="empty"><?= icon('shield') ?><p>Semua aman. Tidak ada alert.</p></div>
            <?php else: ?>
                <div class="alert-list">
                    <?php foreach (array_slice($alerts, 0, 4) as $alert): ?>
                        <div class="alert-item <?= $alert['is_read'] ? '' : 'unread' ?>">
                            <span class="alert-dot <?= e($alert['severity']) ?>"></span>
                            <div class="alert-body">
                                <div class="msg"><?= e($alert['message']) ?></div>
                                <div class="meta">
                                    <span><?= e($alert['account_alias'] ?? 'Global') ?></span>
                                    <span class="dot-sep">•</span>
                                    <span><?= e(relative_time($alert['created_at'])) ?></span>
                                    <?php if (!$alert['is_read']): ?>
                                        <span class="badge badge-warning">Baru</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
</section>

<!-- Akun -->
<section>
    <div class="row-between" style="margin-bottom: 10px;">
        <div>
            <h3 style="font-size: 13px;">Akun AWS</h3>
            <p class="tiny muted">Pemakaian bulan berjalan dibanding budget bulanan.</p>
        </div>
        <a class="btn btn-sm" href="<?= e(url('accounts')) ?>"><?= icon('plus') ?> Tambah akun</a>
    </div>
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(255px, 1fr));">
        <?php foreach ($accounts as $account): ?>
            <?php
            $spend = (float) $account['mtd_spend'];
            $limit = (float) $account['monthly_budget'];
            $usage = $limit > 0 ? min(100, $spend / $limit * 100) : 0;
            $level = $limit > 0 && $usage >= 100 ? 'critical' : ($limit > 0 && $usage >= 80 ? 'warning' : 'ok');
            ?>
            <article class="account-card">
                <div class="top">
                    <span class="env-dot <?= e($account['environment']) ?>"></span>
                    <div style="min-width: 0;">
                        <div class="name"><?= e($account['alias']) ?></div>
                        <div class="id"><?= e($account['account_id']) ?> · <?= e($account['region']) ?></div>
                    </div>
                    <span class="badge <?= $account['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>" style="margin-left: auto;">
                        <?= e(ucfirst($account['status'])) ?>
                    </span>
                </div>
                <div class="stats">
                    <div>Bulan ini<strong><?= e(money($spend)) ?></strong></div>
                    <div style="text-align: right;">Kredit<strong><?= e(money($account['credit_balance'])) ?></strong></div>
                </div>
                <div>
                    <div class="bar-row">
                        <span><?= e($limit > 0 ? pct($usage, 0) . ' dari ' . money($limit) : 'Budget belum diatur') ?></span>
                        <span class="<?= $level === 'critical' ? 'severity-critical' : ($level === 'warning' ? 'severity-warning' : 'faint') ?>">
                            <?= $level === 'critical' ? 'Over' : ($level === 'warning' ? 'Waspada' : 'Aman') ?>
                        </span>
                    </div>
                    <div class="progress <?= e($level) ?>"><span style="width: <?= $usage ?>%"></span></div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- Top layanan + import -->
<section class="grid cols-3">
    <article class="card">
        <div class="card-head">
            <div>
                <h3>Layanan Paling Mahal</h3>
                <p>Kontribusi terhadap biaya bulan ini</p>
            </div>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('services')) ?>">Harga satuan</a>
        </div>
        <div class="table-wrap">
            <?php if (empty($topServices)): ?>
                <div class="empty"><?= icon('services') ?><p>Belum ada data.</p></div>
            <?php else: ?>
                <table>
                    <thead>
                    <tr><th>Layanan</th><th>Kategori</th><th class="num">Biaya</th><th style="width: 130px;">Porsi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topServices as $service): ?>
                        <?php $share = $mtd > 0 ? ((float) $service['total'] / $mtd) * 100 : 0; ?>
                        <tr>
                            <td>
                                <div class="cell-main"><?= e($service['name']) ?></div>
                                <div class="cell-sub"><?= e($service['code']) ?></div>
                            </td>
                            <td class="muted"><?= e($service['category']) ?></td>
                            <td class="num"><?= e(money($service['total'])) ?></td>
                            <td>
                                <div class="progress"><span style="width: <?= min(100, $share) ?>%"></span></div>
                                <div class="cell-sub" style="text-align: right;"><?= e(pct($share, 0)) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </article>

    <article class="card">
        <div class="card-head">
            <div><h3>Aktivitas Import</h3><p>Riwayat unggahan CSV</p></div>
        </div>
        <div class="card-body tight">
            <?php if (empty($imports)): ?>
                <div class="empty"><?= icon('upload') ?><p>Belum ada import.</p></div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column;">
                    <?php foreach ($imports as $import): ?>
                        <div style="padding: 12px 16px; border-bottom: 1px solid var(--line-soft);" class="row-between">
                            <div style="min-width: 0;">
                                <div class="small strong" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= e($import['filename']) ?></div>
                                <div class="tiny faint"><?= (int) $import['rows_imported'] ?> baris · <?= e(relative_time($import['created_at'])) ?></div>
                            </div>
                            <span class="badge <?= $import['status'] === 'success' ? 'badge-success' : ($import['status'] === 'partial' ? 'badge-warning' : 'badge-danger') ?>">
                                <?= e(ucfirst($import['status'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-foot row-between">
            <span class="tiny faint">Format CSV: account_id, service_code, usage_date…</span>
            <a class="btn btn-sm" href="<?= e(url('costs')) ?>#import-csv"><?= icon('upload') ?> Import</a>
        </div>
    </article>
</section>

<div class="row tiny faint" style="gap: 8px;">
    <?= icon('clock') ?>
    <span>Data per <?= e(date('d M Y H:i')) ?> · proyeksi memakai laju <?= e(pct($monthProgress, 0)) ?> progres bulan</span>
    <span class="dot-sep">•</span>
    <span>Proyeksi budget <?= e(pct($projectedVsBudget, 0)) ?></span>
</div>
