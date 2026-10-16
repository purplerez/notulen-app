<?php

namespace App\Support;

class CheckinCode
{
    /**
     * Duration of a single QR code window in seconds.
     */
    public const WINDOW_SECONDS = 30;

    /**
     * Generate the HMAC-SHA256 check-in code for a meeting at a specific timestamp or time window.
     */
    public static function generate(int $meetingId, ?int $timestamp = null): string
    {
        $window = self::windowAt($timestamp ?? time());
        $key = config('app.key');

        return hash_hmac('sha256', "{$meetingId}:{$window}", (string) $key);
    }

    /**
     * Validate whether a given code is valid for the meeting.
     * The server accepts the current window and the immediately preceding window (Instruction 1).
     */
    public static function isValid(int $meetingId, ?string $code, ?int $timestamp = null): bool
    {
        if (empty($code)) {
            return false;
        }

        $now = $timestamp ?? time();
        $currentWindow = self::windowAt($now);
        $previousWindow = $currentWindow - 1;
        $key = (string) config('app.key');

        $currentCode = hash_hmac('sha256', "{$meetingId}:{$currentWindow}", $key);
        $previousCode = hash_hmac('sha256', "{$meetingId}:{$previousWindow}", $key);

        return hash_equals($currentCode, $code) || hash_equals($previousCode, $code);
    }

    /**
     * Get the time window index for a given timestamp.
     */
    public static function windowAt(int $timestamp): int
    {
        return (int) floor($timestamp / self::WINDOW_SECONDS);
    }

    /**
     * Get the seconds remaining in the current window.
     */
    public static function secondsRemaining(?int $timestamp = null): int
    {
        $now = $timestamp ?? time();
        $elapsedInWindow = $now % self::WINDOW_SECONDS;

        return self::WINDOW_SECONDS - $elapsedInWindow;
    }
}
