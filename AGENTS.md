# Proyek: Aplikasi Notulensi Rapat (Laravel 13)
Bahasa komunikasi: Indonesia. Identifier kode, nama tabel, dan komentar kode: Inggris.
Stack: Laravel 13 (PHP >= 8.3), MySQL 8, Blade + Alpine.js + Tailwind, queue driver database, ffmpeg.
Sumber kebenaran: docs/keputusan.md lalu docs/rancangan.md. Jika ragu atau ada konflik, TANYA; jangan menebak.

Aturan inti:
1. Multitenant: semua tabel milik tenant punya organization_id dan memakai trait BelongsToOrganization (global scope). Tidak ada query lintas tenant di luar area super admin.
2. Otorisasi hanya lewat Policy/Gate. Jangan memeriksa peran dengan if di controller atau query.
3. Peran tenant: admin, notulis, pimpinan. Super admin: guard platform terpisah.
4. Audio mentah dan transkrip mentah TIDAK PERNAH dapat diakses admin maupun super admin.
5. audit_logs hanya-tambah. Catat: akses audio, akses transkrip mentah, pembukaan tautan, finalisasi/revisi, koreksi absensi, penghapusan rapat.
6. Layanan eksternal (STT, LLM) hanya lewat interface di app/Contracts. Kunci API hanya di .env. Jangan menaruh rahasia di repo atau tes.
7. Audio dihapus 1 bulan sejak disimpan (nilai dari config); transkrip mentah tetap. Penghapusan harus nyata (berkas + salinan sementara).
8. Tautan publik: token acak, hanya hash disimpan, gerbang nomor WA/kode, throttle, bukan sekali pakai, PDF lewat controller.
9. Jangan menurunkan versi Laravel/PHP. Jangan menambah paket tanpa memeriksa kompatibilitas Laravel 13 dan menyebutkannya di rencana.
10. Jangan mengubah tes agar lulus. Jika tes yang salah, jelaskan dulu.
11. Setiap fitur butuh tes. Jalankan /verify sebelum menyatakan selesai.
12. Keluaran akhir tiap tugas: ringkasan perubahan, daftar berkas, hasil tes, asumsi yang dibuat, hal yang belum selesai.