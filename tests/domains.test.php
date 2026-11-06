<?php

declare(strict_types=1);

use Raips\Domains\Dynadot;
use Raips\Domains\FixtureRegistrar;
use Raips\Domains\Panel;
use Raips\Tests\Runner;

return static function (Runner $t): void {
    $table = [
        'cobaltstudio.com' => ['available' => false],
        'cobaltstudio.net' => ['available' => true, 'price' => '11.90', 'currency' => 'USD'],
        'cobaltstudio.io' => ['available' => false, 'price' => '59.90', 'currency' => 'USD'],
        'cobaltstudio.dev' => ['available' => null, 'price' => null],
    ];

    $t->test('a search answers one row per extension', function (Runner $t) use ($table): void {
        $rows = (new FixtureRegistrar($table))->search('cobaltstudio', ['com', 'net', 'org']);
        $t->assertCount(3, $rows);
        $t->assertSame('cobaltstudio.com', $rows[0]->domain);
        $t->assertSame(false, $rows[0]->available);
    });

    $t->test('a name the fixture does not hold reads as unknown', function (Runner $t) use ($table): void {
        $rows = (new FixtureRegistrar($table))->search('nothing', ['com']);
        $t->assertSame(null, $rows[0]->available);
        $t->assertContains('no answer', (string) $rows[0]->reason);
    });

    $t->test('a full stop in the extension is accepted', function (Runner $t) use ($table): void {
        $rows = (new FixtureRegistrar($table))->search('cobaltstudio', ['.net']);
        $t->assertSame('cobaltstudio.net', $rows[0]->domain);
    });

    $t->test('the panel sorts free names first, then a price, then taken', function (Runner $t) use ($table): void {
        $rows = (new Panel(new FixtureRegistrar($table), ['com', 'net', 'io', 'dev']))->suggestions('cobaltstudio');
        $t->assertSame('cobaltstudio.net', $rows[0]['domain']);
        $t->assertSame('cobaltstudio.io', $rows[1]['domain']);
        $t->assertSame('cobaltstudio.com', $rows[2]['domain']);
    });

    $t->test('the panel keeps only the names a member can act on', function (Runner $t) use ($table): void {
        $rows = (new Panel(new FixtureRegistrar($table), ['com', 'net', 'dev']))->search('cobaltstudio');
        $usable = Panel::usable($rows);
        $t->assertCount(1, $usable);
        $t->assertSame('cobaltstudio.net', $usable[0]['domain']);
    });

    $t->test('a name is lowered and stripped to what a registrar takes', function (Runner $t): void {
        $t->assertSame('cobaltstudio', Dynadot::normalise('  Cobalt_Studio! '));
        $t->assertSame('my-name', Dynadot::normalise('-My-Name-'));
        $t->assertSame('', Dynadot::normalise('---'));
    });

    $t->test('an extension is lowered and loses its dots', function (Runner $t): void {
        $t->assertSame('com', Dynadot::normaliseTld('.COM'));
        $t->assertSame('co.uk', Dynadot::normaliseTld('co.uk'));
    });

    $t->test('the registrar endpoint is https', function (Runner $t): void {
        $t->assertSame('https://api.dynadot.com/api3.json', Dynadot::ENDPOINT);
        $t->assertSame('https://api-sandbox.dynadot.com/api3.json', Dynadot::SANDBOX);
    });

    $t->test('a search with no name is refused', function (Runner $t): void {
        $t->assertThrows(InvalidArgumentException::class, function (Runner $t): void {
            (new Dynadot('key'))->search('', ['com']);
        });
    });

    $t->test('a search with no extension asks nothing', function (Runner $t): void {
        $rows = (new Dynadot('key'))->search('cobaltstudio', []);
        $t->assertCount(0, $rows);
    });

    $t->test('a search over the name cap is refused', function (Runner $t): void {
        $many = array_map(static fn (int $i): string => 'tld' . $i, range(1, Dynadot::MAX_NAMES + 1));
        $t->assertThrows(InvalidArgumentException::class, function (Runner $t) use ($many): void {
            (new Dynadot('key'))->search('cobaltstudio', $many);
        });
    });
};
