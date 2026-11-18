<?php
/**
 * Pemuat file .env sederhana untuk pemakaian di luar Docker.
 *
 * Di Docker, kredensial sudah disuntikkan lewat environment (docker compose),
 * jadi file ini tidak menimpa nilai yang sudah ada. Saat dijalankan dengan
 * `php -S` di WSL/Laragon, file .env proyek dibaca agar AWS key, konfigurasi
 * database, dan setelan lain tetap tersedia.
 */

declare(strict_types=1);

/**
 * Muat file .env tanpa menimpa environment yang sudah di-set proses induk.
 */
function load_env_file(string $file): void
{
    static $loaded = [];
    $file = str_replace('\\', '/', $file);
    if (isset($loaded[$file]) || !is_file($file) || !is_readable($file)) {
        return;
    }
    $loaded[$file] = true;

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
        }

        $value = trim($value);
        // Buang komentar inline yang diawali spasi + tanda pagar.
        if (preg_match('/\s+#/', $value)) {
            $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
        }
        // Buang kutip pembungkus.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
                if ($first === '"') {
                    $value = str_replace(['\\n', '\\r', '\\"', '\\\\'], ["\n", "\r", '"', '\\'], $value);
                }
            }
        }

        // Environment nyata (Docker/OS) selalu menang.
        if (getenv($key) !== false && getenv($key) !== '') {
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

/** Ambil nilai environment yang sudah di-trim, dengan fallback. */
function env_value(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || trim((string) $value) === '') {
        return $default;
    }
    return trim((string) $value);
}
