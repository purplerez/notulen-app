# Keputusan produk (sumber kebenaran, terbaru)

## Peran dan hak akses
- pimpinan (mis. Kepala Sekolah): akun hanya baca. Membuat akun notulis. Melihat semua rapat organisasinya. Notulen final tanpa batas waktu. Transkrip mentah tanpa batas waktu. Audio mentah sejak pertama disimpan sampai audio_expires_at. Tombol 'Tandai diketahui' per versi notulen (hanya pimpinan). Membaca log akses.
- admin: membuat akun notulis (hanya peran notulis), membuat room rapat, menginput peserta, melakukan absensi (tutup absensi, status ketidakhadiran, koreksi dengan alasan), membaca notulen final, membaca log akses, merevisi notulen final. TIDAK dapat mengakses audio atau transkrip mentah.
- notulis: membuat room dan peserta, merekam, memutar ulang audio sampai audio_expires_at, mengakses transkrip mentah (tanpa batas waktu), menyunting, memfinalkan notulen dan menetapkan batas waktu baca peserta saat finalisasi, merevisi, membaca log rapat sendiri, menghapus rapat sendiri.
- peserta: tanpa akun; membaca notulen final lewat tautan sampai batas waktu, dengan gerbang nomor WA terdaftar atau kode rapat.
- super admin (platform, guard terpisah): hanya memantau biaya per lembaga. Tidak melihat judul rapat, peserta, notulen, audio, atau transkrip.
- Akun admin dan pimpinan dibuat operator lewat perintah artisan. Admin dan pimpinan hanya dapat membuat akun notulis.

## Data dan retensi
- Audio dihapus 1 bulan sejak seluruh segmen tersimpan. Transkrip mentah tetap, berlabel 'Transkrip otomatis, belum diperiksa'.
- audit_logs hanya-tambah, tanpa foreign key ke rapat; penghapusan rapat tercatat.
- Revisi notulen final membuat versi baru. 'Diketahui' melekat pada versi, dengan penanda 'ada revisi baru'.
- Tanpa teks persetujuan peserta (keputusan pemilik). Label 'Rapat direkam untuk notulensi' opsional.

## Distribusi
- Tanpa API WhatsApp. Tombol wa.me per penerima dan tautan. Batas waktu baca peserta ditetapkan notulis saat finalisasi. Tautan dapat dicabut.

## Absensi (berita acara)
- QR berganti 30 detik (HMAC). Scan peserta = jalur cepat. Admin/notulis dapat menandai manual. Status: hadir, terlambat, izin, sakit, dinas luar, tanpa keterangan. 'Tutup absensi' mengunci; koreksi sesudahnya wajib alasan dan tercatat. Keluaran: rekap dengan kolom paraf kosong.

## Batas STT (Groq, paket gratis; cek konsol untuk nilai terkini)
- 20 permintaan/menit, 2.000/hari, 7.200 detik audio/jam, 28.800 detik audio/hari, berkas 25 MB. Berlaku per organisasi Groq (seluruh platform).
- Saat 429 atau kuota habis: status waiting_quota, jadwalkan ulang sesuai retry-after atau reset, bukan gagal. Ambang bawaan di config agar mudah diubah pada paket berbayar.