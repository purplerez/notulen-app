<?php

namespace App\Support;

use InvalidArgumentException;

class PhoneNumber
{
    /**
     * Normalize an Indonesian mobile phone number to E.164 without leading plus (e.g. 62812...).
     *
     * Supported input formats:
     * - 0812...
     * - +62812...
     * - 62812...
     * - 812...
     * With spaces, hyphens, parentheses, or dots.
     *
     * Returns null if the number is invalid.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $cleaned = trim($phone);
        if ($cleaned === '') {
            return null;
        }

        // Reject if input contains characters other than digits, whitespace, +, -, ., (, )
        if (preg_match('/[^\d\s\+\-\.\(\)]/', $cleaned)) {
            return null;
        }

        // Plus is only permitted as the very first character
        if (str_contains(substr($cleaned, 1), '+')) {
            return null;
        }

        // Strip whitespace, hyphens, dots, parentheses
        $cleaned = (string) preg_replace('/[\s\-\.\(\)]+/', '', $cleaned);

        // Normalize prefixes to 628...
        if (str_starts_with($cleaned, '+62')) {
            $cleaned = substr($cleaned, 1); // remove +
        } elseif (str_starts_with($cleaned, '0')) {
            $cleaned = '62'.substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62'.$cleaned;
        }

        // Validate: must start with 628 followed by valid mobile digit [1-9] and 6 to 11 digits
        // Total length must be 10 to 15 digits (ITU-T E.164 standard)
        if (! preg_match('/^628[1-9][0-9]{6,11}$/', $cleaned)) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Check if a given phone number is valid for Indonesian mobile.
     */
    public static function isValid(?string $phone): bool
    {
        return self::normalize($phone) !== null;
    }

    /**
     * Normalize and return number, or throw an exception if invalid.
     *
     * @throws InvalidArgumentException
     */
    public static function normalizeOrFail(string $phone): string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            throw new InvalidArgumentException("Nomor telepon tidak valid: '{$phone}'. Nomor harus nomor seluler Indonesia yang valid (contoh: 081234567890).");
        }

        return $normalized;
    }
}
