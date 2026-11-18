#!/bin/sh
# Uji aksi utama: tambah biaya, import CSV, export CSV, cek budget, simpan pengaturan.
set -eu

BASE="${BASE_URL:-http://localhost/billing}"
JAR=/tmp/qween-actions-cookies.txt
rm -f "$JAR"

curl -s -c "$JAR" "$BASE/index.php?page=login" -o /tmp/qa-login.html
TOKEN=$(sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p' /tmp/qa-login.html | head -1)
curl -s -b "$JAR" -c "$JAR" \
    --data-urlencode "_csrf=$TOKEN" \
    --data-urlencode "email=admin@qween.local" \
    --data-urlencode "password=qween123" \
    -o /dev/null "$BASE/index.php?page=login"

curl -s -b "$JAR" "$BASE/index.php?page=costs" -o /tmp/qa-costs.html
TOKEN=$(sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p' /tmp/qa-costs.html | head -1)
echo "csrf token: ${TOKEN%?????????????????????????????????????????????????}..."

count_costs() {
    php -r 'require "/var/www/html/billing/bootstrap.php"; echo (int) db_value("SELECT COUNT(*) FROM cost_records");'
}

BEFORE=$(count_costs)
echo "cost records before: $BEFORE"

echo "== Tambah biaya manual =="
curl -s -b "$JAR" -c "$JAR" -L \
    --data-urlencode "_csrf=$TOKEN" \
    --data-urlencode "action=cost_create" \
    --data-urlencode "account_id=1" \
    --data-urlencode "service_id=1" \
    --data-urlencode "usage_date=$(date +%Y-%m-%d)" \
    --data-urlencode "region=us-east-1" \
    --data-urlencode "usage_quantity=10" \
    --data-urlencode "cost_usd=1.23" \
    --data-urlencode "note=QA smoke test" \
    "$BASE/index.php?page=costs" -o /tmp/qa-after-create.html
grep -o 'Catatan biaya berhasil ditambahkan.' /tmp/qa-after-create.html | head -1 || echo "flash tidak ditemukan"
AFTER_CREATE=$(count_costs)
echo "cost records after create: $AFTER_CREATE"

echo "== Import CSV =="
curl -s -b "$JAR" -c "$JAR" -L \
    -F "_csrf=$TOKEN" \
    -F "action=cost_import" \
    -F "csv=@/var/www/html/billing/sample-costs.csv;type=text/csv" \
    "$BASE/index.php?page=costs" -o /tmp/qa-after-import.html
grep -o 'Import selesai: [0-9]* baris masuk, [0-9]* baris dilewati.' /tmp/qa-after-import.html | head -1 || echo "flash import tidak ditemukan"
AFTER_IMPORT=$(count_costs)
echo "cost records after import: $AFTER_IMPORT"

echo "== Export CSV =="
curl -s -b "$JAR" -D /tmp/qa-export-headers \
    "$BASE/index.php?page=costs&export=csv&from=$(date +%Y-%m-01)&to=$(date +%Y-%m-%d)" \
    -o /tmp/qa-export.csv
head -1 /tmp/qa-export-headers
echo "baris CSV: $(wc -l < /tmp/qa-export.csv)"

echo "== Pemeriksaan budget =="
curl -s -b "$JAR" -c "$JAR" -L \
    --data-urlencode "_csrf=$TOKEN" \
    --data-urlencode "action=run_budget_check" \
    "$BASE/index.php?page=alerts" -o /tmp/qa-alerts.html
grep -o 'Pemeriksaan selesai: [^<]*' /tmp/qa-alerts.html | head -1 || echo "flash budget check tidak ditemukan"

echo "== Simpan pengaturan =="
curl -s -b "$JAR" -c "$JAR" -L \
    --data-urlencode "_csrf=$TOKEN" \
    --data-urlencode "action=settings_save" \
    --data-urlencode "company_name=Qween 3 32B" \
    --data-urlencode "company_tagline=AWS Billing Manager" \
    --data-urlencode "alert_email=finance@qween.local" \
    --data-urlencode "budget_alert_threshold=80" \
    --data-urlencode "credit_alert_threshold=20" \
    --data-urlencode "default_region=us-east-1" \
    --data-urlencode "currency=USD" \
    --data-urlencode "auto_alerts=1" \
    "$BASE/index.php?page=settings" -o /tmp/qa-settings.html
grep -o 'Pengaturan disimpan.' /tmp/qa-settings.html | head -1 || echo "flash settings tidak ditemukan"

echo "== Selesai =="
