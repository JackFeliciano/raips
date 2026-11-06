<?php

declare(strict_types=1);

use Raips\Crypto;
use Raips\Tests\Runner;

return static function (Runner $t): void {
    $body = '{"files":[]}';
    $path = '/__raips/manifest';

    $t->test('the digest is the sha256 of the body', function (Runner $t) use ($body): void {
        $t->assertSame(hash('sha256', $body), Crypto::bodyDigest($body));
    });

    $t->test('the canonical line carries method, path, stamp and digest', function (Runner $t) use ($body, $path): void {
        $canonical = Crypto::canonical('POST', $path, 1700000000, $body);
        $t->assertSame("POST\n" . $path . "\n1700000000\n" . hash('sha256', $body), $canonical);
    });

    $t->test('a signature is the hmac of the canonical line', function (Runner $t) use ($body, $path): void {
        $signature = Crypto::signRequest('secret-key', 'POST', $path, 1700000000, $body);
        $expected = hash_hmac('sha256', Crypto::canonical('POST', $path, 1700000000, $body), 'secret-key');
        $t->assertSame($expected, $signature);
    });

    $t->test('a different body gives a different signature', function (Runner $t) use ($path): void {
        $a = Crypto::signRequest('k', 'POST', $path, 1700000000, '{"a":1}');
        $b = Crypto::signRequest('k', 'POST', $path, 1700000000, '{"a":2}');
        $t->assertFalse($a === $b, 'the signature did not move with the body');
    });

    $t->test('a different method gives a different signature', function (Runner $t) use ($body, $path): void {
        $a = Crypto::signRequest('k', 'POST', $path, 1700000000, $body);
        $b = Crypto::signRequest('k', 'PUT', $path, 1700000000, $body);
        $t->assertFalse($a === $b, 'the signature did not move with the method');
    });

    /** @return array<string, string> */
    $headersFor = static function (string $secret, string $method, string $path, int $stamp, string $body): array {
        return [
            Crypto::SIGNATURE_HEADER => Crypto::signRequest($secret, $method, $path, $stamp, $body),
            Crypto::KEY_HEADER => 'k1',
            Crypto::TIMESTAMP_HEADER => (string) $stamp,
        ];
    };

    $t->test('a request verifies against the same parts and fails against another secret', function (Runner $t) use ($body, $path, $headersFor): void {
        $stamp = 1700000000;
        $headers = $headersFor('right', 'POST', $path, $stamp, $body);
        $t->assertTrue(Crypto::verifyRequest('right', 'POST', $path, $body, $headers, $stamp + 10)['ok']);
        $t->assertSame('bad_signature', Crypto::verifyRequest('wrong', 'POST', $path, $body, $headers, $stamp + 10)['reason']);
    });

    $t->test('a stamp outside the window is stale', function (Runner $t) use ($body, $path, $headersFor): void {
        $stamp = 1700000000;
        $headers = $headersFor('right', 'POST', $path, $stamp, $body);
        $t->assertTrue(Crypto::verifyRequest('right', 'POST', $path, $body, $headers, $stamp + Crypto::WINDOW)['ok']);
        $t->assertSame('stale_timestamp', Crypto::verifyRequest('right', 'POST', $path, $body, $headers, $stamp + Crypto::WINDOW + 1)['reason']);
    });

    $t->test('a request with no signature is refused', function (Runner $t) use ($body, $path): void {
        $t->assertSame('missing_signature', Crypto::verifyRequest('k', 'POST', $path, $body, [], time())['reason']);
    });

    $t->test('a request naming another key is refused', function (Runner $t) use ($body, $path, $headersFor): void {
        $stamp = 1700000000;
        $headers = $headersFor('k', 'POST', $path, $stamp, $body);
        $t->assertSame('unknown_key', Crypto::verifyRequest('k', 'POST', $path, $body, $headers, $stamp, expectedKeyId: 'other')['reason']);
    });

    $t->test('a body edited on the way is refused', function (Runner $t) use ($path, $headersFor): void {
        $stamp = 1700000000;
        $headers = $headersFor('k', 'POST', $path, $stamp, '{"a":1}');
        $t->assertSame('bad_signature', Crypto::verifyRequest('k', 'POST', $path, '{"a":2}', $headers, $stamp)['reason']);
    });

    $t->test('redact takes a secret out of a line', function (Runner $t): void {
        $t->assertNotContains('swordfish', Crypto::redact('sent with swordfish', 'swordfish'));
        $t->assertContains('[redacted]', Crypto::redact('secret=swordfish'));
    });

    $t->test('the header names are on the wire as written', function (Runner $t): void {
        $t->assertSame('x-raips-signature', Crypto::SIGNATURE_HEADER);
        $t->assertSame('x-raips-key', Crypto::KEY_HEADER);
        $t->assertSame('x-raips-timestamp', Crypto::TIMESTAMP_HEADER);
        $t->assertSame(300, Crypto::WINDOW);
    });
};
