#!/bin/bash
# Script untuk update .env file dengan API keys yang benar

# Navigasi ke project directory
cd "/mnt/c/laragon/www/aws billing qween 3 32b"

# Update .env file dengan API keys yang benar
cat > .env << 'EOF'
# ========================================================
# Konfigurasi Kredensial Multi-Provider
# ========================================================

# Groq Configuration
GROQ_API_KEY=gsk_kATSizhTfy78Hvy9StWGdyb3FYwLDZI3fFRaSLVpq4RsyI82le
GROQ_MODEL=qwen/qwen-2.5-coder-32b-instruct

# OpenRouter Configuration
OPENROUTER_API_KEY=sk-or-v1-f61458d2c5174573207984c2ffcb38276e85

# Google Cloud / Gemini Configuration
GEMINI_API_KEY=your_gemini_api_key_here

# AWS Configuration
AWS_ACCESS_KEY_ID=AKIA_YOUR_AWS_ACCESS_KEY
AWS_SECRET_ACCESS_KEY=YOUR_AWS_SECRET_KEY
AWS_DEFAULT_REGION=us-east-1
BEDROCK_MODEL_ID=qwen.qwen3-next-80b-a3b

# Google Cloud Project Configuration
GCP_PROJECT_ID=your-gcp-project-id

# Runtime Configuration
TZ=Asia/Jakarta
DEBUG=false
EOF

echo "✓ .env file updated with API keys"
echo "✓ Silakan update AWS credentials jika diperlukan"
