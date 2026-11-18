#Requires -Version 5.1
<#
  Mengembalikan start-type semua service ke kondisi saat backup.
  Pemakaian:
    powershell -ExecutionPolicy Bypass -File .\rollback.ps1
    powershell -ExecutionPolicy Bypass -File .\rollback.ps1 -BackupFile "C:\path\services-backup-....json"
#>
[CmdletBinding()]
param([string]$BackupFile)

$ErrorActionPreference = 'Continue'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path

function Write-Log {
    param([string]$Message, [string]$Color = 'Gray')
    Write-Host ("[{0}] {1}" -f (Get-Date -Format 'HH:mm:ss'), $Message) -ForegroundColor $Color
}

$id = [Security.Principal.WindowsIdentity]::GetCurrent()
if (-not ([Security.Principal.WindowsPrincipal]$id).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Log "GAGAL: harus dijalankan sebagai Administrator." 'Red'
    exit 1
}

if (-not $BackupFile) {
    $latest = Get-ChildItem -LiteralPath $here -Filter 'services-backup-*.json' |
              Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $latest) { Write-Log "Tidak ada file backup di $here" 'Red'; exit 1 }
    $BackupFile = $latest.FullName
}

Write-Log "Memakai backup: $BackupFile" 'Cyan'
$data = Get-Content -LiteralPath $BackupFile -Raw | ConvertFrom-Json
$svcRoot = 'HKLM:\SYSTEM\CurrentControlSet\Services'
$ok = 0; $fail = 0

foreach ($item in $data) {
    $regPath = Join-Path $svcRoot $item.Name
    if (-not (Test-Path -LiteralPath $regPath)) { continue }

    $mode = switch ([int]$item.Start) {
        2 { 'auto' }
        3 { 'demand' }
        4 { 'disabled' }
        default { $null }
    }
    if ($null -eq $mode) { continue }

    & sc.exe config $item.Name start= $mode 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { $fail++; Write-Log ("FAIL  {0}" -f $item.Name) 'Red'; continue }

    if ($null -ne $item.DelayedAutostart) {
        Set-ItemProperty -LiteralPath $regPath -Name 'DelayedAutostart' -Value ([int]$item.DelayedAutostart) -Type DWord -ErrorAction SilentlyContinue
    }

    if ($item.Status -eq 'Running') {
        Start-Service -Name $item.Name -ErrorAction SilentlyContinue
    }
    $ok++
}

Write-Log ("ROLLBACK SELESAI. dipulihkan={0} gagal={1}" -f $ok, $fail) 'Yellow'
Write-Log "Restart laptop supaya semua service benar-benar kembali ke kondisi awal." 'Yellow'
Write-Host ""
Read-Host "Tekan Enter untuk menutup" | Out-Null
