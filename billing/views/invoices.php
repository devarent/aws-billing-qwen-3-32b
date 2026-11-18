<?php
/**
 * @var array $invoices
 * @var array $summary
 * @var array $accounts
 * @var string $status
 * @var string $defaultStart
 * @var string $defaultEnd
 */
$outstanding = $summary['issued']['total'] + $summary['overdue']['total'];
$paidTotal = $summary['paid']['total'];
$overdueCount = $summary['overdue']['count'];
?>
<section class="grid kpi-grid">
    <article class="card kpi amber">
        <div class="kpi-top"><span class="kpi-label">Outstanding</span></div>
        <div class="kpi-value" style="font-size: 27px;"><?= e(money($outstanding)) ?></div>
        <div class="kpi-sub"><?= (int) ($summary['issued']['count'] + $summary['overdue']['count']) ?> invoice belum lunas</div>
        <div class="kpi-icon"><?= icon('invoices') ?></div>
    </article>
    <article class="card kpi green">
        <div class="kpi-top"><span class="kpi-label">Sudah Dibayar</span></div>
        <div class="kpi-value" style="font-size: 27px;"><?= e(money($paidTotal)) ?></div>
        <div class="kpi-sub"><?= (int) $summary['paid']['count'] ?> invoice lunas</div>
        <div class="kpi-icon"><?= icon('check') ?></div>
    </article>
    <article class="card kpi red">
        <div class="kpi-top"><span class="kpi-label">Jatuh Tempo</span></div>
        <div class="kpi-value" style="font-size: 27px;"><?= (int) $overdueCount ?></div>
        <div class="kpi-sub"><?= e(money($summary['overdue']['total'])) ?> perlu ditindaklanjuti</div>
        <div class="kpi-icon"><?= icon('warning') ?></div>
    </article>
    <article class="card kpi blue">
        <div class="kpi-top"><span class="kpi-label">Total Invoice</span></div>
        <div class="kpi-value" style="font-size: 27px;"><?= e(num(array_sum(array_column($summary, 'count')))) ?></div>
        <div class="kpi-sub">Semua status</div>
        <div class="kpi-icon"><?= icon('calendar') ?></div>
    </article>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3>Daftar Invoice</h3>
            <p>Tagihan bulanan per akun AWS.</p>
        </div>
        <div class="segmented">
            <a class="<?= $status === '' ? 'active' : '' ?>" href="<?= e(url('invoices')) ?>">Semua</a>
            <a class="<?= $status === 'issued' ? 'active' : '' ?>" href="<?= e(url('invoices', ['status' => 'issued'])) ?>">Terbit</a>
            <a class="<?= $status === 'paid' ? 'active' : '' ?>" href="<?= e(url('invoices', ['status' => 'paid'])) ?>">Lunas</a>
            <a class="<?= $status === 'overdue' ? 'active' : '' ?>" href="<?= e(url('invoices', ['status' => 'overdue'])) ?>">Overdue</a>
        </div>
    </div>

    <div class="table-wrap">
        <?php if (empty($invoices)): ?>
            <div class="empty"><?= icon('invoices') ?><p>Tidak ada invoice pada filter ini.</p></div>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Akun</th>
                    <th>Periode</th>
                    <th class="num">Nominal</th>
                    <th>Jatuh tempo</th>
                    <th>Status</th>
                    <th class="num">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <?php [$statusLabel, $statusClass] = status_meta($invoice['status']); ?>
                    <tr>
                        <td>
                            <div class="cell-main mono"><?= e($invoice['invoice_number']) ?></div>
                            <div class="cell-sub">Dibuat <?= e(fmt_date($invoice['created_at'])) ?></div>
                        </td>
                        <td>
                            <div><?= e($invoice['alias']) ?></div>
                            <div class="cell-sub"><?= e($invoice['aws_account_id']) ?></div>
                        </td>
                        <td class="nowrap">
                            <?= e(fmt_date($invoice['period_start'])) ?> –<br>
                            <span class="cell-sub"><?= e(fmt_date($invoice['period_end'])) ?></span>
                        </td>
                        <td class="num strong"><?= e(money($invoice['amount_usd'])) ?></td>
                        <td class="nowrap">
                            <?= e(fmt_date($invoice['due_date'])) ?>
                            <?php if ($invoice['status'] === 'overdue'): ?>
                                <div class="cell-sub" style="color: #fda4af;">Lewat jatuh tempo</div>
                            <?php elseif ($invoice['status'] === 'paid' && $invoice['paid_at']): ?>
                                <div class="cell-sub">Lunas <?= e(fmt_date($invoice['paid_at'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($invoice['status'] === 'paid'): ?>
                                <span class="badge badge-success">Lunas</span>
                            <?php elseif ($invoice['status'] === 'overdue'): ?>
                                <span class="badge badge-danger">Overdue</span>
                            <?php else: ?>
                                <span class="badge <?= $statusClass ?>"><?= e($statusLabel) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num nowrap">
                            <?php if ($invoice['status'] !== 'paid' && $invoice['status'] !== 'void'): ?>
                                <form method="post" action="<?= e(url('invoices')) ?>" style="display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="invoice_pay">
                                    <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
                                    <button class="btn btn-sm" type="submit" title="Tandai lunas"><?= icon('check') ?> Lunas</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('invoices')) ?>" data-confirm="Hapus invoice <?= e($invoice['invoice_number']) ?>?" style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="invoice_delete">
                                <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
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
            <h3>Terbitkan Invoice</h3>
            <p>Nominal dihitung otomatis dari biaya pada rentang periode bila dikosongkan.</p>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('invoices')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="invoice_save">
            <div class="form-grid">
                <div class="field">
                    <label for="i-account">Akun AWS</label>
                    <select class="select" id="i-account" name="account_id" required>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?= (int) $account['id'] ?>"><?= e($account['alias']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="i-amount">Nominal (USD)</label>
                    <input class="input" type="number" id="i-amount" name="amount_usd" min="0" step="0.01" placeholder="Kosongkan = hitung otomatis">
                </div>
                <div class="field">
                    <label for="i-start">Periode mulai</label>
                    <input class="input" type="date" id="i-start" name="period_start" value="<?= e($defaultStart) ?>" required>
                </div>
                <div class="field">
                    <label for="i-end">Periode selesai</label>
                    <input class="input" type="date" id="i-end" name="period_end" value="<?= e($defaultEnd) ?>" required>
                </div>
                <div class="field">
                    <label for="i-due">Jatuh tempo</label>
                    <input class="input" type="date" id="i-due" name="due_date" value="<?= e(date('Y-m-d', strtotime($defaultEnd . ' +20 days'))) ?>">
                </div>
            </div>
            <div class="row" style="margin-top: 14px;">
                <button class="btn btn-primary" type="submit"><?= icon('plus') ?> Terbitkan Invoice</button>
                <span class="hint">Nomor invoice dibuat otomatis: <span class="mono">INV-YYMM-XXXX</span>.</span>
            </div>
        </form>
    </div>
</section>
