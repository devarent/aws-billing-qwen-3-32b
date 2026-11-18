# Battery Tune — Windows 11 (MSI Cyborg 15 A12VE)

Tanggal: 2026-11-03 17:21
Tujuan: mematikan service yang tidak perlu supaya idle load & baterai lebih hemat.

## Hasil eksekusi

| Status | Service | Perubahan |
|---|---|---|
| OK | WSAIFabricSvc | Auto → Manual |
| OK | InventorySvc | Auto → Manual |
| OK | wuqisvc | Auto → Manual |
| OK | CloudBackupRestoreSvc_b9ffa | Auto → Manual |
| OK | PimIndexMaintenanceSvc_b9ffa | Auto → Manual |
| OK | edgeupdate | Auto → Manual |
| OK | ClickToRunSvc | Auto → Manual |
| OK | GoogleUpdaterService157.0.8089.0 | Auto → Manual |
| OK | GoogleUpdaterInternalService157.0.8089.0 | Auto → Manual |
| OK | EgoUpdaterService0.6.2.5 | Auto → Manual |
| OK | EgoUpdaterInternalService0.6.2.5 | Auto → Manual |
| OK | EgoUpdaterInternalService0.6.2.7 | Auto → Manual |
| OK | logi_lamparray_service | Auto → Manual (RGB Logitech) |
| OK | Intel(R) Platform License Manager Service | Auto → Manual |
| OK | NPSMSvc_b9ffa | dihentikan |
| OK | DevicesFlowUserSvc_b9ffa | dihentikan |
| OK | WebClient | Manual → Disabled (WebDAV) |
| dikembalikan Windows | BITS | sempat Manual, Windows Update mengembalikan ke Auto |
| dikembalikan Windows | SharedAccess (ICS) | sempat Disabled, Docker/HNS mengembalikan ke Manual |
| dilindungi | DoSvc | sc.exe ditolak (access denied), tetap Auto tapi sudah Stopped |

Tidak ada error Critical/Error baru di System log setelah perubahan.
Service inti (audio, WiFi, DHCP, DNS, Defender, firewall, power, RPC, DWM) tetap Running.

## Yang sudah aman / tidak disentuh

- Defender, Firewall, Windows Update, audio Realtek/Intel, NVIDIA, Intel graphics & power (ipfsvc), WiFi, Bluetooth config, RPC/DCOM, Event Log, Task Scheduler.
- Kondisi awal memang sudah banyak di-tweak: SysMain, Windows Search, DiagTrack,
  Print Spooler, Xbox, Themes, Telemetry, Remote Registry sudah Disabled.

## File

- `backup-and-apply.ps1` — skrip utama (bikin backup dulu, lalu terapkan).
- `rollback.ps1` — kembalikan semua start-type ke kondisi backup.
- `run-as-admin.cmd` — launcher (memunculkan UAC).
- `services-backup-*.json` — snapshot 763 entry service+driver sebelum perubahan.
- `apply-*.log` — log hasil eksekusi.

## Cara rollback

```
powershell -ExecutionPolicy Bypass -File .\rollback.ps1
```
lalu restart laptop.

## Kandidat lanjutan (belum dijalankan, perlu keputusan)

| Service | Efek kalau dimatikan |
|---|---|
| com.docker.service | Docker Desktop tidak auto-start (RAM/WSL/vmcompute ikut berhenti) |
| WSLService | `wsl` start on-demand |
| sshd + SshdBroker | **port 22 saat ini terbuka di 0.0.0.0** — risiko keamanan |
| LanmanServer | file sharing / folder share tidak jalan |
| CDPSvc / CDPUserSvc | Phone Link / "Continue on PC" mati |
| whesvc | Windows Health & Optimized Experiences |

Jalankan dengan `run-as-admin.cmd -ApplyOptional` bila setuju.
