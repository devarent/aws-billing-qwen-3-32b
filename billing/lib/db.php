<?php
/**
 * Koneksi PDO MySQL + bootstrap skema & data demo.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['name'],
        $cfg['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    // Di Docker, MySQL butuh beberapa detik untuk siap. Ulangi koneksi.
    $isContainer = !in_array($cfg['host'], ['127.0.0.1', 'localhost', '::1'], true);
    $attempts = $isContainer ? 20 : 1;
    $lastError = null;

    for ($i = 1; $i <= $attempts; $i++) {
        try {
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
            return $pdo;
        } catch (PDOException $e) {
            $lastError = $e;
            if ($i < $attempts) {
                sleep(2);
            }
        }
    }

    throw new PDOException(
        'Tidak dapat terhubung ke MySQL: ' . ($lastError?->getMessage() ?? 'unknown error'),
        (int) ($lastError?->getCode() ?: 0),
        $lastError
    );
}

/** Jalankan file schema.sql bila tabel belum ada. */
function install_schema(PDO $pdo): void
{
    $file = __DIR__ . '/../db/schema.sql';
    if (!is_file($file)) {
        throw new RuntimeException('File skema tidak ditemukan: ' . $file);
    }
    $sql = (string) file_get_contents($file);

    // Buang komentar baris agar pemisahan statement aman.
    $lines = preg_split('/\R/', $sql) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (str_starts_with($trimmed, '--') || $trimmed === '') {
            continue;
        }
        $clean[] = $line;
    }
    $sql = implode("\n", $clean);

    foreach (preg_split('/;\s*(?:\R|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

/** Pastikan tabel dan data demo tersedia (idempoten). */
function ensure_database(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $pdo->query('SELECT 1 FROM users LIMIT 1')->fetchColumn();
    } catch (PDOException $e) {
        // 1146 = table not found; 1049 = database not found.
        install_schema($pdo);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        require_once __DIR__ . '/seed.php';
        $lock = $pdo->query("SELECT GET_LOCK('qween_billing_seed', 15)")->fetchColumn();
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($count === 0) {
                seed_demo($pdo);
            }
        } finally {
            if ((int) $lock === 1) {
                $pdo->query("SELECT RELEASE_LOCK('qween_billing_seed')");
            }
        }
    }

    $done = true;
}

/** Ambil satu nilai skalar. */
function db_value(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}

/** Ambil satu baris. */
function db_row(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Ambil banyak baris. */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Eksekusi query tulis, kembalikan jumlah baris terpengaruh. */
function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
