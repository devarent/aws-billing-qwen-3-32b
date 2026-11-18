<?php
/**
 * @var array $settings
 * @var array $imports
 * @var array $dbInfo
 * @var array $aws
 */
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$pmaHost = preg_replace('/:\d+$/', '', $host) . ':8082';
$canSync = in_array((string) ($user['role'] ?? ''), ['admin', 'finance'], true);
$awsBadge = 'badge-muted';
$awsBadgeText = 'Belum dikonfigurasi';
if (!empty($aws['configured'])) {
    if (($aws['last_test'] ?? '') !== '' && !empty($aws['test_ok'])) {
        $awsBadge = 'badge-success';
        $awsBadgeText = 'Terhubung';
    } elseif (($aws['last_test'] ?? '') !== '') {
        $awsBadge = 'badge-danger';
        $awsBadgeText = 'Gagal terhubung';
    } else {
        $awsBadge = 'badge-warning';
        $awsBadgeText = 'Siap diuji';
    }
}
?>
<section class="card" id="aws-connection">
    <div class="card-head">
        <div>
            <h3><?= icon('cloud') ?> Koneksi AWS</h3>
            <p>Sinkronkan biaya nyata dari AWS Cost Explorer dan budget dari AWS Budgets.</p>
        </div>
        <span class="badge <?= e($awsBadge) ?>"><?= e($awsBadgeText) ?></span>
    </div>
    <div class="card-body">
        <div class="grid cols-2">
            <div>
                <div class="stat-line"><span>Access key</span><strong class="mono"><?= e($aws['access_key']) ?></strong></div>
                <div class="stat-line"><span>Region</span><strong class="mono"><?= e($aws['region']) ?></strong></div>
                <div class="stat-line"><span>Session token</span><strong><?= !empty($aws['has_token']) ? 'Aktif' : 'Tidak dipakai' ?></strong></div>
                <div class="stat-line"><span>Account ID</span><strong class="mono"><?= e($aws['account'] ?: '—') ?></strong></div>
                <div class="stat-line"><span>Terakhir tes</span><strong><?= e($aws['last_test'] ? fmt_date($aws['last_test'], true) : 'Belum pernah') ?></strong></div>
                <div class="stat-line"><span>Terakhir sync</span><strong><?= e($aws['last_sync'] ? fmt_date($aws['last_sync'], true) : 'Belum pernah') ?></strong></div>
                <?php if (($aws['sync_rows'] ?? '') !== ''): ?>
                    <div class="stat-line">
                        <span>Hasil sync terakhir</span>
                        <strong><?= e(num((int) $aws['sync_rows'])) ?> baris · <?= e(money((float) $aws['sync_total'])) ?></strong>
                    </div>
                <?php endif; ?>

                <?php if (!empty($aws['test_message'])): ?>
                    <div class="aws-test-note <?= !empty($aws['test_ok']) ? 'ok' : 'fail' ?>">
                        <?= icon(!empty($aws['test_ok']) ? 'check' : 'warning') ?>
                        <span><?= e($aws['test_message']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!$aws['configured']): ?>
                    <div class="aws-empty">
                        <strong>Mode data demo aktif.</strong>
                        Kredensial AWS belum terdeteksi (kosong atau masih placeholder), jadi dashboard
                        memakai data contoh. Isi <span class="mono">AWS_ACCESS_KEY_ID</span> dan
                        <span class="mono">AWS_SECRET_ACCESS_KEY</span> di <span class="mono">.env</span>,
                        lalu jalankan
                        <span class="mono">docker compose up -d --force-recreate ai-billing-hub</span>
                        agar environment baru terbaca.
                    </div>
                <?php endif; ?>

                <div class="row wrap mt-2">
                    <form method="post" action="<?= e(url('settings')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="aws_test">
                        <button class="btn" type="submit" <?= $canSync ? '' : 'disabled title="Butuh role admin/finance"' ?>>
                            <?= icon('key') ?> Tes Koneksi AWS
                        </button>
                    </form>
                    <form method="post" action="<?= e(url('settings')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="aws_sync">
                        <div class="row" style="gap: 8px;">
                            <select class="select select-sm" name="days" aria-label="Rentang hari" style="width: auto;">
                                <?php foreach ([7, 14, 30, 60, 90] as $d): ?>
                                    <option value="<?= $d ?>" <?= (int) $aws['sync_days'] === $d ? 'selected' : '' ?>><?= $d ?> hari</option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="submit" <?= $canSync ? '' : 'disabled title="Butuh role admin/finance"' ?>>
                                <?= icon('refresh') ?> Sinkron dari AWS
                            </button>
                        </div>
                    </form>
                </div>
                <p class="hint" style="margin-top: 10px;">
                    Sinkronisasi menghapus data <span class="mono">aws_ce</span> pada rentang tanggal yang sama
                    lalu mengisinya ulang, jadi aman dijalankan berulang kali.
                </p>
            </div>

            <div class="aws-guide">
                <h4>Izin IAM minimum (read-only)</h4>
                <ul>
                    <li><span class="mono">sts:GetCallerIdentity</span> — verifikasi akun.</li>
                    <li><span class="mono">ce:GetCostAndUsage</span> — tarik biaya per layanan.</li>
                    <li><span class="mono">budgets:DescribeBudgets</span> — tarik limit budget.</li>
                </ul>
                <p class="small muted mt-2">
                    Buat IAM user khusus billing, lampirkan policy read-only di atas, lalu isi key-nya
                    ke <span class="mono">.env</span>. Jangan gunakan root account key.
                </p>
                <div class="aws-region-note">
                    <?= icon('info') ?>
                    <span>Cost Explorer dipanggil lewat endpoint global <span class="mono">ce.us-east-1.amazonaws.com</span>.</span>
                </div>
                <p class="small mt-2 muted">
                    Panduan lengkap: <a href="<?= e(base_url() . '/docs/AWS_BILLING_SETUP.md') ?>" target="_blank" rel="noopener">docs/AWS_BILLING_SETUP.md</a>.
                </p>
            </div>
        </div>
    </div>
</section>

<section class="grid cols-3">
    <article class="card">
        <div class="card-head">
            <div><h3>Identitas & Ambang Alert</h3><p>Dipakai pada dashboard, pesan alert, dan email notifikasi.</p></div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(url('settings')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="settings_save">
                <div class="form-grid">
                    <div class="field">
                        <label for="st-name">Nama aplikasi</label>
                        <input class="input" type="text" id="st-name" name="company_name" maxlength="100"
                               value="<?= e($settings['company_name'] ?? 'Qween 3 32B') ?>">
                    </div>
                    <div class="field">
                        <label for="st-tagline">Tagline</label>
                        <input class="input" type="text" id="st-tagline" name="company_tagline" maxlength="120"
                               value="<?= e($settings['company_tagline'] ?? 'AWS Billing Manager') ?>">
                    </div>
                    <div class="field">
                        <label for="st-email">Email penerima alert</label>
                        <input class="input" type="email" id="st-email" name="alert_email" maxlength="190"
                               value="<?= e($settings['alert_email'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label for="st-region">Region default</label>
                        <input class="input mono" type="text" id="st-region" name="default_region" maxlength="32"
                               value="<?= e($settings['default_region'] ?? 'us-east-1') ?>">
                    </div>
                    <div class="field">
                        <label for="st-budget">Ambang alert budget (%)</label>
                        <input class="input" type="number" id="st-budget" name="budget_alert_threshold" min="1" max="100"
                               value="<?= e($settings['budget_alert_threshold'] ?? '80') ?>">
                    </div>
                    <div class="field">
                        <label for="st-credit">Ambang kredit minimum (USD)</label>
                        <input class="input" type="number" id="st-credit" name="credit_alert_threshold" min="0" step="0.01"
                               value="<?= e($settings['credit_alert_threshold'] ?? '20') ?>">
                    </div>
                    <div class="field">
                        <label for="st-currency">Mata uang tampilan</label>
                        <select class="select" id="st-currency" name="currency">
                            <option value="USD" <?= ($settings['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                        </select>
                        <span class="hint">Konversi kurs belum diterapkan pada versi ini.</span>
                    </div>
                    <div class="field" style="justify-content: flex-end;">
                        <label class="check">
                            <input type="hidden" name="auto_alerts" value="0">
                            <input type="checkbox" name="auto_alerts" value="1" <?= ($settings['auto_alerts'] ?? '1') === '1' ? 'checked' : '' ?>>
                            Aktifkan pemeriksaan alert otomatis
                        </label>
                        <span class="hint">Pemeriksaan berjalan saat halaman alert dibuka atau lewat cron.</span>
                    </div>
                </div>
                <div class="row" style="margin-top: 14px;">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Pengaturan</button>
                </div>
            </form>
        </div>
    </article>

    <article class="card">
        <div class="card-head">
            <div><h3>Status Sistem</h3><p>Runtime container & database.</p></div>
        </div>
        <div class="card-body">
            <div class="stat-line"><span>Host MySQL</span><strong class="mono"><?= e($dbInfo['host']) ?></strong></div>
            <div class="stat-line"><span>Database</span><strong class="mono"><?= e($dbInfo['database']) ?></strong></div>
            <div class="stat-line"><span>Versi MySQL</span><strong class="mono"><?= e($dbInfo['version']) ?></strong></div>
            <div class="stat-line"><span>PHP</span><strong class="mono"><?= e($dbInfo['php']) ?></strong></div>
            <div class="stat-line"><span>Timezone</span><strong class="mono"><?= e($dbInfo['timezone']) ?></strong></div>
            <div class="stat-line"><span>Catatan biaya</span><strong><?= e(num($dbInfo['records'])) ?></strong></div>

            <div class="divider"></div>

            <a class="btn btn-block" href="http://<?= e($pmaHost) ?>/" target="_blank" rel="noopener">
                <?= icon('server') ?> Buka phpMyAdmin
            </a>
            <p class="hint" style="margin-top: 8px;">
                phpMyAdmin berjalan di port <span class="mono">8082</span> dan terhubung ke container
                <span class="mono">billing-db</span>.
            </p>
        </div>
    </article>
</section>

<section class="card">
    <div class="card-head">
        <div><h3>Riwayat Import</h3><p>Audit unggahan CSV biaya.</p></div>
        <a class="btn btn-sm" href="<?= e(url('costs')) ?>#import-csv"><?= icon('upload') ?> Import baru</a>
    </div>
    <div class="table-wrap">
        <?php if (empty($imports)): ?>
            <div class="empty"><?= icon('upload') ?><p>Belum ada riwayat import.</p></div>
        <?php else: ?>
            <table>
                <thead>
                <tr><th>File</th><th class="num">Masuk</th><th class="num">Dilewati</th><th>Status</th><th>Pesan</th><th>Waktu</th></tr>
                </thead>
                <tbody>
                <?php foreach ($imports as $import): ?>
                    <tr>
                        <td class="mono"><?= e($import['filename']) ?></td>
                        <td class="num"><?= (int) $import['rows_imported'] ?></td>
                        <td class="num"><?= (int) $import['rows_skipped'] ?></td>
                        <td>
                            <span class="badge <?= $import['status'] === 'success' ? 'badge-success' : ($import['status'] === 'partial' ? 'badge-warning' : 'badge-danger') ?>">
                                <?= e(ucfirst($import['status'])) ?>
                            </span>
                        </td>
                        <td class="small muted"><?= e($import['message'] ?: '—') ?></td>
                        <td class="small muted nowrap"><?= e(fmt_date($import['created_at'], true)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<section class="card card-body">
    <div class="row" style="align-items: flex-start; gap: 12px;">
        <span class="kpi-icon" style="position: static; flex: 0 0 auto;"><?= icon('shield') ?></span>
        <div>
            <h3 style="font-size: 13px;">Catatan keamanan</h3>
            <p class="small muted mt-1">
                Aplikasi ini berjalan lokal di Docker untuk demo dan tugas. Ganti password demo, jangan buka port
                ke internet publik, dan gunakan kredensial database yang kuat sebelum deploy ke server nyata.
                Kredensial API provider tetap disimpan di <span class="mono">.env</span> dan tidak masuk image.
            </p>
        </div>
    </div>
</section>
