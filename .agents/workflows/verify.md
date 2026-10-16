---
description: Verifikasi sebelum menyatakan tugas selesai (tes, gaya kode, rahasia, rute, scope tenant)
---

# Verifikasi tugas

1. Jalankan php artisan test dan laporkan ringkasan hasil.
2. Jalankan Pint dan perbaiki gaya kode.
3. Jalankan analisis statis bila terpasang (Larastan).
4. Pastikan tidak ada kunci API atau kata sandi di perubahan (periksa git diff).
5. Pastikan setiap rute baru berada di balik guard yang benar, atau memang publik dengan alasan tertulis.
6. Pastikan setiap model milik tenant memakai BelongsToOrganization.
7. Tulis ringkasan: perubahan, berkas, hasil tes, asumsi, yang belum selesai.