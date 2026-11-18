# Build & jalankan contoh assembly x86-64
$root = Split-Path -Parent $PSScriptRoot
Set-Location (Join-Path $root "native\assembly")

Write-Host "Membangun hello.exe dan arith.exe ..."
gcc -O2 -Wall hello.s -o hello.exe
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

gcc -O2 -Wall arith.s -o arith.exe
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host ""
Write-Host "=== hello.exe ==="
.\hello.exe

Write-Host ""
Write-Host "=== arith.exe ==="
.\arith.exe
