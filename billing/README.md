# Qween 3 32B — AWS Billing Manager

Modul manajemen billing AWS yang berjalan di **Docker** dengan **PHP 8.5 + MySQL 8 + phpMyAdmin**.
Aplikasi ini menjadi website localhost di `http://localhost:8081/billing/` dan terhubung ke
dashboard AI Billing Hub yang sudah ada.

## Fitur

- **Dashboard billing** — pengeluaran bulan berjalan, proyeksi akhir bulan, tren 6 bulan,
  komposisi biaya per layanan, kredit tersisa, dan kesehatan budget.
- **Biaya & usage** — filter per akun/layanan/tanggal, pencarian, pagination, tambah manual,
  hapus, **export CSV**, dan **import CSV** dengan validasi.
- **Akun AWS** — CRUD akun (Account ID 12 digit), environment, region, kredit, budget bulanan.
- **Budget guardrail** — limit bulanan/kuartalan/tahunan, ambang alert, hard-stop flag,
  proyeksi over-budget, dan pemeriksaan alert sekali klik.
- **Invoice** — terbitkan tagihan dari data biaya, tandai lunas, deteksi overdue.
- **Alert center** — budget threshold, kredit menipis, anomali, dan invoice jatuh tempo
  (dengan deduplikasi).
- **Katalog layanan** — harga satuan AWS yang bisa diperbarui.
- **Sinkronisasi AWS** — tarik biaya nyata dari **Cost Explorer** dan limit dari
  **AWS Budgets** memakai kredensial read-only di `.env` (STS + SigV4, tanpa SDK).
- **REST API** — ringkasan JSON untuk integrasi eksternal.

Panduan kredensial AWS: **[docs/AWS_BILLING_SETUP.md](docs/AWS_BILLING_SETUP.md)**.

## Arsitektur container

| Container | Image | Port host | Fungsi |
|---|---|---|---|
| `qwen-ai-billing-hub` | build lokal | `8081` | Apache + PHP (dashboard utama & `/billing/`) |
| `qween-billing-mysql` | `mysql:8.0` | internal | Database `aws_billing` (volume `billing-db-data`) |
| `qween-billing-phpmyadmin` | `phpmyadmin:5-apache` | `8082` | Inspeksi database |
| `qwen-ai-billing-redis` | `redis:8-alpine` | internal | Budget enforcement dashboard utama |

Data MySQL disimpan di volume Docker `billing-db-data`, jadi tetap ada setelah
`docker compose restart` atau `down` (tanpa `-v`).

## Menjalankan

Dari root proyek:

```bash
docker compose up -d --build
docker compose ps
```

Buka:

- Aplikasi billing: <http://localhost:8081/billing/>
- Dashboard utama: <http://localhost:8081/>
- phpMyAdmin: <http://localhost:8082/> (server `billing-db`, user `qween`, password `qween_secret`)

Saat pertama kali dijalankan, MySQL membuat tabel dari `billing/db/schema.sql`, lalu
aplikasi PHP mengisi **data demo** otomatis (3 akun, 11 layanan, ±2.700 catatan biaya
6 bulan, invoice, budget, dan alert).

### Akun demo

| Role | Email | Password |
|---|---|---|
| Admin | `admin@qween.local` | `qween123` |
| Finance | `finance@qween.local` | `qween123` |
| Viewer | `viewer@qween.local` | `qween123` |

> Ganti password demo sebelum memakai aplikasi di jaringan yang dapat diakses orang lain.

### Menjalankan di WSL 2 Ubuntu

Docker Desktop dengan integrasi WSL 2 sudah cukup:

```bash
cd "/mnt/c/laragon/www/aws billing qween 3 32b"
docker compose up -d --build
# akses dari browser Windows: http://localhost:8081/billing/
```

Untuk performa I/O yang lebih baik, salin proyek ke filesystem WSL:

```bash
cp -r "/mnt/c/laragon/www/aws billing qween 3 32b" ~/aws-billing-qween
cd ~/aws-billing-qween
docker compose up -d --build
```

Pastikan Docker Engine aktif di dalam distro WSL (Docker Desktop → Settings → Resources →
WSL Integration) atau jalankan Docker Engine native di Ubuntu.

## Perintah harian

```bash
# Status & log
docker compose ps
docker compose logs -f ai-billing-hub
docker compose logs -f billing-db

# Pemeriksaan budget via CLI (membuat alert baru)
docker compose exec ai-billing-hub php /var/www/html/billing/cron/check_budgets.php

# Sinkronisasi AWS dari CLI (30 hari)
docker compose exec ai-billing-hub php /var/www/html/billing/cron/sync_aws.php 30

# Masuk ke container aplikasi
docker compose exec ai-billing-hub bash

# Backup database
docker compose exec billing-db sh -c 'mysqldump -uroot -pqween_root_secret aws_billing' > backup-aws-billing.sql

# Restore
docker compose exec -T billing-db sh -c 'mysql -uroot -pqween_root_secret aws_billing' < backup-aws-billing.sql
```

## Struktur direktori

```text
billing/
├── index.php            # front controller (dashboard, costs, accounts, budgets, invoices, alerts, services, settings)
├── api.php              # REST API JSON
├── bootstrap.php        # session, timezone, koneksi DB, auto-install
├── config.php           # konfigurasi dari environment
├── lib/
│   ├── db.php           # PDO + bootstrap skema
│   ├── seed.php         # data demo deterministik
│   ├── queries.php      # query dashboard & laporan
│   ├── alerts.php       # aturan budget/kredit/invoice
│   ├── actions.php      # handler POST + import/export CSV
│   ├── auth.php         # login/logout
│   └── helpers.php      # format, URL, CSRF, ikon
├── views/               # layout & halaman
├── assets/              # CSS, JS, renderer grafik offline
├── db/schema.sql        # skema MySQL (juga dipakai init container)
├── cron/check_budgets.php
├── sample-costs.csv
└── README.md
```

## REST API

Semua endpoint mengembalikan JSON. Endpoint data memerlukan session login
(login lewat `/billing/` di browser yang sama).

```bash
curl http://localhost:8081/billing/api.php?action=status
curl -c cookies.txt -d "email=admin@qween.local&password=qween123" ...   # gunakan browser untuk sesi
curl -b cookies.txt "http://localhost:8081/billing/api.php?action=summary"
curl -b cookies.txt "http://localhost:8081/billing/api.php?action=monthly&months=6"
curl -b cookies.txt "http://localhost:8081/billing/api.php?action=services&from=2026-11-01&to=2026-11-30"
curl -b cookies.txt "http://localhost:8081/billing/api.php?action=accounts"
curl -b cookies.txt "http://localhost:8081/billing/api.php?action=budgets"
curl -b cookies.txt "http://localhost:8081/billing/api.php?action=alerts&limit=10"
```

## Import CSV

Format kolom (header opsional, urutan tetap):

```csv
account_id,service_code,usage_date,region,usage_quantity,cost_usd,note
111122223333,EC2,2026-10-01,us-east-1,720,33.4080,Contoh instance
```

`account_id` harus 12 digit dan sudah terdaftar; `service_code` harus ada di katalog
(EC2, S3, RDS, Lambda, CloudFront, Bedrock, DynamoDB, EKS, SNS, CloudWatch, Route53).
Jika `cost_usd` kosong/0, biaya dihitung dari `usage_quantity × unit_price`.

## Sinkronisasi AWS (Cost Explorer + Budgets)

1. Isi key read-only di `.env` (lihat `docs/AWS_BILLING_SETUP.md`).
2. Restart container/server.
3. Login sebagai **admin** atau **finance**.
4. Buka **Pengaturan → Koneksi AWS**.
5. Klik **Tes Koneksi AWS**, lalu **Sinkron dari AWS**.

Biaya disimpan dengan `source = aws_ce`; data pada rentang yang sama diganti,
bukan diduplikasi. Status tes dan waktu sync terakhir tersimpan di tabel
`settings` (`aws_last_test_*`, `aws_last_sync_*`).

## Konfigurasi environment

Variabel opsional (dapat ditambahkan ke `.env`):

| Variabel | Default | Keterangan |
|---|---|---|
| `DB_NAME` | `aws_billing` | Nama database |
| `DB_USER` | `qween` | User MySQL |
| `DB_PASSWORD` | `qween_secret` | Password user MySQL |
| `DB_ROOT_PASSWORD` | `qween_root_secret` | Password root MySQL |
| `AWS_ACCESS_KEY_ID` | — | IAM access key read-only untuk billing |
| `AWS_SECRET_ACCESS_KEY` | — | IAM secret key (jangan di-commit) |
| `AWS_SESSION_TOKEN` | — | Opsional untuk temporary credential |
| `AWS_DEFAULT_REGION` | `us-east-1` | Region untuk STS/penyimpanan biaya |
| `AWS_SYNC_DAYS` | `30` | Rentang default sinkronisasi |
| `TZ` | `Asia/Jakarta` | Timezone aplikasi & container |
| `DEBUG` | `false` | Tampilkan detail error koneksi |

Di luar Docker (mis. `php -S`), set `DB_HOST=127.0.0.1`, `DB_USER=root`,
dan `DB_PASSWORD=` (kosong) agar memakai MySQL lokal.

## Troubleshooting

**Halaman menampilkan "MySQL belum bisa dihubungi"**
```bash
docker compose ps billing-db
docker compose logs billing-db
```
Tunggu hingga healthcheck `healthy`, lalu muat ulang.

**Port 8081/8082 sudah dipakai** — ubah mapping di `docker-compose.yml`
(`"8081:80"` / `"8082:80"`) lalu `docker compose up -d`.

**Ingin mengulang data demo dari nol**
```bash
docker compose down
docker volume rm awsbillingqween332b_billing-db-data   # cek nama volume: docker volume ls
docker compose up -d
```
Atau lewat phpMyAdmin: kosongkan tabel lalu buka halaman billing (seeder berjalan
saat tabel `users` kosong).

**`could not find driver`** — image lama belum punya `pdo_mysql`; jalankan
`docker compose build --no-cache ai-billing-hub && docker compose up -d`.

**Perubahan kode tidak muncul** — folder `billing/` di-mount read-only ke container,
jadi perubahan file langsung terlihat. Untuk file di luar `billing/` (mis. `index.php`),
jalankan `docker compose up -d --build`.
