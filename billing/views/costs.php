<?php
/**
 * @var array $filters
 * @var array $rows
 * @var array $totals
 * @var array $accounts
 * @var array $services
 * @var array $imports
 * @var int $perPage
 * @var int $currentPageNo
 * @var int $totalPages
 * @var int $totalRows
 * @var string $from
 * @var string $to
 */
$baseParams = array_filter([
    'account_id' => $filters['account_id'] ?: null,
    'service_id' => $filters['service_id'] ?: null,
    'from'       => $filters['from'] ?: null,
    'to'         => $filters['to'] ?: null,
    'q'          => $filters['q'] ?: null,
], static fn ($value) => $value !== null && $value !== '');

$pageUrl = static function (int $page) use ($baseParams): string {
    return url('costs', $baseParams + ['p' => $page]);
};
$exportUrl = url('costs', $baseParams + ['export' => 'csv']);
$firstRow = $totalRows > 0 ? (($currentPageNo - 1) * $perPage) + 1 : 0;
$lastRow = min($totalRows, $currentPageNo * $perPage);
?>
<section class="card">
    <div class="card-head">
        <div>
            <h3>Filter Biaya</h3>
            <p>Rentang default: awal bulan ini sampai hari ini.</p>
        </div>
        <div class="toolbar">
            <a class="btn btn-sm" href="<?= e($exportUrl) ?>"><?= icon('download') ?> Export CSV</a>
            <a class="btn btn-sm btn-primary" href="#tambah-biaya"><?= icon('plus') ?> Tambah</a>
        </div>
    </div>
    <div class="card-body">
        <form method="get" action="index.php" class="filter-bar">
            <input type="hidden" name="page" value="costs">
            <div class="field">
                <label for="f-account">Akun</label>
                <select class="select" id="f-account" name="account_id">
                    <option value="">Semua akun</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= (int) $account['id'] ?>" <?= (int) $filters['account_id'] === (int) $account['id'] ? 'selected' : '' ?>>
                            <?= e($account['alias']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-service">Layanan</label>
                <select class="select" id="f-service" name="service_id">
                    <option value="">Semua layanan</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= (int) $service['id'] ?>" <?= (int) $filters['service_id'] === (int) $service['id'] ? 'selected' : '' ?>>
                            <?= e($service['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-from">Dari tanggal</label>
                <input class="input" type="date" id="f-from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="field">
                <label for="f-to">Sampai</label>
                <input class="input" type="date" id="f-to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="field grow">
                <label for="f-q">Cari</label>
                <input class="input" type="search" id="f-q" name="q" value="<?= e($filters['q']) ?>" placeholder="Akun, layanan, atau catatan…">
            </div>
            <button class="btn btn-primary" type="submit"><?= icon('search') ?> Terapkan</button>
            <a class="btn btn-ghost" href="<?= e(url('costs')) ?>">Reset</a>
        </form>
    </div>
</section>

<section class="grid" style="grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));">
    <div class="card card-body">
        <div class="kpi-label">Total Biaya (filter)</div>
        <div class="kpi-value" style="font-size: 24px;"><?= e(money($totals['total'])) ?></div>
    </div>
    <div class="card card-body">
        <div class="kpi-label">Jumlah Baris</div>
        <div class="kpi-value" style="font-size: 24px;"><?= e(num($totals['jumlah'])) ?></div>
    </div>
    <div class="card card-body">
        <div class="kpi-label">Total Kuantitas</div>
        <div class="kpi-value" style="font-size: 24px;"><?= e(num($totals['quantity'], 2)) ?></div>
    </div>
    <div class="card card-body">
        <div class="kpi-label">Rata-rata / Baris</div>
        <div class="kpi-value" style="font-size: 24px;"><?= e(money($totals['jumlah'] > 0 ? $totals['total'] / $totals['jumlah'] : 0)) ?></div>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h3>Rincian Biaya</h3>
            <p>Menampilkan <?= (int) $firstRow ?>–<?= (int) $lastRow ?> dari <?= (int) $totalRows ?> baris</p>
        </div>
        <span class="tag"><?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?></span>
    </div>

    <div class="table-wrap">
        <?php if (empty($rows)): ?>
            <div class="empty">
                <?= icon('costs') ?>
                <p>Belum ada catatan biaya pada filter ini.</p>
                <p class="hint">Tambahkan manual lewat form di bawah atau import CSV.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Akun</th>
                    <th>Layanan</th>
                    <th>Region</th>
                    <th class="num">Kuantitas</th>
                    <th class="num">Biaya</th>
                    <th>Sumber</th>
                    <th class="num">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="nowrap"><?= e(fmt_date($row['usage_date'])) ?></td>
                        <td>
                            <div class="cell-main"><?= e($row['alias']) ?></div>
                            <div class="cell-sub mono"><?= e($row['aws_account_id']) ?></div>
                        </td>
                        <td>
                            <div class="cell-main"><?= e($row['service_name']) ?></div>
                            <div class="cell-sub"><?= e($row['service_code']) ?></div>
                        </td>
                        <td class="muted nowrap"><?= e($row['region']) ?></td>
                        <td class="num"><?= e(num($row['usage_quantity'], 2)) ?></td>
                        <td class="num strong"><?= e(money($row['cost_usd'])) ?></td>
                        <td>
                            <?php
                            $source = $row['source'];
                            $sourceClass = $source === 'aws_ce' ? 'badge-blue' : ($source === 'import' ? 'badge-warning' : 'badge-muted');
                            $sourceLabel = $source === 'aws_ce' ? 'AWS CE' : ($source === 'import' ? 'CSV' : 'Manual');
                            ?>
                            <span class="badge <?= $sourceClass ?>"><?= e($sourceLabel) ?></span>
                        </td>
                        <td class="num">
                            <form method="post" action="<?= e(url('costs')) ?>" data-confirm="Hapus catatan biaya ini?" style="display: inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="cost_delete">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit" title="Hapus"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="pagination">
        <span class="tiny faint">Halaman <?= (int) $currentPageNo ?> dari <?= (int) $totalPages ?></span>
        <div class="page-links">
            <?php if ($currentPageNo > 1): ?>
                <a class="page-link" href="<?= e($pageUrl($currentPageNo - 1)) ?>">‹</a>
            <?php endif; ?>
            <?php
            $startPage = max(1, $currentPageNo - 2);
            $endPage = min($totalPages, $startPage + 4);
            $startPage = max(1, $endPage - 4);
            for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <a class="page-link <?= $i === $currentPageNo ? 'active' : '' ?>" href="<?= e($pageUrl($i)) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($currentPageNo < $totalPages): ?>
                <a class="page-link" href="<?= e($pageUrl($currentPageNo + 1)) ?>">›</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="grid cols-2" id="tambah-biaya">
    <article class="card">
        <div class="card-head">
            <div><h3>Tambah Biaya Manual</h3><p>Isi biaya langsung, atau kuantitas untuk dihitung otomatis.</p></div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(url('costs')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cost_create">
                <div class="form-grid">
                    <div class="field">
                        <label for="c-account">Akun AWS</label>
                        <select class="select" id="c-account" name="account_id" required>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?= (int) $account['id'] ?>"><?= e($account['alias']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="c-service">Layanan</label>
                        <select class="select" id="c-service" name="service_id" required>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= (int) $service['id'] ?>"><?= e($service['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="c-date">Tanggal</label>
                        <input class="input" type="date" id="c-date" name="usage_date" value="<?= e(today()) ?>" required>
                    </div>
                    <div class="field">
                        <label for="c-region">Region</label>
                        <input class="input" type="text" id="c-region" name="region" value="us-east-1">
                    </div>
                    <div class="field">
                        <label for="c-qty">Kuantitas</label>
                        <input class="input" type="number" id="c-qty" name="usage_quantity" min="0" step="0.01" value="0">
                    </div>
                    <div class="field">
                        <label for="c-cost">Biaya (USD)</label>
                        <input class="input" type="number" id="c-cost" name="cost_usd" min="0" step="0.0001" placeholder="0.0000">
                        <span class="hint">Kosongkan agar dihitung dari harga satuan × kuantitas.</span>
                    </div>
                    <div class="field full">
                        <label for="c-note">Catatan</label>
                        <input class="input" type="text" id="c-note" name="note" maxlength="255" placeholder="Opsional, misal: batch inference">
                    </div>
                </div>
                <div class="row" style="margin-top: 14px;">
                    <button class="btn btn-primary" type="submit"><?= icon('plus') ?> Simpan Biaya</button>
                    <span class="hint">Data masuk sebagai sumber <span class="mono">manual</span>.</span>
                </div>
            </form>
        </div>
    </article>

    <article class="card" id="import-csv">
        <div class="card-head">
            <div><h3>Import CSV</h3><p>Maksimal 4 MB, hingga 10.000 baris per file.</p></div>
            <a class="btn btn-sm btn-ghost" href="sample-costs.csv" download><?= icon('download') ?> Contoh</a>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(url('costs')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cost_import">
                <div class="field">
                    <label for="csv-file">File CSV</label>
                    <input class="input" type="file" id="csv-file" name="csv" accept=".csv,text/csv" required>
                </div>
                <p class="hint" style="margin-top: 8px;">
                    Header yang didukung: <span class="mono">account_id, service_code, usage_date, region, usage_quantity, cost_usd, note</span>.
                    Tanpa header, urutan kolom tersebut tetap dipakai.
                </p>
                <button class="btn btn-primary" type="submit" style="margin-top: 12px;"><?= icon('upload') ?> Import Sekarang</button>
            </form>

            <div class="divider"></div>
            <div class="row-between">
                <span class="tiny faint">Riwayat import terakhir</span>
            </div>
            <div style="margin-top: 8px;">
                <?php if (empty($imports)): ?>
                    <p class="tiny faint">Belum ada import.</p>
                <?php else: ?>
                    <?php foreach ($imports as $import): ?>
                        <div class="row-between" style="padding: 6px 0; border-bottom: 1px dashed var(--line-soft);">
                            <div class="tiny" style="min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= e($import['filename']) ?>
                                <div class="faint"><?= (int) $import['rows_imported'] ?> masuk · <?= (int) $import['rows_skipped'] ?> dilewati</div>
                            </div>
                            <span class="badge <?= $import['status'] === 'success' ? 'badge-success' : ($import['status'] === 'partial' ? 'badge-warning' : 'badge-danger') ?>">
                                <?= e(ucfirst($import['status'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </article>
</section>
