# WSL Ubuntu Setup Guide

> Untuk modul **AWS Billing Manager** (`/billing/`), termasuk koneksi key AWS,
> lihat panduan khusus: **[docs/AWS_BILLING_SETUP.md](docs/AWS_BILLING_SETUP.md)**.
> Setup cepat: `bash scripts/wsl-billing-setup.sh`.

## Langkah 1: Install Dependencies di WSL

Jalankan perintah ini di WSL Ubuntu:

```bash
sudo apt update
sudo apt install -y python3 python3-pip python3-venv python3-dev
```

## Langkah 2: Navigasi ke Project Directory

```bash
cd "/mnt/c/laragon/www/aws billing qween 3 32b"
```

## Langkah 3: Setup Virtual Environment

```bash
# Buat virtual environment
python3 -m venv venv

# Aktifkan virtual environment
source venv/bin/activate

# Install dependencies
pip install --upgrade pip
pip install -r requirements.txt
```

## Langkah 4: Setup Environment File

```bash
# Copy .env template
cp .env.example .env

# Edit .env untuk isi API keys
nano .env
```

Isi API keys yang diperlukan:

- `GROQ_API_KEY` untuk Groq
- `OPENROUTER_API_KEY` untuk OpenRouter
- `GEMINI_API_KEY` untuk Google Cloud
- `AWS_ACCESS_KEY_ID` dan `AWS_SECRET_ACCESS_KEY` untuk AWS Bedrock

## Langkah 5: Jalankan Script Python CLI

```bash
# Pastikan virtual environment aktif
source venv/bin/activate

# Jalankan script
python3 chat_groq.py
python3 chat_openrouter.py
python3 chat_bedrock.py
```

## Langkah 6: Jalankan dengan Docker (Opsional)

### Perbaikan Dockerfile Sudah Dilakukan

Dockerfile sudah diperbaiki untuk mengatasi error "externally-managed-environment" dengan menggunakan virtual environment di dalam container.

```bash
# Build dan jalankan
docker compose up -d --build

# Lihat logs
docker compose logs -f

# Stop container
docker compose down
```

Akses di browser: `http://localhost:8081`

## Alternatif: Gunakan PHP Server di WSL

Jika ingin menjalankan web dashboard tanpa Docker:

```bash
# Install PHP + ekstensi yang dibutuhkan modul billing
sudo apt install -y php-cli php-curl php-mysql php-mbstring php-xml

# Jalankan PHP server dari root proyek
cd "/mnt/c/laragon/www/aws billing qween 3 32b"
php -S 0.0.0.0:8000 -t billing
```

Akses di browser:

- Billing Manager: `http://localhost:8000/index.php?page=login`
- Dashboard utama: jalankan `php -S 0.0.0.0:8081` dari root proyek.

Pastikan MySQL/MariaDB lokal aktif dan `.env` berisi kredensial database yang
sesuai, misalnya `DB_HOST=127.0.0.1 DB_USER=qween DB_PASSWORD=qween_secret`.

## Troubleshooting

### Error: "externally-managed-environment"

**Solusi:** Selalu gunakan virtual environment:

```bash
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### Error: "ensurepip is not available"

**Solusi:** Install python3-venv:

```bash
sudo apt install -y python3-venv
```

### Error: Docker build fails with externally-managed-environment

**Solusi:** Dockerfile sudah diperbaiki untuk menggunakan venv di dalam container. Coba rebuild:

```bash
docker compose down
docker compose up -d --build
```

### Error: "no such host" saat Docker build

**Solusi:** Cek koneksi internet dan DNS:

```bash
# Test koneksi
ping google.com

# Jika DNS problem, edit /etc/resolv.conf
sudo nano /etc/resolv.conf
# Tambahkan: nameserver 8.8.8.8
```

## Summary

1. ✅ Install Python dependencies: `sudo apt install python3 python3-pip python3-venv`
2. ✅ Create venv: `python3 -m venv venv`
3. ✅ Activate venv: `source venv/bin/activate`
4. ✅ Install packages: `pip install -r requirements.txt`
5. ✅ Setup .env: `cp .env.example .env` lalu edit
6. ✅ Run scripts: `python3 chat_groq.py`
7. ✅ Docker sudah diperbaiki dan siap digunakan
