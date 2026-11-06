<?php

declare(strict_types=1);

namespace Raips\Hosting;

/**
 * Writes a site into a folder on this machine. The tests use it, and a member
 * uses it to look at a build before a real publish.
 */
final class FakeHost implements Host
{
    public function __construct(
        private readonly string $root,
        private readonly ?string $keyId = null,
        private readonly ?string $secret = null,
    ) {
    }

    public function publish(array $files): Receipt
    {
        if ($this->keyId !== null && trim($this->keyId) === '') {
            throw new PublishError('the fake host has no key id', ['reason' => 'missing_credential']);
        }
        if ($this->secret !== null && trim($this->secret) === '') {
            throw new PublishError('the fake host has no secret', ['reason' => 'missing_credential']);
        }

        $root = rtrim($this->root, '/\\');
        $written = [];
        $bytes = 0;

        foreach ($files as $file) {
            $relative = $file['path'];
            if (!self::insideRoot($relative)) {
                throw new PublishError('a file asked to land outside the root', ['path' => $relative]);
            }

            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new PublishError('cannot make the folder for ' . $relative, ['path' => $relative]);
            }

            $length = file_put_contents($target, $file['body']);
            if ($length === false) {
                throw new PublishError('cannot write ' . $relative, ['path' => $relative]);
            }

            $written[] = ['path' => $relative, 'bytes' => $length];
            $bytes += $length;
        }

        return new Receipt(
            host: 'fake',
            files: count($written),
            bytes: $bytes,
            root: $root,
            files_: $written,
        );
    }

    /** A relative path that stays under the root. */
    public static function insideRoot(string $relative): bool
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_starts_with($relative, '\\')) {
            return false;
        }
        if (preg_match('#^[a-z]:#i', $relative) === 1) {
            return false;
        }
        foreach (explode('/', str_replace('\\', '/', $relative)) as $part) {
            if ($part === '..') {
                return false;
            }
        }

        return true;
    }
}
