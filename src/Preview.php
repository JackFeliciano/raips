<?php

declare(strict_types=1);

namespace Raips;

/**
 * Answers a browser from a build held in memory: the same list of files the
 * publish step would send, served over http so a member can look at the pages
 * before they go anywhere.
 */
final class Preview
{
    /** @var array<string, array{path: string, body: string, type: string}> */
    private array $byPath = [];

    /** @param list<array{path: string, body: string, type: string}> $files */
    public function __construct(array $files)
    {
        foreach ($files as $file) {
            $this->byPath['/' . ltrim($file['path'], '/')] = $file;
        }
    }

    /**
     * @return array{status: int, type: string, body: string}
     */
    public function respond(string $requestPath): array
    {
        $path = parse_url($requestPath, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = rawurldecode($path);

        if ($path === '' || str_ends_with($path, '/')) {
            $path .= 'index.html';
        }

        if (str_contains($path, '..')) {
            return $this->notFound();
        }

        $file = $this->byPath[$path] ?? null;
        if ($file === null) {
            return $this->notFound();
        }

        return ['status' => 200, 'type' => $file['type'], 'body' => $file['body']];
    }

    /** @return list<string> */
    public function paths(): array
    {
        $paths = array_keys($this->byPath);
        sort($paths);

        return $paths;
    }

    /** @return array{status: int, type: string, body: string} */
    private function notFound(): array
    {
        return [
            'status' => 404,
            'type' => 'text/plain; charset=utf-8',
            'body' => "not found\n",
        ];
    }
}
