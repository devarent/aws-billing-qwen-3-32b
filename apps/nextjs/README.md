# Next.js Frontend

Frontend opsional untuk AI Billing & Cloud Hub.

## Jalankan

```powershell
cd apps\nextjs
npm install
npm run dev
```

Buka <http://localhost:3000>.

API PHP diproksi lewat `/api/php/...` ke `http://localhost:8081/api.php`
(lihat `next.config.mjs`). Pastikan server PHP berjalan lebih dulu:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ..\..\scripts\run-local.ps1
```
