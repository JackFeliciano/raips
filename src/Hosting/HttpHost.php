<?php

declare(strict_types=1);

namespace Raips\Hosting;

use Raips\Crypto;
use Raips\Html;

/**
 * Reaches a hosting panel over HTTPS. Every request carries a signature the
 * panel rebuilds from the body it received, so a body edited on the way and a
 * secret that does not match both stop at the panel.
 *
 * The protocol is two calls: a manifest that says what is coming, then one
 * PUT per file. A panel answers the second call with the id it stored under.
 */
final class HttpHost implements Host
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $keyId,
        private readonly string $secret,
        private readonly int $timeout = 30,
    ) {
    }

    public function publish(array $files): Receipt
    {
        $manifest = json_encode([
            'files' => array_map(static fn (array $f): array => [
                'path' => $f['path'],
                'bytes' => strlen($f['body']),
                'type' => $f['type'],
            ], $files),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $answer = $this->call('POST', '/__raips/manifest', $manifest);
        $id = $answer['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new PublishError('the host took the manifest and named no publish id', ['answer' => $answer]);
        }

        $bytes = 0;
        foreach ($files as $file) {
            $this->call('PUT', '/__raips/files/' . $id . '/' . ltrim($file['path'], '/'), $file['body']);
            $bytes += strlen($file['body']);
        }

        $this->call('POST', '/__raips/commit/' . $id, '{}');

        return new Receipt(
            host: 'http',
            files: count($files),
            bytes: $bytes,
            root: rtrim($this->baseUrl, '/') . '/' . $id,
            extra: ['id' => $id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, string $body): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;
        if (!Html::safeUrl($url)) {
            throw new PublishError('the host address is not usable', ['url' => $url]);
        }

        $stamp = time();
        $signature = Crypto::signRequest($this->secret, $method, $path, $stamp, $body);

        $headers = [
            'content-type: application/json',
            'accept: application/json',
            Crypto::SIGNATURE_HEADER . ': ' . $signature,
            Crypto::KEY_HEADER . ': ' . $this->keyId,
            Crypto::TIMESTAMP_HEADER . ': ' . $stamp,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new PublishError('cannot open a request to the host', ['url' => $url]);
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new PublishError('the host did not answer', ['url' => $url, 'error' => $error]);
        }

        $answer = json_decode((string) $raw, true);
        if (!is_array($answer)) {
            $answer = ['raw' => substr((string) $raw, 0, 400)];
        }

        if ($status < 200 || $status >= 300) {
            throw new PublishError('the host answered ' . $status, ['url' => $url, 'status' => $status, 'answer' => $answer]);
        }

        return $answer;
    }
}
