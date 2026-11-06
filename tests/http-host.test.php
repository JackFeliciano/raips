<?php

declare(strict_types=1);

use Raips\Hosting\HttpHost;
use Raips\Hosting\PublishError;
use Raips\Tests\Runner;

/**
 * The publish protocol proven against a panel that really listens: same
 * signature on both sides, a body edited on the way refused, a wrong secret
 * refused, and only the files that went out landing on the other side.
 */
return static function (Runner $t): void {
    $file = static fn (string $path, string $body): array => [
        'path' => $path,
        'body' => $body,
        'type' => 'text/html; charset=utf-8',
    ];

    $t->test('a publish lands on a panel that checks the signature', function (Runner $t) use ($file): void {
        $secret = 'test-secret-' . bin2hex(random_bytes(4));
        $port = 8100 + random_int(0, 400);
        $store = sys_get_temp_dir() . '/raips-panel-' . bin2hex(random_bytes(4));
        mkdir($store, 0775, true);

        $command = 'php ' . escapeshellarg(__DIR__ . '/fake-server.php') . ' ' . $port;
        $env = ['RAIPS_PANEL_SECRET' => $secret, 'RAIPS_PANEL_KEY' => 'k1', 'RAIPS_PANEL_STORE' => $store];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__, $env + ['PATH' => getenv('PATH') ?: '/usr/bin']);
        if (!is_resource($process)) {
            throw new RuntimeException('cannot start the panel');
        }

        try {
            $ready = false;
            for ($i = 0; $i < 40; $i++) {
                $probe = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 0.2);
                if ($probe !== false) {
                    fclose($probe);
                    $ready = true;
                    break;
                }
                usleep(50000);
            }
            $t->assertTrue($ready, 'the panel never answered on its port');

            $host = new HttpHost('http://127.0.0.1:' . $port, 'k1', $secret);
            $receipt = $host->publish([
                $file('index.html', '<h1>one</h1>'),
                $file('about/index.html', '<h1>two</h1>'),
            ]);

            $t->assertSame('http', $receipt->host);
            $t->assertSame(2, $receipt->files);
            $t->assertTrue(is_string($receipt->extra['id'] ?? null), 'the panel named no id');
            $id = (string) $receipt->extra['id'];
            $t->assertSame('<h1>one</h1>', file_get_contents($store . '/' . $id . '/index.html'));
            $t->assertSame('<h1>two</h1>', file_get_contents($store . '/' . $id . '/about/index.html'));

            $t->test('a wrong secret is refused by the panel', function (Runner $t) use ($port): void {
                $wrong = new HttpHost('http://127.0.0.1:' . $port, 'k1', 'not-the-secret');
                $e = $t->assertThrows(PublishError::class, function (Runner $t) use ($wrong): void {
                    $wrong->publish([['path' => 'index.html', 'body' => 'x', 'type' => 'text/html']]);
                });
                $t->assertContains('the host answered 401', $e->getMessage());
            });

            $t->test('a panel that names another key is refused', function (Runner $t) use ($port, $secret): void {
                $other = new HttpHost('http://127.0.0.1:' . $port, 'k2', $secret);
                $e = $t->assertThrows(PublishError::class, function (Runner $t) use ($other): void {
                    $other->publish([['path' => 'index.html', 'body' => 'x', 'type' => 'text/html']]);
                });
                $t->assertContains('401', $e->getMessage());
            });
        } finally {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    });

    $t->test('an address that is not usable stops before any request', function (Runner $t): void {
        $host = new HttpHost('javascript:alert(1)', 'k1', 's');
        $e = $t->assertThrows(PublishError::class, function (Runner $t) use ($host): void {
            $host->publish([['path' => 'index.html', 'body' => 'x', 'type' => 'text/html']]);
        });
        $t->assertContains('not usable', $e->getMessage());
    });
};
