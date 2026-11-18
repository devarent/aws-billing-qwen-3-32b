# -*- coding: utf-8 -*-
"""Isi laporan Praktikum 3 (Example 5-15 & 5-19) sebagai daftar blok.

Setiap blok: (jenis, isi, opsi) dengan jenis:
  h1 / h2 / p / li / num / code / table / caption / shot / pagebreak
"""

NAMA = "[Nama Lengkap]"
NPM = "[NPM]"
KELAS = "[Kelas]"
TANGGAL = "3 November 2026"

COVER = {
    "judul": "TUGAS PRAKTIKUM 3",
    "subjudul": "INSTRUKSI ARITMATIKA DAN LOGIKA\n(LANJUTAN)",
    "sub": "Example 5-15 dan Example 5-19 - Barry B. Brey, The Intel Microprocessors",
    "identitas": [("Nama", NAMA), ("NPM", NPM), ("Kelas", KELAS)],
    "meta": "Mata Kuliah: Mikroprosesor\nTanggal Penyelesaian: " + TANGGAL,
    "prodi": "PROGRAM STUDI TEKNIK INFORMATIKA",
    "tahun": "2026",
}

BLOCKS = [
    ("h1", "BAB 1  PENDAHULUAN"),
    ("h2", "1.1  Latar Belakang"),
    ("p", "Instruksi aritmatika dan logika merupakan kelompok instruksi yang mengolah data di dalam "
          "mikroprosesor. Pada praktikum ini dibahas dua kelompok instruksi lanjutan, yaitu instruksi "
          "pembagian bilangan bertanda (signed division) dan instruksi penyesuaian (adjustment) untuk "
          "aritmatika BCD (Binary Coded Decimal). Keduanya dipraktikkan langsung menggunakan program "
          "DEBUG.EXE di dalam DOSBox, sehingga mahasiswa dapat melihat isi register dan flag sebelum "
          "dan sesudah setiap instruksi dijalankan.", {"indent": 1.0, "just": True}),
    ("p", "Sesuai ketentuan tugas, mahasiswa dengan NIM ganjil mengerjakan Example 5-15 dan Example 5-19 "
          "dari buku Barry B. Brey, The Intel Microprocessors, Chapter 5 (Arithmetic and Logic Instructions).",
          {"indent": 1.0, "just": True}),

    ("h2", "1.2  Tujuan Praktikum"),
    ("num", "Memahami cara kerja instruksi pembagian bilangan bertanda 16-bit (CWD dan IDIV) serta "
            "pengaruhnya terhadap register AX dan DX."),
    ("num", "Memahami cara kerja instruksi penyesuaian BCD setelah pengurangan (DAS) pada operasi BCD "
            "empat digit."),
    ("num", "Mampu menuliskan, menjalankan, dan memeriksa program 8086 menggunakan DEBUG.EXE pada DOSBox, "
            "serta membaca hasil unassemble (u), register (r), dan trace (t)."),

    ("h2", "1.3  Alat dan Bahan"),
    ("li", "DOSBox 0.74-3 (emulator lingkungan DOS 16-bit)."),
    ("li", "DEBUG.EXE, program bawaan DOS untuk menulis (assemble), memeriksa (unassemble), dan "
           "menjalankan program .COM sederhana."),
    ("li", "PC/laptop dengan sistem operasi Windows."),
    ("li", "Buku referensi: Barry B. Brey, The Intel Microprocessors, 8th Edition, Chapter 5."),

    ("h2", "1.4  Dasar Teori"),
    ("p", "a.  Register 16-bit dan pasangan DX:AX", {"bold": True, "space": 4}),
    ("p", "Mikroprosesor 8086 memiliki register serbaguna 16-bit: AX (AH:AL), BX (BH:BL), CX (CH:CL), "
          "dan DX (DH:DL). Pada pembagian 16-bit, CPU membentuk dividend 32-bit dari pasangan register "
          "DX:AX, yaitu DX sebagai 16 bit atas (high word) dan AX sebagai 16 bit bawah (low word). Hasil "
          "bagi (quotient) muncul di AX, sedangkan sisa (remainder) muncul di DX.",
          {"indent": 1.0, "just": True}),

    ("p", "b.  Bilangan bertanda dan komplemen dua (two's complement)", {"bold": True, "space": 4}),
    ("p", "Bilangan negatif disimpan dalam bentuk komplemen dua. Contoh: -100 desimal = FF9Ch (karena "
          "100 = 0064h, dibalik menjadi FF9Bh, ditambah 1 menjadi FF9Ch), -11 = FFF5h, dan -1 = FFFFh. "
          "Bit paling kiri (bit tanda/MSB) bernilai 1 untuk bilangan negatif.",
          {"indent": 1.0, "just": True}),

    ("p", "c.  Instruksi CWD dan IDIV", {"bold": True, "space": 4}),
    ("li", "mengubah bilangan bertanda 16-bit di AX menjadi bilangan bertanda 32-bit di DX:AX. Jika AX "
           "bernilai negatif (MSB = 1), DX diisi FFFFh; jika positif, DX diisi 0000h. Instruksi ini wajib "
           "dijalankan sebelum IDIV agar tanda bilangan tidak hilang.", {"prefix": "CWD  "}),
    ("li", "membagi DX:AX (bertanda) dengan operand (bertanda). Hasil bagi di AX dan sisa di DX. Pembagian "
           "dibulatkan ke arah nol (truncate), dan sisa selalu bertanda sama dengan dividend. Jika hasil "
           "bagi tidak muat di AX, terjadi divide error (interrupt 0).", {"prefix": "IDIV  "}),
    ("li", "membagi DX:AX tanpa memperhatikan tanda (unsigned).", {"prefix": "DIV  "}),

    ("p", "d.  BCD (Binary Coded Decimal) dan instruksi DAS", {"bold": True, "space": 4}),
    ("p", "Pada BCD terpaket (packed BCD), satu byte menyimpan dua digit desimal: 4 bit tinggi untuk digit "
          "puluhan dan 4 bit rendah untuk digit satuan. Dengan demikian 1234 BCD ditulis 1234h (DH = 12h, "
          "DL = 34h) dan 3099 BCD ditulis 3099h (BH = 30h, BL = 99h).",
          {"indent": 1.0, "just": True}),
    ("p", "Operasi SUB pada bilangan BCD dapat menghasilkan nilai yang bukan BCD. Instruksi DAS (Decimal "
          "Adjust after Subtraction) memperbaikinya dengan aturan berikut:",
          {"indent": 1.0, "just": True}),
    ("code",
     "IF (AL AND 0Fh) > 9  OR  AF = 1  THEN\n"
     "        AL <- AL - 6\n"
     "        AF <- 1\n"
     "END IF\n"
     "IF AL > 9Fh  OR  CF = 1  THEN\n"
     "        AL <- AL - 60h\n"
     "        CF <- 1\n"
     "END IF"),
    ("p", "DAS hanya bekerja pada register AL dan harus diletakkan tepat setelah instruksi SUB/SBB. Karena "
          "itu pengurangan BCD 4 digit dilakukan per byte: digit rendah dahulu, lalu digit tinggi dengan "
          "memperhitungkan borrow melalui instruksi SBB.",
          {"indent": 1.0, "just": True}),

    ("p", "e.  Perintah dasar DEBUG", {"bold": True, "space": 4}),
    ("table", ["Perintah", "Fungsi"],
     [["A", "Assemble: menulis instruksi assembly ke memori mulai alamat tertentu."],
      ["U", "Unassemble: menampilkan isi memori sebagai instruksi (alamat + opcode + mnemonic)."],
      ["R", "Register: menampilkan/mengubah isi register dan flag."],
      ["T", "Trace: menjalankan satu instruksi, lalu menampilkan register."],
      ["P", "Proceed: menjalankan satu instruksi, melewati INT/call sebagai satu langkah."],
      ["G", "Go: menjalankan program sampai alamat tertentu atau selesai."],
      ["Q", "Quit: keluar dari DEBUG."]], {"widths": [2.5, 12.0]}),

    ("pagebreak",),

    # ------------------------------------------------------------ BAB 2
    ("h1", "BAB 2  EXAMPLE 5-15 - PEMBAGIAN BILANGAN BERTANDA 16-BIT"),
    ("h2", "2.1  Deskripsi Program"),
    ("p", "Example 5-15 menunjukkan pembagian dua bilangan bertanda 16-bit. Bilangan -100 dimuat ke AX dan "
          "+9 dimuat ke CX. Sebelum pembagian, instruksi CWD dipakai untuk menandatangani (sign-extend) isi "
          "AX ke dalam pasangan DX:AX, sehingga -100 direpresentasikan sebagai FFFF:FF9C. Setelah IDIV CX "
          "dijalankan, hasil bagi -11 berada di AX (FFF5h) dan sisa -1 berada di DX (FFFFh).",
          {"indent": 1.0, "just": True}),

    ("h2", "2.2  Listing Program"),
    ("p", "Listing berikut ditulis dengan format keluaran DEBUG (alamat, opcode, dan instruksi):"),
    ("code",
     "0B1F:0100  B8FF9C   MOV  AX,FF9C     ; muat -100 (FF9Ch) ke AX\n"
     "0B1F:0103  B90900   MOV  CX,+9       ; muat +9 ke CX sebagai pembagi\n"
     "0B1F:0106  99       CWD              ; sign-extend AX ke DX:AX\n"
     "0B1F:0107  F7F9     IDIV CX          ; AX = hasil bagi, DX = sisa"),
    ("caption", "Listing 2.1  Program Example 5-15 (pembagian bertanda 16-bit)"),
    ("p", "Catatan penting: DEBUG selalu membaca bilangan sebagai heksadesimal. Oleh karena itu -100 "
          "desimal tidak boleh diketik sebagai -100, karena DEBUG akan membacanya sebagai -100h (-256) "
          "dan menghasilkan FF00h. Nilai yang benar adalah bentuk heksadesimal dari -100 desimal, yaitu "
          "FF9Ch. Bentuk -64 juga sah dan menghasilkan kode mesin yang sama, sebab 64h = 100 desimal. "
          "Di buku Brey instruksi ini ditulis MOV AX,-100 karena assembler buku memakai basis desimal.",
     {"indent": 1.0, "just": True}),

    ("h2", "2.3  Langkah Praktik di DOSBox"),
    ("num", "Jalankan DOSBox, kemudian ketik debug pada prompt DOS."),
    ("num", "Ketik a (assemble) lalu masukkan instruksi berikut satu per satu. Tekan Enter pada baris "
            "kosong untuk mengakhiri mode assemble."),
    ("code",
     "-a\n"
     "0B1F:0100 mov ax,ff9c     ; -100 desimal = FF9Ch (DEBUG memakai heksadesimal)\n"
     "0B1F:0103 mov cx,9        ; pembagi +9\n"
     "0B1F:0106 cwd             ; sign-extend AX ke DX:AX\n"
     "0B1F:0107 idiv cx         ; AX = hasil bagi, DX = sisa\n"
     "0B1F:0109"),
    ("num", "Ketik u 100 108 untuk menampilkan listing program (bahan screenshot nomor 1)."),
    ("num", "Ketik r lalu Enter untuk melihat register awal (AX=0000, CX=0000, DX=0000, IP=0100)."),
    ("num", "Jalankan instruksi satu per satu dengan t sebanyak empat kali, dan perhatikan perubahan AX, "
            "DX, serta IP setiap langkah (bahan screenshot nomor 2)."),
    ("num", "Ketik r sekali lagi untuk mencatat hasil akhir, lalu q untuk keluar dari DEBUG."),
    ("p", "Pembuktian nilai: -100 desimal = -64h. Dalam 16 bit, -64h ditulis 10000h - 64h = FF9Ch. "
          "Setelah IDIV CX, AX berisi FFF5h (-11) dan DX berisi FFFFh (-1).",
     {"italic": True, "size": 10.5, "just": True}),

    ("h2", "2.4  Hasil Eksekusi"),
    ("p", "Tabel berikut menunjukkan isi register setelah setiap instruksi dijalankan:"),
    ("table", ["No", "Instruksi", "AX", "CX", "DX", "IP", "Keterangan"],
     [["0", "(awal)", "0000", "0000", "0000", "0100", "Register masih kosong"],
      ["1", "MOV AX,FF9C", "FF9C", "0000", "0000", "0103", "AX berisi -100 (FF9Ch)"],
      ["2", "MOV CX,9", "FF9C", "0009", "0000", "0106", "CX berisi pembagi +9"],
      ["3", "CWD", "FF9C", "0009", "FFFF", "0107", "DX = FFFFh (tanda negatif)"],
      ["4", "IDIV CX", "FFF5", "0009", "FFFF", "0109", "AX = -11 (FFF5h), DX = -1 (FFFFh)"]],
     {"widths": [1.0, 2.9, 1.6, 1.6, 1.6, 1.4, 4.3], "mono": {1, 2, 3, 4}}),

    ("shot", "«Sisipkan screenshot hasil perintah u di sini»"),
    ("caption", "Gambar 2.1  Hasil unassemble (u) program Example 5-15 pada DEBUG"),
    ("shot", "«Sisipkan screenshot register setelah eksekusi (AX=FFF5, DX=FFFF) di sini»"),
    ("caption", "Gambar 2.2  Isi register setelah IDIV CX dijalankan (AX=FFF5h, DX=FFFFh)"),

    ("h2", "2.5  Penjelasan Setiap Instruksi"),
    ("table", ["Instruksi", "Opcode", "Fungsi dan Penjelasan"],
     [["MOV AX,-100", "B8 FF9C",
       "Menyalin nilai -100 desimal ke register AX. Karena -100 negatif, assembler mengubahnya menjadi "
       "komplemen dua 16-bit, yaitu FF9Ch. Pada DEBUG (yang selalu memakai basis heksadesimal) instruksi "
       "ini diketik sebagai mov ax,ff9c. MOV tidak mengubah flag."],
      ["MOV CX,9", "B9 0009",
       "Menyalin nilai +9 ke register CX. CX dipilih karena akan dipakai sebagai operand pembagi pada "
       "IDIV CX. MOV tidak mengubah flag."],
      ["CWD", "99",
       "Convert Word to Doubleword: menyalin bit tanda AX ke seluruh bit DX. Karena AX = FF9Ch (negatif, "
       "MSB = 1), DX diisi FFFFh sehingga DX:AX menjadi FFFF:FF9C, yaitu representasi -100 dalam 32 bit. "
       "Tanpa CWD, DX masih 0000h dan tanda bilangan akan hilang. Instruksi ini tidak mengubah flag."],
      ["IDIV CX", "F7 F9",
       "Signed divide: membagi DX:AX (-100) dengan CX (+9). Hasil bagi diletakkan di AX dan sisa di DX. "
       "Pembagian dibulatkan ke arah nol: -100 / 9 = -11 sisa -1. Jadi AX = FFF5h (-11) dan DX = FFFFh "
       "(-1). Flag CF, OF, SF, ZF, AF, dan PF tidak terdefinisi setelah DIV/IDIV, sehingga nilainya tidak "
       "boleh dipakai."]],
     {"widths": [3.1, 2.1, 9.3], "mono": {0, 1}}),

    ("h2", "2.6  Analisis Hasil"),
    ("p", "Pembuktian secara matematis:", {"bold": True, "space": 2}),
    ("code",
     "  -100 / 9  =  -11,111...     -> hasil bagi dibulatkan ke arah nol = -11\n"
     "  -11 x 9   =  -99\n"
     "  sisa      =  -100 - (-99)   =  -1\n\n"
     "  -11  desimal = FFF5h   (komplemen dua 16-bit)\n"
     "  -1   desimal = FFFFh   (komplemen dua 16-bit)"),
    ("p", "Hasil di register sesuai dengan perhitungan tersebut: AX = FFF5h dan DX = FFFFh.",
     {"indent": 1.0, "just": True}),
    ("p", "Peran CWD sangat penting. Jika CWD dihilangkan, DX tetap 0000h sehingga CPU membaca DX:AX "
          "sebagai 0000FF9Ch = 65.436 (positif), bukan -100. Hasil pembagiannya menjadi 65.436 / 9 = "
          "7.270 sisa 6, yaitu AX = 1C66h dan DX = 0006h - jelas berbeda dan salah secara aritmatika "
          "bertanda. Inilah sebabnya pembagian bertanda 16-bit selalu didahului CWD, sedangkan pembagian "
          "tidak bertanda didahului XOR DX,DX.",
     {"indent": 1.0, "just": True}),

    ("pagebreak",),

    # ------------------------------------------------------------ BAB 3
    ("h1", "BAB 3  EXAMPLE 5-19 - PENGURANGAN BCD EMPAT DIGIT DENGAN DAS"),
    ("h2", "3.1  Deskripsi Program"),
    ("p", "Example 5-19 adalah pasangan dari Example 5-18 (penjumlahan BCD dengan DAA). Program ini "
          "mengurangkan 1234 BCD (DX = 1234h) dari 3099 BCD (BX = 3099h). Karena instruksi DAS hanya bekerja "
          "pada AL, pengurangan dilakukan dua kali: byte rendah (BL - DL) menghasilkan digit bawah, lalu byte "
          "tinggi (BH - DH) dengan borrow melalui SBB. Setiap pengurangan diikuti DAS agar hasilnya kembali "
          "berbentuk BCD. Hasil akhir 1865 ditempatkan di CX.",
          {"indent": 1.0, "just": True}),

    ("h2", "3.2  Listing Program"),
    ("code",
     "0B1F:0100  BA1234   MOV  DX,1234H    ; muat 1234 BCD (DH=12, DL=34)\n"
     "0B1F:0103  BB3099   MOV  BX,3099H    ; muat 3099 BCD (BH=30, BL=99)\n"
     "0B1F:0106  8AC3     MOV  AL,BL       ; AL = 99 (digit rendah)\n"
     "0B1F:0108  2AC2     SUB  AL,DL       ; 99h - 34h = 65h\n"
     "0B1F:010A  2F       DAS              ; koreksi BCD -> AL = 65h\n"
     "0B1F:010B  8AC8     MOV  CL,AL       ; simpan digit rendah ke CL\n"
     "0B1F:010D  8AC7     MOV  AL,BH       ; AL = 30 (digit tinggi)\n"
     "0B1F:010F  1AC6     SBB  AL,DH       ; 30h - 12h - CF = 1Eh\n"
     "0B1F:0111  2F       DAS              ; koreksi BCD -> AL = 18h\n"
     "0B1F:0112  8AE8     MOV  CH,AL       ; simpan digit tinggi ke CH"),
    ("caption", "Listing 3.1  Program Example 5-19 (pengurangan BCD dengan DAS)"),

    ("h2", "3.3  Langkah Praktik di DOSBox"),
    ("num", "Masuk ke DEBUG dengan mengetik debug pada prompt DOS."),
    ("num", "Ketik a, lalu masukkan kesepuluh instruksi berikut (Enter pada baris kosong untuk mengakhiri)."),
    ("code",
     "-a\n"
     "0B1F:0100 mov dx,1234\n"
     "0B1F:0103 mov bx,3099\n"
     "0B1F:0106 mov al,bl\n"
     "0B1F:0108 sub al,dl\n"
     "0B1F:010A das\n"
     "0B1F:010B mov cl,al\n"
     "0B1F:010D mov al,bh\n"
     "0B1F:010F sbb al,dh\n"
     "0B1F:0111 das\n"
     "0B1F:0112 mov ch,al\n"
     "0B1F:0114"),
    ("num", "Ketik u 100 113 untuk menampilkan listing (bahan screenshot nomor 3)."),
    ("num", "Tekan r untuk melihat register awal, lalu jalankan program sekaligus dengan g =100,114."),
    ("num", "Tekan r untuk melihat hasil akhir: CX harus berisi 1865h, AX = 0018h, BX = 3099h, dan "
            "DX = 1234h (bahan screenshot nomor 4)."),
    ("num", "Untuk analisis, program juga dapat dijalankan bertahap dengan t sambil mencatat perubahan AX "
            "dan flag CF/AF setiap langkah."),
    ("num", "Ketik q untuk keluar dari DEBUG."),

    ("h2", "3.4  Hasil Eksekusi"),
    ("p", "Hasil pengamatan register setelah setiap instruksi:"),
    ("table", ["No", "Instruksi", "AX", "BX", "CX", "DX", "CF", "AF"],
     [["0", "(awal)", "0000", "0000", "0000", "0000", "0", "0"],
      ["1", "MOV DX,1234", "0000", "0000", "0000", "1234", "0", "0"],
      ["2", "MOV BX,3099", "0000", "3099", "0000", "1234", "0", "0"],
      ["3", "MOV AL,BL", "0099", "3099", "0000", "1234", "0", "0"],
      ["4", "SUB AL,DL", "0065", "3099", "0000", "1234", "0", "0"],
      ["5", "DAS", "0065", "3099", "0000", "1234", "0", "0"],
      ["6", "MOV CL,AL", "0065", "3099", "0065", "1234", "0", "0"],
      ["7", "MOV AL,BH", "0030", "3099", "0065", "1234", "0", "0"],
      ["8", "SBB AL,DH", "001E", "3099", "0065", "1234", "0", "1"],
      ["9", "DAS", "0018", "3099", "0065", "1234", "0", "1"],
      ["10", "MOV CH,AL", "0018", "3099", "1865", "1234", "0", "1"]],
     {"widths": [1.0, 3.1, 1.5, 1.7, 1.7, 1.7, 1.2, 1.2], "mono": {1, 2, 3, 4, 5}, "size": 9.5}),

    ("shot", "«Sisipkan screenshot hasil perintah u di sini»"),
    ("caption", "Gambar 3.1  Hasil unassemble (u) program Example 5-19 pada DEBUG"),
    ("shot", "«Sisipkan screenshot register setelah eksekusi (CX=1865) di sini»"),
    ("caption", "Gambar 3.2  Isi register setelah program dijalankan (CX = 1865h)"),

    ("h2", "3.5  Penjelasan Setiap Instruksi"),
    ("table", ["Instruksi", "Opcode", "Fungsi dan Penjelasan"],
     [["MOV DX,1234H", "BA 1234",
       "Memuat pola bit 1234h ke DX. Dalam BCD terpaket, DH = 12h (dua digit atas) dan DL = 34h (dua "
       "digit bawah), sehingga DX menyimpan bilangan BCD 1234."],
      ["MOV BX,3099H", "BB 3099",
       "Memuat BCD 3099 ke BX: BH = 30h dan BL = 99h. Bilangan ini adalah yang dikurangi (minuend)."],
      ["MOV AL,BL", "8A C3",
       "Menyalin BL (99h) ke AL. AL dipakai karena SUB dan DAS hanya dapat bekerja pada AL. DX dan BX "
       "tetap utuh sebagai penyimpan bilangan asli."],
      ["SUB AL,DL", "2A C2",
       "Mengurangkan DL (34h) dari AL (99h): 99h - 34h = 65h. Tidak ada borrow, sehingga CF = 0 dan "
       "AF = 0. Flag SF, ZF, PF ikut diperbarui sesuai hasil 65h."],
      ["DAS", "2F",
       "Decimal Adjust after Subtraction. Karena nibble rendah 5 <= 9 dan AF = 0, tidak ada koreksi; "
       "AL tetap 65h, yang sudah sah sebagai dua digit BCD (6 dan 5)."],
      ["MOV CL,AL", "8A C8",
       "Menyimpan hasil digit rendah (65h) ke CL, sehingga nantinya menjadi byte rendah dari CX."],
      ["MOV AL,BH", "8A C7",
       "Menyalin BH (30h) ke AL untuk memproses digit tinggi. Langkah ini harus dilakukan setelah hasil "
       "digit rendah disimpan, karena AL akan ditimpa."],
      ["SBB AL,DH", "1A C6",
       "Subtract with Borrow: AL = 30h - 12h - CF(0) = 1Eh. Nibble rendah E (14) lebih besar dari 9, "
       "dan AF = 1 karena terjadi borrow dari nibble rendah. Nilai 1Eh belum BCD."],
      ["DAS", "2F",
       "Koreksi BCD: karena (AL AND 0Fh) = Eh > 9 (dan AF = 1), AL dikurangi 6 menjadi 18h dan AF = 1. "
       "CF tetap 0 karena tidak ada borrow dari digit tinggi. Hasil 18h sudah sah sebagai BCD (1 dan 8)."],
      ["MOV CH,AL", "8A E8",
       "Menyimpan digit tinggi (18h) ke CH. Setelah instruksi ini CX = 1865h, yaitu hasil pengurangan "
       "3099 - 1234 dalam bentuk BCD."]],
     {"widths": [3.1, 2.0, 9.4], "mono": {0, 1}}),

    ("h2", "3.6  Analisis Hasil"),
    ("p", "Secara desimal: 3099 - 1234 = 1865, dan hasil di CX memang 1865h. Perhatikan bahwa 1865h di sini "
          "bukan bilangan heksadesimal biasa, melainkan empat digit BCD: 1, 8, 6, dan 5.",
          {"indent": 1.0, "just": True}),
    ("p", "Jika instruksi DAS dihilangkan, hasil di CX akan menjadi 1E65h. Nibble E bukan digit desimal "
          "yang sah, sehingga hasil tersebut tidak dapat dibaca sebagai BCD. Inilah fungsi utama DAS: "
          "mengubah hasil pengurangan biner menjadi bentuk BCD yang benar.",
          {"indent": 1.0, "just": True}),
    ("p", "Penggunaan SUB pada byte rendah dan SBB pada byte tinggi juga penting. SUB dipakai lebih dahulu "
          "karena belum ada borrow yang masuk; hasilnya menentukan CF. SBB kemudian mengurangi byte tinggi "
          "sekaligus borrow tersebut. Pada kasus ini CF = 0, sehingga SBB berperilaku seperti SUB, tetapi "
          "penulisan SBB tetap diperlukan agar program benar untuk semua nilai (misalnya 3000 - 1201, "
          "yang memerlukan borrow).",
          {"indent": 1.0, "just": True}),

    ("pagebreak",),

    # ------------------------------------------------------------ BAB 4
    ("h1", "BAB 4  KESIMPULAN"),
    ("num", "Instruksi CWD diperlukan sebelum IDIV pada pembagian 16-bit bertanda agar isi AX diperluas "
            "menjadi DX:AX dengan tanda yang benar. Pada Example 5-15, CWD mengubah DX menjadi FFFFh "
            "sehingga -100 dibaca sebagai -100, bukan 65.436."),
    ("num", "IDIV CX menghasilkan hasil bagi -11 (FFF5h) di AX dan sisa -1 (FFFFh) di DX, sesuai aturan "
            "pembulatan ke arah nol dan sisa yang bertanda sama dengan dividend."),
    ("num", "Pengurangan BCD empat digit tidak dapat dilakukan dengan satu instruksi SUB 16-bit, tetapi "
            "harus per byte (digit rendah lalu digit tinggi) dan setiap pengurangan diikuti DAS agar "
            "hasilnya tetap sah sebagai BCD."),
    ("num", "DAS memanfaatkan nilai nibble rendah serta flag AF dan CF untuk menentukan koreksi -6 "
            "dan/atau -60. Pada Example 5-19, DAS mengubah 1Eh menjadi 18h sehingga hasil akhir CX = "
            "1865h benar."),
    ("num", "DEBUG.EXE pada DOSBox memudahkan pengamatan prosesor secara langsung melalui perintah a, u, "
            "r, t, p, g, dan q, sehingga hubungan antara instruksi, isi register, dan flag dapat diamati "
            "langkah demi langkah."),

    ("pagebreak",),

    # ------------------------------------------------------------ LAMPIRAN
    ("h1", "LAMPIRAN  CHECKLIST SCREENSHOT"),
    ("p", "Tempelkan screenshot asli dari jendela DOSBox (gunakan Win + Shift + S atau Alt + PrtScr) pada "
          "kotak yang telah disediakan di Bab 2 dan Bab 3. Pastikan angka pada register terlihat jelas. "
          "Daftar screenshot yang diperlukan:"),
    ("table", ["No", "Screenshot", "Letak di laporan"],
     [["1", "Hasil perintah u (listing) Example 5-15", "Gambar 2.1"],
      ["2", "Register setelah IDIV CX (AX=FFF5, DX=FFFF)", "Gambar 2.2"],
      ["3", "Hasil perintah u (listing) Example 5-19", "Gambar 3.1"],
      ["4", "Register setelah program dijalankan (CX=1865)", "Gambar 3.2"]],
     {"widths": [1.2, 10.0, 3.6]}),
    ("p", "Tips pengambilan screenshot: perbesar jendela DOSBox (Alt + Enter untuk fullscreen), gunakan "
          "font yang jelas, lalu tangkap seluruh area jendela termasuk prompt dan baris perintah agar "
          "urutan langkah terlihat.",
     {"italic": True, "size": 10.5, "indent": 1.0}),
]
