<?php

declare(strict_types=1);

namespace Raips;

/**
 * Signs the request that reaches hosting. Three headers travel with it, and
 * the receiver rebuilds the same string from what it got.
 *
 * The signature proves the body and the address came from the holder of the
 * secret. It does not hide them, so the transport still has to be TLS.
 */
final class Crypto
{
    public const SIGNATURE_HEADER = 'x-raips-signature';
    public const KEY_HEADER = 'x-raips-key';
    public const TIMESTAMP_HEADER = 'x-raips-timestamp';

    /** Seconds of clock difference a receiver tolerates. */
    public const WINDOW = 300;

    public static function bodyDigest(string $body): string
    {
        return hash('sha256', $body);
    }

    public static function canonical(string $method, string $path, int $timestamp, string $body): string
    {
        return strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . self::bodyDigest($body);
    }

    public static function signRequest(string $secret, string $method, string $path, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', self::canonical($method, $path, $timestamp, $body), $secret);
    }

    /**
     * Answers an incoming request. A false result means the receiver refuses
     * it, and the reason names which of the three checks failed.
     *
     * @param array<string, string> $headers lower case header names
     * @return array{ok: bool, reason: ?string}
     */
    public static function verifyRequest(
        string $secret,
        string $method,
        string $path,
        string $body,
        array $headers,
        int $now,
        int $window = self::WINDOW,
        ?string $expectedKeyId = null,
    ): array {
        $signature = $headers[self::SIGNATURE_HEADER] ?? '';
        $keyId = $headers[self::KEY_HEADER] ?? '';
        $stamp = $headers[self::TIMESTAMP_HEADER] ?? '';

        if ($signature === '' || $stamp === '') {
            return ['ok' => false, 'reason' => 'missing_signature'];
        }

        if ($expectedKeyId !== null && !hash_equals($expectedKeyId, $keyId)) {
            return ['ok' => false, 'reason' => 'unknown_key'];
        }

        if (!preg_match('/^-?\d+$/', $stamp)) {
            return ['ok' => false, 'reason' => 'bad_timestamp'];
        }

        if (abs($now - (int) $stamp) > $window) {
            return ['ok' => false, 'reason' => 'stale_timestamp'];
        }

        $expected = self::signRequest($secret, $method, $path, (int) $stamp, $body);
        if (!hash_equals($expected, strtolower($signature))) {
            return ['ok' => false, 'reason' => 'bad_signature'];
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * Replaces a secret in a string so a log line can name what was sent
     * without carrying the secret itself.
     */
    public static function redact(string $text, ?string $secret = null): string
    {
        if ($secret !== null && $secret !== '') {
            $text = str_replace($secret, '[redacted]', $text);
        }

        return (string) preg_replace('/([a-z0-9_]*(secret|key|token)[a-z0-9_]*\s*[:=]\s*)\S+/i', '$1[redacted]', $text);
    }
}
