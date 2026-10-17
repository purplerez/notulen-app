<?php

namespace App\Console\Commands;

use App\Contracts\SpeechToTextProvider;
use App\Exceptions\RateLimitException;
use Illuminate\Console\Command;
use Throwable;

class SttTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stt:test {file : Jalur berkas audio yang akan ditranskripsi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uji manual integrasi Speech-to-Text provider dengan berkas audio nyata.';

    /**
     * Execute the console command.
     */
    public function handle(SpeechToTextProvider $provider): int
    {
        $filePath = (string) $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("Berkas audio tidak ditemukan di: {$filePath}");

            return self::FAILURE;
        }

        $mime = mime_content_type($filePath) ?: 'audio/webm';
        $driver = config('stt.driver', 'groq');
        $model = config("stt.drivers.{$driver}.model", 'whisper-large-v3-turbo');

        $this->info('==============================================');
        $this->info('  Uji Integrasi Provider Speech-to-Text (STT)  ');
        $this->info('==============================================');
        $this->line("• Berkas   : {$filePath}");
        $this->line('• Ukuran   : '.number_format(filesize($filePath) / 1024, 2).' KB');
        $this->line("• MIME     : {$mime}");
        $this->line("• Driver   : {$driver}");
        $this->line("• Model    : {$model}");
        $this->line('• Bahasa   : '.config('stt.language', 'id'));
        $this->newLine();

        $this->comment('Mengirim berkas audio ke penyedia STT...');
        $startTime = microtime(true);

        try {
            $result = $provider->transcribe($filePath, $mime, config('stt.language', 'id'));
            $elapsed = round(microtime(true) - $startTime, 2);

            $this->info(" Transkripsi Berhasil! ({$elapsed} detik)");
            $this->table(
                ['Parameter', 'Nilai'],
                [
                    ['Durasi Audio Terdeteksi', "{$result->duration} detik"],
                    ['Jumlah Segmen Verbose', count($result->segments)],
                    ['Panjang Karakter Teks', strlen($result->text)],
                ]
            );

            $this->newLine();
            $this->comment('--- Cuplikan Hasil Transkrip ---');
            $this->line(trim($result->text));
            $this->comment('--------------------------------');

            if (! empty($result->segments)) {
                $this->newLine();
                $this->comment('Tiga segmen pertama:');
                foreach (array_slice($result->segments, 0, 3) as $i => $s) {
                    $this->line(sprintf('  [%05.2fs - %05.2fs] %s', $s['start'], $s['end'], $s['text']));
                }
            }

            return self::SUCCESS;
        } catch (RateLimitException $e) {
            $this->newLine();
            $this->error("⚠️ Batas Laju Terlampaui (HTTP 429): {$e->getMessage()}");
            $this->line("• Retry-After : {$e->getRetryAfterSeconds()} detik");
            $this->line('• Kuota Harian: '.($e->isDailyQuota() ? 'Ya (Batas harian habis)' : 'Tidak (Batas RPM sesaat)'));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error(" Gagal melakukan transkripsi: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
