# Jalankan dashboard PHP di localhost:8081
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host "Menjalankan PHP built-in server di http://localhost:8081 ..."
Write-Host "Tekan Ctrl+C untuk berhenti."
php -S localhost:8081
