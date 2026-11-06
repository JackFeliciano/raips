<?php

declare(strict_types=1);

namespace Raips\Domains;

/**
 * Builds the registrar a config names. The key comes from the environment and
 * never from the config file, which is the copy that reaches a repository.
 */
final class Factory
{
    /**
     * @param array<string, mixed> $domains
     * @param array<string, string> $env
     */
    public static function create(array $domains, array $env = []): Registrar
    {
        $driver = $domains['driver'] ?? 'fixture';
        $env = $env === [] ? self::environment() : $env;

        return match ($driver) {
            'fixture' => new FixtureRegistrar(self::table($domains)),
            'dynadot' => new Dynadot(
                key: self::key($domains, $env),
                endpoint: ($domains['sandbox'] ?? false) === true ? Dynadot::SANDBOX : Dynadot::ENDPOINT,
                showPrice: ($domains['showPrice'] ?? true) === true,
            ),
            default => throw new \RuntimeException('domains.driver ' . (string) $driver . ' is not one this build carries'),
        };
    }

    /** @return array<string, string> */
    private static function environment(): array
    {
        $out = [];
        foreach (['RAIPS_DYNADOT_KEY'] as $name) {
            $value = getenv($name);
            if (is_string($value)) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $domains
     * @param array<string, string> $env
     */
    private static function key(array $domains, array $env): string
    {
        $name = is_string($domains['keyEnv'] ?? null) ? $domains['keyEnv'] : 'RAIPS_DYNADOT_KEY';
        $key = $env[$name] ?? getenv($name);
        if (!is_string($key) || trim($key) === '') {
            throw new \RuntimeException('the registrar key is not in the environment under ' . $name);
        }

        return trim($key);
    }

    /**
     * @param array<string, mixed> $domains
     * @return array<string, array{available: ?bool, price?: ?string, currency?: ?string, premium?: bool}>
     */
    private static function table(array $domains): array
    {
        $table = $domains['fixtures'] ?? [];

        return is_array($table) ? $table : [];
    }
}
