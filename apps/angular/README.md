# Angular Frontend

Frontend opsional untuk AI Billing & Cloud Hub.

## Jalankan

```powershell
cd apps\angular
npm install
npm start
```

Buka <http://localhost:4200>.

API PHP diproksi lewat `/api/php/...` ke `http://localhost:8081/api.php`
(lihat `proxy.conf.json`). Pastikan server PHP berjalan lebih dulu:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ..\..\scripts\run-local.ps1
```

## Build produksi

```powershell
npm run build
```

Hasil ada di `dist/ai-billing-angular/`.
