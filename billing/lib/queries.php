<?php
/**
 * Kumpulan query untuk dashboard dan halaman manajemen.
 */

declare(strict_types=1);

function q_accounts(): array
{
    return db_all('SELECT * FROM aws_accounts ORDER BY alias ASC');
}

function q_account(int $id): ?array
{
    return db_row('SELECT * FROM aws_accounts WHERE id = ?', [$id]);
}

function q_services(): array
{
    return db_all('SELECT * FROM services ORDER BY category ASC, name ASC');
}

function q_service(int $id): ?array
{
    return db_row('SELECT * FROM services WHERE id = ?', [$id]);
}

function q_settings(): array
{
    $rows = db_all('SELECT skey, svalue FROM settings');
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['skey']] = $row['svalue'];
    }
    return $settings;
}

function q_setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        $settings = q_settings();
    }
    return $settings[$key] ?? $default;
}

/** Total biaya bulan berjalan (opsional per akun). */
function q_mtd_spend(?int $accountId = null): float
{
    $sql = 'SELECT COALESCE(SUM(cost_usd), 0) FROM cost_records WHERE usage_date BETWEEN ? AND ?';
    $params = [month_start(), today()];
    if ($accountId !== null) {
        $sql .= ' AND account_id = ?';
        $params[] = $accountId;
    }
    return (float) db_value($sql, $params);
}

/** Total biaya satu bulan (format Y-m). */
function q_month_total(string $monthKey, ?int $accountId = null): float
{
    $start = $monthKey . '-01';
    $end = date('Y-m-t', strtotime($start));
    $sql = 'SELECT COALESCE(SUM(cost_usd), 0) FROM cost_records WHERE usage_date BETWEEN ? AND ?';
    $params = [$start, $end];
    if ($accountId !== null) {
        $sql .= ' AND account_id = ?';
        $params[] = $accountId;
    }
    return (float) db_value($sql, $params);
}

/** Seri total biaya N bulan terakhir (termasuk bulan berjalan). */
function q_monthly_series(int $months = 6): array
{
    $start = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));
    $rows = db_all(
        "SELECT DATE_FORMAT(usage_date, '%Y-%m') AS bulan, SUM(cost_usd) AS total
         FROM cost_records
         WHERE usage_date >= ?
         GROUP BY bulan
         ORDER BY bulan ASC",
        [$start]
    );
    $map = [];
    foreach ($rows as $row) {
        $map[$row['bulan']] = (float) $row['total'];
    }

    $labels = [];
    $totals = [];
    $keys = [];
    for ($i = 0; $i < $months; $i++) {
        $key = date('Y-m', strtotime($start . ' +' . $i . ' months'));
        $keys[] = $key;
        $labels[] = month_name((int) date('n', strtotime($key . '-01'))) . ' ' . date('y', strtotime($key . '-01'));
        $totals[] = round($map[$key] ?? 0, 2);
    }

    return ['keys' => $keys, 'labels' => $labels, 'totals' => $totals];
}

/** Seri biaya harian N hari terakhir. */
function q_daily_series(int $days = 14): array
{
    $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $rows = db_all(
        'SELECT usage_date, SUM(cost_usd) AS total
         FROM cost_records
         WHERE usage_date >= ?
         GROUP BY usage_date
         ORDER BY usage_date ASC',
        [$start]
    );
    $map = [];
    foreach ($rows as $row) {
        $map[$row['usage_date']] = (float) $row['total'];
    }

    $labels = [];
    $totals = [];
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime($start . ' +' . $i . ' days'));
        $labels[] = date('d/m', strtotime($date));
        $totals[] = round($map[$date] ?? 0, 2);
    }
    return ['labels' => $labels, 'totals' => $totals];
}

/** Biaya per layanan pada rentang tanggal. */
function q_spend_by_service(string $from, string $to, ?int $accountId = null, int $limit = 7): array
{
    $sql = 'SELECT s.id, s.code, s.name, s.category, COALESCE(SUM(c.cost_usd), 0) AS total
            FROM cost_records c
            JOIN services s ON s.id = c.service_id
            WHERE c.usage_date BETWEEN ? AND ?';
    $params = [$from, $to];
    if ($accountId !== null) {
        $sql .= ' AND c.account_id = ?';
        $params[] = $accountId;
    }
    $sql .= ' GROUP BY s.id, s.code, s.name, s.category ORDER BY total DESC';
    $rows = db_all($sql, $params);

    if (count($rows) <= $limit) {
        return $rows;
    }

    $top = array_slice($rows, 0, $limit);
    $other = 0.0;
    foreach (array_slice($rows, $limit) as $row) {
        $other += (float) $row['total'];
    }
    if ($other > 0) {
        $top[] = ['id' => 0, 'code' => 'OTHER', 'name' => 'Layanan lainnya', 'category' => 'Lainnya', 'total' => $other];
    }
    return $top;
}

/** Ringkasan biaya per akun pada rentang tanggal. */
function q_spend_by_account(string $from, string $to): array
{
    return db_all(
        'SELECT a.id, a.alias, a.account_id, a.environment,
                COALESCE(SUM(c.cost_usd), 0) AS total
         FROM aws_accounts a
         LEFT JOIN cost_records c ON c.account_id = a.id AND c.usage_date BETWEEN ? AND ?
         GROUP BY a.id, a.alias, a.account_id, a.environment
         ORDER BY total DESC',
        [$from, $to]
    );
}

/** Akun + pemakaian bulan ini & bulan lalu. */
function q_accounts_with_spend(): array
{
    return db_all(
        'SELECT a.*,
                (SELECT COALESCE(SUM(c.cost_usd), 0) FROM cost_records c
                  WHERE c.account_id = a.id AND c.usage_date BETWEEN ? AND ?) AS mtd_spend,
                (SELECT COALESCE(SUM(c.cost_usd), 0) FROM cost_records c
                  WHERE c.account_id = a.id AND c.usage_date BETWEEN ? AND ?) AS prev_spend,
                (SELECT COUNT(*) FROM cost_records c WHERE c.account_id = a.id) AS record_count
         FROM aws_accounts a
         ORDER BY a.alias ASC',
        [
            month_start(), today(),
            date('Y-m-01', strtotime('first day of -1 month')), date('Y-m-t', strtotime('first day of -1 month')),
        ]
    );
}

/** Batas periode budget berdasarkan tipe periodenya. */
function q_period_bounds(array $budget): array
{
    $today = new DateTimeImmutable('today');
    switch ($budget['period'] ?? 'monthly') {
        case 'quarterly':
            $quarter = intdiv((int) $today->format('n') - 1, 3);
            $start = $today->setDate((int) $today->format('Y'), $quarter * 3 + 1, 1);
            $end = $start->modify('+3 months -1 day');
            break;
        case 'annual':
            $start = $today->setDate((int) $today->format('Y'), 1, 1);
            $end = $today->setDate((int) $today->format('Y'), 12, 31);
            break;
        default:
            $start = $today->setDate((int) $today->format('Y'), (int) $today->format('n'), 1);
            $end = $start->modify('last day of this month');
    }

    $totalDays = max(1, (int) $start->diff($end)->days + 1);
    $elapsedDays = min($totalDays, max(1, (int) $start->diff($today)->days + 1));
    return [
        'start'        => $start->format('Y-m-d'),
        'end'          => $end->format('Y-m-d'),
        'total_days'   => $totalDays,
        'elapsed_days' => $elapsedDays,
    ];
}

/** Status seluruh budget aktif + proyeksi akhir periode. */
function q_budget_status(): array
{
    $budgets = db_all(
        'SELECT b.*, a.alias, a.account_id AS aws_account_id, a.environment
         FROM budgets b
         JOIN aws_accounts a ON a.id = b.account_id
         WHERE b.active = 1
         ORDER BY a.alias ASC, b.name ASC'
    );

    foreach ($budgets as &$budget) {
        $bounds = q_period_bounds($budget);
        $spent = (float) db_value(
            'SELECT COALESCE(SUM(cost_usd), 0) FROM cost_records WHERE account_id = ? AND usage_date BETWEEN ? AND ?',
            [$budget['account_id'], $bounds['start'], $bounds['end']]
        );
        $limit = (float) $budget['limit_usd'];
        $projected = $bounds['elapsed_days'] > 0
            ? $spent / $bounds['elapsed_days'] * $bounds['total_days']
            : $spent;

        $usage = $limit > 0 ? $spent / $limit * 100 : 0.0;
        $projectedPct = $limit > 0 ? $projected / $limit * 100 : 0.0;
        $threshold = (int) $budget['alert_threshold'];

        if ($usage >= 100 || $projectedPct >= 100) {
            $level = 'critical';
        } elseif ($usage >= $threshold || $projectedPct >= $threshold) {
            $level = 'warning';
        } else {
            $level = 'ok';
        }

        $budget['period_start'] = $bounds['start'];
        $budget['period_end'] = $bounds['end'];
        $budget['spent'] = round($spent, 2);
        $budget['projected'] = round($projected, 2);
        $budget['usage_pct'] = round($usage, 1);
        $budget['projected_pct'] = round($projectedPct, 1);
        $budget['level'] = $level;
        $budget['remaining'] = round(max(0, $limit - $spent), 2);
    }
    unset($budget);

    return $budgets;
}

function q_unread_alert_count(): int
{
    return (int) db_value('SELECT COUNT(*) FROM alerts WHERE is_read = 0');
}

function q_alerts(int $limit = 10, bool $unreadOnly = false): array
{
    $sql = 'SELECT al.*, a.alias AS account_alias
            FROM alerts al
            LEFT JOIN aws_accounts a ON a.id = al.account_id';
    if ($unreadOnly) {
        $sql .= ' WHERE al.is_read = 0';
    }
    $sql .= ' ORDER BY al.created_at DESC LIMIT ' . max(1, $limit);
    return db_all($sql);
}

function q_invoices(?string $status = null): array
{
    $sql = 'SELECT i.*, a.alias, a.account_id AS aws_account_id, a.environment
            FROM invoices i
            JOIN aws_accounts a ON a.id = i.account_id';
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= ' WHERE i.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY i.period_start DESC, i.invoice_number ASC';
    return db_all($sql, $params);
}

function q_invoice_summary(): array
{
    $rows = db_all(
        "SELECT status, COUNT(*) AS jumlah, COALESCE(SUM(amount_usd), 0) AS total
         FROM invoices GROUP BY status"
    );
    $summary = [
        'issued'  => ['count' => 0, 'total' => 0.0],
        'paid'    => ['count' => 0, 'total' => 0.0],
        'overdue' => ['count' => 0, 'total' => 0.0],
        'draft'   => ['count' => 0, 'total' => 0.0],
        'void'    => ['count' => 0, 'total' => 0.0],
    ];
    foreach ($rows as $row) {
        $summary[$row['status']] = ['count' => (int) $row['jumlah'], 'total' => (float) $row['total']];
    }
    return $summary;
}

/** Filter daftar biaya. */
function q_cost_where(array $filters): array
{
    $where = ['1 = 1'];
    $params = [];

    if (!empty($filters['account_id'])) {
        $where[] = 'c.account_id = ?';
        $params[] = (int) $filters['account_id'];
    }
    if (!empty($filters['service_id'])) {
        $where[] = 'c.service_id = ?';
        $params[] = (int) $filters['service_id'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'c.usage_date >= ?';
        $params[] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'c.usage_date <= ?';
        $params[] = $filters['to'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(s.name LIKE ? OR s.code LIKE ? OR c.note LIKE ? OR a.alias LIKE ?)';
        $term = '%' . $filters['q'] . '%';
        array_push($params, $term, $term, $term, $term);
    }

    return [implode(' AND ', $where), $params];
}

function q_costs(array $filters, int $limit = 25, int $offset = 0): array
{
    [$where, $params] = q_cost_where($filters);
    $sql = 'SELECT c.*, a.alias, a.account_id AS aws_account_id, s.code AS service_code, s.name AS service_name
            FROM cost_records c
            JOIN aws_accounts a ON a.id = c.account_id
            JOIN services s ON s.id = c.service_id
            WHERE ' . $where . '
            ORDER BY c.usage_date DESC, c.id DESC
            LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    return db_all($sql, $params);
}

function q_costs_count(array $filters): int
{
    [$where, $params] = q_cost_where($filters);
    $sql = 'SELECT COUNT(*)
            FROM cost_records c
            JOIN aws_accounts a ON a.id = c.account_id
            JOIN services s ON s.id = c.service_id
            WHERE ' . $where;
    return (int) db_value($sql, $params);
}

function q_cost_totals(array $filters): array
{
    [$where, $params] = q_cost_where($filters);
    $row = db_row(
        'SELECT COALESCE(SUM(c.cost_usd), 0) AS total, COALESCE(SUM(c.usage_quantity), 0) AS quantity, COUNT(*) AS jumlah
         FROM cost_records c
         JOIN aws_accounts a ON a.id = c.account_id
         JOIN services s ON s.id = c.service_id
         WHERE ' . $where,
        $params
    );
    return $row ?? ['total' => 0, 'quantity' => 0, 'jumlah' => 0];
}

function q_top_services(string $from, string $to, int $limit = 5): array
{
    return db_all(
        'SELECT s.name, s.code, s.category, COALESCE(SUM(c.cost_usd), 0) AS total
         FROM cost_records c
         JOIN services s ON s.id = c.service_id
         WHERE c.usage_date BETWEEN ? AND ?
         GROUP BY s.id, s.name, s.code, s.category
         ORDER BY total DESC
         LIMIT ' . max(1, $limit),
        [$from, $to]
    );
}

function q_recent_imports(int $limit = 5): array
{
    return db_all('SELECT * FROM import_logs ORDER BY created_at DESC LIMIT ' . max(1, $limit));
}
