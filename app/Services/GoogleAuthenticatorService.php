<?php

namespace App\Services;

use RuntimeException;

/**
 * RFC 6238-compatible TOTP generator/verifier for Google Authenticator.
 * The secret is stored encrypted by the Admin model cast.
 */
class GoogleAuthenticatorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), self::PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exposed for deterministic feature tests and operational diagnostics.
     */
    public function codeForTesting(string $secret, ?int $timestamp = null): string
    {
        return $this->code($secret, intdiv($timestamp ?? time(), self::PERIOD));
    }

    public function provisioningUri(string $secret, string $email, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $email);

        return 'otpauth://totp/' . $label . '?' . http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function code(string $secret, int $counter): string
    {
        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $value): string
    {
        $bits = '';
        foreach (str_split($value) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($bits, 5) as $chunk) {
            $result .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $result;
    }

    private function base32Decode(string $value): string
    {
        $value = strtoupper(rtrim($value, '='));
        $bits = '';

        foreach (str_split($value) as $char) {
            $position = strpos(self::BASE32_ALPHABET, $char);
            if ($position === false) {
                throw new RuntimeException('Invalid Google Authenticator secret.');
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $result .= chr(bindec($chunk));
            }
        }

        return $result;
    }
}
