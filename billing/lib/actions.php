<?php
/**
 * Handler aksi POST (CRUD, import, export) untuk AWS Billing Manager.
 */

declare(strict_types=1);

function valid_date(?string $date): bool
{
    if (!$date) {
        return false;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $dt !== false && $dt->format('Y-m-d') === $date;
}

function back_to(string $page, array $params = []): never
{
    redirect(url($page, $params));
}

/**
 * Jalankan aksi berdasarkan field `action`.
 */
function handle_action(string $action): void
{
    switch ($action) {
        // -------------------------------------------------------------
        // Biaya / usage
        // -------------------------------------------------------------
        case 'cost_create':
            $accountId = safe_int($_POST['account_id'] ?? 0);
            $serviceId = safe_int($_POST['service_id'] ?? 0);
            $date = trim((string) ($_POST['usage_date'] ?? ''));
            $region = trim((string) ($_POST['region'] ?? 'us-east-1'));
            $quantity = max(0, safe_float($_POST['usage_quantity'] ?? 0));
            $cost = safe_float($_POST['cost_usd'] ?? 0);

            if (!q_account($accountId) || !q_service($serviceId) || !valid_date($date)) {
                flash('error', 'Akun, layanan, atau tanggal tidak valid.');
                back_to('costs');
            }

            if ($cost <= 0) {
                $price = (float) q_service($serviceId)['unit_price'];
                $cost = round($quantity * $price, 4);
            }
            if ($cost <= 0) {
                flash('error', 'Biaya harus lebih dari 0 (isi biaya atau kuantitas).');
                back_to('costs');
            }

            db_exec(
                'INSERT INTO cost_records (account_id, service_id, usage_date, region, usage_quantity, cost_usd, source, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$accountId, $serviceId, $date, $region, $quantity, round($cost, 4), 'manual', substr(trim((string) ($_POST['note'] ?? '')), 0, 255) ?: null]
            );
            flash('success', 'Catatan biaya berhasil ditambahkan.');
            back_to('costs');

        case 'cost_delete':
            $id = safe_int($_POST['id'] ?? 0);
            db_exec('DELETE FROM cost_records WHERE id = ?', [$id]);
            flash('success', 'Catatan biaya dihapus.');
            back_to('costs');

        // -------------------------------------------------------------
        // Akun AWS
        // -------------------------------------------------------------
        case 'account_save':
            $id = safe_int($_POST['id'] ?? 0);
            $accountNumber = preg_replace('/\D/', '', (string) ($_POST['account_id'] ?? '')) ?? '';
            $alias = trim((string) ($_POST['alias'] ?? ''));
            $environment = (string) ($_POST['environment'] ?? 'production');
            $region = trim((string) ($_POST['region'] ?? 'us-east-1'));
            $status = (string) ($_POST['status'] ?? 'active');
            $credit = max(0, safe_float($_POST['credit_balance'] ?? 0));
            $budget = max(0, safe_float($_POST['monthly_budget'] ?? 0));
            $owner = trim((string) ($_POST['owner_email'] ?? ''));

            if ($alias === '' || strlen($accountNumber) !== 12) {
                flash('error', 'Nama akun wajib diisi dan Account ID harus 12 digit.');
                back_to('accounts', $id ? ['edit' => $id] : []);
            }
            if (!in_array($environment, ['production', 'staging', 'development', 'sandbox'], true)) {
                $environment = 'production';
            }
            if (!in_array($status, ['active', 'suspended', 'closed'], true)) {
                $status = 'active';
            }
            if ($owner !== '' && !filter_var($owner, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Email owner tidak valid.');
                back_to('accounts', $id ? ['edit' => $id] : []);
            }

            try {
                if ($id > 0) {
                    db_exec(
                        'UPDATE aws_accounts SET account_id = ?, alias = ?, environment = ?, region = ?, status = ?,
                                credit_balance = ?, monthly_budget = ?, owner_email = ? WHERE id = ?',
                        [$accountNumber, $alias, $environment, $region, $status, $credit, $budget, $owner ?: null, $id]
                    );
                    flash('success', 'Akun AWS diperbarui.');
                } else {
                    db_exec(
                        'INSERT INTO aws_accounts (account_id, alias, environment, region, status, credit_balance, monthly_budget, owner_email)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        [$accountNumber, $alias, $environment, $region, $status, $credit, $budget, $owner ?: null]
                    );
                    $newId = (int) db()->lastInsertId();
                    if ($budget > 0) {
                        db_exec(
                            'INSERT INTO budgets (account_id, name, period, limit_usd, alert_threshold, hard_stop)
                             VALUES (?, ?, ?, ?, ?, ?)',
                            [$newId, 'Monthly Guard — ' . $alias, 'monthly', $budget, (int) q_setting('budget_alert_threshold', '80'), 0]
                        );
                    }
                    flash('success', 'Akun AWS ditambahkan beserta budget bulanannya.');
                }
            } catch (PDOException $e) {
                flash('error', 'Gagal menyimpan akun: Account ID mungkin sudah dipakai.');
            }
            back_to('accounts');

        case 'account_delete':
            $id = safe_int($_POST['id'] ?? 0);
            db_exec('DELETE FROM aws_accounts WHERE id = ?', [$id]);
            flash('success', 'Akun AWS beserta data terkait dihapus.');
            back_to('accounts');

        // -------------------------------------------------------------
        // Budget
        // -------------------------------------------------------------
        case 'budget_save':
            $accountId = safe_int($_POST['account_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $period = (string) ($_POST['period'] ?? 'monthly');
            $limit = max(0, safe_float($_POST['limit_usd'] ?? 0));
            $threshold = min(100, max(1, safe_int($_POST['alert_threshold'] ?? 80)));
            $hardStop = isset($_POST['hard_stop']) ? 1 : 0;

            if (!q_account($accountId) || $name === '' || $limit <= 0) {
                flash('error', 'Akun, nama budget, dan limit wajib diisi (limit > 0).');
                back_to('budgets');
            }
            if (!in_array($period, ['monthly', 'quarterly', 'annual'], true)) {
                $period = 'monthly';
            }

            db_exec(
                'INSERT INTO budgets (account_id, name, period, limit_usd, alert_threshold, hard_stop)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$accountId, $name, $period, $limit, $threshold, $hardStop]
            );
            flash('success', 'Budget baru dibuat.');
            back_to('budgets');

        case 'budget_delete':
            db_exec('DELETE FROM budgets WHERE id = ?', [safe_int($_POST['id'] ?? 0)]);
            flash('success', 'Budget dihapus.');
            back_to('budgets');

        // -------------------------------------------------------------
        // Invoice
        // -------------------------------------------------------------
        case 'invoice_save':
            $accountId = safe_int($_POST['account_id'] ?? 0);
            $start = trim((string) ($_POST['period_start'] ?? ''));
            $end = trim((string) ($_POST['period_end'] ?? ''));
            $due = trim((string) ($_POST['due_date'] ?? ''));
            $amount = safe_float($_POST['amount_usd'] ?? 0);

            if (!q_account($accountId) || !valid_date($start) || !valid_date($end) || $start > $end) {
                flash('error', 'Akun dan periode invoice tidak valid.');
                back_to('invoices');
            }

            if ($amount <= 0) {
                $amount = (float) db_value(
                    'SELECT COALESCE(SUM(cost_usd), 0) FROM cost_records WHERE account_id = ? AND usage_date BETWEEN ? AND ?',
                    [$accountId, $start, $end]
                );
            }
            $amount = round($amount, 2);
            if ($amount <= 0) {
                flash('error', 'Tidak ada biaya pada periode tersebut. Isi nominal invoice secara manual.');
                back_to('invoices');
            }

            if (!valid_date($due)) {
                $due = date('Y-m-d', strtotime($end . ' +20 days'));
            }

            $account = q_account($accountId);
            $base = 'INV-' . date('ym', strtotime($start)) . '-' . substr((string) $account['account_id'], -4);
            $number = $base;
            $suffix = 1;
            while (db_value('SELECT COUNT(*) FROM invoices WHERE invoice_number = ?', [$number]) > 0) {
                $suffix++;
                $number = $base . '-' . $suffix;
            }

            db_exec(
                'INSERT INTO invoices (account_id, invoice_number, period_start, period_end, amount_usd, status, due_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$accountId, $number, $start, $end, $amount, 'issued', $due]
            );
            flash('success', 'Invoice ' . $number . ' dibuat.');
            back_to('invoices');

        case 'invoice_pay':
            $id = safe_int($_POST['id'] ?? 0);
            db_exec("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ?", [$id]);
            flash('success', 'Invoice ditandai lunas.');
            back_to('invoices');

        case 'invoice_delete':
            db_exec('DELETE FROM invoices WHERE id = ?', [safe_int($_POST['id'] ?? 0)]);
            flash('success', 'Invoice dihapus.');
            back_to('invoices');

        // -------------------------------------------------------------
        // Alert
        // -------------------------------------------------------------
        case 'alert_ack':
            $id = safe_int($_POST['id'] ?? 0);
            db_exec('UPDATE alerts SET is_read = 1, acknowledged_at = NOW() WHERE id = ?', [$id]);
            flash('success', 'Alert ditandai sudah dibaca.');
            back_to('alerts');

        case 'alert_ack_all':
            db_exec('UPDATE alerts SET is_read = 1, acknowledged_at = NOW() WHERE is_read = 0');
            flash('success', 'Semua alert ditandai sudah dibaca.');
            back_to('alerts');

        case 'run_budget_check':
            $result = check_budgets();
            flash('success', sprintf(
                'Pemeriksaan selesai: %d budget diperiksa, %d alert baru, %d invoice jatuh tempo.',
                $result['checked'],
                $result['created'],
                $result['overdue']
            ));
            back_to('alerts');

        // -------------------------------------------------------------
        // Layanan & pengaturan
        // -------------------------------------------------------------
        case 'service_save':
            $id = safe_int($_POST['id'] ?? 0);
            $existing = $id > 0 ? q_service($id) : null;
            $name = trim((string) ($_POST['name'] ?? ''));
            $unit = trim((string) ($_POST['unit'] ?? ''));
            $price = max(0, safe_float($_POST['unit_price'] ?? 0));
            if (!$existing) {
                flash('error', 'Layanan tidak ditemukan.');
                back_to('services');
            }
            $name = $name !== '' ? $name : (string) $existing['name'];
            $unit = $unit !== '' ? $unit : (string) $existing['unit'];
            db_exec('UPDATE services SET name = ?, unit = ?, unit_price = ? WHERE id = ?', [$name, $unit, $price, $id]);
            flash('success', 'Harga layanan ' . $name . ' diperbarui.');
            back_to('services');

        case 'aws_test':
            if (!in_array((string) (current_user()['role'] ?? ''), ['admin', 'finance'], true)) {
                flash('error', 'Hanya admin atau finance yang boleh menguji koneksi AWS.');
                back_to('settings');
            }
            try {
                $identity = aws_test_connection();
                flash('success', 'Koneksi AWS berhasil. Account ID: ' . $identity['account'] . '.');
            } catch (Throwable $e) {
                aws_record_test_failure($e);
                flash('error', 'Koneksi AWS gagal: ' . $e->getMessage());
            }
            back_to('settings');

        case 'aws_sync':
            if (!in_array((string) (current_user()['role'] ?? ''), ['admin', 'finance'], true)) {
                flash('error', 'Hanya admin atau finance yang boleh menjalankan sinkronisasi AWS.');
                back_to('settings');
            }
            $days = safe_int($_POST['days'] ?? 30, 30);
            try {
                $identity = aws_test_connection();
                $costs = aws_sync_costs($days, $identity);
                $budgetNote = '';
                try {
                    $budgets = aws_sync_budgets((string) $identity['account']);
                    $budgetNote = sprintf(
                        ' Budget: %d baru, %d diperbarui.',
                        $budgets['created'],
                        $budgets['updated']
                    );
                } catch (Throwable $e) {
                    $budgetNote = ' Budget dilewati: ' . $e->getMessage();
                }
                $message = sprintf(
                    'Sinkronisasi AWS selesai: %d baris biaya %s s/d %s, total %s.%s',
                    $costs['rows'],
                    $costs['start'],
                    $costs['end'],
                    money($costs['total']),
                    $budgetNote
                );
                flash('success', $message);
            } catch (Throwable $e) {
                aws_record_test_failure($e);
                flash('error', 'Sinkronisasi AWS gagal: ' . $e->getMessage());
            }
            back_to('settings');

        case 'settings_save':
            $allowed = [
                'company_name', 'company_tagline', 'alert_email', 'budget_alert_threshold',
                'credit_alert_threshold', 'default_region', 'currency', 'auto_alerts',
            ];
            $stmt = db()->prepare(
                'INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
            );
            foreach ($allowed as $key) {
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }
                $value = trim((string) $_POST[$key]);
                if ($key === 'auto_alerts') {
                    $value = $value === '1' ? '1' : '0';
                }
                $stmt->execute([$key, $value]);
            }
            flash('success', 'Pengaturan disimpan.');
            back_to('settings');
    }

    flash('error', 'Aksi tidak dikenal.');
    back_to('dashboard');
}

/**
 * Import CSV biaya.
 *
 * Kolom yang didukung (header opsional):
 * account_id, service_code, usage_date, region, usage_quantity, cost_usd, note
 */
function handle_cost_import(): void
{
    if (!isset($_FILES['csv']) || ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'File CSV tidak terunggah.');
        back_to('costs');
    }

    $file = $_FILES['csv'];
    if ((int) $file['size'] > 4 * 1024 * 1024) {
        flash('error', 'Ukuran CSV maksimal 4 MB.');
        back_to('costs');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        flash('error', 'File CSV tidak bisa dibaca.');
        back_to('costs');
    }

    $accounts = [];
    foreach (q_accounts() as $account) {
        $accounts[$account['account_id']] = (int) $account['id'];
    }
    $services = [];
    foreach (q_services() as $service) {
        $services[strtolower($service['code'])] = $service;
    }

    $imported = 0;
    $skipped = 0;
    $errors = [];
    $line = 0;
    $header = null;

    db()->beginTransaction();
    try {
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $line++;
            if ($row === [null] || $row === []) {
                continue;
            }
            $row = array_map(static fn ($v) => trim((string) $v), $row);

            if ($header === null) {
                $normalized = array_map(static fn ($v) => strtolower(str_replace([' ', '-'], '_', $v)), $row);
                if (in_array('account_id', $normalized, true) || in_array('usage_date', $normalized, true)) {
                    $header = $normalized;
                    continue;
                }
                $header = ['account_id', 'service_code', 'usage_date', 'region', 'usage_quantity', 'cost_usd', 'note'];
            }

            if (count($row) < 3) {
                $skipped++;
                continue;
            }

            $data = [];
            foreach ($header as $index => $key) {
                $data[$key] = $row[$index] ?? '';
            }

            $accountNumber = preg_replace('/\D/', '', (string) ($data['account_id'] ?? '')) ?? '';
            $serviceCode = strtolower(trim((string) ($data['service_code'] ?? $data['service'] ?? '')));
            $date = trim((string) ($data['usage_date'] ?? $data['date'] ?? ''));
            $region = trim((string) ($data['region'] ?? '')) ?: 'us-east-1';
            $quantity = max(0, safe_float($data['usage_quantity'] ?? $data['quantity'] ?? 0));
            $cost = safe_float($data['cost_usd'] ?? $data['cost'] ?? 0);
            $note = substr(trim((string) ($data['note'] ?? '')), 0, 255);

            if (!isset($accounts[$accountNumber])) {
                $skipped++;
                $errors[] = "Baris {$line}: account_id {$accountNumber} tidak dikenal";
                continue;
            }
            if (!isset($services[$serviceCode])) {
                $skipped++;
                $errors[] = "Baris {$line}: service_code '{$serviceCode}' tidak dikenal";
                continue;
            }
            if (!valid_date($date)) {
                $skipped++;
                $errors[] = "Baris {$line}: tanggal '{$date}' tidak valid";
                continue;
            }

            $service = $services[$serviceCode];
            if ($cost <= 0) {
                $cost = round($quantity * (float) $service['unit_price'], 4);
            }
            if ($cost <= 0) {
                $skipped++;
                continue;
            }

            db_exec(
                'INSERT INTO cost_records (account_id, service_id, usage_date, region, usage_quantity, cost_usd, source, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$accounts[$accountNumber], (int) $service['id'], $date, $region, $quantity, round($cost, 4), 'import', $note ?: null]
            );
            $imported++;
        }

        $status = $skipped > 0 ? ($imported > 0 ? 'partial' : 'failed') : 'success';
        $logMessage = $errors ? substr(implode('; ', array_slice($errors, 0, 5)), 0, 255) : null;
        db_exec(
            'INSERT INTO import_logs (filename, rows_imported, rows_skipped, status, message) VALUES (?, ?, ?, ?, ?)',
            [substr((string) $file['name'], 0, 190), $imported, $skipped, $status, $logMessage]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        flash('error', 'Import gagal: ' . $e->getMessage());
        back_to('costs');
    } finally {
        fclose($handle);
    }

    if ($imported > 0) {
        $message = "Import selesai: {$imported} baris masuk, {$skipped} baris dilewati.";
        if ($errors) {
            $message .= ' Contoh: ' . implode('; ', array_slice($errors, 0, 3));
        }
        flash('success', $message);
    } else {
        flash('error', 'Tidak ada baris yang berhasil diimpor. ' . implode('; ', array_slice($errors, 0, 3)));
    }
    back_to('costs');
}

/** Unduh daftar biaya sebagai CSV. */
function export_costs_csv(array $filters): never
{
    $rows = q_costs($filters, 10000, 0);
    $filename = 'aws-costs-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM agar Excel membaca UTF-8 dengan benar.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['account_id', 'account_alias', 'service_code', 'service_name', 'usage_date', 'region', 'usage_quantity', 'cost_usd', 'source', 'note'], ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['aws_account_id'],
            $row['alias'],
            $row['service_code'],
            $row['service_name'],
            $row['usage_date'],
            $row['region'],
            $row['usage_quantity'],
            $row['cost_usd'],
            $row['source'],
            $row['note'],
        ], ',', '"', '');
    }
    fclose($out);
    exit;
}
