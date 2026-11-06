<?php

declare(strict_types=1);

use Raips\Preview;
use Raips\Tests\Runner;

return static function (Runner $t): void {
    $files = [
        ['path' => 'index.html', 'body' => '<h1>home</h1>', 'type' => 'text/html; charset=utf-8'],
        ['path' => 'about/index.html', 'body' => '<h1>about</h1>', 'type' => 'text/html; charset=utf-8'],
        ['path' => 'assets/site.css', 'body' => 'body{}', 'type' => 'text/css; charset=utf-8'],
    ];

    $t->test('the root serves index.html', function (Runner $t) use ($files): void {
        $answer = (new Preview($files))->respond('/');
        $t->assertSame(200, $answer['status']);
        $t->assertSame('<h1>home</h1>', $answer['body']);
        $t->assertSame('text/html; charset=utf-8', $answer['type']);
    });

    $t->test('a folder serves its index.html', function (Runner $t) use ($files): void {
        $answer = (new Preview($files))->respond('/about/');
        $t->assertSame('<h1>about</h1>', $answer['body']);
    });

    $t->test('a file address serves the file with its type', function (Runner $t) use ($files): void {
        $answer = (new Preview($files))->respond('/assets/site.css');
        $t->assertSame('text/css; charset=utf-8', $answer['type']);
        $t->assertSame('body{}', $answer['body']);
    });

    $t->test('an address that is not there reads 404', function (Runner $t) use ($files): void {
        $answer = (new Preview($files))->respond('/nowhere/');
        $t->assertSame(404, $answer['status']);
    });

    $t->test('an address that climbs out reads 404', function (Runner $t) use ($files): void {
        $t->assertSame(404, (new Preview($files))->respond('/../../etc/passwd')['status']);
    });

    $t->test('a query string does not change which file is served', function (Runner $t) use ($files): void {
        $t->assertSame('<h1>home</h1>', (new Preview($files))->respond('/?a=1')['body']);
    });

    $t->test('the preview lists every path it holds', function (Runner $t) use ($files): void {
        $paths = (new Preview($files))->paths();
        $t->assertCount(3, $paths);
        $t->assertSame('/about/index.html', $paths[0]);
    });
};
