#Requires -Version 5.1
<#
  Battery / idle tuning untuk Windows 11 - service yang tidak penting.
  Semua perubahan DICATAT dulu ke services-backup-<waktu>.json
  Rollback: jalankan rollback.ps1 (as Administrator).

  Pemakaian:
    powershell -ExecutionPolicy Bypass -File .\backup-and-apply.ps1 -DryRun
    powershell -ExecutionPolicy Bypass -File .\backup-and-apply.ps1
    powershell -ExecutionPolicy Bypass -File .\backup-and-apply.ps1 -ApplyOptional
#>
[CmdletBinding()]
param(
    [switch]$DryRun,
    [switch]$ApplyOptional
)

$ErrorActionPreference = 'Continue'
$here       = Split-Path -Parent $MyInvocation.MyCommand.Path
$stamp      = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupFile = Join-Path $here "services-backup-$stamp.json"
$logFile    = Join-Path $here "apply-$stamp.log"

function Write-Log {
    param([string]$Message, [string]$Color = 'Gray')
    $line = "[{0}] {1}" -f (Get-Date -Format 'HH:mm:ss'), $Message
    Write-Host $line -ForegroundColor $Color
    try { Add-Content -LiteralPath $logFile -Value $line -Encoding UTF8 } catch { }
}

function Test-Admin {
    $id = [Security.Principal.WindowsIdentity]::GetCurrent()
    return ([Security.Principal.WindowsPrincipal]$id).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

Write-Host ""
Write-Host "=== Battery tune: service cleanup ===" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Admin) -and -not $DryRun) {
    Write-Host "GAGAL: skrip ini harus dijalankan sebagai Administrator." -ForegroundColor Red
    Write-Host "Klik kanan file run-as-admin.cmd lalu pilih 'Run as administrator'." -ForegroundColor Yellow
    exit 1
}
if (-not (Test-Admin)) { Write-Host "Mode DryRun (tanpa Administrator) - hanya simulasi." -ForegroundColor Yellow }

# ------------------------------------------------------------------
# 1. BACKUP seluruh start-type service (registry Start + DelayedAutostart)
# ------------------------------------------------------------------
$svcRoot = 'HKLM:\SYSTEM\CurrentControlSet\Services'
$backup = @()
foreach ($key in (Get-ChildItem -LiteralPath $svcRoot -ErrorAction SilentlyContinue)) {
    $props = Get-ItemProperty -LiteralPath $key.PSPath -ErrorAction SilentlyContinue
    if ($null -eq $props -or $null -eq $props.Start) { continue }
    $svc = Get-Service -Name $key.PSChildName -ErrorAction SilentlyContinue
    $backup += [pscustomobject]@{
        Name             = $key.PSChildName
        Start            = [int]$props.Start
        DelayedAutostart = $(if ($null -ne $props.DelayedAutostart) { [int]$props.DelayedAutostart } else { $null })
        Status           = $(if ($svc) { [string]$svc.Status } else { 'Unknown' })
    }
}

if ($DryRun) {
    Write-Log ("DRY RUN - backup tidak ditulis, tidak ada perubahan.") 'Cyan'
} else {
    $backup | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath $backupFile -Encoding UTF8
    Write-Log ("BACKUP OK -> {0} ({1} service)" -f $backupFile, $backup.Count) 'Green'
}

# ------------------------------------------------------------------
# 2. Daftar target (aman: updater, telemetry ringan, fitur opsional)
# ------------------------------------------------------------------
$targets = @(
    # --- Auto -> Manual -------------------------------------------------
    @{ Name = 'BITS';                                   Mode = 'Manual'; Stop = $true; Note = 'Windows Update/Store akan menyalakannya saat perlu' }
    @{ Name = 'DoSvc';                                  Mode = 'Manual'; Stop = $true; Note = 'Delivery Optimization (P2P update)' }
    @{ Name = 'WSAIFabricSvc';                           Mode = 'Manual'; Stop = $true; Note = 'Windows AI Components (on-demand)' }
    @{ Name = 'InventorySvc';                            Mode = 'Manual'; Stop = $true; Note = 'Inventory & Compatibility Appraisal' }
    @{ Name = 'wuqisvc';                                 Mode = 'Manual'; Stop = $true; Note = 'Usage & Quality Insights' }
    @{ Name = 'CloudBackupRestoreSvc_b9ffa';             Mode = 'Manual'; Stop = $true; Note = 'Windows Backup (cloud)' }
    @{ Name = 'PimIndexMaintenanceSvc_b9ffa';            Mode = 'Manual'; Stop = $true; Note = 'Index kontak (People)' }
    @{ Name = 'edgeupdate';                              Mode = 'Manual'; Stop = $true; Note = 'Microsoft Edge updater' }
    @{ Name = 'ClickToRunSvc';                           Mode = 'Manual'; Stop = $true; Note = 'Office Click-to-Run updater' }
    @{ Name = 'GoogleUpdaterService157.0.8089.0';        Mode = 'Manual'; Stop = $true; Note = 'Google updater' }
    @{ Name = 'GoogleUpdaterInternalService157.0.8089.0';Mode = 'Manual'; Stop = $true; Note = 'Google updater (internal)' }
    @{ Name = 'EgoUpdaterService0.6.2.5';                Mode = 'Manual'; Stop = $true; Note = 'ego browser updater' }
    @{ Name = 'EgoUpdaterInternalService0.6.2.5';        Mode = 'Manual'; Stop = $true; Note = 'ego updater (internal)' }
    @{ Name = 'EgoUpdaterInternalService0.6.2.7';        Mode = 'Manual'; Stop = $true; Note = 'ego updater (internal) lama' }
    @{ Name = 'logi_lamparray_service';                  Mode = 'Manual'; Stop = $true; Note = 'Logitech RGB lamp array' }
    @{ Name = 'Intel(R) Platform License Manager Service'; Mode = 'Manual'; Stop = $false; Note = 'Intel platform licensing' }

    # --- hanya dihentikan (start-type sudah Manual) ---------------------
    @{ Name = 'NPSMSvc_b9ffa';                           Mode = 'Manual'; Stop = $true; Note = 'Now Playing Session Manager' }
    @{ Name = 'DevicesFlowUserSvc_b9ffa';                Mode = 'Manual'; Stop = $true; Note = 'Devices Flow (Add device UI)' }

    # --- Disabled -------------------------------------------------------
    @{ Name = 'WebClient';                               Mode = 'Disabled'; Stop = $true; Note = 'WebDAV client (jarang dipakai)' }
    @{ Name = 'SharedAccess';                            Mode = 'Disabled'; Stop = $true; Note = 'Internet Connection Sharing (bukan hotspot)' }
)

$optional = @(
    @{ Name = 'com.docker.service';  Mode = 'Manual'; Stop = $true; Note = 'Docker Desktop (start manual saat dipakai)' }
    @{ Name = 'WSLService';          Mode = 'Manual'; Stop = $true; Note = 'WSL (start saat `wsl` dipanggil)' }
    @{ Name = 'sshd';                Mode = 'Disabled'; Stop = $true; Note = 'OpenSSH server (matikan jika tidak perlu remote)' }
    @{ Name = 'SshdBroker';          Mode = 'Manual'; Stop = $true; Note = 'OpenSSH broker' }
    @{ Name = 'LanmanServer';        Mode = 'Manual'; Stop = $true; Note = 'File sharing (matikan jika tidak share folder)' }
    @{ Name = 'CDPSvc';              Mode = 'Manual'; Stop = $true; Note = 'Connected Devices Platform (Phone Link)' }
    @{ Name = 'CDPUserSvc_b9ffa';    Mode = 'Manual'; Stop = $true; Note = 'Connected Devices Platform (user)' }
    @{ Name = 'whesvc';              Mode = 'Manual'; Stop = $true; Note = 'Windows Health & Optimized Experiences' }
)

if ($ApplyOptional) { $targets += $optional }

# ------------------------------------------------------------------
# 3. Terapkan
# ------------------------------------------------------------------
$okCount = 0; $failCount = 0

foreach ($t in $targets) {
    $svc = Get-Service -Name $t.Name -ErrorAction SilentlyContinue
    if (-not $svc) {
        Write-Log ("SKIP  {0} (tidak ada di sistem ini)" -f $t.Name) 'DarkGray'
        continue
    }
    if ($DryRun) {
        Write-Log ("PLAN  {0,-44} {1,-8} {2}" -f $t.Name, $t.Mode, $t.Note) 'Cyan'
        continue
    }

    $startArg = switch ($t.Mode) {
        'Manual'   { 'demand' }
        'Disabled' { 'disabled' }
        default    { 'auto' }
    }

    if ($t.Stop -and $svc.Status -eq 'Running') {
        & sc.exe stop $t.Name 2>&1 | Out-Null
        Start-Sleep -Milliseconds 300
    }

    & sc.exe config $t.Name start= $startArg 2>&1 | Out-Null
    $code = $LASTEXITCODE

    $now = Get-Service -Name $t.Name -ErrorAction SilentlyContinue
    if ($code -eq 0) {
        $okCount++
        Write-Log ("OK    {0,-44} -> {1,-8} [{2}]  {3}" -f $t.Name, $t.Mode, $now.Status, $t.Note) 'Green'
    } else {
        $failCount++
        Write-Log ("FAIL  {0,-44} (sc exit {1})" -f $t.Name, $code) 'Red'
    }
}

Write-Host ""
if ($DryRun) {
    Write-Log "Dry run selesai. Tidak ada yang diubah." 'Cyan'
} else {
    Write-Log ("SELESAI. berhasil={0} gagal={1}" -f $okCount, $failCount) 'Yellow'
    Write-Log ("Rollback: powershell -ExecutionPolicy Bypass -File .\rollback.ps1 -BackupFile `"{0}`"" -f $backupFile) 'Yellow'
}
Write-Host ""
if (-not $DryRun) { Read-Host "Tekan Enter untuk menutup" | Out-Null }
