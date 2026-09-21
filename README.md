# Jago Kali Bagi — Panduan Pemasangan

## Isi folder
| File | Fungsi |
|---|---|
| `index.html` | Tampilan game (HP-first) |
| `api.php` | Simpan skor & peringkat global |
| `config.php` | Pengaturan database (WAJIB diedit) |
| `database.sql` | Struktur tabel MySQL |

## Langkah
1. Buat database MySQL di cPanel (MySQL Databases), buat user, beri hak akses penuh.
2. Buka phpMyAdmin → pilih database → tab **Import** → pilih `database.sql`.
3. Edit `config.php`: isi `DB_NAME`, `DB_USER`, `DB_PASS`, dan ganti `APP_SECRET` dengan teks acak panjang.
4. Upload `index.html`, `api.php`, `config.php` ke satu folder di hosting (mis. `public_html/game/`).
5. Buka `https://domain-anda/game/` di HP.

## Opsional
- Nomor WhatsApp guru bawaan: di `index.html`, cari `WA_GURU_DEFAULT` lalu isi `'6281234567890'` (format 62, tanpa +).
  Jika kosong, siswa mengetik nomor guru di layar hasil. Jika nomor dikosongkan saat mengirim, WhatsApp akan membuka pilihan kontak.
- Tingkat kesulitan & poin: bagian `CFG` di `index.html` (`RANGE`, `POIN`).

## Aturan permainan
- Waktu 60 detik. Jawab benar 3x berturut-turut = +10 detik (berlaku lagi di 6, 9, dst.).
- Jawab salah atau tombol Lewati = hitungan berturut-turut mulai dari 0.
- Poin per jawaban benar: Mudah 10, Sedang 15, Sulit 20.
- Bunyi: pada sisa 10 detik berbunyi alarm, lalu tik tiap detik sampai habis; ada bunyi benar, salah, bonus, dan waktu habis.
- Peringkat global memakai skor terbaik tiap pemain (nama + sekolah); bisa difilter per permainan.

## Keamanan dasar
Skor dihitung ulang di server dari jumlah jawaban benar, token permainan bertanda tangan (HMAC) dan hanya bisa dipakai sekali, serta durasi dicek. Ini cukup untuk kelas/sekolah, tetapi bukan perlindungan mutlak terhadap kecurangan.
