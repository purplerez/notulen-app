<?php

namespace App\Services\LLM;

use App\Contracts\LlmProvider;
use App\Exceptions\LlmException;
use Illuminate\Support\Facades\Http;

class GroqLlmProvider implements LlmProvider
{
    /**
     * Send a prompt to Groq's chat completions endpoint and return the text response.
     *
     * Reuses GROQ_API_KEY (same key as STT) unless LLM_API_KEY is explicitly set.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws LlmException
     */
    public function complete(string $prompt, array $options = []): string
    {
        $apiKey = trim((string) config('summarizer.drivers.llm.api_key', ''));

        // Fallback: reuse the Groq API key already configured for STT
        if (empty($apiKey)) {
            $apiKey = trim((string) config('stt.drivers.groq.key', ''));
        }

        if (empty($apiKey)) {
            throw new LlmException('API key LLM belum dikonfigurasi. Pastikan GROQ_API_KEY atau LLM_API_KEY ada di .env.');
        }

        $model = (string) config('summarizer.drivers.llm.model', 'llama-3.3-70b-versatile');
        $timeout = (int) config('summarizer.drivers.llm.timeout', 120);

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.3,
                'max_tokens' => $options['max_tokens'] ?? 4096,
            ]);

        if ($response->failed()) {
            $status = $response->status();
            $message = $response->json('error.message', $response->body());
            throw new LlmException("Groq LLM gagal HTTP {$status}: {$message}", $status);
        }

        $content = $response->json('choices.0.message.content', '');

        if (empty($content)) {
            throw new LlmException('Groq LLM menghasilkan respons kosong.');
        }

        return (string) $content;
    }
}
