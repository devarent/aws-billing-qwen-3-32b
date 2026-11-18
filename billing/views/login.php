<?php
/** @var string $pageTitle */
$appName = q_setting('company_name', 'Qween 3 32B');
$appTagline = q_setting('company_tagline', 'AWS Billing Manager');
$awsReady = aws_is_configured();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#070d18">
    <title>Masuk · <?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='24' fill='%23ffb02e'/><text x='50' y='68' font-size='54' font-family='Arial' font-weight='bold' text-anchor='middle' fill='%231a1203'>Q</text></svg>">
</head>
<body>
<div class="login-wrap">
    <section class="login-hero">
        <div>
            <div class="brand">
                <div class="brand-mark">Q</div>
                <div>
                    <h1 style="font-size:15px; margin:0;"><?= e($appName) ?></h1>
                    <p style="margin:0;"><?= e($appTagline) ?></p>
                </div>
            </div>

            <h1>Kelola biaya AWS <span>tanpa</span> kejutan tagihan.</h1>
            <p>
                Dashboard lokal untuk memantau pengeluaran per akun, budget guardrail,
                invoice, dan alert. Tarik data nyata dari AWS Cost Explorer memakai
                kredensial read-only di <span class="mono">.env</span>.
            </p>

            <div class="login-badges">
                <span class="aws-chip <?= $awsReady ? 'live' : 'offline' ?>">
                    <span class="pulse"></span><?= $awsReady ? 'AWS key terdeteksi' : 'Mode data demo' ?>
                </span>
                <span class="tag"><?= icon('server') ?> Docker / WSL 2</span>
            </div>

            <div class="login-points">
                <div class="login-point">
                    <span class="tick"><?= icon('trend') ?></span>
                    <div><strong>AWS Cost Explorer.</strong> Riwayat 6 bulan, rincian harian per layanan, dan proyeksi akhir bulan.</div>
                </div>
                <div class="login-point">
                    <span class="tick"><?= icon('shield') ?></span>
                    <div><strong>Budget Guardrail.</strong> Alert otomatis, hard-stop flag, dan pemeriksaan dengan satu klik.</div>
                </div>
                <div class="login-point">
                    <span class="tick"><?= icon('server') ?></span>
                    <div><strong>Data Lokal & Aman.</strong> MySQL 8 di container terpisah, volume persisten, dan phpMyAdmin.</div>
                </div>
            </div>
        </div>

        <div class="login-foot">
            Docker Compose · PHP <?= e(PHP_VERSION) ?> · MySQL 8 · <?= e(date('Y')) ?>
        </div>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <h2>Masuk ke Dashboard</h2>
            <p>Silakan gunakan akun demo atau akun yang terdaftar di database billing.</p>

            <?php foreach (flashes() as $flash): ?>
                <div class="flash <?= e($flash['type']) ?>" style="margin-top: 18px;">
                    <?= icon($flash['type'] === 'error' ? 'warning' : 'info') ?>
                    <div><?= e($flash['message']) ?></div>
                </div>
            <?php endforeach; ?>

            <form method="post" action="<?= e(url('login')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" placeholder="admin@qween.local"
                           value="admin@qween.local" autocomplete="username" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" type="password" id="password" name="password" placeholder="••••••••"
                           autocomplete="current-password" required>
                </div>
                <input class="btn btn-primary btn-block" type="submit" value="Masuk ke Dashboard">
            </form>

            <div class="demo-box">
                <div class="title">Akun Demo</div>
                <div class="line"><span>Admin</span><code>admin@qween.local / qween123</code></div>
                <div class="line"><span>Finance</span><code>finance@qween.local / qween123</code></div>
                <div class="line"><span>Viewer</span><code>viewer@qween.local / qween123</code></div>
            </div>

            <p class="hint" style="margin-top: 16px;">
                Jalankan <span class="mono">docker compose up -d --build</span>.<br>
                Isi <span class="mono">AWS_ACCESS_KEY_ID</span> &amp; <span class="mono">AWS_SECRET_ACCESS_KEY</span>
                di <span class="mono">.env</span>, lalu
                <span class="mono">docker compose up -d --force-recreate ai-billing-hub</span>
                untuk sinkronisasi Cost Explorer.
            </p>
        </div>
    </section>
</div>
</body>
</html>
