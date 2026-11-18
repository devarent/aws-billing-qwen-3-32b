#!/usr/bin/env bash
# =====================================================================
# Setup AWS Billing Manager di WSL 2 (Ubuntu / Debian / Amazon Linux)
#
# Jalankan dari root proyek:
#   bash scripts/wsl-billing-setup.sh
#
# Script ini hanya memasang dependency PHP dan menyiapkan .env.
# Database bisa memakai Docker (disarankan) atau MySQL/MariaDB native.
# =====================================================================
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

echo "== AWS Billing Manager · WSL 2 setup =="
echo "Proyek : $PROJECT_DIR"
echo

if command -v apt-get >/dev/null 2>&1; then
    PKG="apt"
elif command -v dnf >/dev/null 2>&1; then
    PKG="dnf"
elif command -v yum >/dev/null 2>&1; then
    PKG="yum"
else
    echo "Distro belum dikenali (tidak ada apt/dnf/yum)."
    echo "Pasang manual: php-cli php-curl php-mysql php-mbstring php-xml"
    exit 1
fi

echo "Distro package manager: $PKG"
echo

if [ "$PKG" = "apt" ]; then
    sudo apt-get update
    sudo apt-get install -y php-cli php-curl php-mysql php-mbstring php-xml ca-certificates curl
elif [ "$PKG" = "dnf" ]; then
    sudo dnf install -y php-cli php-curl php-mysqlnd php-mbstring php-xml ca-certificates curl
else
    sudo yum install -y php-cli php-curl php-mysql php-mbstring php-xml ca-certificates curl
fi

echo
echo "PHP: $(php -v | head -n 1)"

if [ ! -f .env ]; then
    cp .env.example .env
    echo "Dibuat .env dari .env.example — isi AWS_ACCESS_KEY_ID dan AWS_SECRET_ACCESS_KEY."
else
    echo ".env sudah ada (tidak ditimpa)."
fi

if grep -qE '^AWS_ACCESS_KEY_ID=(|AKIA_YOUR_AWS_ACCESS_KEY)$' .env 2>/dev/null; then
    echo
    echo "PERHATIAN: AWS_ACCESS_KEY_ID di .env masih kosong/placeholder."
    echo "Edit dengan: nano .env"
fi

echo
echo "Pilih cara menjalankan:"
echo "  1) Docker (disarankan)"
echo "       docker compose up -d --build"
echo "       http://localhost:8081/billing/"
echo
echo "  2) PHP native (butuh MySQL/MariaDB lokal)"
echo "       php -S 0.0.0.0:8000 -t billing"
echo "       http://localhost:8000/index.php?page=login"
echo
echo "Setelah server hidup:"
echo "  Login admin@qween.local / qween123"
echo "  Buka Pengaturan → Koneksi AWS → Tes Koneksi AWS"
echo
echo "Panduan lengkap: docs/AWS_BILLING_SETUP.md"
