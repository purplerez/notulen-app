<?php

namespace App\Support;

use InvalidArgumentException;

class Totp
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random RFC 6238 Base32 secret key.
     */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        $max = strlen(self::BASE32_CHARS) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $max)];
        }

        return $secret;
    }

    /**
     * Calculate 6-digit TOTP code for a secret and time slice.
     */
    public static function getCode(string $secret, ?int $timeSlice = null): string
    {
        $timeSlice = $timeSlice ?? (int) floor(time() / 30);
        $secretBin = self::base32Decode(strtoupper(trim($secret)));

        // 8-byte big-endian binary representation of the time slice
        $timePacked = pack('N*', 0).pack('N*', $timeSlice);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $timePacked, $secretBin, true);

        // Dynamic truncation (RFC 4226 section 5.4)
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $hashPart = substr($hash, $offset, 4);
        $unpacked = unpack('N', $hashPart);
        $value = ($unpacked[1] ?? 0) & 0x7FFFFFFF;

        $modulo = $value % 1000000;

        return str_pad((string) $modulo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a 6-digit TOTP code with time-step tolerance window.
     */
    public static function verify(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        $currentTimeSlice = (int) floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate standard otpauth:// URI for authenticator apps (Google Authenticator, Authy, etc.).
     */
    public static function getOtpAuthUri(string $email, string $secret, string $issuer = 'Notulen App Platform'): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($email);

        return "otpauth://totp/{$label}?secret=".rawurlencode(strtoupper(trim($secret)))
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * Decode a Base32 string to binary data.
     */
    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(str_replace('=', '', trim($b32)));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $char = $b32[$i];
            $val = strpos(self::BASE32_CHARS, $char);

            if ($val === false) {
                throw new InvalidArgumentException("Karakter Base32 tidak valid: '{$char}'");
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
