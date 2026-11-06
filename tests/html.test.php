<?php

declare(strict_types=1);

use Raips\Html;
use Raips\Tests\Runner;

return static function (Runner $t): void {
    $t->test('esc keeps a quote out of an attribute', function (Runner $t): void {
        $t->assertSame('a&quot;b&#039;c', Html::esc('a"b\'c'));
    });

    $t->test('esc leaves an ampersand readable', function (Runner $t): void {
        $t->assertSame('Tom &amp; Li', Html::esc('Tom & Li'));
    });

    $t->test('safeUrl takes a plain relative address', function (Runner $t): void {
        $t->assertSame('/contact/', Html::safeUrl('/contact/'));
        $t->assertSame('assets/site.css', Html::safeUrl('assets/site.css'));
    });

    $t->test('safeUrl takes https and mailto', function (Runner $t): void {
        $t->assertSame('https://example.com/a', Html::safeUrl('https://example.com/a'));
        $t->assertSame('mailto:hello@example.com', Html::safeUrl('mailto:hello@example.com'));
    });

    $t->test('safeUrl refuses javascript', function (Runner $t): void {
        $t->assertSame(null, Html::safeUrl('javascript:alert(1)'));
        $t->assertSame(null, Html::safeUrl('JaVaScRiPt:alert(1)'));
    });

    $t->test('safeUrl refuses a protocol relative address', function (Runner $t): void {
        $t->assertSame(null, Html::safeUrl('//evil.example/x'));
    });

    $t->test('safeUrl refuses a newline inside the address', function (Runner $t): void {
        $t->assertSame(null, Html::safeUrl("/a\r\nSet-Cookie: x=y"));
        $t->assertSame(null, Html::safeUrl('/a' . "\0"));
    });

    $t->test('safeUrl refuses an empty value and a non string', function (Runner $t): void {
        $t->assertSame(null, Html::safeUrl(''));
        $t->assertSame(null, Html::safeUrl('   '));
        $t->assertSame(null, Html::safeUrl(null));
        $t->assertSame(null, Html::safeUrl(12));
    });

    $t->test('attrs drops a null and keeps a bare true', function (Runner $t): void {
        $t->assertSame(' hidden', Html::attrs(['hidden' => true, 'title' => null]));
        $t->assertSame(' title="a&quot;b"', Html::attrs(['title' => 'a"b']));
    });

    $t->test('lines joins what it is given and drops the empty parts', function (Runner $t): void {
        $t->assertSame("a\nb", Html::lines('a', '', 'b'));
    });
};
