<?php

declare(strict_types=1);

/**
 * A hosting panel small enough to read, used by the tests and by anyone who
 * wants to see the publish protocol with their own eyes.
 *
 * It rebuilds the signature from the body it received, so a body edited on the
 * way and a secret that does not match both stop here. Run it with:
 *
 *   RAIPS_PANEL_SECRET=... php tests/fake-server.php 8099
 */

require __DIR__ . '/../autoload.php';

use Raips\Crypto;

$secret = getenv('RAIPS_PANEL_SECRET');
$keyId = getenv('RAIPS_PANEL_KEY') ?: 'k1';
$store = getenv('RAIPS_PANEL_STORE') ?: sys_get_temp_dir() . '/raips-panel';

if (!is_string($secret) || $secret === '') {
    fwrite(STDERR, "set RAIPS_PANEL_SECRET before starting the panel\n");
    exit(2);
}

final class Panel
{
    /** @var array<string, array<string, string>> */
    public array $published = [];

    public function __construct(
        private readonly string $secret,
        private readonly string $keyId,
        private readonly string $store,
    ) {
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function handle(string $method, string $path, string $body, array $headers): array
    {
        $check = Crypto::verifyRequest($this->secret, $method, $path, $body, $headers, time(), expectedKeyId: $this->keyId);
        if (!$check['ok']) {
            return ['status' => 401, 'body' => ['error' => $check['reason']]];
        }

        if ($method === 'POST' && $path === '/__raips/manifest') {
            $data = json_decode($body, true);
            $files = is_array($data) && is_array($data['files'] ?? null) ? $data['files'] : [];
            $id = bin2hex(random_bytes(6));
            $this->published[$id] = [];
            foreach ($files as $file) {
                if (is_array($file) && is_string($file['path'] ?? null)) {
                    $this->published[$id][$file['path']] = '';
                }
            }

            return ['status' => 201, 'body' => ['id' => $id, 'files' => count($this->published[$id])]];
        }

        if ($method === 'PUT' && preg_match('#^/__raips/files/([a-f0-9]+)/(.+)$#', $path, $m) === 1) {
            $id = $m[1];
            if (!isset($this->published[$id])) {
                return ['status' => 404, 'body' => ['error' => 'no_such_publish']];
            }
            if (!array_key_exists($m[2], $this->published[$id])) {
                return ['status' => 409, 'body' => ['error' => 'file_not_in_manifest', 'path' => $m[2]]];
            }
            $this->published[$id][$m[2]] = $body;

            return ['status' => 200, 'body' => ['path' => $m[2], 'bytes' => strlen($body)]];
        }

        if ($method === 'POST' && preg_match('#^/__raips/commit/([a-f0-9]+)$#', $path, $m) === 1) {
            $id = $m[1];
            if (!isset($this->published[$id])) {
                return ['status' => 404, 'body' => ['error' => 'no_such_publish']];
            }
            $root = rtrim($this->store, '/') . '/' . $id;
            foreach ($this->published[$id] as $file => $content) {
                $target = $root . '/' . $file;
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0775, true);
                }
                file_put_contents($target, $content);
            }

            return ['status' => 200, 'body' => ['id' => $id, 'committed' => count($this->published[$id]), 'root' => $root]];
        }

        return ['status' => 404, 'body' => ['error' => 'no_such_route', 'path' => $path]];
    }
}

$panel = new Panel($secret, $keyId, $store);
$address = 'tcp://127.0.0.1:' . (int) ($argv[1] ?? 8099);
$server = @stream_socket_server($address, $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, 'cannot listen on ' . $address . ': ' . $errstr . "\n");
    exit(1);
}

fwrite(STDOUT, 'raips panel on http://127.0.0.1:' . (int) ($argv[1] ?? 8099) . "/, storing under " . $store . "\n");

while (true) {
    $client = @stream_socket_accept($server, 60);
    if ($client === false) {
        continue;
    }

    $raw = '';
    while (!str_contains($raw, "\r\n\r\n")) {
        $chunk = fread($client, 1024);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $raw .= $chunk;
    }

    [$head] = explode("\r\n\r\n", $raw, 2);
    $lines = explode("\r\n", $head);
    $first = array_shift($lines) ?? '';
    $method = 'GET';
    $path = '/';
    if (preg_match('#^([A-Z]+) (\S+)#', $first, $m) === 1) {
        $method = $m[1];
        $path = $m[2];
    }

    $headers = [];
    $length = 0;
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $name = strtolower(trim($parts[0]));
        $headers[$name] = trim($parts[1]);
        if ($name === 'content-length') {
            $length = (int) trim($parts[1]);
        }
    }

    $body = '';
    $already = substr($raw, strpos($raw, "\r\n\r\n") + 4);
    $body = $already;
    while (strlen($body) < $length) {
        $chunk = fread($client, $length - strlen($body));
        if ($chunk === false || $chunk === '') {
            break;
        }
        $body .= $chunk;
    }

    $answer = $panel->handle($method, $path, $body, $headers);
    $payload = json_encode($answer['body'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    fwrite($client, 'HTTP/1.1 ' . $answer['status'] . " X\r\n");
    fwrite($client, "content-type: application/json\r\n");
    fwrite($client, 'content-length: ' . strlen($payload) . "\r\n");
    fwrite($client, "connection: close\r\n\r\n");
    fwrite($client, $payload);
    fclose($client);
}
