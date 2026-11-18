<?php
/**
 * Helper umum: escaping, format angka, URL, flash message, CSRF, ikon SVG.
 */

declare(strict_types=1);

/** Ambil konfigurasi aplikasi (di-cache per request). */
function config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    if ($key === null) {
        return $config;
    }
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escape HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format mata uang USD. */
function money(float|int|string|null $amount, int $decimals = 2): string
{
    return '$' . number_format((float) $amount, $decimals, '.', ',');
}

/** Format angka ribuan. */
function num(float|int|string|null $value, int $decimals = 0): string
{
    return number_format((float) $value, $decimals, '.', ',');
}

/** Persentase (0-100) dengan satu desimal bila perlu. */
function pct(float|int|null $value, int $decimals = 0): string
{
    $value = (float) $value;
    if ($decimals === 0) {
        return number_format($value, 0, '.', ',') . '%';
    }
    return number_format($value, $decimals, '.', ',') . '%';
}

/**
 * Direktori dasar aplikasi, mis. "/billing".
 *
 * Diturunkan dari REQUEST_URI (bukan SCRIPT_NAME) supaya tetap benar saat
 * aplikasi diakses lewat proxy/port-forward WSL, subdirektori, atau saat
 * document root langsung menunjuk ke folder billing/.
 */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    // Saat dijalankan dari CLI (cron), gunakan folder aplikasi.
    if (PHP_SAPI === 'cli') {
        $base = '/billing';
        return $base;
    }

    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $path = str_replace('\\', '/', rawurldecode($path));

    // "/billing/index.php" -> "/billing", "/index.php" -> ""
    $base = preg_replace('~/index\.php$~i', '', $path) ?? '';

    // Akses direktori: "/billing/" atau "/billing" -> "/billing"
    if ($base === $path) {
        $base = rtrim($path, '/');
    }

    $base = rtrim(str_replace('\\', '/', $base), '/');
    if ($base === '.' || $base === '/') {
        $base = '';
    }

    return $base;
}

/**
 * URL halaman internal (relatif).
 *
 * Sengaja relatif supaya tetap benar saat aplikasi dipasang di subfolder,
 * document root langsung, maupun di belakang reverse-proxy / port-forward
 * WSL yang memotong prefix path.
 */
function url(string $page = 'dashboard', array $params = []): string
{
    $query = array_merge(['page' => $page], $params);
    return 'index.php?' . http_build_query($query);
}

/** URL aset statis (relatif) dengan cache-buster. */
function asset(string $path): string
{
    $file = __DIR__ . '/../assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : config('app.version', '1');
    return 'assets/' . ltrim($path, '/') . '?v=' . rawurlencode($version);
}

/** Redirect lalu hentikan request. */
function redirect(string $target): never
{
    header('Location: ' . $target);
    exit;
}

/** Set flash message (bertahan satu request). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Ambil dan kosongkan flash message. */
function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/** Token CSRF untuk sesi ini. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Input hidden CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Validasi CSRF; hentikan request bila tidak cocok. */
function verify_csrf(): void
{
    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    $given = (string) ($_POST['_csrf'] ?? '');
    if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
        http_response_code(419);
        exit('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

/** True bila request method POST. */
function is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

/** Nama bulan Indonesia (1-12). */
function month_name(int $month): string
{
    $names = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return $names[$month] ?? '';
}

/** Format tanggal Indonesia. */
function fmt_date(?string $date, bool $withTime = false): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($date);
    if ($ts === false) {
        return '—';
    }
    return date($withTime ? 'd M Y H:i' : 'd M Y', $ts);
}

/** Waktu relatif sederhana. */
function relative_time(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'baru saja';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    }
    if ($diff < 86400 * 7) {
        return floor($diff / 86400) . ' hari lalu';
    }
    return fmt_date($datetime);
}

/** Angka aman dari input. */
function safe_float(mixed $value, float $default = 0.0): float
{
    if (is_string($value)) {
        $value = str_replace(['$', ' ', ','], '', $value);
    }
    return is_numeric($value) ? (float) $value : $default;
}

/** Integer aman dari input. */
function safe_int(mixed $value, int $default = 0): int
{
    return is_numeric($value) ? (int) $value : $default;
}

/** Label & kelas badge untuk status. */
function status_meta(string $status): array
{
    return match (strtolower($status)) {
        'paid', 'active', 'success'          => ['Lunas', 'badge-success'],
        'issued', 'warning', 'partial'       => ['Terbit', 'badge-warning'],
        'overdue', 'suspended', 'critical'   => ['Overdue', 'badge-danger'],
        'draft', 'info'                      => ['Draft', 'badge-muted'],
        'void', 'closed'                     => ['Batal', 'badge-muted'],
        'failed'                             => ['Gagal', 'badge-danger'],
        default                              => [ucwords(str_replace('_', ' ', $status)), 'badge-muted'],
    };
}

/** Ambil path ikon SVG (Feather-style) berdasarkan nama. */
function icon(string $name, string $class = ''): string
{
    $paths = [
        'dashboard'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'costs'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
        'accounts'   => '<path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>',
        'budget'     => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        'invoices'   => '<path d="M4 19V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'alerts'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'services'   => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12"/>',
        'settings'   => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'plus'       => '<path d="M12 5v14M5 12h14"/>',
        'download'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/>',
        'upload'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5M12 3v12"/>',
        'trash'      => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/>',
        'check'      => '<path d="M20 6 9 17l-5-5"/>',
        'search'     => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'menu'       => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'shield'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'trend'      => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
        'wallet'     => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'calendar'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'warning'    => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
        'info'       => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/>',
        'x'          => '<path d="M18 6 6 18M6 6l12 12"/>',
        'refresh'    => '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'arrow-right'=> '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'server'     => '<rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><path d="M6 6.5h.01M6 17.5h.01"/>',
        'cloud'      => '<path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>',
        'key'        => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m21 2-9.6 9.6M15.5 7.5 18 10M18 10l2-2"/>',
        'eye'        => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    ];
    $inner = $paths[$name] ?? $paths['info'];
    return '<svg class="icon ' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/** Hari ini (Y-m-d) pada timezone aplikasi. */
function today(): string
{
    return date('Y-m-d');
}

/** Awal bulan berjalan. */
function month_start(): string
{
    return date('Y-m-01');
}

/** Akhir bulan berjalan. */
function month_end(): string
{
    return date('Y-m-t');
}
