<?php

declare(strict_types=1);

namespace Raips;

use Raips\Hosting\Factory;
use Raips\Hosting\Host;
use Raips\Hosting\PublishError;
use Raips\Hosting\Receipt;

/**
 * A project on disk: the site description, the config that reaches hosting and
 * the pages the builder reads.
 *
 * The config names the site folder with `site.dir`, resolved against the folder
 * that holds the config. One config then serves a checkout from anywhere, and
 * the CLI works from the project root or from examples/site without being told
 * which one it is looking at.
 */
final class Project
{
    /** @param list<Page> $pages */
    private function __construct(
        public readonly string $dir,
        public readonly string $name,
        public readonly string $base,
        public readonly ?string $lang,
        public readonly array $pages,
        public readonly Config $config,
    ) {
    }

    public static function load(string $dir): self
    {
        $config = Config::load(self::configDir($dir));
        $root = dirname($config->path);
        $declared = $config->data['site']['dir'] ?? null;

        $siteDir = is_string($declared) && $declared !== ''
            ? rtrim($root . DIRECTORY_SEPARATOR . $declared, "/\\")
            : rtrim($dir, "/\\");

        $config = $config->withHosting('__dir', $root);

        $siteFile = $siteDir . DIRECTORY_SEPARATOR . 'site.json';
        if (!is_file($siteFile)) {
            throw new \RuntimeException('no site.json in ' . $siteDir);
        }

        $data = self::read($siteFile);

        $base = is_string($data['base'] ?? null) ? $data['base'] : '/';
        $envBase = getenv('RAIPS_BASE');
        if (is_string($envBase) && trim($envBase) !== '') {
            $base = trim($envBase);
        }

        $pages = [];
        foreach (is_array($data['pages'] ?? null) ? $data['pages'] : [] as $page) {
            if (is_array($page)) {
                $pages[] = Page::fromArray($page);
            }
        }

        return new self(
            dir: $siteDir,
            name: is_string($data['name'] ?? null) ? $data['name'] : basename($siteDir),
            base: $base,
            lang: is_string($data['lang'] ?? null) ? $data['lang'] : null,
            pages: $pages,
            config: $config,
        );
    }

    /**
     * The config sits in the folder a member named or one level above it, so
     * a build from the project root and a build from the site folder find the
     * same file.
     */
    public static function configDir(string $dir): string
    {
        $dir = rtrim($dir, "/\\");
        $walk = $dir;
        for ($i = 0; $i < 3; $i++) {
            if (is_file($walk . DIRECTORY_SEPARATOR . Config::NAME)) {
                return $walk;
            }
            $up = dirname($walk);
            if ($up === $walk) {
                break;
            }
            $walk = $up;
        }

        return $dir;
    }

    /** @return array<string, mixed> */
    private static function read(string $file): array
    {
        $raw = file_get_contents($file);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException(basename($file) . ' is not a JSON object');
        }

        return $data;
    }

    /** @return list<array{path: string, body: string, type: string}> */
    public function build(): array
    {
        return Builder::buildSite($this->pages, $this->base, $this->lang);
    }

    public function host(): Host
    {
        return Factory::create($this->config->data['hosting']);
    }

    public function publish(): Receipt
    {
        $problems = Config::publishReadiness($this->config->data);
        if ($problems !== []) {
            throw new PublishError('the config is not ready to publish', ['problems' => $problems]);
        }

        return $this->host()->publish($this->build());
    }

    /** @return list<string> */
    public function problems(): array
    {
        $problems = [];
        foreach ($this->pages as $page) {
            foreach ($page->problems() as $problem) {
                $problems[] = $page->route . ': ' . $problem;
            }
        }

        return $problems;
    }
}
