# Build & jalankan via Docker Compose
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "Dibuat .env dari .env.example. Isi API key terlebih dahulu!"
}

Write-Host "Membangun dan menjalankan container..."
docker compose up -d --build

Write-Host ""
Write-Host "Status container:"
docker compose ps
Write-Host ""
Write-Host "Buka dashboard : http://localhost:8081"
Write-Host "AWS Billing    : http://localhost:8081/billing/"
Write-Host "phpMyAdmin     : http://localhost:8082"
