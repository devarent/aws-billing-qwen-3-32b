# Panduan Praktikum 3 — Example 5-15 & 5-19 (NIM Ganjil)

Panduan ini dipakai bersama file **Praktik3_NAMA_NPM_Ganjil.docx / .pdf**.
Isi dokumen sudah lengkap; yang perlu kamu lakukan hanya **menjalankan DEBUG di DOSBox,
mengambil 4 screenshot, lalu menempelkannya ke dokumen**.

---

## 1. Persiapan

| Kebutuhan | Keterangan |
|---|---|
| DOSBox | Sudah terpasang di `C:\Program Files (x86)\DOSBox-0.74-3` |
| DEBUG.EXE | Biasanya sudah tersedia di drive `Z:` DOSBox (ketik `debug` di prompt) |
| Dokumen | `Praktik3_NAMA_NPM_Ganjil.docx` (untuk diedit) dan `.pdf` (hasil akhir) |

Buka **DOSBox** dari Start Menu. Akan muncul layar hitam dengan prompt seperti:

```
Z:\>
```

> Kalau `debug` tidak dikenali, salin `DEBUG.EXE` (MS-DOS 6.22) ke `C:\DEBUG`,
> lalu di DOSBox ketik: `mount c C:\DEBUG` → `c:` → `debug`.

---

## 2. Example 5-15 — Pembagian bilangan bertanda 16-bit

### 2.1 Ketik program

Di prompt DOSBox:

```
debug
```

lalu ketik (huruf besar/kecil sama saja, **angka selalu heksadesimal**):

```
-a
0B1F:0100 mov ax,ff9c
0B1F:0103 mov cx,9
0B1F:0106 cwd
0B1F:0107 idiv cx
0B1F:0109
```

> **Penting:** jangan mengetik `mov ax,-100`. DEBUG membaca semua angka sebagai
> heksadesimal, sehingga `-100` berarti `-100h` (= -256) dan hasilnya jadi FF00h.
> `FF9C` adalah bentuk heksadesimal dari **-100 desimal**.

Alamat (`0B1F:xxxx`) muncul otomatis dan bisa berbeda di komputermu — tidak masalah,
yang penting urutannya. Tekan **Enter pada baris kosong** untuk menutup mode assemble.

### 2.2 Tampilkan listing (Screenshot 1)

```
-u 100 108
```

Hasil yang diharapkan:

```
0B1F:0100 B8FF9C        MOV     AX,FF9C
0B1F:0103 B90900        MOV     CX,0009
0B1F:0106 99            CWD
0B1F:0107 F7F9          IDIV    CX
0B1F:0109
```

📸 **Screenshot 1** — seluruh hasil `u` + baris perintahnya.

### 2.3 Lihat register awal

```
-r
```

Akan tampil `AX=0000 BX=0000 CX=0000 DX=0000 ... IP=0100`.

### 2.4 Jalankan per instruksi (Screenshot 2)

Tekan `t` lalu Enter, **empat kali**. Setiap kali, DEBUG menampilkan instruksi yang
baru dijalankan beserta register setelahnya:

| Setelah `t` ke- | Instruksi | AX | DX | IP |
|---|---|---|---|---|
| 1 | MOV AX,FF9C | FF9C | 0000 | 0103 |
| 2 | MOV CX,0009 | FF9C | 0000 | 0106 |
| 3 | CWD | FF9C | **FFFF** | 0107 |
| 4 | IDIV CX | **FFF5** | **FFFF** | 0109 |

📸 **Screenshot 2** — layar yang menunjukkan `AX=FFF5 DX=FFFF` (boleh sekalian
setelah mengetik `r`).

### 2.5 Selesai

```
q
```

untuk keluar dari DEBUG (kembali ke prompt `Z:\>`).

---

## 3. Example 5-19 — Pengurangan BCD dengan DAS

### 3.1 Ketik program

```
debug
-a
0B1F:0100 mov dx,1234
0B1F:0103 mov bx,3099
0B1F:0106 mov al,bl
0B1F:0108 sub al,dl
0B1F:010A das
0B1F:010B mov cl,al
0B1F:010D mov al,bh
0B1F:010F sbb al,dh
0B1F:0111 das
0B1F:0112 mov ch,al
0B1F:0114
```

Di sini angka `1234` dan `3099` **memang** bilangan BCD, jadi tidak perlu diubah —
`1234h` sama dengan BCD 1234.

### 3.2 Tampilkan listing (Screenshot 3)

```
-u 100 113
```

Hasil yang diharapkan:

```
0B1F:0100 BA1234        MOV     DX,1234
0B1F:0103 BB3099        MOV     BX,3099
0B1F:0106 8AC3          MOV     AL,BL
0B1F:0108 2AC2          SUB     AL,DL
0B1F:010A 2F            DAS
0B1F:010B 8AC8          MOV     CL,AL
0B1F:010D 8AC7          MOV     AL,BH
0B1F:010F 1AC6          SBB     AL,DH
0B1F:0111 2F            DAS
0B1F:0112 8AE8          MOV     CH,AL
```

📸 **Screenshot 3** — seluruh hasil `u`.

### 3.3 Jalankan dan periksa hasil (Screenshot 4)

```
-r
g =100,114
-r
```

Setelah `g`, program berhenti di alamat `0114`. Hasil yang diharapkan pada `-r`:

```
AX=0018  BX=3099  CX=1865  DX=1234  ...
```

📸 **Screenshot 4** — baris register dengan **CX=1865**.

> Ingin lebih detail? Jalankan bertahap dengan `t` (10 kali) dan catat AX, CF, AF
> setiap langkah — tabelnya sudah ada di Bab 3.4 dokumen.

Terakhir ketik `q`.

---

## 4. Checklist screenshot

| No | Isi screenshot | Ditempel di |
|---|---|---|
| 1 | Hasil `u` Example 5-15 | Gambar 2.1 |
| 2 | Register `AX=FFF5 DX=FFFF` | Gambar 2.2 |
| 3 | Hasil `u` Example 5-19 | Gambar 3.1 |
| 4 | Register `CX=1865` | Gambar 3.2 |

**Cara mengambil screenshot**

1. Perbesar jendela DOSBox: `Alt + Enter` (fullscreen). Tekan lagi untuk kembali.
2. Tekan `Win + Shift + S` → seret kotak ke area layar DEBUG → gambar tersalin.
3. Buka Word → klik kotak putus-putus di dokumen → `Ctrl + V`.
4. Klik gambar → *Picture Format* → atur lebar ± 14 cm agar rapi.
5. Hapus tulisan *«Sisipkan screenshot …»* di dalam kotak.

Alternatif: di DOSBox tekan `Ctrl + F5`. File PNG tersimpan di
`C:\Users\<nama>\AppData\Local\DOSBox\capture\`.

---

## 5. Merapikan dokumen dan membuat PDF

1. Buka `Praktik3_NAMA_NPM_Ganjil.docx`.
2. Ganti `[Nama Lengkap]`, `[NPM]`, dan `[Kelas]` di halaman cover.
3. Tempel 4 screenshot sesuai checklist di atas.
4. **File → Save As → PDF** dengan nama:
   `Praktik3_NamaKamu_NIM_Ganjil.pdf`
   (contoh: `Praktik3_Budi_2023010123_Ganjil.pdf`)
5. Unggah PDF tersebut ke halaman tugas.

> File `Praktik3_NAMA_NPM_Ganjil.pdf` yang disertakan sudah berisi seluruh materi
> dan penjelasan, tetapi kotak screenshot-nya masih kosong. Itu bisa langsung
> dipakai sebagai cadangan bila Word bermasalah.

---

## 6. Jika ada masalah

| Gejala | Penyebab & solusi |
|---|---|
| `Bad command or file name` saat ketik `debug` | DEBUG tidak ada di `Z:`. Salin `DEBUG.EXE` ke `C:\DEBUG`, lalu `mount c C:\DEBUG`, `c:`, `debug` |
| Hasil `u` menunjukkan `MOV AX,FF00` | Kamu mengetik `-100`. Ulangi dengan `mov ax,ff9c` |
| `Divide overflow` saat `t`/`g` | DX:AX terlalu besar. Pastikan `cwd` sudah dijalankan sebelum `idiv cx` |
| `CX` bukan `1865` | Periksa urutan: `sub` untuk digit rendah, `sbb` untuk digit tinggi, dan `das` tepat setelah masing-masing |
| Huruf di DOSBox kecil | `Alt + Enter` untuk fullscreen, atau ubah `windowresolution` di `dosbox.conf` |
| Screenshot gelap/kosong | Ambil saat jendela DOSBox aktif (klik dulu), jangan saat fullscreen berpindah monitor |
