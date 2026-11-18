# Contoh Assembly x86-64 (GAS / MinGW)

Contoh program assembly 64-bit untuk Windows, dirakit dengan GCC (MinGW-w64).
Menggunakan sintaks **GAS** (AT&T) dan simbol `main` agar bisa di-link oleh GCC.

## Build

```powershell
pwsh -File ..\..\scripts\build-assembly.ps1
```

Atau manual:

```powershell
gcc -O2 -Wall hello.s -o hello.exe
gcc -O2 -Wall arith.s -o arith.exe
```

## Menjalankan

```powershell
.\hello.exe
.\arith.exe
```

## Catatan ABI Windows x64

- Argumen pertama di `RCX`, kedua `RDX`, ketiga `R8`, keempat `R9`.
- Sebelum `call`, `RSP` harus 16-byte aligned dan tersedia **32 byte shadow space**.
- Pada `main`, setelah entry `RSP % 16 == 8`, sehingga `subq $40, %rsp` membuatnya aligned.

## File

| File | Isi |
|---|---|
| `hello.s` | Mencetak teks memakai `printf` |
| `arith.s` | Menghitung jumlah 1..10 lalu mencetaknya |
| `Makefile` | Build via `make` (jika tersedia) |
