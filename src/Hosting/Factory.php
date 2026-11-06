<?php

declare(strict_types=1);

namespace Raips\Hosting;

/**
 * Builds the driver a config names. The publish step receives a Host and never
 * branches on the driver again.
 */
final class Factory
{
    /** @param array<string, mixed> $hosting */
    public static function create(array $hosting): Host
    {
        $driver = $hosting['driver'] ?? '';
        $credential = is_array($hosting['credential'] ?? null) ? $hosting['credential'] : [];

        return match ($driver) {
            'fake' => new FakeHost(
                root: self::pathFrom((string) ($hosting['fakeRoot'] ?? ''), $hosting),
                keyId: isset($credential['keyId']) ? (string) $credential['keyId'] : null,
                secret: isset($credential['secret']) ? (string) $credential['secret'] : null,
            ),
            'http' => new HttpHost(
                baseUrl: (string) ($hosting['baseUrl'] ?? ''),
                keyId: (string) ($credential['keyId'] ?? ''),
                secret: (string) ($credential['secret'] ?? ''),
            ),
            'null' => new NullHost(),
            default => throw new PublishError('hosting.driver ' . (string) $driver . ' is not one this build carries', [
                'reason' => 'unknown_driver',
            ]),
        };
    }

    /**
     * A relative fakeRoot hangs off the directory that holds the config, so a
     * checkout published to the fake host keeps its output beside itself.
     *
     * @param array<string, mixed> $hosting
     */
    private static function pathFrom(string $path, array $hosting): string
    {
        $backslash = chr(92);
        $absolute = str_starts_with($path, '/') || str_starts_with($path, $backslash)
            || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':');
        if ($path === '' || $absolute) {
            return $path;
        }

        $dir = isset($hosting['__dir']) && is_string($hosting['__dir']) ? $hosting['__dir'] : getcwd();

        return rtrim((string) $dir, "/\\") . DIRECTORY_SEPARATOR . $path;
    }
}
