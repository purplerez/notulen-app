<?php

namespace App\Exceptions;

use RuntimeException;

class RateLimitException extends RuntimeException
{
    /**
     * @param  string  $message  Error message
     * @param  int  $retryAfterSeconds  Seconds to wait before retrying
     * @param  bool  $isDailyQuota  Whether the limit hit is daily quota rather than momentary RPM
     * @param  array<string, mixed>  $responseBody  Raw response payload
     */
    public function __construct(
        string $message,
        protected int $retryAfterSeconds = 15,
        protected bool $isDailyQuota = false,
        protected array $responseBody = []
    ) {
        parent::__construct($message, 429);
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }

    public function isDailyQuota(): bool
    {
        return $this->isDailyQuota;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseBody(): array
    {
        return $this->responseBody;
    }
}
