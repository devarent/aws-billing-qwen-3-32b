<?php
/**
 * @var array $services
 * @var array $grouped
 */
$categoryIcons = [
    'Compute'    => 'server',
    'Storage'    => 'services',
    'Database'   => 'server',
    'Serverless' => 'refresh',
    'Networking' => 'accounts',
    'AI/ML'      => 'trend',
    'Containers' => 'services',
    'Integration'=> 'alerts',
    'Management' => 'settings',
];
?>
<section class="card">
    <div class="card-head">
        <div>
            <h3>Katalog & Harga Satuan</h3>
            <p><?= count($services) ?> layanan AWS · dipakai untuk menghitung biaya dari kuantitas.</p>
        </div>
        <a class="btn btn-sm btn-ghost" href="#edit-harga"><?= icon('settings') ?> Ubah harga</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Layanan</th>
                <th>Kategori</th>
                <th>Unit</th>
                <th class="num">Harga satuan</th>
                <th class="num">Contoh 1.000 unit</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($services as $service): ?>
                <tr>
                    <td>
                        <div class="cell-main"><?= e($service['name']) ?></div>
                        <div class="cell-sub mono"><?= e($service['code']) ?></div>
                    </td>
                    <td>
                        <span class="tag"><?= icon($categoryIcons[$service['category']] ?? 'services') ?> <?= e($service['category']) ?></span>
                    </td>
                    <td class="muted"><?= e($service['unit']) ?></td>
                    <td class="num strong"><?= e(money($service['unit_price'], 4)) ?></td>
                    <td class="num muted"><?= e(money((float) $service['unit_price'] * 1000)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card" id="edit-harga">
    <div class="card-head">
        <div>
            <h3>Perbarui Harga Layanan</h3>
            <p>Perubahan harga hanya memengaruhi perhitungan biaya baru dari kuantitas.</p>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('services')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="service_save">
            <div class="form-grid">
                <div class="field">
                    <label for="s-id">Layanan</label>
                    <select class="select" id="s-id" name="id" required>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service['id'] ?>">
                                <?= e($service['name']) ?> (<?= e($service['code']) ?>) — <?= e(money($service['unit_price'], 4)) ?>/<?= e($service['unit']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="s-price">Harga satuan baru (USD)</label>
                    <input class="input" type="number" id="s-price" name="unit_price" min="0" step="0.000001" required placeholder="0.046400">
                </div>
                <div class="field">
                    <label for="s-unit">Unit</label>
                    <input class="input" type="text" id="s-unit" name="unit" maxlength="40" placeholder="instance-hour">
                    <span class="hint">Kosongkan untuk mempertahankan unit saat ini.</span>
                </div>
                <div class="field">
                    <label for="s-name">Nama layanan (opsional)</label>
                    <input class="input" type="text" id="s-name" name="name" maxlength="120" placeholder="Kosongkan untuk mempertahankan nama">
                </div>
            </div>
            <div class="row" style="margin-top: 14px;">
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan Harga</button>
            </div>
        </form>
    </div>
</section>
