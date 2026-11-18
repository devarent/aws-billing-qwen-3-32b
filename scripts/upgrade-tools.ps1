# PERINGATAN: upgrade global bisa memecahkan kompatibilitas proyek.
# Gunakan hanya jika Anda siap menyesuaikan versi.
$ErrorActionPreference = "Continue"

Write-Host "=== Upgrade semua package via winget ==="
Write-Host "Catatan: tool di mesin ini sudah versi terbaru (PHP 8.5, Python 3.15, Node 26, dll)."
Write-Host ""

winget upgrade --all --accept-package-agreements --accept-source-agreements

Write-Host ""
Write-Host "Selesai. Cek ulang dengan: powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check-tools.ps1"
