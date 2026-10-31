<?php

namespace App\Contracts;

use App\Exceptions\LlmException;

interface LlmProvider
{
    /**
     * Send a prompt to the LLM and return the text completion.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws LlmException
     */
    public function complete(string $prompt, array $options = []): string;
}
