<?php
/**
 * Seeder data demo untuk AWS Billing Manager.
 *
 * Data dibuat deterministik (tanpa random global) agar tampilan dashboard
 * konsisten. Dipanggil otomatis sekali saat database masih kosong.
 */

declare(strict_types=1);

function seed_demo(PDO $pdo): void
{
    $pdo->beginTransaction();

    try {
        // -----------------------------------------------------------------
        // 1. Pengguna demo
        // -----------------------------------------------------------------
        $users = [
            ['Admin Qween', 'admin@qween.local', 'admin', 'qween123'],
            ['Finance Qween', 'finance@qween.local', 'finance', 'qween123'],
            ['Viewer Qween', 'viewer@qween.local', 'viewer', 'qween123'],
        ];
        $userStmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        foreach ($users as [$name, $email, $role, $password]) {
            $userStmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        }

        // -----------------------------------------------------------------
        // 2. Akun AWS
        // -----------------------------------------------------------------
        $accounts = [
            ['111122223333', 'Qween Production', 'production', 'us-east-1', 'active', 200.00, 120.00, 'cloudops@qween.local'],
            ['444455556666', 'Qween Staging', 'staging', 'ap-southeast-1', 'active', 80.00, 45.00, 'devops@qween.local'],
            ['777788889999', 'Qween Sandbox', 'sandbox', 'us-west-2', 'active', 9.50, 18.00, 'lab@qween.local'],
        ];
        $accountStmt = $pdo->prepare(
            'INSERT INTO aws_accounts
                (account_id, alias, environment, region, status, credit_balance, monthly_budget, owner_email)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $accountIds = [];
        foreach ($accounts as $row) {
            $accountStmt->execute($row);
            $accountIds[$row[0]] = (int) $pdo->lastInsertId();
        }

        // -----------------------------------------------------------------
        // 3. Katalog layanan AWS + harga satuan
        // -----------------------------------------------------------------
        $services = [
            ['EC2', 'Amazon EC2', 'Compute', 'instance-hour', 0.046400],
            ['S3', 'Amazon S3', 'Storage', 'GB-month', 0.023000],
            ['RDS', 'Amazon RDS', 'Database', 'instance-hour', 0.115000],
            ['Lambda', 'AWS Lambda', 'Serverless', '1M requests', 0.200000],
            ['CloudFront', 'Amazon CloudFront', 'Networking', 'GB transfer', 0.085000],
            ['Bedrock', 'Amazon Bedrock', 'AI/ML', '1M tokens', 0.450000],
            ['DynamoDB', 'Amazon DynamoDB', 'Database', 'WCU-hour', 0.000650],
            ['EKS', 'Amazon EKS', 'Containers', 'cluster-hour', 0.100000],
            ['SNS', 'Amazon SNS', 'Integration', '1M notifications', 0.500000],
            ['CloudWatch', 'Amazon CloudWatch', 'Management', 'metric-month', 0.300000],
            ['Route53', 'Amazon Route 53', 'Networking', 'hosted zone-month', 0.500000],
        ];
        $serviceStmt = $pdo->prepare(
            'INSERT INTO services (code, name, category, unit, unit_price) VALUES (?, ?, ?, ?, ?)'
        );
        $serviceIds = [];
        $servicePrices = [];
        foreach ($services as $row) {
            $serviceStmt->execute($row);
            $serviceIds[$row[0]] = (int) $pdo->lastInsertId();
            $servicePrices[$row[0]] = (float) $row[4];
        }

        // -----------------------------------------------------------------
        // 4. Budget bulanan per akun
        // -----------------------------------------------------------------
        $budgetStmt = $pdo->prepare(
            'INSERT INTO budgets (account_id, name, period, limit_usd, alert_threshold, hard_stop, active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $budgetIds = [];
        foreach ($accounts as [$accountId, $alias, $env, , , , $monthlyBudget]) {
            $budgetStmt->execute([
                $accountIds[$accountId],
                'Monthly Guard — ' . $alias,
                'monthly',
                $monthlyBudget,
                80,
                $env === 'production' ? 1 : 0,
            ]);
            $budgetIds[$accountId] = (int) $pdo->lastInsertId();
        }

        // -----------------------------------------------------------------
        // 5. Riwayat biaya 6 bulan (per hari, per layanan)
        // -----------------------------------------------------------------
        $plans = [
            '111122223333' => [
                'EC2' => 3.2, 'RDS' => 1.6, 'Bedrock' => 1.5, 'S3' => 1.1,
                'CloudFront' => 0.9, 'Lambda' => 0.5, 'EKS' => 0.7,
            ],
            '444455556666' => [
                'EC2' => 2.2, 'RDS' => 1.3, 'S3' => 1.0, 'Lambda' => 0.9,
                'CloudWatch' => 0.7, 'SNS' => 0.5,
            ],
            '777788889999' => [
                'Lambda' => 1.3, 'S3' => 1.0, 'DynamoDB' => 0.9,
                'SNS' => 0.6, 'Route53' => 0.4,
            ],
        ];

        $accountIndex = array_flip(array_keys($plans));
        $accountMeta = [];
        foreach ($accounts as [$accountId, , , $region, , , $monthlyBudget]) {
            $accountMeta[$accountId] = ['region' => $region, 'budget' => (float) $monthlyBudget];
        }

        $costStmt = $pdo->prepare(
            'INSERT INTO cost_records
                (account_id, service_id, usage_date, region, usage_quantity, cost_usd, source, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $today = new DateTimeImmutable('today');
        $firstMonth = new DateTimeImmutable('first day of -5 months');
        $currentMonthKey = $today->format('Y-m');

        $month = $firstMonth;
        $monthIndex = 0;
        while ($month <= $today) {
            $monthKey = $month->format('Y-m');
            $daysInMonth = (int) $month->format('t');
            $lastDay = $monthKey === $currentMonthKey ? (int) $today->format('j') : $daysInMonth;

            foreach ($plans as $accountId => $weights) {
                $ai = $accountIndex[$accountId];
                $meta = $accountMeta[$accountId];

                // Faktor musiman + variasi antar akun/bulan.
                $season = 0.80 + 0.16 * sin(($monthIndex + $ai) * 1.37);
                if ($monthKey === $currentMonthKey) {
                    $season = 0.95;
                }

                for ($day = 1; $day <= $lastDay; $day++) {
                    $date = $monthKey . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                    $weekday = (int) date('N', strtotime($date));
                    $weekdayFactor = $weekday >= 6 ? 0.72 : 1.06;
                    $noise = 0.88 + 0.24 * abs(sin($day * 12.9898 + $ai * 78.233 + $monthIndex * 3.7));

                    $dayCost = $meta['budget'] * $season / $daysInMonth * $weekdayFactor * $noise;

                    $weightSum = array_sum($weights);
                    foreach ($weights as $code => $weight) {
                        if (!isset($serviceIds[$code])) {
                            continue;
                        }
                        $share = $weight / $weightSum;
                        $cost = $dayCost * $share;
                        $price = max((float) $servicePrices[$code], 0.000001);
                        $quantity = round($cost / $price, 2);
                        $cost = round($quantity * $price, 4);
                        if ($cost <= 0) {
                            continue;
                        }
                        $costStmt->execute([
                            $accountIds[$accountId],
                            $serviceIds[$code],
                            $date,
                            $meta['region'],
                            $quantity,
                            $cost,
                            'aws_ce',
                            null,
                        ]);
                    }
                }
            }

            $month = $month->modify('+1 month');
            $monthIndex++;
        }

        // -----------------------------------------------------------------
        // 6. Invoice 3 bulan terakhir
        // -----------------------------------------------------------------
        $invoiceStmt = $pdo->prepare(
            'INSERT INTO invoices
                (account_id, invoice_number, period_start, period_end, amount_usd, status, due_date, paid_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $sumStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(cost_usd), 0) FROM cost_records
             WHERE account_id = ? AND usage_date BETWEEN ? AND ?'
        );

        for ($back = 3; $back >= 1; $back--) {
            $period = new DateTimeImmutable('first day of -' . $back . ' month');
            $start = $period->format('Y-m-01');
            $end = $period->format('Y-m-t');
            $suffix = substr($period->format('Y'), 2) . $period->format('m');

            foreach ($accounts as [$accountId, , $env]) {
                $sumStmt->execute([$accountIds[$accountId], $start, $end]);
                $amount = round((float) $sumStmt->fetchColumn(), 2);
                $number = 'INV-' . $suffix . '-' . substr($accountId, -4);

                $status = 'paid';
                $dueDate = $period->modify('+20 days')->format('Y-m-d');
                $paidAt = $period->modify('+25 days')->format('Y-m-d H:i:s');

                if ($back === 1) {
                    $status = $env === 'sandbox' ? 'paid' : 'issued';
                    $dueDate = (new DateTimeImmutable($end))->modify('+20 days')->format('Y-m-d');
                    $paidAt = $env === 'sandbox' ? $period->modify('+24 days')->format('Y-m-d H:i:s') : null;
                } elseif ($back === 2 && $env === 'staging') {
                    $status = 'overdue';
                    $dueDate = (new DateTimeImmutable($end))->modify('+12 days')->format('Y-m-d');
                    $paidAt = null;
                }

                $invoiceStmt->execute([
                    $accountIds[$accountId],
                    $number,
                    $start,
                    $end,
                    $amount,
                    $status,
                    $dueDate,
                    $paidAt,
                    date('Y-m-d H:i:s', strtotime($start . ' +1 day')),
                ]);
            }
        }

        // -----------------------------------------------------------------
        // 7. Alert contoh
        // -----------------------------------------------------------------
        $prevMonthStart = date('Y-m-01', strtotime('first day of -1 month'));
        $prevMonthEnd = date('Y-m-t', strtotime('first day of -1 month'));
        $sumStmt->execute([$accountIds['111122223333'], $prevMonthStart, $prevMonthEnd]);
        $prodPrevSpend = (float) $sumStmt->fetchColumn();
        $prodPrevPct = (int) round($prodPrevSpend / 120 * 100);

        $overdueInvoiceNumber = 'INV-' . date('ym', strtotime('first day of -2 month')) . '-6666';

        $alertStmt = $pdo->prepare(
            'INSERT INTO alerts
                (account_id, budget_id, type, severity, message, threshold_pct, dedupe_key, is_read, acknowledged_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $alerts = [
            [
                $accountIds['777788889999'], null, 'credit_low', 'critical',
                'Kredit AWS Sandbox tersisa $9.50 — di bawah ambang $20.00.',
                null, 'credit-low:sandbox:2026-11', 0, null, '-2 hours',
            ],
            [
                $accountIds['111122223333'], $budgetIds['111122223333'], 'budget_threshold', 'warning',
                sprintf(
                    'Qween Production memakai %d%% dari budget %s (%s / %s).',
                    $prodPrevPct,
                    date('F', strtotime($prevMonthStart)),
                    '$' . number_format($prodPrevSpend, 2),
                    '$120.00'
                ),
                80, 'budget:prod:' . substr($prevMonthStart, 0, 7) . ':80', 0, null, '-1 day',
            ],
            [
                $accountIds['444455556666'], null, 'invoice_overdue', 'critical',
                'Invoice ' . $overdueInvoiceNumber . ' untuk Qween Staging sudah melewati jatuh tempo.',
                null, 'invoice:' . $overdueInvoiceNumber, 1, '-12 hours', '-3 days',
            ],
            [
                $accountIds['111122223333'], null, 'anomaly', 'info',
                'Biaya Amazon Bedrock naik 38% dibanding rata-rata 7 hari sebelumnya.',
                null, 'anomaly:bedrock:2026-11', 1, '-4 hours', '-6 hours',
            ],
        ];
        foreach ($alerts as [$acc, $budget, $type, $severity, $message, $threshold, $dedupe, $read, $ack, $created]) {
            $alertStmt->execute([
                $acc,
                $budget,
                $type,
                $severity,
                $message,
                $threshold,
                $dedupe,
                $read,
                $ack === null ? null : date('Y-m-d H:i:s', strtotime($ack)),
                date('Y-m-d H:i:s', strtotime($created)),
            ]);
        }

        // -----------------------------------------------------------------
        // 8. Pengaturan
        // -----------------------------------------------------------------
        $settingStmt = $pdo->prepare(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
        );
        $settings = [
            'company_name'           => 'Qween 3 32B',
            'company_tagline'        => 'AWS Billing Manager',
            'alert_email'            => 'finance@qween.local',
            'budget_alert_threshold' => '80',
            'credit_alert_threshold' => '20',
            'default_region'         => 'us-east-1',
            'currency'               => 'USD',
            'auto_alerts'            => '1',
        ];
        foreach ($settings as $key => $value) {
            $settingStmt->execute([$key, $value]);
        }

        // -----------------------------------------------------------------
        // 9. Log import contoh
        // -----------------------------------------------------------------
        $pdo->prepare(
            'INSERT INTO import_logs (filename, rows_imported, rows_skipped, status, message)
             VALUES (?, ?, ?, ?, ?)'
        )->execute(['aws-cost-report-2026-10.csv', 186, 0, 'success', 'Import otomatis data demo']);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
