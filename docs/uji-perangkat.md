# Daftar Periksa Gerbang A (Gate A Acceptance Checklist)
## Protokol Pengujian Manual Perangkat Fisik (Web Audio Recording)

Dokumen ini merupakan panduan dan daftar periksa resmi (**Gerbang A**) untuk memvalidasi stabilitas dan keandalan perekaman audio berbasis peramban (`MediaRecorder`, `getUserMedia`, `Screen Wake Lock`, dan antrean `IndexedDB`) pada perangkat fisik nyata sebelum aplikasi dinyatakan siap produksi.

> [!IMPORTANT]
> **Mengapa Gerbang A Wajib Pengujian Manual Fisik?**
> Perilaku manajemen daya agresif (*battery saver/optimization*), pemutusan mikrofon latar belakang, penangguhan tab (*tab suspension*), dan interupsi panggilan telepon seluler oleh sistem operasi ponsel **TIDAK DAPAT** disimulasikan oleh headless browser maupun pengujian unit/fitur PHP. Validasi fisik pada ponsel nyata adalah satu-satunya cara membuktikan keandalan rekam.

---

## 1. Kriteria Kelulusan Gerbang A (Acceptance Criteria)

Sistem dinyatakan **LOLOS GERBANG A** jika dan hanya jika memenuhi kriteria mutlak berikut:

1. **3 Kali Rekaman Penuh Berturut-turut Tanpa Cacat**:
   - Berhasil menyelesaikan minimal **3 kali sesi rekaman penuh (durasi nyata $\ge$ 60–90 menit)** berturut-turut tanpa kehilangan satu segmen pun ($0\%$ *data loss*).
2. **Keterwakilan Perangkat Minimal**:
   - Minimal **1 perangkat Android** (disarankan Samsung OneUI atau Xiaomi HyperOS).
   - Minimal **1 perangkat Apple iPhone** (iOS Safari versi 16/17/18).
3. **Integritas Segmen Audio**:
   - Seluruh segmen (nomor urut 1 sampai $N$) berhasil terunggah ke server dengan checksum SHA-256 yang valid, file tergabung utuh tanpa celah suara hening akibat kegagalan sinkronisasi antrean.
4. **Tidak Ada Rekaman Menggantung**:
   - Status rapat berpindah mulus dari `recording` $\rightarrow$ `uploaded` $\rightarrow$ `ready` (transkripsi selesai).

---

## 2. Prasyarat Lingkungan Uji

1. **Protokol HTTPS Wajib**:
   - Web API `getUserMedia`, `crypto.subtle`, dan `navigator.wakeLock` secara ketat diblokir oleh browser pada koneksi HTTP tidak aman (kecuali localhost).
   - Untuk pengujian jaringan lokal/kantor, gunakan sertifikat SSL valid atau tunnel HTTPS resmi (Herd Pro, Ngrok, Cloudflare Tunnel).
2. **Izin Peramban**:
   - Mikrofon berstatus *Allowed* (Diizinkan).
3. **Kondisi Baterai**:
   - Minimal 50% atau tersambung dengan charger untuk menghindari *extreme battery saver mode* dari OS ponsel.
4. **Mode Jangan Ganggu (DND)**:
   - Notulis sangat dianjurkan mengaktifkan *Do Not Disturb* selama merekam rapat resmi.

---

## 3. Matriks Skenario Uji Fisik (SKN-01 s/d SKN-07)

Setiap skenario berikut wajib diuji dan dicatat perilakunya:

| ID | Skenario Pengujian | Prosedur Langkah Uji | Hasil yang Diharapkan | Kriteria Kritis |
|---|---|---|---|---|
| **SKN-01** | **Rotasi Segmen ~60 Detik & Screen Wake Lock** | 1. Buka halaman rekam rapat.<br>2. Tekan tombol *Mulai Rekam*.<br>3. Letakkan ponsel di meja, diamkan selama 10 menit tanpa menyentuh layar sama sekali. | Layar ponsel tetap menyala (tidak meredup, tidak *sleep/lock* otomatis). Segmen audio terbentuk dan terunggah otomatis setiap ~60 detik. | Wake Lock API menjaga layar aktif; segmen tidak terpotong. |
| **SKN-02** | **Penguncian Manual Tombol Daya (Power Button)** | 1. Sedang merekam (menit ke-2).<br>2. Tekan tombol Power fisik untuk mematikan layar ponsel.<br>3. Biarkan terkunci selama 2 menit.<br>4. Nyalakan dan buka kunci ponsel kembali. | *Catat perilaku*: Pada iOS Safari / Android tertentu, perekam mungkin menghasilkan segmen senyap atau terjeda oleh OS. Saat layar dibuka, sistem menampilkan banner status, Wake Lock dipulihkan, dan segmen sebelum layar dikunci tetap aman tersimpan. | Tidak terjadi crash aplikasi; audio sebelum penguncian tidak hilang. |
| **SKN-03** | **Pindah Aplikasi (Multitasking / Home Button)** | 1. Sedang merekam.<br>2. Tekan tombol Home atau beralih ke WhatsApp / Catatan selama 1 menit.<br>3. Kembali ke tab browser notulen. | Banner peringatan muncul mengingatkan notulis agar tab tetap aktif di layar utama. Segmen yang selesai sebelum berpindah aplikasi telah terunggah atau tersimpan aman di IndexedDB. | Perekam pulih tanpa galat fatal (*unhandled exception*). |
| **SKN-04** | **Panggilan Telepon Masuk (Interupsi Audio)** | 1. Sedang merekam.<br>2. Lakukan panggilan telepon seluler atau panggilan WhatsApp masuk ke ponsel uji.<br>3. Biarkan berdering 15 detik, lalu tolak (*Decline*).<br>4. Lanjutkan rekaman. | Perekam menangani event interupsi mikrofon dengan anggun. Segmen sebelum panggilan masuk tersimpan di antrean IndexedDB. Sesi rekam dapat dilanjutkan. | Antrean IndexedDB tidak rusak; rekaman dapat dituntaskan. |
| **SKN-05** | **Putus Sinyal (Mode Pesawat) & Pemulihan Otomatis** | 1. Sedang merekam di menit ke-3.<br>2. Aktifkan *Airplane Mode* (koneksi putus total).<br>3. Biarkan rekaman berjalan 3 menit (terbentuk 3 segmen baru).<br>4. Amati antrean: badge *Offline* aktif, counter antrean lokal bertambah (1..2..3).<br>5. Matikan *Airplane Mode* (internet pulih). | Tanpa memuat ulang halaman (*zero page reload*), worker pengunggah otomatis mendeteksi status daring dan menguras seluruh antrean IndexedDB ke server hingga tuntas. | Semua segmen luring berhasil terunggah berurutan tanpa duplikasi checksum. |
| **SKN-06** | **Rekaman Durasi Penuh (60–90 Menit)** | 1. Letakkan ponsel di tengah meja rapat.<br>2. Mulai rekam selama 90 menit tanpa henti dengan aktivitas audio ruangan.<br>3. Amati performa memori peramban.<br>4. Tekan *Selesai Rekam*. | Ponsel tidak mengalami *overheat* ekstrem. Peramban tidak kehabisan memori (*out-of-memory crash*). Seluruh ~90 segmen terunggah lengkap dari seq 1 hingga seq 90. | 100% segmen tersimpan; kuota menit terpotong presisi. |
| **SKN-07** | **Penyelesaian dengan Segmen Tertunda (Offline Finish)** | 1. Saat rekaman berlangsung, putuskan koneksi internet.<br>2. Tekan tombol *Selesai Rekam*.<br>3. Sambungkan kembali internet. | Tombol menampilkan status *Menyelesaikan & Mengunggah Antrean...*. Sistem menahan pengiriman sinyal `finish` ke server sampai antrean IndexedDB kosong. Setelah seluruh segmen terunggah, barulah permintaan `finish` dikirim. | Server tidak menerima `finish` dengan segmen bolong (*missing segments error* dicegah). |

---

## 4. Log Rekapitulasi Uji Fisik Gerbang A

Gunakan tabel berikut untuk mendokumentasikan setiap sesi pengujian formal:

| Sesi # | Perangkat (Merk & Seri) | Versi OS | Peramban & Versi | Durasi Uji (menit) | Segmen (Target / Aktual) | Hasil (Lolos / Gagal) | Catatan Penguji & Temuan | Tanda Tangan Penguji |
|:---:|:---|:---|:---|:---:|:---:|:---:|:---|:---:|
| **UJI-01** | *Contoh: Samsung Galaxy A54* | *Android 14 (OneUI 6.1)* | *Chrome 128* | 90 mnt | 90 / 90 | **LOLOS** | Wake Lock stabil, suhu baterai normal 38°C, antrean IndexedDB bersih. | [ Ahmad ] |
| **UJI-02** | *Contoh: iPhone 13* | *iOS 17.5* | *Mobile Safari 17* | 90 mnt | 90 / 90 | **LOLOS** | Layar menyala penuh, audio jernih, transkripsi pipeline sukses. | [ Siti ] |
| **UJI-03** | *Contoh: Xiaomi Redmi Note 12* | *HyperOS 1.0 (Android 14)* | *Chrome 128* | 90 mnt | 90 / 90 | **LOLOS** | Uji mode pesawat di menit ke-40 terunggah sempurna saat online kembali. | [ Budi ] |
| **UJI-04** | | | | | / | | | |
| **UJI-05** | | | | | / | | | |

---

## 5. Lembar Pengesahan Kelulusan Gerbang A

Setelah 3 sesi rekaman penuh berturut-turut pada minimal satu Android dan satu iPhone dinyatakan lolos tanpa cacat:

| Peran | Nama Terang | Tanggal Pengesahan | Status Keputusan | Tanda Tangan |
|---|---|---|:---:|:---:|
| **Lead Quality Assurance (QA)** | ................................... | .......................... | [ ] Disetujui / [ ] Ditolak | ................................... |
| **Lead Developer** | ................................... | .......................... | [ ] Disetujui / [ ] Ditolak | ................................... |
| **Product Owner** | ................................... | .......................... | [ ] Disetujui / [ ] Ditolak | ................................... |

> **Catatan Keputusan**:
> Dengan ditandatanganinya lembar ini, modul perekaman audio web dinyatakan **Lolos Gerbang A** dan diizinkan untuk digunakan pada rapat resmi institusi.
