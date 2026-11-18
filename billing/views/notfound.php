<?php
/** Halaman 404. */
?>
<section class="card">
    <div class="empty" style="padding: 70px 20px;">
        <?= icon('search') ?>
        <p style="font-size: 16px; color: var(--text); font-weight: 600;">Halaman tidak ditemukan</p>
        <p class="hint">Menu yang Anda tuju tidak tersedia atau sudah dipindahkan.</p>
        <a class="btn btn-primary" href="<?= e(url('dashboard')) ?>" style="margin-top: 16px;">Kembali ke Dashboard</a>
    </div>
</section>
