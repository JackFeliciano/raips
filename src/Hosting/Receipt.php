<?php

declare(strict_types=1);

namespace Raips\Hosting;

/**
 * What a host answers after it takes a site. Every driver returns this same
 * shape, so the publish step never asks which driver ran.
 */
final class Receipt
{
    /**
     * @param list<array{path: string, bytes: int}> $files
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $host,
        public readonly int $files,
        public readonly int $bytes,
        public readonly string $root,
        public readonly array $files_ = [],
        public readonly array $extra = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'files' => $this->files,
            'bytes' => $this->bytes,
            'root' => $this->root,
        ] + $this->extra;
    }
}
