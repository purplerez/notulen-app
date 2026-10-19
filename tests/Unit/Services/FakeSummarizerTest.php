<?php

namespace Tests\Unit\Services;

use App\Services\Minutes\FakeSummarizer;
use Tests\TestCase;

class FakeSummarizerTest extends TestCase
{
    private FakeSummarizer $summarizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->summarizer = new FakeSummarizer;
    }

    public function test_summarize_returns_valid_structure(): void
    {
        $transcript = 'Rapat dimulai pukul sembilan pagi. Seluruh peserta menyetujui program kerja semester depan. Notulis diminta menindaklanjuti koordinasi dengan pihak dinas.';
        $result = $this->summarizer->summarize($transcript, [
            'style' => 'ringkas',
            'use_followup' => true,
        ]);

        $this->assertArrayHasKey('ringkasan', $result);
        $this->assertArrayHasKey('agenda', $result);
        $this->assertArrayHasKey('pembahasan', $result);
        $this->assertArrayHasKey('keputusan', $result);
        $this->assertArrayHasKey('tindak_lanjut', $result);

        $this->assertNotEmpty($result['ringkasan']);
        $this->assertNotEmpty($result['keputusan']);
        $this->assertNotEmpty($result['tindak_lanjut']);
    }

    public function test_empty_transcript_returns_empty_structure_without_error(): void
    {
        $result = $this->summarizer->summarize('', [
            'style' => 'lengkap',
            'use_followup' => true,
        ]);

        $this->assertSame('', $result['ringkasan']);
        $this->assertSame([], $result['agenda']);
        $this->assertSame('', $result['pembahasan']);
        $this->assertSame([], $result['keputusan']);
        $this->assertSame([], $result['tindak_lanjut']);
    }

    public function test_style_ringkas_omits_agenda_and_pembahasan(): void
    {
        $transcript = 'Pembahasan mengenai anggaran tahun 2027. Tim sepakat memotong pos pengeluaran yang tidak perlu.';
        $result = $this->summarizer->summarize($transcript, [
            'style' => 'ringkas',
            'use_followup' => false,
        ]);

        $this->assertSame([], $result['agenda']);
        $this->assertSame('', $result['pembahasan']);
        $this->assertNotEmpty($result['ringkasan']);
        $this->assertNotEmpty($result['keputusan']);
    }

    public function test_style_lengkap_includes_agenda_and_pembahasan(): void
    {
        $transcript = 'Pertemuan evaluasi tengah semester. Agenda pertama kurikulum merdeka. Agenda kedua sarana prasarana. Semua pihak sepakat mempercepat renovasi gedung.';
        $result = $this->summarizer->summarize($transcript, [
            'style' => 'lengkap',
            'use_followup' => false,
        ]);

        $this->assertNotEmpty($result['agenda']);
        $this->assertNotEmpty($result['pembahasan']);
    }

    public function test_use_followup_false_strictly_enforces_empty_tindak_lanjut(): void
    {
        $transcript = 'Ada tugas penting untuk pak Budi. Notulis diminta menugaskan tim dokumentasi.';
        $result = $this->summarizer->summarize($transcript, [
            'style' => 'lengkap',
            'use_followup' => false,
        ]);

        $this->assertSame([], $result['tindak_lanjut']);
    }

    public function test_use_followup_true_generates_action_items(): void
    {
        $transcript = 'Bapak kepala sekolah menugaskan panitia untuk menyiapkan laporan keuangan sebelum jumat.';
        $result = $this->summarizer->summarize($transcript, [
            'style' => 'ringkas',
            'use_followup' => true,
        ]);

        $this->assertNotEmpty($result['tindak_lanjut']);
        $this->assertArrayHasKey('description', $result['tindak_lanjut'][0]);
        $this->assertArrayHasKey('assignee_name', $result['tindak_lanjut'][0]);
        $this->assertArrayHasKey('due_date', $result['tindak_lanjut'][0]);
    }

    public function test_markers_are_integrated_into_decisions_and_tasks(): void
    {
        $markers = [
            ['type' => 'keputusan', 'at_sec' => 30.5, 'note' => 'Pemberlakuan jam masuk baru pukul 07.00'],
            ['type' => 'tugas', 'at_sec' => 60.0, 'note' => 'Sosialisasi ke orang tua murid'],
        ];

        $transcript = 'Diskusi panjang mengenai kedisiplinan siswa.';
        $result = $this->summarizer->summarize($transcript, [
            'style' => 'ringkas',
            'use_followup' => true,
            'markers' => $markers,
        ]);

        $this->assertContains('Pemberlakuan jam masuk baru pukul 07.00', $result['keputusan']);
        $foundTask = false;
        foreach ($result['tindak_lanjut'] as $item) {
            if ($item['description'] === 'Sosialisasi ke orang tua murid') {
                $foundTask = true;
                break;
            }
        }
        $this->assertTrue($foundTask);
    }
}
