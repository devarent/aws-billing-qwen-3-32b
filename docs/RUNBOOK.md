# RUNBOOK — AI Billing & Cloud Hub

Dokumen ini menjelaskan cara menjalankan seluruh komponen proyek setelah perbaikan.

---

## 1. Ringkasan Perbaikan (Changelog)

| Area | Masalah | Status |
|---|---|---|
| PHP API | `require vendor/autoload.php` padahal tidak ada `vendor/` → fatal error | ✅ Diperbaiki (guard `file_exists`) |
| PHP API | `new Redis()` fatal jika phpredis tidak ada | ✅ Diperbaiki (fail-open) |
| PHP API | `$action` & objek middleware dipakai sebelum didefinisikan | ✅ Diperbaiki (urutan include) |
| Docker | Port host bentrok dengan layanan WSL di `8080` | ✅ Distandarkan ke `8081` |
| Helm | Template Grafana memakai `{{provider}}` yang dianggap fungsi Go template | ✅ Diperbaiki (escape) |
| Helm | Secret tidak di-quote → rawan error YAML | ✅ Diperbaiki (`| quote`) |
| Terraform | Resource log group duplikat | ✅ Diperbaiki |
| Terraform | `module "dynamodb"` menunjuk ke file `.tf` (tidak valid) | ✅ Diperbaiki |
| Terraform | `aws_dynamodb_table_item` kurang `hash_key`/`range_key` | ✅ Diperbaiki |
| Terraform | Format tanggal budget salah | ✅ Diperbaiki |
| Keamanan | API key asli tertulis di `values.yaml`, `kubernetes/secrets.yaml`, `.env.example` | ✅ Diganti placeholder |

> **PENTING:** API key Groq/OpenRouter Anda pernah tersimpan di file manifest.
> Sebaiknya **rotate/regenerate** key tersebut di dashboard provider.

> **PORT:** Aplikasi memakai port **8081** karena port **8080** sudah dipakai
> layanan lain di WSL pada mesin ini. Semua skrip, dokumentasi, dan proxy
> frontend sudah disamakan ke `8081`.

---

## 2. Prasyarat (sudah terpasang di mesin ini)

| Tool | Versi terdeteksi |
|---|---|
| PHP | 8.5.12 |
| Composer | 2.10.2 |
| Python | 3.15.1 |
| Node.js / npm | 26.12.1 / 11.20.0 |
| Next.js / React | 16.4.4 / 19.3.1 |
| Angular | 22.2.4 (CLI 22.2.5) |
| TypeScript | 6.0.x |
| Docker | 29.8.2 |
| Redis (container) | 8-alpine |
| kubectl | 1.36.1 |
| Helm | 4.3.1 |
| Terraform | 1.16.6 |
| Laravel Installer | 5.32.0 |

Cek ulang dengan:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check-tools.ps1
```

---

## 3. Menjalankan Aplikasi

### 3.1 Web Dashboard PHP (Laragon / localhost)

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/run-local.ps1
# atau manual:
php -S localhost:8081
```

Buka: <http://localhost:8081>

### 3.2 Docker Compose

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/run-docker.ps1
# atau manual:
docker compose up -d --build
```

Buka: <http://localhost:8081>

Compose menjalankan dua service:

| Service | Fungsi |
|---|---|
| `ai-billing-hub` | Apache + PHP 8.5 + Python (dashboard & API) |
| `redis` | Cache budget enforcement (phpredis sudah aktif di image) |

Budget enforcement default **nonaktif** (`BUDGET_ENFORCEMENT_ENABLED=false`).
Aktifkan lewat `.env` bila ingin hard limit:

```env
BUDGET_ENFORCEMENT_ENABLED=true
DEFAULT_DAILY_CAP=10.0
DEFAULT_MONTHLY_CAP=100.0
```

### 3.3 Python CLI

```powershell
cd "C:\laragon\www\aws billing qween 3 32b"
python -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
python chat_groq.py
python chat_openrouter.py
python chat_bedrock.py
```

### 3.4 Terraform

```powershell
cd terraform
Copy-Item terraform.tfvars.example terraform.tfvars
# edit terraform.tfvars (isi API key & email)
terraform init
terraform plan
terraform apply
```

Validasi saja (tanpa apply):

```powershell
terraform -chdir=terraform validate
```

### 3.5 Helm / Kubernetes

```powershell
cd helm\ai-billing-hub
Copy-Item values.yaml custom-values.yaml
# edit custom-values.yaml -> isi secrets (jangan commit!)
helm lint .
helm template ai-billing-hub .
helm install ai-billing-hub . -f custom-values.yaml
```

Atau manifest langsung:

```powershell
kubectl apply -f kubernetes\secrets.yaml
kubectl apply -f kubernetes\deployment.yaml
```

### 3.6 Frontend Next.js

```powershell
cd apps\nextjs
npm install
npm run dev
```

Buka: <http://localhost:3000>

### 3.7 Frontend Angular

```powershell
cd apps\angular
npm install
npm start
```

Buka: <http://localhost:4200>

### 3.8 Assembly (x86-64, GCC/MinGW)

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-assembly.ps1
```

### 3.9 Laravel (opsional)

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/new-laravel.ps1
cd apps\laravel
php artisan serve
```

### 3.10 Cost Tracking DynamoDB (opsional)

Aplikasi berjalan normal tanpa AWS SDK (fitur cost tracking otomatis nonaktif).
Aktifkan hanya jika Anda punya tabel DynamoDB & kredensial AWS:

```powershell
cd "C:\laragon\www\aws billing qween 3 32b"
composer require aws/aws-sdk-php
# isi AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION,
# dan DYNAMODB_TABLE_NAME di .env
```

---

## 4. Perintah Validasi Cepat

```powershell
# PHP syntax semua file
Get-ChildItem -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch 'venv|vendor' } | ForEach-Object { php -l $_.FullName }

# Docker compose
docker compose config --quiet

# Helm
helm lint helm/ai-billing-hub

# Terraform
terraform -chdir=terraform validate

# Kubernetes (dry-run)
kubectl apply --dry-run=client -f kubernetes/deployment.yaml
```

Atau lewat Makefile:

```bash
make validate
```

---

## 5. Keamanan

1. **Jangan commit** `.env`, `terraform.tfvars`, `custom-values.yaml`, atau file berisi key.
2. Gunakan environment variable / Docker secret / SSM / K8s Secret.
3. Rotate key yang pernah bocor di manifest.
4. Untuk production, aktifkan HTTPS dan batasi CORS.

---

## 6. Struktur Folder

```
.
├── api.php                     # Entry point API (Groq/OpenRouter/Gemini/status)
├── index.php                   # Dashboard web (calculator + playground)
├── api/                        # Cost tracking, endpoints, metrics
├── middleware/                 # Budget enforcement & identity propagation
├── chat_*.py                   # CLI Python (Groq, OpenRouter, Bedrock)
├── apps/
│   ├── nextjs/                 # Frontend Next.js
│   ├── angular/                # Frontend Angular
│   └── laravel/                # (dibuat via script)
├── native/assembly/            # Contoh assembly x86-64
├── scripts/                    # Skrip bantu (check/run/build/upgrade)
├── docs/RUNBOOK.md             # Dokumen ini
├── terraform/                  # Infrastruktur AWS
├── helm/                       # Helm chart
├── kubernetes/                 # Manifest K8s
├── monitoring/                 # Prometheus & Grafana
└── docker-compose.yml / Dockerfile
```

---

## 7. Troubleshooting

| Gejala | Solusi |
|---|---|
| API 500 / "Class Redis not found" | Sudah diperbaiki; pastikan file terbaru dipakai |
| Chat "Invalid JSON response" | Cek key provider & koneksi internet |
| `docker compose` gagal | Jalankan Docker Desktop, lalu `docker compose config` |
| Helm lint error | Pastikan pakai chart versi terbaru ini |
| Terraform validate error | Jalankan `terraform init -backend=false` dulu |
| Port bentrok | Port `8080` dipakai WSL di mesin ini; aplikasi memakai `8081`. Cek dengan `Get-NetTCPConnection -LocalPort 8081` |
| Container memakai kode lama | Jalankan `docker compose up -d --build` (bukan hanya `up -d`) |
| Budget selalu allowed | Pastikan `BUDGET_ENFORCEMENT_ENABLED=true` dan Redis healthy |

---

## 8. Hasil Verifikasi Terakhir (mesin ini)

| Pemeriksaan | Hasil |
|---|---|
| `php -l` semua file PHP | ✅ Tidak ada error sintaks |
| Runtime API lokal (`php -S`) | ✅ `/`, `status`, `chat` (validasi JSON), `cost_history`, `budget_check`, `alerts`, `metrics.php` |
| `docker compose config` | ✅ Exit 0 |
| `docker compose up -d --build` | ✅ App healthy + Redis healthy di `http://localhost:8081` |
| Ekstensi container | ✅ `curl`, `redis` aktif |
| Budget enforcement (Redis) | ✅ `DAILY_BUDGET_EXCEEDED` saat spend > cap |
| `helm lint` | ✅ 0 failed |
| `helm template` | ✅ Exit 0 |
| `terraform init -backend=false` | ✅ Berhasil |
| `terraform validate` | ✅ Success |
| 7 file YAML | ✅ Semua parse |
| `kubectl apply --dry-run=client` | ✅ Deployment/Service/HPA valid |
| Next.js `npm run build` | ✅ Compiled successfully |
| Next.js dev + proxy `/api/php` | ✅ HTTP 200 |
| Angular `npm run build` | ✅ Bundle berhasil |
| Angular dev + proxy `/api/php` | ✅ HTTP 200 |
| Assembly `hello.exe` / `arith.exe` | ✅ Output benar |

Smoke test budget di container:

```powershell
docker cp scripts\budget-smoke.php qwen-ai-billing-hub:/var/www/html/scripts/budget-smoke.php
docker exec -e BUDGET_ENFORCEMENT_ENABLED=true -e REDIS_HOST=redis `
  qwen-ai-billing-hub php /var/www/html/scripts/budget-smoke.php
```
