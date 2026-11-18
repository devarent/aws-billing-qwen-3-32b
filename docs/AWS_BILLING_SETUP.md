# AWS Billing Setup — Qween 3 32B

Panduan menghubungkan **AWS Billing Manager** ke akun AWS nyata memakai
kredensial read-only, lalu menjalankannya di **Docker**, **WSL 2 Ubuntu**, atau
**Amazon Linux 2023 di WSL**.

---

## 1. Yang dipakai aplikasi

| Layanan AWS | API | Fungsi |
|---|---|---|
| AWS STS | `GetCallerIdentity` | Validasi key dan ambil Account ID |
| AWS Cost Explorer | `GetCostAndUsage` | Tarik biaya harian per layanan |
| AWS Budgets | `DescribeBudgets` | Tarik limit budget |

Semua request ditandatangani dengan **Signature Version 4** langsung dari PHP
(cURL), jadi tidak perlu Composer atau AWS SDK.

Data hasil sync masuk ke MySQL lokal:

- akun baru otomatis dibuat dari Account ID STS;
- biaya disimpan dengan `source = aws_ce`;
- layanan baru otomatis ditambahkan ke katalog;
- budget AWS di-upsert ke tabel `budgets`.

---

## 2. Buat IAM user read-only

Jangan memakai root account key. Buat IAM user khusus, misalnya
`qween-billing-readonly`, lalu lampirkan policy berikut:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "BillingIdentity",
      "Effect": "Allow",
      "Action": ["sts:GetCallerIdentity"],
      "Resource": "*"
    },
    {
      "Sid": "BillingCostExplorer",
      "Effect": "Allow",
      "Action": ["ce:GetCostAndUsage"],
      "Resource": "*"
    },
    {
      "Sid": "BillingBudgets",
      "Effect": "Allow",
      "Action": ["budgets:DescribeBudgets", "budgets:ViewBudget"],
      "Resource": "*"
    }
  ]
}
```

Buat **Access key → Application running outside AWS**, lalu salin
`AWS_ACCESS_KEY_ID` dan `AWS_SECRET_ACCESS_KEY`.

> Cost Explorer API dikenakan biaya kecil per request (sekitar **$0.01/request**).
> Tombol sinkronisasi memanggil 1 request biaya + 1 request budget per klik,
> jadi jangan dijalankan berlebihan. Sync 30 hari tetap 1 request.

---

## 3. Isi `.env`

Dari root proyek:

```bash
cp .env.example .env
nano .env
```

Isi bagian AWS:

```dotenv
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
# Opsional bila memakai temporary credential / AWS SSO
# AWS_SESSION_TOKEN=...
AWS_DEFAULT_REGION=us-east-1
AWS_SYNC_DAYS=30
```

Aplikasi membaca `.env` dari root proyek, baik saat dijalankan lewat Docker
maupun `php -S`. Environment Docker/OS selalu diprioritaskan di atas `.env`.

Setelah mengubah `.env`:

```bash
# Docker
docker compose up -d --force-recreate ai-billing-hub

# Native / WSL php -S
# hentikan server (Ctrl+C) lalu jalankan ulang
```

---

## 4. Tes koneksi & sinkronisasi

1. Buka `http://localhost:8081/billing/`, login sebagai admin/finance.
2. Masuk **Pengaturan → Koneksi AWS**.
3. Klik **Tes Koneksi AWS**. Status berubah menjadi **Terhubung** dan
   Account ID muncul.
4. Pilih rentang (7/14/30/60/90 hari), klik **Sinkron dari AWS**.
5. Cek **Dashboard**, **Biaya & Usage**, **Budget**, dan **Riwayat Import**.

Sinkronisasi bersifat idempotent: data `source = aws_ce` pada rentang tanggal
yang sama dihapus lalu diisi ulang, sehingga aman diklik berkali-kali.

Untuk otomatisasi harian, jalankan dari cron host/WSL:

```bash
# contoh: setiap hari 06:00 WIB
0 6 * * * cd "/mnt/c/laragon/www/aws billing qween 3 32b" && docker compose exec -T ai-billing-hub php /var/www/html/billing/cron/sync_aws.php 30 >> /tmp/qween-sync.log 2>&1
```

### Status yang mungkin muncul

| Status | Arti |
|---|---|
| Belum dikonfigurasi | Key kosong atau masih placeholder di `.env` |
| Siap diuji | Key terdeteksi, belum pernah dites |
| Terhubung | STS berhasil, Account ID valid |
| Gagal terhubung | Lihat pesan error di kartu Koneksi AWS |

---

## 5. Menjalankan di WSL 2 Ubuntu

### Opsi A — Docker (disarankan)

Prasyarat: Docker Desktop dengan **WSL Integration** aktif, atau Docker Engine
native di Ubuntu.

```bash
cd "/mnt/c/laragon/www/aws billing qween 3 32b"
docker compose up -d --build
docker compose ps
```

Buka dari browser Windows: <http://localhost:8081/billing/>

### Opsi B — PHP native di WSL

```bash
sudo apt update
sudo apt install -y php-cli php-curl php-mysql php-mbstring php-xml mariadb-server
sudo service mariadb start

sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS aws_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'qween'@'localhost' IDENTIFIED BY 'qween_secret';
GRANT ALL PRIVILEGES ON aws_billing.* TO 'qween'@'localhost';
FLUSH PRIVILEGES;
SQL

cd "/mnt/c/laragon/www/aws billing qween 3 32b"
DB_HOST=127.0.0.1 DB_USER=qween DB_PASSWORD=qween_secret \
  php -S 0.0.0.0:8000 -t billing
```

Buka <http://localhost:8000/index.php?page=login>. Skema dan data demo dibuat
otomatis saat pertama kali dijalankan.

> Untuk akses dari perangkat lain di LAN, gunakan `php -S 0.0.0.0:8000`.
> Jangan buka port ini ke internet publik.

### Opsi C — Amazon Linux 2023 di WSL

```bash
sudo dnf update -y
sudo dnf install -y php-cli php-curl php-mysqlnd php-mbstring php-xml
php -v
```

Lalu jalankan seperti Opsi B (atau gunakan Docker). Jika memakai Docker Engine
native di Amazon Linux:

```bash
sudo dnf install -y docker
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
# logout/login WSL, lalu:
docker compose up -d --build
```

Script bantuan: [`scripts/wsl-billing-setup.sh`](../scripts/wsl-billing-setup.sh).

---

## 6. Troubleshooting

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| `InvalidClientTokenId` | Key salah, placeholder, atau sudah dihapus | Buat access key baru, update `.env`, restart |
| `SignatureDoesNotMatch` | Jam sistem/WSL melenceng | `sudo hwclock -s` atau sinkronkan waktu Windows/WSL |
| `AccessDenied` / `UnauthorizedOperation` | IAM policy kurang | Lampirkan policy di bagian 2 |
| `DataUnavailableException` | Cost Explorer belum aktif | Buka AWS Console → Billing → Cost Explorer sekali |
| `Could not connect to server` / SSL error | CA certificate belum ada di WSL | `sudo apt install -y ca-certificates` lalu ulangi |
| Halaman tanpa CSS di port proxy | URL aset absolut | Sudah diperbaiki: aset memakai URL relatif |
| `MySQL belum bisa dihubungi` | Container DB belum healthy | `docker compose ps` lalu tunggu `healthy` |

### Cek kredensial lewat AWS CLI

```bash
aws sts get-caller-identity
aws ce get-cost-and-usage \
  --time-period Start=2026-11-01,End=2026-11-05 \
  --granularity DAILY \
  --metrics UnblendedCost
```

Kalau AWS CLI juga gagal, masalahnya ada di key/IAM — bukan di aplikasi.

---

## 7. Keamanan

- Gunakan IAM user **read-only**, bukan root.
- Jangan commit `.env`; file ini sudah masuk `.dockerignore`.
- Rotasi key bila pernah terekspos di screenshot, chat, atau log.
- Aplikasi ini untuk dashboard lokal. Jangan ekspos port ke internet tanpa
  autentikasi tambahan, HTTPS, dan pembatasan firewall.
- Hapus key setelah demo/tugas selesai bila tidak dipakai lagi.
