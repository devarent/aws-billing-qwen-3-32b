@echo off
setlocal
set "PS1=%~dp0backup-and-apply.ps1"
if "%~1"=="" goto :run
set "EXTRA=%~1"
:run
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Start-Process -FilePath powershell -Verb RunAs -ArgumentList @('-NoProfile','-ExecutionPolicy','Bypass','-File','\"%PS1%\"','%EXTRA%')"
echo.
echo Jendela UAC akan muncul. Klik "Yes" untuk melanjutkan.
echo Kalau tidak muncul, klik kanan file ini lalu pilih "Run as administrator".
timeout /t 5 >nul
