<?php

declare(strict_types=1);

use Raips\BuildError;
use Raips\Builder;
use Raips\Page;
use Raips\Sections;
use Raips\Tests\Runner;

return static function (Runner $t): void {
    $page = static function (array $over = []): Page {
        return Page::fromArray($over + [
            'route' => '/',
            'title' => 'A page',
            'sections' => [['type' => 'hero', 'props' => ['heading' => 'Hello']]],
        ]);
    };

    $t->test('the builder carries thirteen templates', function (Runner $t): void {
        $t->assertCount(13, Sections::names());
        foreach (['header', 'hero', 'features', 'overview', 'pricing', 'faq', 'about', 'gallery', 'blog', 'contact', 'cta', 'testimonials', 'footer'] as $name) {
            $t->assertTrue(Sections::knows($name), $name . ' is missing');
        }
    });

    $t->test('a page writes a doctype, a title and its sections', function (Runner $t) use ($page): void {
        $html = Builder::renderPage($page());
        $t->assertContains('<!doctype html>', $html);
        $t->assertContains('<title>A page</title>', $html);
        $t->assertContains('<h1>Hello</h1>', $html);
    });

    $t->test('a page without a base gets the root', function (Runner $t) use ($page): void {
        $html = Builder::renderPage($page());
        $t->assertContains('<base href="/">', $html);
        $t->assertContains('href="assets/site.css"', $html);
    });

    $t->test('a site that names a base puts it in front of every relative address', function (Runner $t) use ($page): void {
        $html = Builder::renderPage($page(), '/raips/');
        $t->assertContains('<base href="/raips/">', $html);
        $t->assertContains('href="assets/site.css"', $html);
    });

    $t->test('a base without a trailing slash gains one', function (Runner $t) use ($page): void {
        $html = Builder::renderPage($page(), '/shop');
        $t->assertContains('<base href="/shop/">', $html);
    });

    $t->test('the heading goes through the escaper', function (Runner $t) use ($page): void {
        $html = Builder::renderPage($page([
            'sections' => [['type' => 'hero', 'props' => ['heading' => '<script>alert(1)</script>']]],
        ]));
        $t->assertNotContains('<script>alert(1)</script>', $html);
        $t->assertContains('&lt;script&gt;', $html);
    });

    $t->test('a template this build does not carry stops the build', function (Runner $t) use ($page): void {
        $e = $t->assertThrows(BuildError::class, function (Runner $t) use ($page): void {
            Builder::renderPage($page(['sections' => [['type' => 'carousel']]]));
        });
        $t->assertContains('is not a template this build carries', $e->problems[0]);
    });

    $t->test('a page with no route is refused', function (Runner $t) use ($page): void {
        $e = $t->assertThrows(BuildError::class, function (Runner $t) use ($page): void {
            Builder::renderPage($page(['route' => '']));
        });
        $t->assertContains('page.route is required', $e->problems[0]);
    });

    $t->test('a route that climbs out of the root is refused', function (Runner $t) use ($page): void {
        $problems = $page(['route' => '/../etc/passwd'])->problems();
        $t->assertTrue($problems !== [], 'a climbing route passed');
    });

    $t->test('a section with no type is refused', function (Runner $t) use ($page): void {
        $problems = $page(['sections' => [['props' => []]]])->problems();
        $t->assertContains('page.sections[0].type is required', $problems[0]);
    });

    $t->test('the root route writes index.html', function (Runner $t): void {
        $t->assertSame('index.html', Builder::routeToFile('/'));
        $t->assertSame('contact/index.html', Builder::routeToFile('/contact/'));
        $t->assertSame('contact/index.html', Builder::routeToFile('/contact'));
        $t->assertSame('feed.xml', Builder::routeToFile('/feed.xml'));
    });

    $t->test('a build writes one file per page and one stylesheet', function (Runner $t) use ($page): void {
        $files = Builder::buildSite([$page(), $page(['route' => '/about/', 'title' => 'About'])]);
        $paths = array_map(static fn (array $f): string => $f['path'], $files);
        $t->assertCount(3, $paths);
        $t->assertTrue(in_array('index.html', $paths, true), 'index.html is missing');
        $t->assertTrue(in_array('about/index.html', $paths, true), 'about/index.html is missing');
        $t->assertTrue(in_array('assets/site.css', $paths, true), 'the stylesheet is missing');
    });

    $t->test('two pages that write the same file stop the build', function (Runner $t) use ($page): void {
        $e = $t->assertThrows(BuildError::class, function (Runner $t) use ($page): void {
            Builder::buildSite([$page(), $page()]);
        });
        $t->assertContains('two pages write the same file', $e->problems[0]);
    });

    $t->test('the stylesheet carries the crimson and no external address', function (Runner $t): void {
        $css = Builder::stylesheet();
        $t->assertContains('#880000', $css);
        $t->assertNotContains('@import', $css);
        $t->assertNotContains('http://', $css);
    });

    $t->test('a section with a bad link address drops the link instead of writing it', function (Runner $t) use ($page): void {
        $html = Builder::renderPage($page([
            'sections' => [['type' => 'cta', 'props' => ['heading' => 'Go', 'href' => 'javascript:alert(1)']]],
        ]));
        $t->assertNotContains('javascript:', $html);
        $t->assertContains('<h2>Go</h2>', $html);
    });

    $t->test('a page names its language', function (Runner $t) use ($page): void {
        $t->assertContains('<html lang="en">', Builder::renderPage($page()));
        $t->assertContains('<html lang="pt-BR">', Builder::renderPage($page(['lang' => 'pt-BR'])));
    });

    $t->test('a description reaches the head as a meta tag', function (Runner $t) use ($page): void {
        $html = Builder::renderPage($page(['description' => 'A short note.']));
        $t->assertContains('<meta name="description" content="A short note.">', $html);
    });
};
