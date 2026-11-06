<?php

declare(strict_types=1);

namespace Raips\Showroom;

use Raips\Builder;
use Raips\Crypto;
use Raips\Page;
use Raips\Sections;

/**
 * The live box. One process, one port, four things to look at.
 *
 *   /              the built site, rendered by this repository's builder
 *   /demo/         a page that builds a new site from a section template and
 *                  publishes it, on the visitor's press
 *   /run/<id>/     the site that press produced
 *   /health        a plain readiness answer
 *
 * Every press writes into its own directory under the data directory, so two
 * presses never collide. Nothing outside the data directory is written.
 */
final class Server
{
    private const TYPES = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
    ];

    private string $root;
    private string $data;
    private string $runs;
    private string $live;
    private string $demoFile;
    private string $base;

    public function __construct(private readonly string $site, ?string $dataDir = null)
    {
        $this->root = dirname($site);
        $this->data = $dataDir ?? $this->root . DIRECTORY_SEPARATOR . '.data';
        $this->runs = $this->data . DIRECTORY_SEPARATOR . 'runs';
        $this->live = $this->runs . DIRECTORY_SEPARATOR . 'live';
        $this->demoFile = $site . DIRECTORY_SEPARATOR . 'demo.html';
        $this->base = self::env('RAIPS_BASE', '/');

        if (!is_dir($this->live) && !mkdir($this->live, 0o775, true) && !is_dir($this->live)) {
            throw new \RuntimeException('cannot create the data directory at ' . $this->live);
        }

        // The landing site is rebuilt on start, so the host always serves what
        // this version of the builder renders.
        $this->writeSite($this->live, self::landingPages());
    }

    private static function env(string $name, string $fallback): string
    {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            return $fallback;
        }

        return trim($value);
    }

    public function serve(): never
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        if ($method === 'GET' && $path === '/health') {
            $this->json(200, ['ok' => true, 'sections' => count(Sections::names())]);
        }

        if ($method === 'GET' && $path === '/section-names') {
            $this->json(200, ['sections' => Sections::names()]);
        }

        if ($method === 'GET' && $path === '/demo') {
            header('location: ./demo/', true, 301);
            exit;
        }

        if ($method === 'GET' && $path === '/demo/') {
            $this->demo();
        }

        if ($method === 'POST' && $path === '/publish') {
            $this->publish();
        }

        if ($method === 'GET' && preg_match('#^/run/([0-9a-f]{6,16})(/.*)?$#', $path, $m) === 1) {
            $this->file($this->runs . DIRECTORY_SEPARATOR . $m[1], $m[2] ?? '/');
        }

        if ($method === 'GET') {
            $this->file($this->live, $path);
        }

        $this->json(405, ['error' => ['code' => 'method_not_allowed', 'message' => $method]]);
    }

    // ---- the site that answers on / --------------------------------------

    /** @return list<Page> */
    private static function landingPages(): array
    {
        $names = Sections::names();

        return [
            Page::fromArray([
                'route' => '/',
                'title' => 'RAIPS',
                'description' => 'A website system: a page written as JSON, rendered by section templates, published to a hosting panel.',
                'sections' => [
                    ['type' => 'header', 'props' => [
                        'brand' => 'RAIPS',
                        'links' => [
                            ['label' => 'Sections', 'href' => '#sections'],
                            ['label' => 'Demo', 'href' => 'demo/'],
                        ],
                        'cta' => ['label' => 'Build a page', 'href' => 'demo/'],
                    ]],
                    ['type' => 'hero', 'props' => [
                        'eyebrow' => 'Website system',
                        'heading' => 'A page written as JSON, published to a panel',
                        'lead' => 'This page came out of the builder in this repository. Every section on it is a template taking props, and the publish step signs what it sends.',
                        'buttons' => [
                            ['label' => 'Build a page', 'href' => 'demo/', 'style' => 'primary'],
                            ['label' => 'Read the code', 'href' => 'https://github.com/JackFeliciano/raips', 'style' => 'secondary'],
                        ],
                    ]],
                    ['type' => 'features', 'props' => [
                        'heading' => 'The path a page takes',
                        'items' => [
                            ['title' => 'site.json', 'text' => 'A route, a title and a list of sections, each with its props.'],
                            ['title' => 'Builder', 'text' => 'Checks the route, renders each section through its template, writes the files.'],
                            ['title' => 'Publish', 'text' => 'Sends a manifest, then one signed request per file.'],
                        ],
                    ]],
                    ['type' => 'overview', 'props' => [
                        'heading' => 'Thirteen section templates',
                        'items' => array_map(
                            static fn (string $n): string => $n . ': a function that takes props and returns a fragment.',
                            $names,
                        ),
                    ]],
                    ['type' => 'cta', 'props' => [
                        'heading' => 'Watch it build',
                        'text' => 'The demo builds a page from whichever template you pick and puts it on this host.',
                        'cta' => 'Open the demo',
                        'href' => 'demo/',
                    ]],
                    ['type' => 'footer', 'props' => [
                        'brand' => 'RAIPS',
                        'links' => [['label' => 'Demo', 'href' => 'demo/']],
                        'social' => [['label' => 'Repository', 'href' => 'https://github.com/JackFeliciano/raips']],
                        'legal' => 'Mozilla Public License 2.0',
                    ]],
                ],
            ]),
            Page::fromArray([
                'route' => '/about',
                'title' => 'How it works',
                'description' => 'The path from a page object to files on a hosting panel.',
                'sections' => [
                    ['type' => 'header', 'props' => ['brand' => 'RAIPS', 'links' => [['label' => 'Home', 'href' => './']]]],
                    ['type' => 'about', 'props' => [
                        'heading' => 'How it works',
                        'paragraphs' => [
                            'A site project holds site.json and a config. The config names the hosting driver, the endpoint and the credential.',
                            'The builder validates every route before it renders anything, so a page that would escape the output directory stops the build rather than writing outside it.',
                            'The publish step collects the files, sends a manifest, then one request per file. Each request carries three headers: the key id, a unix timestamp and an HMAC-SHA256 signature over the method, the path, the timestamp and a digest of the body.',
                            'A panel that verifies the signature with a shared secret and a 300 second window will reject a tampered body, a wrong key and a replayed request.',
                        ],
                    ]],
                    ['type' => 'cta', 'props' => [
                        'heading' => 'Read the protocol',
                        'text' => 'The signature and the two endpoints are documented in the README.',
                        'cta' => 'Repository',
                        'href' => 'https://github.com/JackFeliciano/raips',
                    ]],
                    ['type' => 'footer', 'props' => ['brand' => 'RAIPS', 'legal' => 'Mozilla Public License 2.0']],
                ],
            ]),
        ];
    }

    /**
     * Writes a set of pages into a directory, refusing any path that would
     * land outside it.
     *
     * @param list<Page> $pages
     * @return list<array{path: string, body: string, type: string}>
     */
    private function writeSite(string $dir, array $pages): array
    {
        $files = Builder::buildSite($pages, $this->base);
        $base = rtrim(str_replace('\\', '/', $dir), '/') . '/';

        foreach ($files as $file) {
            $full = $dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['path']);
            $normal = str_replace('\\', '/', $full);
            if (!str_starts_with($normal, $base)) {
                throw new \RuntimeException('path escapes the run directory');
            }
            $parent = dirname($full);
            if (!is_dir($parent) && !mkdir($parent, 0o775, true) && !is_dir($parent)) {
                throw new \RuntimeException('cannot create ' . $parent);
            }
            file_put_contents($full, $file['body']);
        }

        return $files;
    }

    // ---- one press, one run ----------------------------------------------

    /** @return array<string, mixed> */
    private function run(string $section): array
    {
        $id = bin2hex(random_bytes(6));
        $dir = $this->runs . DIRECTORY_SEPARATOR . $id;
        if (!mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException('cannot create the run directory');
        }

        $props = [
            'heading' => 'The ' . $section . ' template, rendered from props',
            'lead' => 'This page exists because someone pressed a button. The builder read a page object with a ' . $section . ' section and wrote these files.',
            'items' => [
                ['title' => 'Props in', 'text' => 'A page object holding the route, the title and the sections.'],
                ['title' => 'Template', 'text' => 'The ' . $section . ' function escaped every value and returned a fragment.'],
                ['title' => 'Files out', 'text' => 'One HTML document and one stylesheet, signed and published.'],
            ],
            'paragraphs' => [
                'This is the run ' . $id . '.',
                'The builder rendered the ' . $section . ' template from the props above, and the stylesheet beside this document is the one the builder ships.',
            ],
        ];

        $files = $this->writeSite($dir, [Page::fromArray([
            'route' => '/',
            'title' => 'RAIPS run ' . $id,
            'description' => 'A page built from the ' . $section . ' template.',
            'sections' => [
                ['type' => 'header', 'props' => [
                    'brand' => 'Run ' . $id,
                    'links' => [['label' => 'Back to the demo', 'href' => '../../demo/']],
                ]],
                ['type' => $section, 'props' => $props],
                ['type' => 'footer', 'props' => [
                    'brand' => 'RAIPS',
                    'legal' => 'Run ' . $id,
                    'copyright' => 'Built by the builder at ' . gmdate('c'),
                ]],
            ],
        ])]);

        $stamp = time();
        $manifest = json_encode([
            'site' => 'run-' . $id,
            'files' => array_map(
                static fn (array $f): array => ['path' => $f['path'], 'bytes' => strlen($f['body'])],
                $files,
            ),
        ], JSON_UNESCAPED_SLASHES);

        $key = self::env('RAIPS_KEY', 'demo-key');
        $bytes = 0;
        foreach ($files as $f) {
            $bytes += strlen($f['body']);
        }

        return [
            'id' => $id,
            'url' => 'run/' . $id . '/',
            'section' => $section,
            'pages' => 1,
            'files' => count($files),
            'bytes' => $bytes,
            'manifest' => [
                'path' => '/__raips/manifest',
                'timestamp' => $stamp,
                'signature' => Crypto::signRequest($key, 'POST', '/__raips/manifest', $stamp, (string) $manifest),
                'body' => $manifest,
            ],
        ];
    }

    // ---- answers ---------------------------------------------------------

    /** @param array<string, mixed> $value */
    private function json(int $status, array $value): never
    {
        $body = json_encode($value, JSON_UNESCAPED_SLASHES);
        http_response_code($status);
        header('content-type: application/json; charset=utf-8');
        header('cache-control: no-store');
        echo $body;
        exit;
    }

    private function demo(): never
    {
        if (!is_file($this->demoFile)) {
            $this->json(500, ['error' => ['code' => 'demo_missing', 'message' => 'demo page is not on disk']]);
        }

        header('content-type: text/html; charset=utf-8');
        header('cache-control: no-store');
        readfile($this->demoFile);
        exit;
    }

    private function publish(): never
    {
        $raw = file_get_contents('php://input', false, null, 0, 100_001);
        $raw = is_string($raw) ? $raw : '';

        if (strlen($raw) > 100_000) {
            $this->json(413, ['error' => ['code' => 'too_big', 'message' => 'body over 100000 bytes']]);
        }

        $body = [];
        if (trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $this->json(400, ['error' => ['code' => 'bad_body', 'message' => 'body is not json']]);
            }
            $body = $decoded;
        }

        $type = $body['section'] ?? null;
        $section = is_string($type) && Sections::knows($type) ? $type : 'hero';

        try {
            $this->json(200, $this->run($section));
        } catch (\Throwable $e) {
            $this->json(500, ['error' => ['code' => 'build_failed', 'message' => $e->getMessage()]]);
        }
    }

    private function file(string $dir, string $path): never
    {
        $resolved = self::resolve($dir, $path);
        if ($resolved === null) {
            $this->json(404, ['error' => ['code' => 'not_found', 'message' => 'nothing at this path']]);
        }

        $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
        header('content-type: ' . (self::TYPES[$ext] ?? 'application/octet-stream'));
        header('cache-control: ' . ($ext === 'html' ? 'no-store' : 'public, max-age=300'));
        header('x-content-type-options: nosniff');
        readfile($resolved);
        exit;
    }

    /**
     * Turns a request path into a file inside one directory, or null when the
     * path points at nothing or climbs out.
     */
    public static function resolve(string $dir, string $path): ?string
    {
        $clean = str_replace('\\', '/', $path);
        if (str_contains($clean, "\0") || str_contains($clean, '..')) {
            return null;
        }

        $relative = ltrim($clean, '/');
        if ($relative === '' || str_ends_with($relative, '/')) {
            $relative .= 'index.html';
        }

        $root = rtrim(str_replace('\\', '/', $dir), '/');
        $full = $root . '/' . $relative;

        if (!is_file($full)) {
            return null;
        }

        $real = realpath($full);
        $realRoot = realpath($root);
        if ($real === false || $realRoot === false || !str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }
}
