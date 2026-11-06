<?php

declare(strict_types=1);

namespace Raips;

/**
 * Turns pages into files. Nothing here writes to disk, so a build can be
 * inspected, signed and published from the same list of strings.
 */
final class Builder
{
    /**
     * @param list<Page> $pages
     * @return list<array{path: string, body: string, type: string}>
     */
    public static function buildSite(array $pages, string $base = '/', ?string $lang = null): array
    {
        $files = [];
        $routes = [];

        foreach ($pages as $page) {
            $problems = $page->problems();
            if ($problems !== []) {
                throw new BuildError($problems);
            }

            $target = self::routeToFile($page->route);
            if (isset($routes[$target])) {
                throw new BuildError(['two pages write the same file, ' . $target]);
            }
            $routes[$target] = true;

            $files[] = [
                'path' => $target,
                'body' => self::renderPage($page, $base, $lang),
                'type' => 'text/html; charset=utf-8',
            ];
        }

        $files[] = [
            'path' => 'assets/site.css',
            'body' => self::stylesheet(),
            'type' => 'text/css; charset=utf-8',
        ];

        usort($files, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $files;
    }

    public static function renderPage(Page $page, string $base = '/', ?string $lang = null): string
    {
        $problems = $page->problems();
        if ($problems !== []) {
            throw new BuildError($problems);
        }

        $language = $page->lang ?? $lang ?? 'en';
        $base = $base === '' ? '/' : $base;
        $base = str_ends_with($base, '/') ? $base : $base . '/';

        $body = [];
        foreach ($page->sections as $section) {
            $body[] = Sections::render($section);
        }

        return Html::lines(
            '<!doctype html>',
            '<html lang="' . Html::esc($language) . '">',
            '  <head>',
            '    <meta charset="utf-8">',
            '    <meta name="viewport" content="width=device-width, initial-scale=1">',
            '    <title>' . Html::esc($page->title) . '</title>',
            $page->description === null ? '' : '    <meta name="description" content="' . Html::esc($page->description) . '">',
            '    <base href="' . Html::esc($base) . '">',
            '    <link rel="stylesheet" href="assets/site.css">',
            '  </head>',
            '  <body>',
            implode("\n", $body),
            '  </body>',
            '</html>',
        ) . "\n";
    }

    /** The file a route writes to, relative to the site root. */
    public static function routeToFile(string $route): string
    {
        $clean = trim($route, '/');
        if ($clean === '') {
            return 'index.html';
        }
        if (pathinfo($clean, PATHINFO_EXTENSION) !== '') {
            return $clean;
        }

        return $clean . '/index.html';
    }

    public static function stylesheet(): string
    {
        return <<<'CSS'
        :root {
          --crimson: #880000;
          --ink: #1c1c1c;
          --paper: #ffffff;
          --muted: #6a6a6a;
          --line: #e4e4e4;
        }
        * { box-sizing: border-box; }
        body {
          margin: 0;
          font-family: Georgia, "Times New Roman", serif;
          color: var(--ink);
          background: var(--paper);
          line-height: 1.6;
        }
        .r-header {
          display: flex;
          gap: 1.5rem;
          align-items: center;
          padding: 1.1rem 6vw;
          border-bottom: 1px solid var(--line);
        }
        .r-header .brand {
          font-weight: 700;
          color: var(--crimson);
          text-decoration: none;
          margin-right: auto;
        }
        .r-header nav a { color: var(--ink); text-decoration: none; margin-left: 1.1rem; }
        .r-section, .r-hero { padding: 3rem 6vw; max-width: 60rem; margin: 0 auto; }
        .r-hero { padding-top: 5rem; padding-bottom: 4rem; }
        .r-hero .eyebrow {
          text-transform: uppercase;
          letter-spacing: 0.14em;
          font-size: 0.78rem;
          color: var(--crimson);
          margin: 0 0 0.7rem;
        }
        .r-hero h1 { font-size: 2.6rem; line-height: 1.15; margin: 0 0 1rem; }
        .r-hero .lede { font-size: 1.15rem; color: var(--muted); margin: 0 0 1.6rem; }
        h2 { font-size: 1.7rem; margin: 0 0 1.4rem; color: var(--crimson); }
        h3 { font-size: 1.1rem; margin: 0 0 0.4rem; }
        .grid { list-style: none; padding: 0; margin: 0; display: grid; gap: 1.6rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
        .plan .price { font-size: 1.5rem; color: var(--crimson); margin: 0.2rem 0 0.6rem; }
        details { border-top: 1px solid var(--line); padding: 0.9rem 0; }
        details summary { cursor: pointer; font-weight: 600; }
        .note { color: var(--muted); font-size: 0.85rem; margin: 0 0 0.4rem; }
        figure { margin: 0; }
        blockquote { margin: 0 0 0.5rem; font-style: italic; }
        blockquote + figcaption { color: var(--muted); font-size: 0.9rem; }
        img { max-width: 100%; height: auto; display: block; }
        .cta {
          display: inline-block;
          margin-top: 0.8rem;
          padding: 0.65rem 1.3rem;
          background: var(--crimson);
          color: #ffffff;
          text-decoration: none;
          border-radius: 2px;
        }
        .r-cta { text-align: center; }
        .r-footer {
          border-top: 1px solid var(--line);
          padding: 2rem 6vw;
          color: var(--muted);
          font-size: 0.9rem;
        }
        .r-footer nav a { color: var(--muted); margin-right: 1rem; }
        CSS;
    }
}
