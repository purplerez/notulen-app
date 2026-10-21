# Panduan Operasional, Penerapan (Deployment), & Pemeliharaan Sistem

Dokumen ini merangkum seluruh prosedur operasional, persyaratan server, panduan penerapan (*deployment*), konfigurasi *worker*, rotasi kunci, kebijakan retensi & cadangan (*backup*), batasan operator, serta integrasi pihak ketiga untuk administrator sistem dan tim DevOps.

---

## 1. Persyaratan Sistem & Server

Aplikasi Notulensi Rapat berjalan di atas stack Laravel 13 dan memerlukan lingkungan server berikut:

| Komponen | Persyaratan Minimal | Rekomendasi Produksi | Catatan Khusus |
|---|---|---|---|
| **Sistem Operasi** | Linux (Ubuntu 22.04 LTS / 24.04 LTS / Debian 12) | Ubuntu 24.04 LTS x86_64 / arm64 | Akses non-root untuk eksekusi PHP-FPM |
| **PHP** | PHP $\ge$ 8.3 | PHP 8.3 / 8.4 (PHP-FPM) | `memory_limit >= 256M`, `upload_max_filesize >= 30M`, `post_max_size >= 35M` |
| **Ekstensi PHP** | `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl`, `bcmath`, `gd` | Versi terbaru dari repositori Ondřej Surý | `gd` dibutuhkan untuk validasi grafis ikon & QR |
| **Basis Data** | MySQL 8.0+ atau MariaDB 10.11+ | MySQL 8.0 (InnoDB, `utf8mb4_unicode_ci`) | Mode STRICT diaktifkan, time zone server `+07:00` (WIB) |
| **Audio Processing** | FFmpeg $\ge$ 5.0 (dengan pustaka AAC) | FFmpeg 6.x / 7.x (`ffmpeg`, `ffprobe`) | Wajib tersedia di `PATH` sistem (misal `/usr/bin/ffmpeg`) |
| **Process Manager** | Supervisor 4.x atau Systemd | Supervisor untuk mengelola antrean worker | Mencegah worker antrean mati saat terjadi *unhandled crash* |
| **Penjadwal Tugas** | Linux Cron Daemon | `cron` aktif | Menjalankan `php artisan schedule:run` tiap menit |
| **Web Server & SSL** | Nginx 1.24+ atau Caddy dengan HTTPS | Nginx + Sertifikat Let's Encrypt / Cloudflare SSL | **HTTPS WAJIB** untuk Web Audio API & Service Worker |

---

## 2. Prosedur Penerapan Produksi (Deployment Checklist)

Setiap kali melakukan pembaruan versi atau penerapan rilis baru ke server produksi, jalankan urutan perintah berikut:

```bash
# 1. Pindah ke direktori root aplikasi
cd /var/www/notulen-app

# 2. Masuk ke mode pemeliharaan (opsional jika ada migrasi besar)
php artisan down --secret="akses-rahasia-operator" --render="errors::503"

# 3. Tarik pembaruan kode dari repositori Git resmi
git fetch origin
git checkout main
git pull origin main

# 4. Pasang dependensi PHP tanpa paket pengembangan (no-dev)
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Jalankan migrasi basis data (bersifat non-destruktif dan transaksional)
php artisan migrate --force

# 6. Kompilasi aset frontend produksi (jika ada pembaruan Tailwind/JS)
npm ci --prefer-offline --no-audit
npm run build

# 7. Segarkan dan optimalkan seluruh cache internal framework
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 8. Restart pekerja antrean latar belakang (Supervisor)
php artisan queue:restart

# 9. Keluar dari mode pemeliharaan
php artisan up
```

---

## 3. Konfigurasi Supervisor (Queue Worker)

Aplikasi mengandalkan queue driver basis data (`database`) untuk pemrosesan transkripsi STT, penggabungan audio FFmpeg, pembuatan draf notulen, dan pembuatan ringkasan berversi.

Buat berkas konfigurasi Supervisor di `/etc/supervisor/conf.d/notulen-worker.conf`:

```ini
[program:notulen-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/notulen-app/artisan queue:work database --queue=high,default,stt --sleep=3 --tries=3 --max-time=3600 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/notulen-worker.log
stopwaitsecs=3600
```

Aktifkan konfigurasi:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start notulen-worker:*
```

---

## 4. Konfigurasi Cron Scheduler

Aplikasi memiliki jadwal rutin untuk:
- Pembersihan berkas audio kedaluwarsa (`audio:purge`) setiap pukul 02:00 WIB.
- Pengingat masa berlaku audio (`audio:remind`) setiap pukul 08:00 WIB.
- Pemantauan biaya penyimpanan audio harian.

Tambahkan entri berikut pada crontab pengguna `www-data`:

```bash
# Buka crontab
sudo crontab -u www-data -e

# Masukkan satu baris scheduler Laravel:
* * * * * cd /var/www/notulen-app && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Langkah Wajib Setelah Mengubah Berkas `.env`

> [!IMPORTANT]
> Karena di lingkungan produksi seluruh berkas konfigurasi di-*cache* ke dalam satu berkas `bootstrap/cache/config.php` via `php artisan config:cache`, perubahan nilai variabel di `.env` **TIDAK AKAN BERPENGARUH** sebelum dua perintah ini dijalankan:

```bash
# 1. Bersihkan dan perbarui cache konfigurasi
php artisan config:clear
php artisan config:cache

# 2. Restart pekerja antrean (worker membaca konfigurasi baru saat spawn ulang)
php artisan queue:restart
```

---

## 6. Prosedur Rotasi Kunci (Key Rotation) & Analisis Dampak

### 6.1 Rotasi Kunci Aplikasi (`APP_KEY`)
Kunci `APP_KEY` digunakan Laravel untuk mengenkripsi sesi, cookie terenkripsi, token CSRF, serta data sensitif yang dienkripsi via `Crypt::encryptString()`.

- **Dampak Rotasi**:
  1. Seluruh sesi pengguna aktif akan seketika putus (semua pengguna logout otomatis).
  2. Cookie yang dienkripsi dengan kunci lama tidak dapat dibaca kembali.
  3. Setiap kolom basis data yang pernah dienkripsi dengan `Crypt::encrypt()` menggunakan kunci lama **TIDAK BISA DIDEKRIPSI KEMBALI** (kecuali didekripsi terlebih dahulu sebelum rotasi).
- **Prosedur**:
  ```bash
  # Generate kunci baru
  php artisan key:generate
  # Bersihkan cache dan restart queue
  php artisan config:clear && php artisan config:cache && php artisan queue:restart
  ```

### 6.2 Rotasi Kunci API Pihak Ketiga (Groq STT & LLM)
Kunci API eksternal disimpan secara aman di `.env` server tanpa pernah masuk ke repositori.

- **Dampak Rotasi**:
  - Nol waktu henti (*zero downtime*) jika kunci baru disiapkan sebelum kunci lama dinonaktifkan di konsol penyedia.
- **Prosedur**:
  1. Masuk ke konsol penyedia (misal [console.groq.com](https://console.groq.com)).
  2. Buat API Key baru.
  3. Buka `.env` di server dan perbarui nilai:
     ```env
     GROQ_API_KEY=gsk_kunci_baru_xxxxxxxxxxxxxxxxxxxx
     ```
  4. Jalankan:
     ```bash
     php artisan config:clear && php artisan config:cache
     php artisan queue:restart
     ```
  5. Uji dengan perintah bawaan:
     ```bash
     php artisan stt:test /jalur/ke/sample.webm
     ```
  6. Setelah terbukti sukses, hapus (*revoke*) kunci lama di konsol penyedia.

---

## 7. Kebijakan Retensi Audio & Rotasi Cadangan (Backup Rotation Policy)

Sesuai aturan privasi aplikasi (dokumen keputusan & AGENTS.md Aturan 7), rekaman audio mentah dan audio gabungan memiliki masa simpan terbatas (**1 bulan** dari waktu rapat selesai, diatur via `RECORDING_RETENTION_MONTHS` di `config/recording.php`).

### 7.1 Pembersihan Otomatis (`audio:purge`)
- Perintah terjadwal `php artisan audio:purge` menghapus secara permanen berkas fisik audio dari storage privat (`storage/app/private/orgs/*/meetings/*`).
- Mengisi kolom `audio_deleted_at = now()`, mengosongkan `merged_audio_path = null`, dan mencatat riwayat audit `audio_purge`.
- Teks transkrip mentah pada `transcript_segments` dan notulen final **TIDAK DIHAPUS** dan tetap tersimpan utuh.

### 7.2 Syarat Mutlak Rotasi Cadangan Server (Maksimal 30 Hari)

> [!CAUTION]
> **PERINGATAN KEPATUHAN CADANGAN & PRIVASI INSTITUSI**:
> **Rotasi penyimpanan cadangan berkas (storage backup) TIDAK BOLEH lebih lama dari masa retensi audio (maksimal 30 hari / 1 bulan).**
>
> Jika kebijakan backup server (cold storage, snapshot disk, AWS S3 Glacier, dsb.) menyimpan arsip berkas lebih dari 30 hari (misal 90 hari atau 1 tahun):
> - Berkas audio yang telah dihapus secara sah oleh aplikasi akan tetap tertinggal di dalam arsip cadangan dingin.
> - Hal ini menggugurkan jaminan privasi kepada organisasi dan peserta rapat.
>
> **Aturan Wajib Operator Server**:
> 1. Atur *lifecycle policy* retensi cadangan berkas storage maksimal 30 hari (otomatis terhapus setelah 30 hari).
> 2. ATAU terapkan aturan penyaringan (*exclude filter*) pada script backup agar direktori audio dari rapat yang telah berstatus `audio_deleted_at IS NOT NULL` tidak dicadangkan ke arsip jangka panjang.

---

## 8. Batasan Operator Server (Application vs Infrastructure Boundaries)

> [!WARNING]
> **BATASAN PERAN HANYA BERLAKU DI TINGKAT APLIKASI (APPLICATION LEVEL)**:
>
> Seluruh aturan otorisasi ketat di dalam kode (seperti *Rule 4*: Admin organisasi dan Super Admin platform tidak dapat mengakses audio mentah atau transkrip mentah; *Rule 1*: Multitenancy global scope) diterapkan dan ditegakkan melalui **Policy, Gate, dan Middleware Laravel**.
>
> Namun, operator infrastruktur yang memiliki:
> - Akses SSH tingkat `root` ke sistem operasi server,
> - Hak akses pengguna basis data administratif (`root` di MySQL), atau
> - Hak akses langsung ke disk penyimpanan berkas (`/storage/app/private/`),
>
> secara teknis **dapat melampaui batasan peran aplikasi** dan membaca langsung berkas fisik audio maupun isi tabel basis data.
>
> **Tindakan Mitigasi yang Wajib Diterapkan Pemilik Sistem**:
> 1. **Prinsip Hak Akses Terkecil (Least Privilege)**: Batasi akses SSH server hanya untuk personil yang telah menandatangani perjanjian kerahasiaan (*Non-Disclosure Agreement*).
> 2. **Audit Akses Server**: Aktifkan audit logging pada level OS (misal `auditd` di Linux) dan catat setiap sesi SSH/sudo.
> 3. **Pemisahan Kredensial Database**: Aplikasi harus menggunakan akun MySQL non-root dengan batasan hak akses hanya pada skema database aplikasi.
> 4. **Enkripsi Disk (At-Rest)**: Aktifkan enkripsi volume disk (LUKS atau cloud volume encryption) pada media penyimpanan server produksi.

---

## 9. Konfigurasi Penyedia Speech-to-Text (STT) & LLM

### 9.1 Konfigurasi Awal: Groq (Whisper)
Aplikasi menggunakan Groq Cloud untuk transkripsi audio cepat berbiaya rendah dengan model Whisper.

```env
STT_DRIVER=groq
STT_LANGUAGE=id
GROQ_API_KEY=gsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
GROQ_STT_MODEL=whisper-large-v3-turbo
GROQ_STT_TIMEOUT=120
STT_RPM_LIMIT=18
STT_ASH_LIMIT=7000
```

- **RPM Limit**: 18 permintaan per menit (ambang aman dari batas konsol 20 RPM).
- **ASH Limit**: 7.000 detik audio per jam per seluruh instansi. Jika terlampaui, segmen ditahan sementara dengan status `ditahan` dan diulang otomatis saat jendela waktu kembali lapang.

### 9.2 Pemantauan Pemakaian (`usage_logs`)
Setiap percobaan transkripsi dan perangkuman dicatat pada tabel `usage_logs`. Untuk memantau dari konsol MySQL:

```sql
-- Detik audio sukses 1 jam terakhir
SELECT SUM(audio_sec) AS total_detik 
FROM usage_logs 
WHERE kind = 'stt' AND status = 'sukses' AND created_at >= NOW() - INTERVAL 1 HOUR;

-- Riwayat per status per hari
SELECT DATE(created_at) AS tgl, status, COUNT(*) AS jml 
FROM usage_logs 
GROUP BY DATE(created_at), status;
```

---

## 10. Keamanan Distribusi Tautan Publik (`/n/{token}`) & Mode Akses

Sesuai dokumen rancangan Bagian 8.6 dan 10 serta keputusan distribusi, tautan baca publik disajikan dengan kontrol ketat:

### 10.1 Zero-Knowledge Token Storage
- Tautan menggunakan token acak 64 karakter berentropi tinggi.
- Basis data **hanya menyimpan hash SHA-256** dari token (`token_hash`). Kebocoran database tidak membocorkan URL publik asli.
- Nomor ID rapat (`meeting_id`) sama sekali tidak ditampilkan pada URL publik.

### 10.2 Evaluasi Keamanan: Mode WhatsApp vs Mode Kode (PIN)
> [!IMPORTANT]
> **Nomor WhatsApp Bukan Bukti Identitas Kriptografis**:
> Verifikasi berbasis nomor WhatsApp memudahkan distribusi bagi peserta rapat umum, namun bukan merupakan pembuktian identitas yang tak terbantahkan. Jika seseorang mengetahui nomor peserta yang hadir, ia dapat melewati gerbang.
>
> **Rekomendasi Operasional**:
> - **Rapat Umum**: Gunakan **Mode WhatsApp** (`phone`) dengan cakupan `hadir`.
> - **Rapat Tertutup / Sensitif**: **Wajib gunakan Mode Kode (PIN 6-Digit)** (`code`). PIN acak 6 digit diberikan langsung kepada notulis satu kali saat pembuatan tautan dan harus didistribusikan melalui kanal komunikasi terpisah.

### 10.3 Mitigasi Brute Force & Anti-Enumeration
1. **Rate Limiting**: Dibatasi maksimal 10 percobaan verifikasi per menit per alamat IP.
2. **Penguncian Tautan Otomatis**: Jika terjadi 5 kali kegagalan verifikasi berturut-turut pada suatu tautan, tautan dikunci sementara selama 15 menit (`locked_until`).
3. **Pesan Galat Seragam**: Semua kegagalan validasi (baik nomor salah, nomor tidak terdaftar, atau PIN salah) menghasilkan pesan galat identik:
   `"Nomor WhatsApp atau kode akses tidak sesuai atau tidak memiliki izin akses."`
   Hal ini mencegah penyerang melakukan *user enumeration* terhadap peserta rapat.

### 10.4 Isolasi Bahan Mentah (Zero-Exposure Audio & Transkrip)
- Antarmuka publik `/n/{token}` dan berkas PDF publik **sama sekali tidak memuat tautan, pemutar, maupun cuplikan rekaman audio mentah/gabungan dan transkrip mentah**.
- Hak akses materi mentah tetap eksklusif hanya untuk notulis pembuat rapat dan pimpinan organisasi melalui sesi login resmi terautentikasi.
