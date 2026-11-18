# AI Billing & Cloud Hub (Multi-Provider)

Aplikasi kalkulator biaya token, simulator runway saldo, pengaman billing otomatis (AWS Budgets & GCP), serta antarmuka chat langsung dengan model **Qwen 32B/72B/235B dan Qwen3-Next 80B A3B** via Groq, OpenRouter, Amazon Bedrock, dan Google Cloud Gemini.

## 🚀 Production-Grade Features (Based on AWS Samples)

Project ini telah ditingkatkan dengan pattern production-grade dari AWS samples:

- **Hard Budget Enforcement** - Cek budget sebelum API call (<100ms dengan Redis)
- **DynamoDB Cost Tracking** - Single-table design ala FlowAMP untuk cost tracking
- **JWT Identity Propagation** - Identity tracking multi-provider (Groq, OpenRouter, Bedrock, Gemini)
- **Prometheus/Grafana Monitoring** - Real-time metrics dan dashboards
- **Multi-Table Schema** - Separate policy & metrics tables dengan GSI untuk efisiensi

Lihat [INTEGRATIONS.md](INTEGRATIONS.md) untuk detail lengkap.

## 🧾 AWS Billing Manager (PHP + MySQL + Docker)

Modul manajemen billing AWS tersedia di **`http://localhost:8081/billing/`**, berjalan di
container PHP yang sama dengan database **MySQL 8** terpisah dan **phpMyAdmin**.

- Login demo: `admin@qween.local` / `qween123`
- phpMyAdmin: `http://localhost:8082`
- Data demo otomatis: 3 akun AWS, 11 layanan, riwayat biaya 6 bulan, budget, invoice, alert.
- Fitur: dashboard biaya, import/export CSV, budget guardrail, invoice, alert center, REST API.
- **Sinkronisasi AWS nyata**: Cost Explorer + AWS Budgets via kredensial read-only di `.env`
  (panduan: **[docs/AWS_BILLING_SETUP.md](docs/AWS_BILLING_SETUP.md)**).

Panduan lengkap: **[billing/README.md](billing/README.md)**

```powershell
docker compose up -d --build
# aplikasi  : http://localhost:8081/billing/
# phpMyAdmin: http://localhost:8082
```

---

## ⚡ Quick Start

Panduan lengkap: **[docs/RUNBOOK.md](docs/RUNBOOK.md)**

```powershell
# 1. Jalankan dashboard PHP + API
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\run-local.ps1
#    -> http://localhost:8081

# 2. (Opsional) Jalankan via Docker (termasuk Redis)
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\run-docker.ps1
#    -> http://localhost:8081

# 3. (Opsional) Frontend tambahan
cd apps\nextjs;  npm install; npm run dev    # http://localhost:3000
cd apps\angular; npm install; npm start      # http://localhost:4200

# 4. (Opsional) Contoh Assembly x86-64
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\build-assembly.ps1
```

Validasi cepat semua konfigurasi:

```powershell
make validate   # docker compose + helm + terraform + kubernetes
```

---

## 🚀 Fitur Utama

1. **Kalkulator Biaya & Runway Saldo ($200 AWS / $300 GCP)**:
   - Menghitung konsumsi token harian dan bulanan.
   - Perbandingan langsung tarif keluarga model Qwen:
     - **Qwen 2.5 Coder 32B** ($0.66 in / $1.00 out per 1M token)
     - **Qwen3 235B A22B Thinking** ($0.45 in / $3.50 out per 1M token)
     - **Qwen-Plus** ($0.26 in / $0.78 out per 1M token)
     - **Qwen3 VL 30B** ($0.29 in / $1.00 out per 1M token)
     - **Qwen 2.5 72B Instruct** ($0.35 in / $0.40 out per 1M token)
     - **Claude 3.5 Sonnet di Bedrock** ($3.00 in / $15.00 out per 1M token)
     - **Llama 3.3 70B** ($0.72 in / $0.72 out per 1M token)
     - **Qwen3-Next 80B A3B di Bedrock** (tarif diisi dari AWS Pricing sesuai region/service tier)
     - **Gemini 2.5 Flash** ($0.075 in / $0.30 out per 1M token)
   - Grafik interaktif Chart.js memperlihatkan proyeksi penurunan saldo hari demi hari.
   - Untuk Qwen3-Next Bedrock, kalkulator meminta tarif input/output per 1 juta token dari AWS Pricing; proyeksi biaya tidak ditampilkan sebelum tarif diisi.

2. **AI Chat Playground (Multi-Provider)**:
   - Chat langsung di browser dengan latensi super cepat via Groq LPUs.
   - Dukungan OpenRouter untuk akses ke semua model Qwen.
   - Dukungan Google Cloud Gemini.
   - Menampilkan token prompt, token output, waktu respon, dan biaya mikro per query secara real-time.

3. **Generator AWS & GCP Budget Guard (Anti-Tagihan Bobol)**:
   - Form interaktif untuk men-generate perintah `aws budgets create-budget` dan `gcloud billing budgets create`.
   - Mengirim email notifikasi otomatis saat pengeluaran mencapai ambang tertentu (contoh: 80% dari $5).

4. **Panduan AWS Student ($200 Free Credits)**:
   - Rangkuman cara klaim AWS Builder ID (tanpa kartu kredit) dan pemanfaatan Bedrock serta Amazon Q Developer.

---

## 💻 Cara Menjalankan

### Opsi A: Lewat Web Dashboard (Laragon / Localhost)

Siapkan konfigurasi lokal lebih dulu:

1. Salin template environment:

   ```powershell
   # Windows PowerShell
   Copy-Item .env.example .env

   # atau di Bash/WSL
   cp .env.example .env
   ```

2. Isi key yang diperlukan di `.env`. Jangan commit file ini.

API PHP membaca environment variable proses dan `.env` secara langsung. Jadi,
untuk Laragon Anda tidak perlu menjalankan `direnv`; restart Apache jika Anda
mengubah environment variable sistem Windows.

Jika Laragon aktif di komputer Anda:

1. Akses lewat browser:
   - `http://localhost/aws billing qween 3 32b/`
   - atau `http://aws-billing-qween-3-32b.test` (jika virtual host otomatis Laragon aktif).

2. Atau jalankan PHP built-in server dari terminal:

   ```bash
   php -S localhost:8000
   ```

   Lalu buka `http://localhost:8000`.

#### Opsional: direnv di Bash/WSL

File `.envrc` proyek menjalankan `dotenv_if_exists .env`; file tersebut tidak
menyimpan kredensial. Dari Bash/WSL yang sudah memasang dan mengaktifkan hook
direnv:

```bash
cd "/mnt/c/laragon/www/aws billing qween 3 32b"
direnv allow .
direnv status
php -S localhost:8000
```

`direnv allow .` adalah persetujuan keamanan yang diperlukan setelah `.envrc`
dibuat atau berubah. Jalankan server dari shell tersebut agar mewarisi
environment variable. Apache Laragon yang dijalankan terpisah tidak mewarisi
environment dari WSL; gunakan `.env` atau konfigurasi environment Windows untuk
Laragon.

### Opsi B: Menggunakan Script Python CLI

1. Install dependensi:

   ```bash
   pip install -r requirements.txt
   ```

2. Buat file `.env` dari `.env.example`:

   ```powershell
   Copy-Item .env.example .env
   ```

3. Jalankan client Groq (Qwen):

   ```bash
   python chat_groq.py
   ```

4. Jalankan client OpenRouter (Full Qwen catalog):

   ```bash
   python chat_openrouter.py
   ```

5. Jalankan client Bedrock (Claude / Llama / Amazon Nova / Qwen3-Next):

   ```bash
   python chat_bedrock.py
   ```

   Di CLI, ketik `/model` lalu pilih `7` untuk Qwen3-Next 80B A3B. Model ini memakai AWS Bedrock Converse API. Pastikan region dan izin model/IAM sudah sesuai. Tarifnya tidak di-hardcode; periksa [AWS Bedrock Pricing](https://aws.amazon.com/bedrock/pricing/) sesuai region dan service tier sebelum mengirim prompt.

### Opsi C: Menggunakan Docker (Windows / WSL / Linux)

#### 1. Persiapan Environment

Buat file `.env` dari template:

```bash
cp .env.example .env
```

Edit file `.env` dan isi API keys yang diperlukan:

- `GROQ_API_KEY` untuk Groq
- `OPENROUTER_API_KEY` untuk OpenRouter
- `GEMINI_API_KEY` untuk Google Cloud Gemini
- `AWS_ACCESS_KEY_ID` dan `AWS_SECRET_ACCESS_KEY` untuk AWS Bedrock

#### 2. Build dan Jalankan Container

```bash
# Build dan jalankan di background
docker compose up -d --build

# Lihat logs
docker compose logs -f

# Stop container
docker compose down
```

#### 3. Akses Dashboard

Buka browser dan akses:

- `http://localhost:8081`

#### 4. Jalankan Script Python di dalam Container

```bash
# Masuk ke container
docker compose exec ai-billing-hub bash

# Jalankan script CLI
python3 chat_groq.py
python3 chat_openrouter.py
python3 chat_bedrock.py
```

### Opsi D: WSL Ubuntu 2 Setup

Untuk menjalankan di WSL Ubuntu (Ubuntu 22.04 atau 24.04):

#### 1. Install Dependencies

```bash
# Update package list
sudo apt update && sudo apt upgrade -y

# Install Python 3 dan pip
sudo apt install -y python3 python3-pip python3-venv

# Install PHP (untuk web dashboard)
sudo apt install -y php php-curl php-json

# Install Docker (jika belum ada)
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo apt install -y docker-compose

# Tambah user ke docker group (logout dan login kembali setelah ini)
sudo usermod -aG docker $USER
```

#### 2. Clone dan Setup Project

```bash
# Navigasi ke project directory (jika di Windows drive)
cd /mnt/c/laragon/www/aws\ billing\ qween\ 3\ 32b

# Atau copy ke direktori WSL native untuk performa lebih baik
cp -r /mnt/c/laragon/www/aws\ billing\ qween\ 3\ 32b ~/ai-billing-hub
cd ~/ai-billing-hub
```

#### 3. Setup Virtual Environment Python

```bash
# Buat virtual environment
python3 -m venv venv

# Aktifkan virtual environment
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt
```

#### 4. Configure Environment

```bash
# Copy .env template
cp .env.example .env

# Edit .env dengan nano atau vim
nano .env
```

Isi API keys yang diperlukan.

#### 5. Jalankan dengan Docker Compose

```bash
# Build dan jalankan
docker compose up -d --build

# Cek status
docker compose ps

# Lihat logs
docker compose logs -f
```

#### 6. Akses dari Windows Browser

Karena WSL dan Windows share network, Anda bisa akses dari Windows browser:

- `http://localhost:8081`

Atau dari dalam WSL:

- `http://127.0.0.1:8081`

#### 7. Jalankan Script CLI langsung (tanpa Docker)

```bash
# Pastikan virtual environment aktif
source venv/bin/activate

# Jalankan script
python chat_groq.py
python chat_openrouter.py
python chat_bedrock.py
```

#### 8. Troubleshooting WSL

Jika ada masalah dengan permission Docker:

```bash
# Restart WSL
wsl --shutdown

# Buka WSL lagi dan cek docker
docker --version
docker compose version
```

Jika port conflict:

```bash
# Cek port yang sedang digunakan
sudo netstat -tulpn | grep :8081

# Kill process jika ada conflict
sudo kill -9 <PID>
```

---

## 🛡️ Tips Keamanan Saldo Mahasiswa

- Selalu pasang budget alert sebelum bereksperimen.
- Gunakan fitur streaming di Groq atau Amazon Bedrock pay-per-token ketimbang menyewa instance EC2 GPU yang berjalan non-stop.
- Docker hanya sebagai runtime lokal; koneksi ke provider tetap melalui internet (outbound HTTPS).
- API keys disuntikkan saat runtime, tidak pernah di-hardcode ke dalam Docker image.

---

## 📊 Supported Providers & Models

### Groq (Ultra Fast LPUs)

- Qwen 2.5 Coder 32B
- Qwen 2.5 32B Instruct
- Qwen 3.8 27B
- Llama 3.3 70B
- Llama 3.1 70B
- Mixtral 8x7B

### OpenRouter (Full Catalog)

- Qwen 2.5 Coder 32B Instruct
- Qwen 2.5 72B Instruct
- Qwen QwQ 32B Preview (Reasoning)
- Qwen 2 VL 72B (Vision)
- Qwen Plus 131K
- Qwen 3 VL 30B

### AWS Bedrock ($200 Credit)

- **Qwen3-Next 80B A3B** (MoE Architecture - 80B total, 3B active)
- Claude 3.5 Sonnet v2
- Claude 3.5 Haiku
- Llama 3.3 70B
- Llama 3.1 70B
- Amazon Nova Lite
- Amazon Nova Pro

### Google Cloud Gemini ($300 Credit)

- Gemini 2.5 Flash
- Gemini 2.5 Pro

---

## 🏗️ Production Deployment

### Terraform (AWS EKS)

Deploy full infrastructure with Terraform:

```bash
cd terraform
cp terraform.tfvars.example terraform.tfvars
# Edit terraform.tfvars with your values
terraform init
terraform plan
terraform apply
```

See [INFRASTRUCTURE.md](INFRASTRUCTURE.md) for complete guide.

### Kubernetes Deployment

Deploy to Kubernetes with Helm:

```bash
cd helm/ai-billing-hub
cp values.yaml custom-values.yaml
# Edit custom-values.yaml with API keys
helm install ai-billing-hub . -f custom-values.yaml
```

Or use raw manifests:

```bash
cd kubernetes
kubectl apply -f secrets.yaml
kubectl apply -f deployment.yaml
```

---

## 🧪 Advanced Examples (Qwen3-Next)

Explore Qwen3-Next's advanced capabilities:

```bash
cd examples/advanced

# Agentic reasoning with multi-step planning
python agentic_reasoning.py

# Deep code analysis and optimization
python code_analysis.py

# Multilingual processing and translation
python multilingual_processing.py

# Long context document analysis
python long_context_analysis.py
```

Qwen3-Next Features:

- **Mixture-of-Experts (MoE)**: 80B total parameters, 3B active per token
- **Agentic Workflows**: Multi-step reasoning and tool orchestration
- **Long Context**: Extended document understanding
- **Multilingual**: Cross-lingual translation and processing
- **Code Understanding**: Deep analysis and optimization suggestions

---

## 🔧 Konfigurasi Docker Secrets (Opsional)

Untuk deployment production dengan Docker Swarm:

1. Buat direktori secrets:

   ```bash
   mkdir -p secrets
   ```

2. Buat file secrets:

   ```bash
   echo "your_groq_key" > secrets/groq_key.txt
   echo "your_openrouter_key" > secrets/openrouter_key.txt
   echo "your_gemini_key" > secrets/gemini_key.txt
   echo "your_aws_access_key" > secrets/aws_access_key.txt
   echo "your_aws_secret_key" > secrets/aws_secret_key.txt
   ```

3. Uncomment bagian `secrets` di `docker-compose.yml`

4. Deploy dengan Docker secrets:

   ```bash
   docker stack deploy -c docker-compose.yml ai-billing-hub
   ```

---

## 📝 Development

Untuk development lokal tanpa Docker, pastikan:

- PHP 8.3+ terinstall
- Python 3.8+ terinstall
- Extension PHP: curl, json
- Python packages: groq, boto3, openai, python-dotenv

---

## 📄 License

Project ini dibuat untuk edukasi dan manajemen billing cloud.

---

## Project Governance

[![CI](https://github.com/aryafatthurahman4-collab/aws-billing-qwen-3-32b/actions/workflows/ci.yml/badge.svg)](https://github.com/aryafatthurahman4-collab/aws-billing-qwen-3-32b/actions/workflows/ci.yml)

[Contributing](CONTRIBUTING.md) | [Security Policy](SECURITY.md) | [Code of Conduct](CODE_OF_CONDUCT.md)