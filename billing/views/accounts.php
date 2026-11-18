<?php
/**
 * @var array $accounts
 * @var array|null $editAccount
 * @var string $defaultRegion
 */
$editing = $editAccount !== null;
?>
<section class="card">
    <div class="card-head">
        <div>
            <h3>Portofolio Akun AWS</h3>
            <p><?= count($accounts) ?> akun · total kredit <?= e(money(array_sum(array_column($accounts, 'credit_balance')))) ?></p>
        </div>
        <a class="btn btn-sm btn-primary" href="#form-akun"><?= icon('plus') ?> <?= $editing ? 'Form edit terbuka' : 'Tambah akun' ?></a>
    </div>
    <div class="card-body">
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
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
                            <div class="id"><?= e($account['account_id']) ?></div>
                        </div>
                        <span class="badge <?= $account['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>" style="margin-left: auto;">
                            <?= e(ucfirst($account['status'])) ?>
                        </span>
                    </div>

                    <div class="row wrap tiny muted" style="gap: 6px;">
                        <span class="tag"><?= e(ucfirst($account['environment'])) ?></span>
                        <span class="tag"><?= e($account['region']) ?></span>
                        <?php if (!empty($account['owner_email'])): ?><span class="tag"><?= e($account['owner_email']) ?></span><?php endif; ?>
                    </div>

                    <div class="stats">
                        <div>Bulan ini<strong><?= e(money($spend)) ?></strong></div>
                        <div>Bulan lalu<strong><?= e(money($account['prev_spend'])) ?></strong></div>
                        <div style="text-align: right;">Kredit<strong><?= e(money($account['credit_balance'])) ?></strong></div>
                    </div>

                    <div>
                        <div class="bar-row">
                            <span><?= e($limit > 0 ? pct($usage, 0) . ' dari ' . money($limit) : 'Budget belum diatur') ?></span>
                            <span class="faint"><?= (int) $account['record_count'] ?> catatan</span>
                        </div>
                        <div class="progress <?= e($level) ?>"><span style="width: <?= $usage ?>%"></span></div>
                    </div>

                    <div class="row" style="gap: 7px; padding-top: 2px;">
                        <a class="btn btn-sm" href="<?= e(url('accounts', ['edit' => $account['id']])) ?>#form-akun"><?= icon('settings') ?> Edit</a>
                        <a class="btn btn-sm btn-ghost" href="<?= e(url('costs', ['account_id' => $account['id']])) ?>">Biaya</a>
                        <form method="post" action="<?= e(url('accounts')) ?>" data-confirm="Hapus akun <?= e($account['alias']) ?>? Semua biaya, budget, dan invoice terkait ikut terhapus." style="margin-left: auto;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="account_delete">
                            <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit" title="Hapus akun"><?= icon('trash') ?></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="card" id="form-akun">
    <div class="card-head">
        <div>
            <h3><?= $editing ? 'Edit Akun AWS' : 'Tambah Akun AWS' ?></h3>
            <p><?= $editing ? 'Perubahan langsung dipakai pada perhitungan berikutnya.' : 'Budget bulanan otomatis dibuat bila nominal diisi.' ?></p>
        </div>
        <?php if ($editing): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('accounts')) ?>">Batal edit</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('accounts')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="account_save">
            <input type="hidden" name="id" value="<?= $editing ? (int) $editAccount['id'] : 0 ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="a-alias">Nama / alias</label>
                    <input class="input" type="text" id="a-alias" name="alias" required maxlength="120"
                           value="<?= e($editing ? $editAccount['alias'] : '') ?>" placeholder="Qween Production">
                </div>
                <div class="field">
                    <label for="a-account-id">AWS Account ID (12 digit)</label>
                    <input class="input mono" type="text" id="a-account-id" name="account_id" required pattern="\d{12}" maxlength="12"
                           value="<?= e($editing ? $editAccount['account_id'] : '') ?>" placeholder="111122223333">
                </div>
                <div class="field">
                    <label for="a-env">Environment</label>
                    <select class="select" id="a-env" name="environment">
                        <?php foreach (['production', 'staging', 'development', 'sandbox'] as $env): ?>
                            <option value="<?= e($env) ?>" <?= $editing && $editAccount['environment'] === $env ? 'selected' : '' ?>>
                                <?= e(ucfirst($env)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="a-region">Region default</label>
                    <input class="input mono" type="text" id="a-region" name="region" maxlength="32"
                           value="<?= e($editing ? $editAccount['region'] : $defaultRegion) ?>">
                </div>
                <div class="field">
                    <label for="a-credit">Kredit tersisa (USD)</label>
                    <input class="input" type="number" id="a-credit" name="credit_balance" min="0" step="0.01"
                           value="<?= e($editing ? $editAccount['credit_balance'] : '0.00') ?>">
                </div>
                <div class="field">
                    <label for="a-budget">Budget bulanan (USD)</label>
                    <input class="input" type="number" id="a-budget" name="monthly_budget" min="0" step="0.01"
                           value="<?= e($editing ? $editAccount['monthly_budget'] : '0.00') ?>">
                    <span class="hint">Dipakai untuk kartu budget dan guardrail.</span>
                </div>
                <div class="field">
                    <label for="a-status">Status</label>
                    <select class="select" id="a-status" name="status">
                        <?php foreach (['active' => 'Active', 'suspended' => 'Suspended', 'closed' => 'Closed'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $editing && $editAccount['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="a-owner">Email owner</label>
                    <input class="input" type="email" id="a-owner" name="owner_email" maxlength="190"
                           value="<?= e($editing ? $editAccount['owner_email'] : '') ?>" placeholder="cloudops@qween.local">
                </div>
            </div>

            <div class="row" style="margin-top: 16px;">
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $editing ? 'Simpan Perubahan' : 'Tambah Akun' ?></button>
                <span class="hint">Menghapus akun akan menghapus seluruh biaya, budget, invoice, dan alert terkait.</span>
            </div>
        </form>
    </div>
</section>
