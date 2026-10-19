<?php

namespace App\Services\Minutes;

use App\Contracts\Summarizer;

class FakeSummarizer implements Summarizer
{
    /**
     * Generate deterministic structured minutes from transcript and meeting options.
     */
    public function summarize(string $transcript, array $options = []): array
    {
        $transcript = trim($transcript);
        $style = $options['style'] ?? 'ringkas';
        $useFollowup = (bool) ($options['use_followup'] ?? true);
        $markers = $options['markers'] ?? [];

        if (empty($transcript)) {
            return [
                'ringkasan' => '',
                'agenda' => [],
                'pembahasan' => '',
                'keputusan' => [],
                'tindak_lanjut' => [],
            ];
        }

        // Split transcript into sentences
        $sentences = preg_split('/(?<=[.?!])\s+/u', $transcript, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // 1. Ringkasan
        $ringkasanSentences = array_slice($sentences, 0, min(3, count($sentences)));
        $ringkasan = implode(' ', $ringkasanSentences);

        // 2. Keputusan: extracted from markers or keyword matching
        $keputusan = [];
        foreach ($markers as $marker) {
            $type = is_array($marker) ? ($marker['type'] ?? '') : ($marker->type ?? '');
            $note = is_array($marker) ? ($marker['note'] ?? '') : ($marker->note ?? '');
            if ($type === 'keputusan' && ! empty($note)) {
                $keputusan[] = $note;
            }
        }

        $decisionKeywords = ['sepakat', 'putuskan', 'setuju', 'memutuskan', 'kesepakatan', 'menetapkan'];
        foreach ($sentences as $sentence) {
            $lower = strtolower($sentence);
            foreach ($decisionKeywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $cleaned = trim($sentence);
                    if (! in_array($cleaned, $keputusan, true)) {
                        $keputusan[] = $cleaned;
                    }
                    break;
                }
            }
        }

        if (empty($keputusan) && ! empty($sentences)) {
            $keputusan[] = 'Peserta rapat menyetujui seluruh materi yang telah dibahas.';
        }

        // 3. Agenda & Pembahasan (hanya dibuat jika style === 'lengkap')
        $agenda = [];
        $pembahasan = '';

        if ($style === 'lengkap') {
            $agenda = [
                'Pembukaan dan pengantar rapat',
                'Pembahasan poin-poin utama evaluasi',
                'Penyusunan kesepakatan dan langkah tindak lanjut',
            ];

            $pembahasan = implode("\n\n", array_slice($sentences, 0, min(8, count($sentences))));
        }

        // 4. Tindak Lanjut (hanya dibuat jika use_followup aktif)
        $tindakLanjut = [];
        if ($useFollowup) {
            foreach ($markers as $marker) {
                $type = is_array($marker) ? ($marker['type'] ?? '') : ($marker->type ?? '');
                $note = is_array($marker) ? ($marker['note'] ?? '') : ($marker->note ?? '');
                if ($type === 'tugas' && ! empty($note)) {
                    $tindakLanjut[] = [
                        'description' => $note,
                        'assignee_name' => 'Tim Terkait',
                        'due_date' => now()->addDays(7)->format('Y-m-d'),
                    ];
                }
            }

            $taskKeywords = ['tugas', 'tindak lanjut', 'menugaskan', 'tanggung jawab', 'deadline'];
            foreach ($sentences as $sentence) {
                $lower = strtolower($sentence);
                foreach ($taskKeywords as $kw) {
                    if (str_contains($lower, $kw)) {
                        $tindakLanjut[] = [
                            'description' => trim($sentence),
                            'assignee_name' => 'Koordinator',
                            'due_date' => now()->addDays(7)->format('Y-m-d'),
                        ];
                        break;
                    }
                }
            }

            // Fallback action item if none detected
            if (empty($tindakLanjut) && count($sentences) > 0) {
                $tindakLanjut[] = [
                    'description' => 'Koordinasi teknis pelaksanaan hasil keputusan rapat',
                    'assignee_name' => 'Notulis / Sekretariat',
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                ];
            }
        }

        return [
            'ringkasan' => $ringkasan,
            'agenda' => $agenda,
            'pembahasan' => $pembahasan,
            'keputusan' => $keputusan,
            'tindak_lanjut' => $tindakLanjut,
        ];
    }
}
