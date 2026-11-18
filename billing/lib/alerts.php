<?php
/**
 * Aturan alert: budget threshold, kredit menipis, invoice jatuh tempo.
 */

declare(strict_types=1);

/** Simpan alert baru; abaikan bila dedupe_key sudah ada. */
function create_alert(
    string $type,
    string $severity,
    string $message,
    ?int $accountId = null,
    ?int $budgetId = null,
    ?int $thresholdPct = null,
    ?string $dedupeKey = null
): bool {
    $stmt = db()->prepare(
        'INSERT IGNORE INTO alerts
            (account_id, budget_id, type, severity, message, threshold_pct, dedupe_key)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$accountId, $budgetId, $type, $severity, $message, $thresholdPct, $dedupeKey]);
    return $stmt->rowCount() > 0;
}

/**
 * Jalankan pemeriksaan alert otomatis bila diaktifkan di pengaturan.
 * Dibatasi sekali per 5 menit per sesi agar tidak membebani database.
 */
function maybe_check_budgets(): void
{
    if (q_setting('auto_alerts', '1') !== '1') {
        return;
    }
    $last = (int) ($_SESSION['last_budget_check'] ?? 0);
    if (time() - $last < 300) {
        return;
    }
    $_SESSION['last_budget_check'] = time();
    check_budgets();
}

/**
 * Evaluasi seluruh budget aktif, kredit akun, dan invoice jatuh tempo.
 *
 * @return array{created:int, checked:int, overdue:int}
 */
function check_budgets(): array
{
    $created = 0;
    $checked = 0;
    $overdue = 0;

    // 1. Budget bulanan/kuartalan/tahunan.
    $budgets = db_all(
        'SELECT b.*, a.alias FROM budgets b JOIN aws_accounts a ON a.id = b.account_id WHERE b.active = 1'
    );
    foreach ($budgets as $budget) {
        $checked++;
        $bounds = q_period_bounds($budget);
        $spent = (float) db_value(
            'SELECT COALESCE(SUM(cost_usd), 0) FROM cost_records WHERE account_id = ? AND usage_date BETWEEN ? AND ?',
            [$budget['account_id'], $bounds['start'], $bounds['end']]
        );
        $limit = (float) $budget['limit_usd'];
        if ($limit <= 0) {
            continue;
        }

        $usagePct = $spent / $limit * 100;
        $threshold = max(1, min(100, (int) $budget['alert_threshold']));
        $periodKey = substr($bounds['start'], 0, 7) . ':' . $budget['id'];

        if ($usagePct >= $threshold) {
            $severity = $usagePct >= 100 ? 'critical' : 'warning';
            $message = sprintf(
                '%s memakai %s dari budget %s (%s / %s).',
                $budget['alias'],
                number_format($usagePct, 0) . '%',
                $budget['name'],
                money($spent),
                money($limit)
            );
            if (create_alert(
                'budget_threshold',
                $severity,
                $message,
                (int) $budget['account_id'],
                (int) $budget['id'],
                $threshold,
                'budget:' . $periodKey . ':' . $threshold
            )) {
                $created++;
            }
        }

        if ($usagePct >= 100 && create_alert(
            'budget_threshold',
            'critical',
            sprintf('%s melampaui budget %s: %s / %s.', $budget['alias'], $budget['name'], money($spent), money($limit)),
            (int) $budget['account_id'],
            (int) $budget['id'],
            100,
            'budget:' . $periodKey . ':100'
        )) {
            $created++;
        }
    }

    // 2. Kredit akun menipis.
    $creditThreshold = (float) q_setting('credit_alert_threshold', '20');
    $accounts = db_all('SELECT * FROM aws_accounts WHERE status = ?', ['active']);
    foreach ($accounts as $account) {
        if ((float) $account['credit_balance'] <= $creditThreshold) {
            $message = sprintf(
                'Kredit AWS %s tersisa %s — di bawah ambang %s.',
                $account['alias'],
                money($account['credit_balance']),
                money($creditThreshold)
            );
            if (create_alert(
                'credit_low',
                'critical',
                $message,
                (int) $account['id'],
                null,
                null,
                'credit:' . $account['id'] . ':' . date('Y-m')
            )) {
                $created++;
            }
        }
    }

    // 3. Invoice jatuh tempo.
    $overdueRows = db_all(
        "SELECT i.*, a.alias FROM invoices i JOIN aws_accounts a ON a.id = i.account_id
         WHERE i.status = 'issued' AND i.due_date IS NOT NULL AND i.due_date < CURDATE()"
    );
    foreach ($overdueRows as $invoice) {
        db_exec("UPDATE invoices SET status = 'overdue' WHERE id = ?", [$invoice['id']]);
        $overdue++;
        if (create_alert(
            'invoice_overdue',
            'critical',
            sprintf('Invoice %s untuk %s sudah melewati jatuh tempo (%s).', $invoice['invoice_number'], $invoice['alias'], fmt_date($invoice['due_date'])),
            (int) $invoice['account_id'],
            null,
            null,
            'invoice:' . $invoice['invoice_number']
        )) {
            $created++;
        }
    }

    return ['created' => $created, 'checked' => $checked, 'overdue' => $overdue];
}
