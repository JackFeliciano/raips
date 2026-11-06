<?php

declare(strict_types=1);

use Raips\Config;
use Raips\Tests\Runner;

return static function (Runner $t): void {
    $good = [
        'site' => ['dir' => 'examples/site'],
        'hosting' => [
            'driver' => 'http',
            'baseUrl' => 'https://panel.example.org',
            'credential' => ['keyId' => 'k1', 'secret' => 'shh'],
        ],
    ];

    $t->test('a filled config has no problems', function (Runner $t) use ($good): void {
        $t->assertCount(0, Config::validate($good));
    });

    $t->test('a config with no site folder is refused', function (Runner $t) use ($good): void {
        $bad = $good;
        unset($bad['site']['dir']);
        $t->assertCount(1, Config::validate($bad));
    });

    $t->test('a config with no hosting block is refused', function (Runner $t) use ($good): void {
        $bad = $good;
        unset($bad['hosting']);
        $t->assertCount(1, Config::validate($bad));
    });

    $t->test('a driver this build does not carry is refused', function (Runner $t) use ($good): void {
        $bad = $good;
        $bad['hosting']['driver'] = 'ftp';
        $problems = Config::validate($bad);
        $t->assertCount(1, $problems);
        $t->assertContains('hosting.driver must be one of', $problems[0]);
    });

    $t->test('a base address without a scheme is refused', function (Runner $t) use ($good): void {
        $bad = $good;
        $bad['hosting']['baseUrl'] = 'panel.example.org';
        $t->assertCount(1, Config::validate($bad));
    });

    $t->test('plain http off the loopback address is refused', function (Runner $t) use ($good): void {
        $bad = $good;
        $bad['hosting']['baseUrl'] = 'http://panel.example.org';
        $t->assertCount(1, Config::validate($bad));
    });

    $t->test('plain http on the loopback address is allowed', function (Runner $t) use ($good): void {
        $local = $good;
        $local['hosting']['baseUrl'] = 'http://127.0.0.1:8099';
        $t->assertCount(0, Config::validate($local));
    });

    $t->test('an empty key names the credential problem', function (Runner $t) use ($good): void {
        $bad = $good;
        $bad['hosting'] = ['driver' => 'fake', 'fakeRoot' => '.raips-out', 'credential' => ['keyId' => 'k1', 'secret' => '']];
        $problems = Config::validate($bad);
        $t->assertCount(1, $problems);
        $t->assertContains('hosting.credential.secret is declared but empty', $problems[0]);
    });

    $t->test('the null driver needs no address and no credential', function (Runner $t) use ($good): void {
        $bare = $good;
        $bare['hosting'] = ['driver' => 'null'];
        $t->assertCount(0, Config::validate($bare));
    });

    $t->test('the fake driver needs a root', function (Runner $t) use ($good): void {
        $bad = $good;
        $bad['hosting'] = ['driver' => 'fake'];
        $problems = Config::validate($bad);
        $t->assertCount(1, $problems);
        $t->assertContains('hosting.fakeRoot', $problems[0]);
    });

    $t->test('a sample on disk still reads as a sample and publish refuses it', function (Runner $t): void {
        $dir = sys_get_temp_dir() . '/raips-config-' . getmypid();
        @mkdir($dir, 0775, true);
        copy(__DIR__ . '/../raips.config.sample.json', $dir . '/' . Config::NAME);
        $config = Config::load($dir);
        $t->assertSame('fake', $config->data['hosting']['driver']);
        $t->assertCount(0, Config::publishReadiness($config->data), 'the fake driver needs no credential');
        @unlink($dir . '/' . Config::NAME);
        @rmdir($dir);
    });
};
