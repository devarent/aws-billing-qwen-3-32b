# Buat project Laravel baru di apps/laravel (butuh internet)
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (-not (Test-Path "apps")) {
    New-Item -ItemType Directory "apps" | Out-Null
}

if (Test-Path "apps\laravel") {
    Write-Host "Folder apps\laravel sudah ada. Hapus dulu jika ingin membuat ulang."
    exit 1
}

Set-Location "apps"
Write-Host "Membuat project Laravel baru..."
laravel new laravel --no-interaction
Write-Host ""
Write-Host "Selesai. Jalankan:"
Write-Host "  cd apps\laravel"
Write-Host "  php artisan serve"
