#!/bin/sh
# Smoke test aplikasi billing di dalam container.
set -eu

BASE="${BASE_URL:-http://localhost/billing}"
JAR=/tmp/qween-cookies.txt
rm -f "$JAR"

echo "== Halaman login =="
curl -s -c "$JAR" "$BASE/index.php?page=login" -o /tmp/qween-login.html
TOKEN=$(sed -n 's/.*name="_csrf" value="\([^"]*\)".*/\1/p' /tmp/qween-login.html | head -1)
echo "csrf: ${TOKEN%?????????????????????????????????????????????????}..."
echo "login page bytes: $(wc -c < /tmp/qween-login.html)"

echo "== Login =="
curl -s -b "$JAR" -c "$JAR" \
    --data-urlencode "_csrf=$TOKEN" \
    --data-urlencode "email=admin@qween.local" \
    --data-urlencode "password=qween123" \
    -D /tmp/qween-login-headers -o /dev/null \
    "$BASE/index.php?page=login"
head -1 /tmp/qween-login-headers

echo "== Halaman =="
for page in dashboard costs accounts budgets invoices alerts services settings; do
    code=$(curl -s -b "$JAR" -o "/tmp/qween-$page.html" -w '%{http_code}' "$BASE/index.php?page=$page")
    size=$(wc -c < "/tmp/qween-$page.html")
    echo "$page: HTTP $code, $size bytes"
done

echo "== Judul dashboard =="
grep -o '<title>[^<]*' /tmp/qween-dashboard.html || true
grep -c 'Pengeluaran Bulan Ini' /tmp/qween-dashboard.html || true

echo "== API =="
curl -s "$BASE/api.php?action=status" | head -c 220
echo
curl -s -b "$JAR" "$BASE/api.php?action=summary" | head -c 320
echo

echo "== Ringkasan data =="
php -r '
require "/var/www/html/billing/bootstrap.php";
printf("users=%d accounts=%d costs=%d budgets=%d invoices=%d alerts=%d mtd=%s\n",
    (int) db_value("SELECT COUNT(*) FROM users"),
    (int) db_value("SELECT COUNT(*) FROM aws_accounts"),
    (int) db_value("SELECT COUNT(*) FROM cost_records"),
    (int) db_value("SELECT COUNT(*) FROM budgets"),
    (int) db_value("SELECT COUNT(*) FROM invoices"),
    (int) db_value("SELECT COUNT(*) FROM alerts"),
    money(q_mtd_spend())
);
'
