<?php

namespace App\Contracts;

interface Summarizer
{
    /**
     * Summarize transcript into structured minutes sections based on meeting options.
     *
     * @param  string  $transcript  Full transcript text of the meeting
     * @param  array<string, mixed>  $options  ['style' => 'ringkas'|'lengkap', 'use_followup' => bool, 'markers' => array]
     * @return array{
     *     ringkasan: string,
     *     agenda: array<int, string>,
     *     pembahasan: array<int, string>|string,
     *     keputusan: array<int, string>,
     *     tindak_lanjut: array<int, array{description: string, assignee_name: ?string, due_date: ?string}>
     * }
     */
    public function summarize(string $transcript, array $options = []): array;
}
