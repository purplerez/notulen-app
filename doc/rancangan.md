Rancangan Perangkat Lunak: Aplikasi Notulensi Rapat (Laravel 13)
Versi 1.0 (draf untuk pilot) · 15 Oktober 2026 · Disusun dari keputusan yang disepakati selama diskusi perancangan
1. Ringkasan
Aplikasi web mobile-first untuk merekam rapat tatap muka, mengubah suara menjadi teks, menyusun notulen, dan membagikannya. Sasaran: sekolah dan instansi berbahasa Indonesia, dengan penggunaan umum. Arsitektur multitenant sejak awal, tetapi pilot hanya berisi satu organisasi (paket internal, gratis). Dokumen ini memuat keputusan yang sudah disepakati, arsitektur, model data, hak akses, alur, keamanan, retensi, instruksi akun speech-to-text, dan rencana bangun. Baca bagian 15 (asumsi dan hal belum diputuskan) sebelum mulai membangun.
2. Keputusan yang sudah disepakati
Produk dan distribusi
• Fokus rapat tatap muka, dapat dipakai umum. Kop, tanda tangan, daftar hadir, dan tindak lanjut bersifat opsional. Dua gaya notulen: Ringkas dan Lengkap.
• Tanpa API WhatsApp. Distribusi lewat tombol wa.me dan tautan berbagi berbatas waktu (masa berlaku bisa diatur, bisa dicabut), diakses dengan nomor WA terdaftar atau kode rapat, hanya baca, di luar area login.
• Room rapat berisi judul dan peserta; check-in lewat QR yang berganti berkala; kontak peserta dikumpulkan sekali.
Peran
• Pimpinan (label dapat diatur, misalnya Kepala Sekolah): akun hanya baca, semua rapat di organisasinya, tanpa batas waktu, tanpa penanda 'terbatas'. Melihat notulen final, audio mentah, dan transkrip mentah sejak rapat selesai. Tombol opsional Tandai diketahui = konfirmasi sudah membaca; hanya pimpinan yang dapat menandai.
• Notulis: membuat dan merekam rapat; dapat mencantumkan status 'rapat direkam untuk notulensi'.
• Admin: dapat membaca log akses, tidak dapat mengakses audio maupun transkrip mentah.
• Peserta: tanpa akun.
Data
• Audio dihapus 1 bulan sejak disimpan; transkrip mentah tetap tersimpan dan berlabel 'Transkrip otomatis, belum diperiksa'.
• Log akses (audio dan transkrip mentah) dapat dilihat pimpinan, notulis, dan admin; log tidak dapat diubah atau dihapus; penghapusan rapat ikut tercatat.
• Revisi notulen final hanya oleh notulis pembuat dan admin; setiap revisi membuat versi baru, versi lama tersimpan. Status 'diketahui' tetap melekat pada versi yang dibaca, dengan penanda 'ada revisi baru'.
• Tanpa teks persetujuan peserta.
Bisnis
• Lembaga pemilik gratis (pilot); SaaS berbayar ke lembaga lain nanti; penagihan ditunda sampai ada pelanggan yang membayar.
3. Risiko yang diterima secara sadar
• Tanpa teks persetujuan peserta: ada risiko kepercayaan dan kepatuhan pelindungan data pribadi. Keputusan pemilik.
• Pembatasan peran hanya berlaku di tingkat aplikasi; siapa pun yang memegang server atau database tetap secara teknis dapat membuka bahan mentah.
• Nomor WA bukan bukti identitas; rekan satu daftar bisa menyamar. Untuk rapat sensitif gunakan mode kode rapat.
• Audio dikirim ke penyedia STT di luar negeri; untuk sekolah negeri keputusan lokasi data belum final.
• Persetujuan sekolah diurus pemilik di luar aplikasi.
4. Stack dan arsitektur
• Framework: Laravel 13. Wajib PHP 8.3 atau lebih baru (8.3 sampai 8.5). Pastikan semua paket pihak ketiga kompatibel dengan Laravel 13 sebelum dipakai.
• Basis data: MySQL 8. Antrean: driver database untuk pilot, pindah ke Redis bila perlu. Penjadwal: cron memanggil schedule:run. Pekerja antrean dijalankan Supervisor.
• Penyimpanan: disk privat lokal (bukan public), jalur orgs/{id}/meetings/{id}/.... ffmpeg terpasang di server.
• Antarmuka: Blade + Alpine.js + Tailwind; halaman rekam memakai modul JavaScript tersendiri; PWA (manifest) agar bisa dipasang di layar utama.
• PDF: DomPDF untuk pilot (tata letak sederhana: kop, tanda tangan, daftar hadir); evaluasi ulang bila format menuntut lebih.
• Layanan eksternal di balik interface: SpeechToTextProvider dan Summarizer, sehingga penyedia bisa diganti lewat konfigurasi.
Browser (HP) --unggah segmen--> Laravel (HTTP) --dispatch--> Queue worker
   |                                |                           |-- TranscribeSegment --> penyedia STT
   |-- QR check-in / tautan baca    |-- Policy + audit_logs     |-- MergeAudio (ffmpeg)
   |                                v                           |-- GenerateMinutes --> penyedia LLM
   |                        MySQL + storage privat <------------+
Prinsip kode: controller tipis; logika di kelas Action; semua akses lewat Policy; pemanggilan layanan eksternal hanya lewat interface; seluruh model milik tenant memakai trait BelongsToOrganization (global scope).
5. Struktur kode
app/
  Actions/        CreateMeeting, CheckInAttendee, StoreAudioSegment, FinishRecording,
                  FinalizeMinutes, ReviseMinutes, AcknowledgeMinutes, CreateShareLink,
                  PurgeExpiredAudio
  Contracts/      SpeechToTextProvider, Summarizer
  Services/Stt/   GroqWhisperProvider, (LocalWhisperProvider nanti)
  Services/Minutes/  LlmSummarizer
  Jobs/           TranscribeSegment, AssembleTranscript, MergeAudio, GenerateMinutes
  Models/         Organization, User, Meeting, Attendee, AudioSegment, TranscriptSegment,
                  Marker, Minute, ShareLink, AuditLog, UsageLog, ...
  Models/Concerns/BelongsToOrganization
  Policies/       MeetingPolicy, MinutePolicy, RawMaterialPolicy, AuditLogPolicy
  Http/Controllers/  Dashboard, Recorder, CheckIn, Minutes, PublicShare, AudioStream, Audit
  Console/Commands/  audio:purge, audio:remind
config/           stt.php, retention.php
6. Model data (MySQL 8)
Semua tabel milik tenant memiliki organization_id.
• organizations: id, name, slug, address_text, kop_path (opsional), plan (internal|free|paid), quota_minutes, parent_id (opsional), settings (json: label pimpinan, masa berlaku tautan bawaan, dll.)
• users: id, organization_id, name, email, password, role (admin|notulis|pimpinan), is_active
• org_contacts: id, organization_id, name, position, phone_e164 (unik per organisasi). Kontak dikumpulkan sekali.
• organization_signatories: id, organization_id, name, position, role_label (Mengetahui, Ketua Rapat, Notulis), sort
• meetings: id, organization_id, created_by, title, meeting_date, location, style (ringkas|lengkap), use_followup, use_kop, use_signature, show_attendance, show_recorded_notice, status (draft|recording|uploaded|transcribing|ready|failed), started_at, ended_at, expected_segments, audio_saved_at, audio_expires_at, audio_deleted_at, merged_audio_path
• meeting_signatories: meeting_id, signatory_id
• attendees: id, meeting_id, contact_id, name, position, phone_e164, present, checked_in_at, method (qr|manual), is_guest, guest_status (pending|approved|rejected)
• audio_segments: id, meeting_id, seq, path, mime, size_bytes, duration_sec, checksum, status. UNIQUE(meeting_id, seq)
• transcript_segments: id, meeting_id, audio_segment_id, start_sec, end_sec, text, edited_text, edited_by. Teks asli dan hasil edit terpisah; selisihnya adalah ukuran tingkat koreksi manual.
• markers: id, meeting_id, type (keputusan|penting|tugas), at_sec, note
• minutes: id, meeting_id, version, status (draft|final), content_json, created_by, finalized_at, revision_reason. UNIQUE(meeting_id, version)
• minute_acknowledgements: id, minute_id, user_id, acknowledged_at. UNIQUE(minute_id, user_id). Melekat pada versi.
• action_items: id, meeting_id, minute_id, description, assignee_name, due_date, status (hanya bila use_followup aktif)
• share_links: id, meeting_id, token_hash, access_mode (phone|code), code_hash, recipients_scope (hadir|semua_undangan), expires_at, revoked_at, failed_attempts, locked_until, created_by
• audit_logs (hanya-tambah): id, organization_id, meeting_id (tanpa foreign key agar bertahan setelah rapat dihapus), actor_user_id, actor_attendee_id, action (audio_play|transcript_view|link_open|link_create|minute_finalize|minute_revise|minute_acknowledge|meeting_delete|audio_purge|attendee_checkin), meta (json), created_at
• usage_logs: id, organization_id, meeting_id, provider, kind (stt|llm), audio_sec, tokens, est_cost
7. Peran dan hak akses
Tanda * = asumsi yang perlu dikonfirmasi (lihat bagian 15).
Tindakan
Admin
Notulis (pembuat)
Pimpinan
Peserta (tautan)
Membuat room rapat
Ya*
Ya
Tidak
Tidak
Merekam, menyunting transkrip dan draf notulen
Tidak
Ya
Tidak
Tidak
Memfinalkan notulen
Tidak*
Ya
Tidak
Tidak
Merevisi notulen final (versi baru)
Ya
Ya
Tidak
Tidak
Membaca notulen final
Ya*
Ya
Ya, semua rapat
Ya, lewat tautan
Audio dan transkrip mentah
Tidak
Ya
Ya, semua rapat
Tidak
Menandai 'diketahui'
Tidak
Tidak
Ya
Tidak
Membaca log akses
Ya, semua
Ya, rapat sendiri*
Ya, semua
Tidak
Menghapus rapat
Ya*
Ya*
Tidak
Tidak
Mengatur organisasi (kop, penandatangan, pengguna)
Ya
Tidak
Tidak
Tidak
Mengubah konfigurasi STT/LLM dan kunci API
Tidak
Tidak
Tidak
Tidak
Baris terakhir hanya dapat dilakukan operator server lewat berkas .env, bukan lewat peran aplikasi mana pun. Implementasi: satu Policy per sumber daya; pemeriksaan selalu lewat Gate/authorize, tidak tersebar dalam query.
8. Alur utama
8.1 Persiapan dan check-in
1. Notulis membuat room: judul, tanggal, lokasi, peserta (dari org_contacts atau input baru), gaya notulen dan toggle (tindak lanjut, daftar hadir, kop, tanda tangan, label rekaman).
2. Layar host menampilkan QR berisi URL check-in dengan kode = HMAC(rahasia, id rapat + jendela waktu 30 detik). Server menerima jendela saat ini dan sebelumnya; tanpa tabel token.
3. Peserta scan, memilih atau mengetik nama dan nomor WA (divalidasi, dinormalisasi ke E.164: 08... menjadi 628...), lalu menekan Hadir. Kontak tersimpan sekali; rapat berikutnya cukup konfirmasi (dikenali dari nomor). Tamu di luar daftar berstatus pending sampai disetujui host. Notulis dapat mencentang manual.
4. Bila show_recorded_notice aktif dan rapat direkam, label 'Rapat direkam untuk notulensi' tampil di halaman check-in (label, bukan formulir persetujuan).
8.2 Perekaman
• getUserMedia + MediaRecorder dihentikan dan dimulai ulang tiap sekitar 60 detik agar setiap segmen menjadi berkas mandiri. Pilih format dengan MediaRecorder.isTypeSupported (Chrome biasanya webm/opus, Safari mp4/aac). Wake Lock API menjaga layar menyala; AnalyserNode untuk indikator level suara; tampilkan status koneksi dan panduan penempatan HP.
• Antrean unggah di IndexedDB dengan retry bertahap. Setiap unggahan membawa id rapat, seq, dan checksum SHA-256. Server idempotent: UNIQUE(meeting_id, seq); unggah ulang dengan checksum sama dijawab sukses tanpa menggandakan data.
• Selesai: POST /meetings/{id}/finish dengan expected_segments. Jika seq 1..N lengkap: set audio_saved_at = sekarang, audio_expires_at = +1 bulan, status uploaded, jalankan pipeline. Jika kurang, klien melanjutkan mengunggah sisa.
• Keterbatasan: perekaman web saat layar mati atau tab di latar belakang tidak dijamin, terutama di iOS. Inilah yang diuji paling awal (Fase 0). Kata di batas segmen bisa terpotong; diterima untuk pilot.
8.3 Transkripsi (antrean)
• TranscribeSegment per segmen: kirim ke STT lewat interface (language=id, verbose_json), simpan teks dan stempel waktu (offset menurut urutan segmen), tulis usage_logs. Retry dengan jeda bertahap untuk galat 429 dan 5xx; setelah habis, status failed dan notulis dapat mencoba ulang. Audio tidak dihapus saat gagal.
• AssembleTranscript setelah semua segmen selesai. MergeAudio menggabungkan segmen dan mentranskode ke AAC (m4a) agar dapat diputar lintas browser. Lalu GenerateMinutes (bagian 9).
8.4 Notulen
Draf otomatis dibuat sebagai versi 1 (draft). Notulis menyunting transkrip (kolom edited_text) dan notulen, lalu memfinalkan. Hanya versi final yang tampil ke pimpinan dan peserta.
8.5 Revisi dan 'diketahui'
• Revisi notulen final (notulis pembuat atau admin) menyalin versi final ke draf baru dengan revision_reason; selama draf baru belum final, yang tampil tetap versi final terakhir. Setelah difinalkan menjadi versi berikutnya; versi lama tersimpan.
• Pimpinan menandai 'diketahui' per versi. Tampilan: 'Diketahui (versi N)', ditambah 'Ada revisi baru' bila versi final terbaru lebih tinggi. Pimpinan dapat menandai lagi untuk versi baru.
8.6 Distribusi
• Halaman notulen menyediakan: salin tautan, dan daftar penerima (hadir saja atau semua undangan) dengan tombol wa.me/{nomor tanpa + dan tanpa nol depan}?text={teks terenkode}. Status 'sudah diklik' hanya penanda; status 'dibuka' berasal dari audit_logs.
• Halaman publik /n/{token} memakai gerbang nomor atau kode (bagian 10).
8.7 Retensi
Perintah terjadwal audio:purge (harian, malam) menghapus segmen, berkas gabungan, dan salinan sementara untuk rapat yang audio_expires_at-nya lewat, mengisi audio_deleted_at, dan menulis audit_logs. audio:remind memberi pengingat di dalam aplikasi kepada notulis, sekitar 7 hari sebelum audio terhapus, bila notulen belum final. Halaman rapat menampilkan 'Audio tersedia sampai [tanggal]' atau 'Audio dihapus pada [tanggal]'. Transkrip mentah tidak ikut dihapus.
9. Pembuatan notulen otomatis
• Interface Summarizer::summarize(transkrip, opsi rapat) menghasilkan JSON: ringkasan, agenda, pembahasan, keputusan, tindak_lanjut. Bagian yang tidak sesuai gaya atau toggle tidak dibuat; semua bagian boleh kosong.
• Aturan prompt: pakai hanya isi transkrip; jangan mengarang nama, tugas, atau tanggal; bila tidak ada tindak lanjut, kosongkan; keluaran JSON valid; bahasa Indonesia baku; penanda manual (keputusan, penting, tugas) dipakai sebagai petunjuk. Bagian tindak lanjut hanya diminta bila use_followup aktif.
• Transkrip panjang (1-2 jam): dipecah menjadi blok, tiap blok diringkas, lalu digabung. Validasi JSON dan ulangi bila tidak valid.
• Hasilnya selalu draf; notulis wajib memeriksa sebelum final. Penyedia LLM belum dipilih (bagian 15).
10. Keamanan
Tautan berbagi (/n/{token}, di luar login)
• Token acak minimal 48 karakter; hanya hash (SHA-256) yang disimpan. ID rapat tidak muncul di URL.
• Mode phone: nomor yang diketik dinormalisasi ke E.164 lalu dicocokkan dengan daftar penerima. Mode code: PIN acak per rapat (hash disimpan).
• Throttle per IP dan per tautan, kunci sementara setelah beberapa kali gagal, pesan galat seragam (tidak membedakan nomor tidak ada dan nomor salah).
• Setelah lolos: sesi pendek terikat pada tautan itu. Rute ini hanya punya aksi baca.
• Header: Referrer-Policy: no-referrer, X-Robots-Tag: noindex, Cache-Control: no-store. Tag og:title generik ('Notulen rapat'), bukan judul rapat.
• Jangan memakai tautan sekali pakai (pratinjau WhatsApp bisa menghabiskannya). PDF disajikan lewat controller yang memeriksa sesi, bukan URL penyimpanan publik.
• Masa berlaku dapat diatur per rapat; tautan dapat dicabut. Kedaluwarsa tautan tidak menghapus arsip di area login.
Audio dan transkrip mentah
• Audio diputar lewat rute bertanda tangan berumur pendek (mendukung permintaan rentang). Tanpa tombol unduh; ini mengurangi, bukan mencegah, penyalinan.
• Setiap mulai putar audio dan setiap pembukaan transkrip mentah tercatat di audit_logs.
Umum
• audit_logs hanya-tambah: akun database aplikasi hanya memiliki INSERT dan SELECT pada tabel ini; model Eloquent menolak update dan delete.
• Throttle login, hash kata sandi, CSRF, validasi ukuran dan tipe unggahan, kunci API hanya di .env server. Verifikasi dua langkah untuk pimpinan dan admin masuk daftar berikutnya.
• Rotasi cadangan database dan berkas tidak boleh lebih lama dari retensi audio, atau 'dihapus setelah 1 bulan' tidak benar secara praktis.
• Audio yang sudah dikirim ke penyedia STT berada di luar kendali aplikasi; periksa kebijakan retensi penyedia.
11. Multitenant
• Satu basis data, kolom organization_id pada semua tabel milik tenant; trait BelongsToOrganization menerapkan global scope dan mengisi kolom otomatis.
• Tes otomatis yang mencoba akses lintas organisasi wajib lulus sebelum tenant kedua masuk.
• Jalur penyimpanan per organisasi. Paket per organisasi (internal, free, paid), kuota menit, dan batas durasi per rekaman diperiksa saat memulai rekaman; pemutus biaya menghentikan pemrosesan baru bila pemakaian melonjak.
• parent_id (opsional) pada organisasi untuk yayasan atau dinas dengan banyak sekolah.
• Path URL (/o/{slug}/...) untuk awal; subdomain per tenant ditunda.
12. Konfigurasi dan akun speech-to-text
Bagian ini menjawab permintaan keterangan akun untuk mengubah suara menjadi teks. Yang dimaksud: akun pada penyedia STT, cara mengatur, dan cara menggantinya.
12.1 Penyedia awal: Groq (Whisper)
• Endpoint kompatibel OpenAI: https://api.groq.com/openai/v1/audio/transcriptions.
• Model: whisper-large-v3-turbo (sekitar $0,04 per jam audio) dan whisper-large-v3 (sekitar $0,111 per jam). Tabel penyedia mencantumkan galat kata large-v3 lebih rendah (10,3% vs 12%), tetapi itu bukan pengukuran bahasa Indonesia. Uji keduanya pada rekaman rapat nyata sebelum memilih.
• Batas unggahan 25 MB per berkas; segmen 60 detik jauh di bawahnya. Whisper menurunkan audio ke 16 kHz mono sebelum diproses.
• Parameter: language=id (opsional; auto-detect bila kosong. Paksa id bila rapat hampir seluruhnya berbahasa Indonesia, jangan bila banyak istilah Inggris; uji) dan response_format=verbose_json (menghasilkan stempel waktu segmen).
12.2 Langkah membuat dan memasang akun
1. Buka console.groq.com dan daftar akun.
2. Masuk ke menu API Keys, pilih Create API Key, beri nama jelas (misalnya notulensi-pilot).
3. Salin kunci saat ditampilkan dan simpan di .env server. Jangan menaruhnya di repositori, kode frontend, atau pesan obrolan.
4. Isi .env (blok di bawah), lalu jalankan php artisan config:clear dan php artisan queue:restart agar pekerja antrean memakai kunci baru.
5. Uji satu segmen dengan perintah curl (di bawah) sebelum menyambungkan ke aplikasi.
6. Periksa halaman batas dan penggunaan di console. Tier gratis punya batas harian per akun dan tanpa jaminan layanan; pindah ke tier berbayar sebelum tenant dari luar masuk. Antarmuka console dapat berubah, jadi ikuti petunjuk di sana.
STT_DRIVER=groq
STT_LANGUAGE=id
GROQ_API_KEY=isi_kunci_di_sini
GROQ_STT_MODEL=whisper-large-v3-turbo
curl https://api.groq.com/openai/v1/audio/transcriptions -H "Authorization: Bearer $GROQ_API_KEY" -F model=whisper-large-v3-turbo -F language=id -F response_format=verbose_json -F file=@contoh.webm
12.3 Siapa yang boleh mengubah
Hanya operator server (pemegang .env). Admin organisasi tidak melihat kunci dan tidak dapat mengganti penyedia. Di versi SaaS, kunci dipegang platform, bukan per tenant. Rotasi berkala; jika kunci bocor: cabut di console, buat baru, perbarui .env, jalankan config:clear dan queue:restart.
12.4 Mengganti penyedia atau model
1. Ganti model saja: ubah GROQ_STT_MODEL, bersihkan konfigurasi, restart pekerja.
2. Ganti penyedia: buat kelas baru yang mengimplementasikan SpeechToTextProvider, daftarkan di config/stt.php dan di binding AppServiceProvider, lalu ubah STT_DRIVER. Kontrak interface tetap sama, jadi tidak ada kode lain yang berubah.
3. Jalankan kedua penyedia pada rekaman yang sama dan bandingkan hasil serta biaya dari usage_logs sebelum beralih penuh.
4. Jika sekolah atau pembeli mensyaratkan pemrosesan di dalam negeri, jalur yang tersedia adalah Whisper di server sendiri (LocalWhisperProvider). Biaya server, GPU, dan beban kerja perlu dihitung dulu.
12.5 Ketika STT gagal atau melambat
Job TranscribeSegment mencoba ulang dengan jeda bertahap (khusus galat 429 dan 5xx). Setelah percobaan habis, segmen bertanda failed, audio tidak dihapus, dan notulis dapat menekan tombol coba ulang. Setiap galat dicatat. Pantau pemakaian dari usage_logs dan console penyedia setiap bulan.
12.6 Akun lain yang akan dibutuhkan
Penyedia LLM untuk ringkasan (belum dipilih; lihat bagian 15). Pola konfigurasinya sama: config/summarizer.php, kunci di .env, interface Summarizer.
13. Pengujian
• Isolasi tenant (akses lintas organisasi ditolak).
• Matriks Policy (tiap peran dan tindakan di bagian 7).
• Unggah segmen idempotent dan finish yang menolak segmen tidak lengkap.
• Tautan berbagi: token salah, nomor di luar daftar, throttle dan kunci sementara, kedaluwarsa, pencabutan.
• Revisi menghasilkan versi baru; status 'diketahui' tetap pada versi lama.
• audio:purge menghapus berkas, mencatat log, dan tidak menyentuh transkrip mentah.
• Skenario ujung ke ujung di perangkat nyata (bagian 14).
14. Rencana bangun
1. Fase 0, uji kelayakan: halaman rekam, unggah per segmen, TranscribeSegment ke STT. Uji di dua merek Android (dan iPhone bila ada): layar mati, pindah aplikasi, sinyal putus, rekaman 90 menit.
2. Fase 1a: organisasi, pengguna dan peran, room rapat, QR check-in, kontak organisasi.
3. Fase 1b: transkrip, editor sinkron audio, GenerateMinutes, versi notulen, finalisasi.
4. Fase 1c: PDF, tautan berbagi dan wa.me, dashboard pimpinan, 'diketahui', audit_logs, audio:purge dan audio:remind, usage_logs.
5. Gerbang A lolos bila skenario ujung ke ujung berhasil tiga kali berturut-turut tanpa perbaikan di antaranya: buat room; QR check-in; rekam 60+ menit utuh; transkrip; notulen; PDF; tautan (nomor terdaftar diterima, lainnya ditolak, kedaluwarsa dan cabut berfungsi); pimpinan melihat daftar dan menandai diketahui; audio terhapus sesuai jadwal.
6. Gerbang B (layak ditawarkan berbayar): tingkat koreksi manual (selisih text dan edited_text), rapat aktif per minggu yang bertahan, biaya per rapat, persentase tautan dibuka, hasil banding dengan produk pembanding, dan komitmen dari lembaga lain.
Prasyarat non-teknis (di luar aplikasi): persetujuan sekolah, keputusan lokasi data dan penyedia, daftar uji tertulis, dan contoh notulen dari 2-3 lembaga untuk tata letak PDF.
15. Asumsi dan hal yang belum diputuskan
Asumsi dalam rancangan ini (ubah bila tidak sesuai):
• Admin dapat membuat room dan membaca notulen final; admin tidak memfinalkan notulen.
• Notulis hanya mengakses rapat yang ia buat; notulis melihat log untuk rapat sendiri.
• Penghapusan rapat oleh notulis pembuat dan admin.
• Tautan berbagi selalu menampilkan versi final terbaru, dengan penanda 'direvisi'.
• Masa berlaku tautan bawaan 7 hari; penerima bawaan: peserta yang hadir.
• Revisi dikerjakan sebagai salinan draf; versi final lama tetap tampil sampai versi baru difinalkan.
Belum diputuskan:
1. Penyedia dan lokasi pemrosesan LLM, serta keputusan akhir lokasi data (STT di luar negeri untuk sekolah negeri).
2. Siapa yang boleh membuat room dan menjadi notulis di sekolah.
3. Apakah notulis melihat status 'diketahui' dan apakah dicetak di PDF.
4. Isi dan tata letak PDF (menunggu contoh notulen, termasuk label penandatangan 'Mengetahui').
5. Hosting dan kebijakan cadangan (wilayah server dan rotasi sesuai retensi 1 bulan).
6. Kebijakan akses operator untuk versi SaaS.
16. Lampiran: kerangka kode
Contoh kerangka, perlu diuji dan disesuaikan.
<?php
namespace App\Contracts;

interface SpeechToTextProvider
{
    /** @return array{text:string, segments:array, duration:float, raw:array} */
    public function transcribe(string $absolutePath, string $mime, string $language = 'id'): array;
}
<?php
namespace App\Services\Stt;

use App\Contracts\SpeechToTextProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GroqWhisperProvider implements SpeechToTextProvider
{
    public function transcribe(string $absolutePath, string $mime, string $language = 'id'): array
    {
        $cfg = config('stt.drivers.groq');

        $response = Http::withToken($cfg['key'])
            ->timeout($cfg['timeout'])
            ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
            ->post($cfg['endpoint'], [
                'model' => $cfg['model'],
                'language' => $language,
                'response_format' => 'verbose_json',
                'temperature' => 0,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('STT gagal: HTTP ' . $response->status());
        }

        $json = $response->json();

        return [
            'text' => $json['text'] ?? '',
            'segments' => $json['segments'] ?? [],
            'duration' => (float) ($json['duration'] ?? 0),
            'raw' => $json,
        ];
    }
}
<?php
// config/stt.php
return [
    'driver' => env('STT_DRIVER', 'groq'),
    'language' => env('STT_LANGUAGE', 'id'),
    'drivers' => [
        'groq' => [
            'key' => env('GROQ_API_KEY'),
            'endpoint' => 'https://api.groq.com/openai/v1/audio/transcriptions',
            'model' => env('GROQ_STT_MODEL', 'whisper-large-v3-turbo'),
            'timeout' => 120,
        ],
    ],
];
// AppServiceProvider::register()
$this->app->bind(\App\Contracts\SpeechToTextProvider::class, fn () => match (config('stt.driver')) {
    'groq' => new \App\Services\Stt\GroqWhisperProvider(),
    default => throw new \InvalidArgumentException('Driver STT tidak dikenal'),
});

// routes/console.php
Schedule::command('audio:purge')->dailyAt('02:00');
Schedule::command('audio:remind')->dailyAt('08:00');
Percobaan ulang untuk galat 429 dan 5xx diatur di job ($tries dan backoff()), bukan di dalam provider.
17. Sumber
• Laravel News, 'Laravel 13 Released' (syarat PHP 8.3, jadwal dukungan): laravel-news.com/laravel-13-released
• GroqDocs, 'Speech to Text' (endpoint, model, harga, batas berkas): console.groq.com/docs/speech-to-text