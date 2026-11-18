<?php
/**
 * @var array $budgets
 * @var array $accounts
 * @var string $defaultThreshold
 * @var float $totalLimit
 * @var float $totalSpent
 */
$totalRemaining = max(0, $totalLimit - $totalSpent);
$atRisk = count(array_filter($budgets, static fn ($b) => $b['level'] !== 'ok'));
?>
<section class="grid kpi-grid">
    <article class="card kpi amber">
        <div class="kpi-top"><span class="kpi-label">Total Limit Budget</span></div>
        <div class="kpi-value" style="font-size: 27px;"><?= e(money($totalLimit)) ?></div>
        <div class="kpi-sub"><?= count($budgets) ?> budget aktif</div>
        <div class="kpi-icon"><?= icon('budget') ?></div>
    </article>
    <article class="card kpi blue">
        <div class="kpi-top"><span class="kpi-label">Terpakai Periode Ini</span></div>
        <div class="kpi-value" style="font-size: 27px;"><?= e(money($totalSpent)) ?></div>
        <div class="kpi-sub"><?= e(pct($totalLimit > 0 ? $totalSpent / $totalLimit * 100 : 0, 1)) ?> dari total limit</div>
        <div class="kpi-icon"><?= icon('costs') ?></div>
    </article>
    <article class="card kpi green">
        <div class="kpi-top"><span class="kpi-label">Sisa Anggaran</span></div>
        <div class="kpi-value" style="font-size: 27px;"><?= e(money($totalRemaining)) ?></div>
        <div class="kpi-sub">Sebelum menyentuh hard-stop</div>
        <div class="kpi-icon"><?= icon('shield') ?></div>
    </article>
    <article class="card kpi <?= $atRisk > 0 ? 'red' : 'green' ?>">
        <div class="kpi-top"><span class="kpi-label">Perlu Perhatian</span></div>
        <div class="kpi-value" style="font-size: 27px;"><?= (int) $atRisk ?></div>
        <div class="kpi-sub">Melewati ambang atau proyeksi over</div>
        <div class="kpi-icon"><?= icon('alerts') ?></div>
    </article>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3>Guardrail Budget</h3>
            <p>Pemakaian aktual dan proyeksi akhir periode berdasarkan burn-rate.</p>
        </div>
        <form method="post" action="<?= e(url('budgets')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_budget_check">
            <button class="btn btn-sm" type="submit"><?= icon('refresh') ?> Periksa Sekarang</button>
        </form>
    </div>

    <div class="table-wrap">
        <?php if (empty($budgets)): ?>
            <div class="empty"><?= icon('budget') ?><p>Belum ada budget. Buat di form bawah.</p></div>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Budget</th>
                    <th>Periode</th>
                    <th class="num">Limit</th>
                    <th class="num">Terpakai</th>
                    <th style="min-width: 180px;">Progres</th>
                    <th class="num">Proyeksi</th>
                    <th>Status</th>
                    <th class="num">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($budgets as $budget): ?>
                    <tr>
                        <td>
                            <div class="cell-main"><?= e($budget['name']) ?></div>
                            <div class="cell-sub"><?= e($budget['alias']) ?> · <?= e(ucfirst($budget['environment'])) ?></div>
                        </td>
                        <td class="nowrap">
                            <div class="small"><?= e(month_name((int) date('n', strtotime($budget['period_start']))) . ' ' . date('Y', strtotime($budget['period_start']))) ?></div>
                            <div class="cell-sub"><?= e(fmt_date($budget['period_start'])) ?> – <?= e(fmt_date($budget['period_end'])) ?></div>
                        </td>
                        <td class="num"><?= e(money($budget['limit_usd'])) ?></td>
                        <td class="num strong"><?= e(money($budget['spent'])) ?></td>
                        <td>
                            <div class="row-between tiny" style="margin-bottom: 4px;">
                                <span><?= e(pct($budget['usage_pct'], 1)) ?></span>
                                <span class="faint">threshold <?= (int) $budget['alert_threshold'] ?>%</span>
                            </div>
                            <div class="progress <?= e($budget['level']) ?>">
                                <span style="width: <?= min(100, max(0, $budget['usage_pct'])) ?>%"></span>
                            </div>
                        </td>
                        <td class="num">
                            <div><?= e(money($budget['projected'])) ?></div>
                            <div class="cell-sub"><?= e(pct($budget['projected_pct'], 0)) ?> dari limit</div>
                        </td>
                        <td>
                            <?php if ($budget['level'] === 'critical'): ?>
                                <span class="badge badge-danger">Over / kritis</span>
                            <?php elseif ($budget['level'] === 'warning'): ?>
                                <span class="badge badge-warning">Waspada</span>
                            <?php else: ?>
                                <span class="badge badge-success">Aman</span>
                            <?php endif; ?>
                            <?php if ((int) $budget['hard_stop'] === 1): ?>
                                <div class="cell-sub" style="margin-top: 4px;"><?= icon('shield') ?> hard-stop</div>
                            <?php endif; ?>
                        </td>
                        <td class="num">
                            <form method="post" action="<?= e(url('budgets')) ?>" data-confirm="Hapus budget <?= e($budget['name']) ?>?" style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="budget_delete">
                                <input type="hidden" name="id" value="<?= (int) $budget['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit" title="Hapus"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3>Buat Budget Baru</h3>
            <p>Alert dibuat otomatis saat pemakaian melewati threshold.</p>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('budgets')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="budget_save">
            <div class="form-grid">
                <div class="field">
                    <label for="b-account">Akun AWS</label>
                    <select class="select" id="b-account" name="account_id" required>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>"><?= e($account['alias']) ?> · <?= e($account['environment']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="b-name">Nama budget</label>
                    <input class="input" type="text" id="b-name" name="name" required maxlength="120" placeholder="Monthly Guard — Production">
                </div>
                <div class="field">
                    <label for="b-period">Periode</label>
                    <select class="select" id="b-period" name="period">
                        <option value="monthly">Bulanan</option>
                        <option value="quarterly">Kuartalan</option>
                        <option value="annual">Tahunan</option>
                    </select>
                </div>
                <div class="field">
                    <label for="b-limit">Limit (USD)</label>
                    <input class="input" type="number" id="b-limit" name="limit_usd" min="0.01" step="0.01" required placeholder="120.00">
                </div>
                <div class="field">
                    <label for="b-threshold">Ambang alert (%)</label>
                    <input class="input" type="number" id="b-threshold" name="alert_threshold" min="1" max="100" value="<?= e($defaultThreshold) ?>">
                </div>
                <div class="field" style="justify-content: flex-end;">
                    <label class="check"><input type="checkbox" name="hard_stop" value="1"> Tandai sebagai hard-stop</label>
                    <span class="hint">Flag untuk integrasi enforcement di masa depan.</span>
                </div>
            </div>
            <div class="row" style="margin-top: 14px;">
                <button class="btn btn-primary" type="submit"><?= icon('plus') ?> Buat Budget</button>
                <span class="hint">Budget bulanan juga otomatis dibuat saat menambah akun dengan nominal budget.</span>
            </div>
        </form>
    </div>
</section>
