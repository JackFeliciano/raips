<?php

declare(strict_types=1);

use Raips\Hosting\FakeHost;
use Raips\Project;
use Raips\Tests\Runner;

/**
 * The example site that ships in the repository is the one a new member runs
 * first, so it has to build, publish to the fake host and pass the route
 * checks without a key anywhere.
 */
return static function (Runner $t): void {
    $project = static function (): Project {
        return Project::load(__DIR__ . '/../examples/site');
    };

    $t->test('the example site loads and has no problems', function (Runner $t) use ($project): void {
        $t->assertCount(0, $project()->problems());
    });

    $t->test('the example site builds three pages and a stylesheet', function (Runner $t) use ($project): void {
        $files = $project()->build();
        $paths = array_map(static fn (array $f): string => $f['path'], $files);
        $t->assertCount(4, $paths);
        $t->assertTrue(in_array('index.html', $paths, true), 'index.html is missing');
        $t->assertTrue(in_array('writing/index.html', $paths, true), 'writing/index.html is missing');
        $t->assertTrue(in_array('contact/index.html', $paths, true), 'contact/index.html is missing');
    });

    $t->test('the home page carries the crimson and the sections in order', function (Runner $t) use ($project): void {
        $files = $project()->build();
        $home = '';
        foreach ($files as $file) {
            if ($file['path'] === 'index.html') {
                $home = $file['body'];
            }
        }
        $t->assertContains('<h1>Sites that stay up</h1>', $home);
        $t->assertContains('r-features', $home);
        $t->assertContains('r-faq', $home);
        $t->assertContains('r-footer', $home);
        $t->assertTrue(strpos($home, 'r-hero') < strpos($home, 'r-footer'), 'the hero came after the footer');
    });

    $t->test('the example site publishes to a fake host on disk', function (Runner $t) use ($project): void {
        $root = sys_get_temp_dir() . '/raips-example-' . bin2hex(random_bytes(4));
        mkdir($root, 0775, true);
        $receipt = (new FakeHost($root))->publish($project()->build());
        $t->assertSame(4, $receipt->files);
        $t->assertTrue(is_file($root . '/index.html'), 'index.html did not land');
        $t->assertTrue(is_file($root . '/assets/site.css'), 'the stylesheet did not land');
    });

    $t->test('the environment names the base and beats the site file', function (Runner $t): void {
        $site = __DIR__ . '/../examples/site';
        $plain = Project::load($site);
        $t->assertSame('/', $plain->base);

        putenv('RAIPS_BASE=/raips/');
        $prefixed = Project::load($site);
        putenv('RAIPS_BASE');

        $t->assertSame('/raips/', $prefixed->base);
        $built = $prefixed->build();
        $home = '';
        foreach ($built as $file) {
            if ($file['path'] === 'index.html') {
                $home = $file['body'];
            }
        }
        $t->assertContains('<base href="/raips/">', $home);
    });

    $t->test('the sample config names the fake driver and carries no credential', function (Runner $t): void {
        $sample = json_decode((string) file_get_contents(__DIR__ . '/../raips.config.sample.json'), true);
        $t->assertTrue(is_array($sample), 'the sample is not a JSON object');
        $t->assertSame('fake', (string) ($sample['hosting']['driver'] ?? ''));
        $t->assertFalse(
            array_key_exists('credential', $sample['hosting'] ?? []),
            'the sample declares a credential block, and an empty one stops a publish'
        );
        $t->assertSame('RAIPS_DYNADOT_KEY', (string) ($sample['domains']['keyEnv'] ?? ''));
    });
};
