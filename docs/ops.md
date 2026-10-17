# Panduan Operasional & Konfigurasi Speech-to-Text (STT)

Dokumen ini merangkum petunjuk teknis operasional untuk administrator dan operator server mengenai konfigurasi akun speech-to-text (STT), manajemen kunci API, batasan kuota, dan tata cara penggantian penyedia/model (merujuk pada dokumen rancangan Bagian 12).

---

## 1. Penyedia Awal: Groq (Whisper)

Aplikasi menggunakan Groq Cloud untuk transkripsi audio cepat berbiaya rendah dengan model Whisper.

### Batasan Groq (Paket Gratis & Konsol)
- **RPM**: 20 permintaan per menit (di aplikasi dibatasi via `STT_RPM_LIMIT=18` sebagai ambang aman).
- **RPD**: 2.000 permintaan per hari.
- **ASH**: 7.200 detik audio per jam (di aplikasi dibatasi via `STT_ASH_LIMIT=7000` detik per jam).
- **Ukuran Berkas**: Maksimal 25 MB per segmen.
- **Format Keluaran**: `verbose_json` untuk mendapatkan stempel waktu segmen per kalimat.

---

## 2. Langkah Membuat Akun & Mengisi Kunci API

1. Buka [console.groq.com](https://console.groq.com) dan buat akun.
2. Masuk ke menu **API Keys**, klik **Create API Key**.
3. Beri nama kunci yang deskriptif, misalnya `notulen-app-prod`.
4. Salin kunci API yang ditampilkan (hanya muncul sekali).
5. Buka berkas `.env` di server dan masukkan konfigurasi berikut:

```env
STT_DRIVER=groq
STT_LANGUAGE=id
GROQ_API_KEY=gsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
GROQ_STT_MODEL=whisper-large-v3-turbo
GROQ_STT_TIMEOUT=120
STT_RPM_LIMIT=18
STT_ASH_LIMIT=7000
```

> [!CAUTION]
> **Keamanan Kunci**: Jangan pernah memasukkan `GROQ_API_KEY` ke repositori Git, berkas frontend/JavaScript, atau dokumen publik. Kunci ini hanya ada di lingkungan server (`.env`).

---

## 3. Langkah Menerapkan Perubahan Konfigurasi (Wajib)

Setiap kali mengubah berkas `.env` atau konfigurasi di `config/stt.php`, dua perintah ini **wajib** dijalankan agar worker antrean dan aplikasi memuat konfigurasi baru:

```bash
# 1. Bersihkan dan perbarui cache konfigurasi
php artisan config:clear

# 2. Restart pekerja antrean latar belakang
php artisan queue:restart
```

---

## 4. Menguji Integrasi dengan Berkas Nyata

Gunakan perintah artisan bawaan untuk memvalidasi apakah kunci API dan koneksi jaringan berfungsi normal:

```bash
php artisan stt:test /jalur/ke/berkas-audio-contoh.webm
```

Contoh keluaran sukses:
```text
==============================================
  Uji Integrasi Provider Speech-to-Text (STT)  
==============================================
• Berkas   : storage/app/private/sample.webm
• Ukuran   : 412.30 KB
• MIME     : audio/webm
• Driver   : groq
• Model    : whisper-large-v3-turbo
• Bahasa   : id

Mengirim berkas audio ke penyedia STT...
 Transkripsi Berhasil! (1.42 detik)
+-------------------------+-------------+
| Parameter               | Nilai       |
+-------------------------+-------------+
| Durasi Audio Terdeteksi | 59.8 detik  |
| Jumlah Segmen Verbose   | 8           |
| Panjang Karakter Teks   | 420         |
+-------------------------+-------------+
```

---

## 5. Mengganti Penyedia atau Model STT

### Skenario A: Hanya Mengganti Model Groq
Ubah variabel lingkungan di `.env`:
```env
# Pilihan: whisper-large-v3-turbo atau whisper-large-v3
GROQ_STT_MODEL=whisper-large-v3
```
Lalu jalankan `php artisan config:clear && php artisan queue:restart`.

### Skenario B: Mengganti Penyedia (Misal ke Local Whisper / On-Premise)
Jika institusi mewajibkan pemrosesan suara di server lokal (dalam negeri):
1. Buat kelas baru yang mengimplementasikan interface kontrak:
   ```php
   namespace App\Services\STT;

   use App\Contracts\SpeechToTextProvider;
   use App\DTOs\TranscriptionResult;

   class LocalWhisperProvider implements SpeechToTextProvider
   {
       public function transcribe(string $path, string $mime, ?string $language = null): TranscriptionResult
       {
           // Implementasi pemanggilan server lokal via ffmpeg/whisper.cpp/faster-whisper
       }
   }
   ```
2. Daftarkan driver baru di `app/Providers/AppServiceProvider.php`:
   ```php
   return match ($driver) {
       'groq' => $app->make(GroqWhisperProvider::class),
       'local' => $app->make(LocalWhisperProvider::class),
       default => throw new InvalidArgumentException("Driver tidak didukung"),
   };
   ```
3. Ubah `.env`:
   ```env
   STT_DRIVER=local
   ```
4. Bersihkan konfigurasi dan restart antrean.

---

## 6. Pemantauan Pemakaian & Galat (Usage Logs)

Setiap percobaan transkripsi (baik berhasil, ditahan karena batas ASH, atau gagal) dicatat pada tabel `usage_logs`.

Untuk memantau penggunaan:
```sql
-- Total detik audio sukses dalam 1 jam terakhir
SELECT SUM(audio_sec) AS detik_audio_1_jam_terakhir 
FROM usage_logs 
WHERE kind = 'stt' AND status = 'sukses' AND created_at >= NOW() - INTERVAL 1 HOUR;

-- Riwayat galat dan status ditahan per hari
SELECT DATE(created_at) AS tanggal, status, COUNT(*) AS jumlah_percobaan 
FROM usage_logs 
WHERE kind = 'stt' 
GROUP BY DATE(created_at), status;
```
