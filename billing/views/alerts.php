<?php
/**
 * @var array $alerts
 * @var bool $unreadOnly
 * @var int $unreadCount
 */
$typeLabels = [
    'budget_threshold' => 'Budget',
    'credit_low'       => 'Kredit',
    'anomaly'          => 'Anomali',
    'invoice_overdue'  => 'Invoice',
];
$severityIcons = ['info' => 'info', 'warning' => 'warning', 'critical' => 'warning'];
$severityLabels = ['info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical'];
?>
<section class="card">
    <div class="card-head">
        <div>
            <h3>Alert Center</h3>
            <p><?= (int) $unreadCount ?> alert belum dibaca dari <?= count($alerts) ?> alert ditampilkan.</p>
        </div>
        <div class="toolbar">
            <div class="segmented">
                <a class="<?= !$unreadOnly ? 'active' : '' ?>" href="<?= e(url('alerts')) ?>">Semua</a>
                <a class="<?= $unreadOnly ? 'active' : '' ?>" href="<?= e(url('alerts', ['filter' => 'unread'])) ?>">Belum dibaca</a>
            </div>
            <form method="post" action="<?= e(url('alerts')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="run_budget_check">
                <button class="btn btn-sm" type="submit"><?= icon('refresh') ?> Periksa</button>
            </form>
            <?php if ($unreadCount > 0): ?>
                <form method="post" action="<?= e(url('alerts')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="alert_ack_all">
                    <button class="btn btn-sm btn-ghost" type="submit"><?= icon('check') ?> Tandai semua</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($alerts)): ?>
        <div class="empty">
            <?= icon('shield') ?>
            <p>Belum ada alert. Semua budget dan kredit dalam kondisi aman.</p>
        </div>
    <?php else: ?>
        <div class="alert-list">
            <?php foreach ($alerts as $alert): ?>
                <div class="alert-item <?= $alert['is_read'] ? '' : 'unread' ?>">
                    <span class="alert-dot <?= e($alert['severity']) ?>"></span>
                    <div class="alert-body">
                        <div class="row-between" style="align-items: flex-start;">
                            <div class="msg"><?= e($alert['message']) ?></div>
                            <?php if (!$alert['is_read']): ?>
                                <form method="post" action="<?= e(url('alerts')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="alert_ack">
                                    <input type="hidden" name="id" value="<?= (int) $alert['id'] ?>">
                                    <button class="btn btn-sm btn-ghost" type="submit"><?= icon('check') ?> Tandai</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="meta">
                            <span class="badge badge-muted"><?= e($typeLabels[$alert['type']] ?? $alert['type']) ?></span>
                            <span class="badge <?= $alert['severity'] === 'critical' ? 'badge-danger' : ($alert['severity'] === 'warning' ? 'badge-warning' : 'badge-blue') ?>">
                                <?= e($severityLabels[$alert['severity']] ?? $alert['severity']) ?>
                            </span>
                            <span><?= e($alert['account_alias'] ?? 'Global') ?></span>
                            <span class="dot-sep">•</span>
                            <span><?= e(relative_time($alert['created_at'])) ?></span>
                            <?php if ($alert['acknowledged_at']): ?>
                                <span class="dot-sep">•</span>
                                <span>Dibaca <?= e(relative_time($alert['acknowledged_at'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card card-body">
    <div class="row" style="align-items: flex-start; gap: 12px;">
        <span class="kpi-icon" style="position: static; flex: 0 0 auto;"><?= icon('info') ?></span>
        <div>
            <h3 style="font-size: 13px;">Bagaimana alert dibuat?</h3>
            <p class="small muted mt-1">
                Klik <strong>Periksa</strong> untuk mengevaluasi seluruh budget aktif, kredit akun yang berada di bawah ambang,
                dan invoice yang melewati jatuh tempo. Alert duplikat untuk periode yang sama otomatis dicegah.
                Pemeriksaan juga bisa dijalankan dari CLI:
            </p>
            <pre class="mono tiny" style="background:#0a1424;border:1px solid var(--line);border-radius:9px;padding:10px;margin-top:9px;overflow:auto;">docker compose exec ai-billing-hub php /var/www/html/billing/cron/check_budgets.php</pre>
        </div>
    </div>
</section>
