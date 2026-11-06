<?php

declare(strict_types=1);

use Raips\Hosting\FakeHost;
use Raips\Hosting\NullHost;
use Raips\Hosting\PublishError;
use Raips\Tests\Runner;

return static function (Runner $t): void {
    $file = static fn (string $path, string $body): array => [
        'path' => $path,
        'body' => $body,
        'type' => 'text/html; charset=utf-8',
    ];

    $fresh = static function (): string {
        $dir = sys_get_temp_dir() . '/raips-fake-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        return $dir;
    };

    $t->test('the fake host writes each file under the root', function (Runner $t) use ($file, $fresh): void {
        $root = $fresh();
        $receipt = (new FakeHost($root))->publish([$file('index.html', '<h1>a</h1>'), $file('about/index.html', '<h1>b</h1>')]);
        $t->assertSame('fake', $receipt->host);
        $t->assertSame(2, $receipt->files);
        $t->assertSame('<h1>a</h1>', file_get_contents($root . '/index.html'));
        $t->assertSame('<h1>b</h1>', file_get_contents($root . '/about/index.html'));
    });

    $t->test('the fake host counts the bytes it wrote', function (Runner $t) use ($file, $fresh): void {
        $root = $fresh();
        $receipt = (new FakeHost($root))->publish([$file('index.html', 'abc')]);
        $t->assertSame(3, $receipt->bytes);
    });

    $t->test('the fake host makes the folder a nested file needs', function (Runner $t) use ($file, $fresh): void {
        $root = $fresh();
        (new FakeHost($root))->publish([$file('a/b/c/index.html', 'x')]);
        $t->assertTrue(is_dir($root . '/a/b/c'), 'the folder was not made');
    });

    $t->test('the fake host refuses a path that leaves the root', function (Runner $t) use ($file, $fresh): void {
        $t->assertThrows(PublishError::class, function (Runner $t) use ($file, $fresh): void {
            (new FakeHost($fresh()))->publish([$file('../escape.html', 'x')]);
        });
        $t->assertThrows(PublishError::class, function (Runner $t) use ($file, $fresh): void {
            (new FakeHost($fresh()))->publish([$file('/abs.html', 'x')]);
        });
        $t->assertThrows(PublishError::class, function (Runner $t) use ($file, $fresh): void {
            (new FakeHost($fresh()))->publish([$file('C:/windows.html', 'x')]);
        });
    });

    $t->test('insideRoot says yes to a plain name and no to a climb', function (Runner $t): void {
        $t->assertTrue(FakeHost::insideRoot('index.html'));
        $t->assertTrue(FakeHost::insideRoot('a/b/index.html'));
        $t->assertFalse(FakeHost::insideRoot('..'));
        $t->assertFalse(FakeHost::insideRoot('a/../../b'));
        $t->assertFalse(FakeHost::insideRoot(''));
    });

    $t->test('the fake host refuses an empty credential when one was named', function (Runner $t) use ($file, $fresh): void {
        $t->assertThrows(PublishError::class, function (Runner $t) use ($file, $fresh): void {
            (new FakeHost($fresh(), 'k1', ''))->publish([$file('index.html', 'x')]);
        });
    });

    $t->test('the null host writes nothing and still answers a receipt', function (Runner $t) use ($file, $fresh): void {
        $receipt = (new NullHost())->publish([$file('index.html', 'abc')]);
        $t->assertSame('null', $receipt->host);
        $t->assertSame(1, $receipt->files);
        $t->assertSame(3, $receipt->bytes);
        $t->assertSame('', $receipt->root);
    });

    $t->test('a receipt reads back as an array with host, files, bytes and root', function (Runner $t) use ($file, $fresh): void {
        $data = (new FakeHost($fresh()))->publish([$file('index.html', 'ab')])->toArray();
        $t->assertSame('fake', $data['host']);
        $t->assertSame(1, $data['files']);
        $t->assertSame(2, $data['bytes']);
        $t->assertTrue(is_string($data['root']), 'root is not a string');
    });
};
