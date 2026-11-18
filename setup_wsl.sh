#!/bin/bash
# Setup script untuk WSL Ubuntu - AI Billing Hub

echo "=== AI Billing Hub - WSL Setup Script ==="
echo ""

# Navigasi ke project directory
cd "/mnt/c/laragon/www/aws billing qween 3 32b"

# Install python3-venv jika belum ada
echo "✓ Memastikan python3-venv terinstall..."
sudo apt update
sudo apt install -y python3-venv python3-pip

# Check jika .env sudah ada
if [ ! -f .env ]; then
    echo "✓ Membuat .env dari template..."
    cp .env.example .env
    echo "  → Silakan edit .env dan isi API keys Anda"
    echo "  → Contoh: nano .env"
else
    echo "✓ File .env sudah ada"
fi

# Hapus venv yang corrupt jika ada
if [ -d "venv" ] && [ ! -f "venv/bin/activate" ]; then
    echo "✓ Menghapus venv yang corrupt..."
    rm -rf venv
fi

# Create virtual environment
if [ ! -d "venv" ]; then
    echo ""
    echo "✓ Membuat Python virtual environment..."
    python3 -m venv venv
    echo "  → Virtual environment dibuat di ./venv/"
else
    echo "✓ Virtual environment sudah ada"
fi

# Activate virtual environment
echo ""
echo "✓ Mengaktifkan virtual environment..."
source venv/bin/activate

# Install dependencies
echo ""
echo "✓ Menginstall Python dependencies..."
pip install --upgrade pip
pip install -r requirements.txt

echo ""
echo "=== Setup Selesai! ==="
echo ""
echo "Untuk menjalankan script Python CLI:"
echo "  source venv/bin/activate"
echo "  python3 chat_groq.py"
echo "  python3 chat_openrouter.py"
echo "  python3 chat_bedrock.py"
echo ""
echo "Untuk menjalankan dengan Docker:"
echo "  docker compose up -d --build"
echo "  # Akses di browser: http://localhost:8080"
echo ""
