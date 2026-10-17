# Protokol Uji Manual Perangkat Fisik (Perekaman Audio Web)

Dokumen ini adalah protokol pengujian manual untuk mengevaluasi keandalan dan stabilitas perekaman audio berbasis browser (`MediaRecorder` + `getUserMedia`) pada berbagai perangkat seluler nyata.

> [!IMPORTANT]
> Perilaku proses latar belakang (backgrounding), manajemen daya, dan pemutusan mikrofon oleh sistem operasi seluler **TIDAK BISA diuji secara otomatis** menggunakan simulasi headless atau tes unit PHP. Pengujian fisik wajib dijalankan pada minimal 2 merek ponsel Android (misal: Samsung OneUI, Xiaomi HyperOS/MIUI) dan iPhone (iOS Safari).

---

## Prasyarat Pengujian
1. **Protokol Jaringan**: Akses aplikasi melalui **HTTPS** (wajib untuk Web API `getUserMedia`, `crypto.subtle`, dan `Screen Wake Lock`). Gunakan tunnel HTTPS lokal (seperti Herd Pro / Ngrok / Cloudflare Tunnel) saat pengujian lokal.
2. **Izin Browser**: Mikrofon diizinkan (*Allowed*) pada peramban.
3. **Perangkat Uji**:
   - Perangkat A: Ponsel Android Merek 1 (misal Samsung Galaxy, Android 13/14, Chrome)
   - Perangkat B: Ponsel Android Merek 2 (misal Xiaomi / Oppo / Vivo, Chrome)
   - Perangkat C: Apple iPhone (iOS 16/17/18, Safari)

---

## Matriks Protokol Skenario Pengujian

| ID | Skenario Uji | Prosedur Langkah Uji | Hasil yang Diharapkan | Hasil Aktual (Diisi Penguji) | Status (Lolos / Gagal) |
|---|---|---|---|---|---|
| **SKN-01** | **Screen Wake Lock & Layar Mati Alami** | 1. Buka halaman rekam.<br>2. Tekan *Mulai Rekam*.<br>3. Diamkan ponsel selama 5–10 menit tanpa menyentuh layar. | Layar ponsel tetap menyala (tidak meredup/terkunci otomatis karena Wake Lock aktif). Audio tetap terekam tanpa jeda. | | [ ] |
| **SKN-02** | **Kunci Layar Manual (Tombol Power)** | 1. Sedang merekam.<br>2. Tekan tombol Power fisik untuk mengunci layar.<br>3. Biarkan terkunci selama 1 menit.<br>4. Nyalakan dan buka kunci layar kembali. | **Catat perilaku**: Pada iOS Safari / Android tertentu, perekam mungkin terhenti atau menghasilkan segmen senyap. Periksa apakah segmen sebelum kunci tersimpan dan saat dibuka kembali Wake Lock terpulihkan. | | [ ] |
| **SKN-03** | **Pindah Aplikasi (Multitasking / Home)** | 1. Sedang merekam.<br>2. Tekan tombol Home atau beralih ke aplikasi WhatsApp / Catatan.<br>3. Diamkan 1 menit.<br>4. Kembali ke tab browser notulensi. | Periksa banner peringatan. Segmen sebelum berpindah aplikasi harus sudah terunggah atau tersimpan di IndexedDB. Saat kembali, rekaman dapat dilanjutkan. | | [ ] |
| **SKN-04** | **Panggilan Telepon Masuk (Interupsi Suara)** | 1. Sedang merekam.<br>2. Lakukan panggilan telepon masuk (seluler/WhatsApp) ke ponsel uji.<br>3. Tolak (*decline*) atau biarkan berdering.<br>4. Lanjutkan rekaman. | Perekam tidak crash (*unhandled exception*). Segmen yang selesai sebelum panggilan tersimpan utuh di antrean lokal. | | [ ] |
| **SKN-05** | **Sinyal Putus (Mode Pesawat) & Pulih Kembali** | 1. Sedang merekam.<br>2. Aktifkan *Airplane Mode* (sinyal putus total).<br>3. Biarkan rekaman berjalan selama 2–3 menit (terbentuk 2–3 segmen).<br>4. Amati antrean: badge *Offline* aktif, jumlah *Antrean Lokal* bertambah.<br>5. Matikan *Airplane Mode* (koneksi internet pulih). | Tanpa me-refresh halaman, *worker* pengunggah otomatis mendeteksi koneksi dan menguras seluruh antrean IndexedDB ke server. | | [ ] |
| **SKN-06** | **Rekaman Penuh 90 Menit (Durasi Nyata)** | 1. Posisikan ponsel di tengah meja rapat.<br>2. Mulai rekam selama 90 menit tanpa henti.<br>3. Sediakan pembicaraan atau pemutaran podcast/audio di ruangan.<br>4. Amati rotasi segmen tiap ~60 detik.<br>5. Tekan *Selesai Rekam*. | Ponsel tidak *overheat* berlebihan. Browser tidak kehabisan memori (*out of memory*). Semua 90 segmen berhasil diunggah lengkap (1..90) dan status berubah menjadi `uploaded`. | | [ ] |
| **SKN-07** | **Penyelesaian dengan Segmen Tertunda** | 1. Saat rekaman berjalan, matikan internet sesaat sebelum menekan *Selesai Rekam*.<br>2. Tekan *Selesai Rekam*.<br>3. Nyalakan internet kembali. | Sistem menampilkan status *Menyelesaikan & Mengunggah...*, mengunggah sisa segmen dari IndexedDB hingga tuntas, baru mengirim permintaan `finish` ke server. | | [ ] |

---

## Log Hasil Pengujian Fisik

### Perangkat Uji 1: [Isi Tipe Ponsel & Versi OS]
- **Penguji**: 
- **Tanggal Uji**: 
- **Browser & Versi**: 
- **Catatan & Temuan**:

### Perangkat Uji 2: [Isi Tipe Ponsel & Versi OS]
- **Penguji**: 
- **Tanggal Uji**: 
- **Browser & Versi**: 
- **Catatan & Temuan**:

---

## Rekomendasi Prosedur Operasional Standar (SOP) Notulis
Berdasarkan sifat peramban mobile:
1. Selalu aktifkan mode **Jangan Ganggu (Do Not Disturb)** sebelum rapat dimulai.
2. Pastikan daya baterai ponsel minimal 50% atau sambungkan ke pengisi daya.
3. Jangan menekan tombol daya untuk mematikan layar selama rapat; biarkan aplikasi menjaga layar tetap menyala melalui Wake Lock.
4. Letakkan ponsel di titik pusat meja rapat dan jangan ditutup benda lain.
