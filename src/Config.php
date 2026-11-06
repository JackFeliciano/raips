<?php

declare(strict_types=1);

namespace Raips;

/**
 * Reads the file that holds the hosting account and checks it before the
 * publish step trusts it. The sample in the repository names every key and
 * leaves it empty, and this reader refuses a config that still looks like the
 * sample.
 */
final class Config
{
    public const NAME = 'raips.config.json';

    /** @param array<string, mixed> $data */
    private function __construct(
        public readonly array $data,
        public readonly string $path,
    ) {
    }

    public static function path(string $dir): string
    {
        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . self::NAME;
    }

    public static function load(string $dir): self
    {
        $path = self::path($dir);
        if (!is_file($path)) {
            throw new \RuntimeException('no ' . self::NAME . ' in ' . $dir . ', copy raips.config.sample.json and fill it in');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('cannot read ' . self::NAME . ' in ' . $dir);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException(self::NAME . ' is not a JSON object');
        }

        return new self($data, $path);
    }

    /**
     * A copy with one more key in the hosting block, so the caller that knows
     * where the config lives can hand that fact to the hosting factory without
     * reaching into a readonly array.
     *
     * @param mixed $value
     */
    public function withHosting(string $key, $value): self
    {
        $data = $this->data;
        $data['hosting'] = is_array($data['hosting'] ?? null) ? $data['hosting'] : [];
        $data['hosting'][$key] = $value;

        return new self($data, $this->path);
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string> empty when the config is usable
     */
    public static function validate(array $data): array
    {
        $problems = [];

        $site = $data['site'] ?? [];
        if (!is_array($site) || !isset($site['dir']) || !is_string($site['dir']) || trim($site['dir']) === '') {
            $problems[] = 'site.dir is required and names the folder that holds site.json';
        }

        $hosting = $data['hosting'] ?? null;
        if (!is_array($hosting)) {
            $problems[] = 'hosting is required';
            return $problems;
        }

        $driver = $hosting['driver'] ?? null;
        $known = ['fake', 'http', 'null'];
        if (!is_string($driver) || !in_array($driver, $known, true)) {
            $problems[] = 'hosting.driver must be one of ' . implode(', ', $known);
            return $problems;
        }

        if ($driver === 'fake') {
            $root = $hosting['fakeRoot'] ?? null;
            if (!is_string($root) || trim($root) === '') {
                $problems[] = 'hosting.fakeRoot is required for the fake driver';
            }

            $credential = $hosting['credential'] ?? null;
            if (is_array($credential)) {
                foreach (['keyId', 'secret'] as $name) {
                    if (array_key_exists($name, $credential) && (string) $credential[$name] === '') {
                        $problems[] = 'hosting.credential.' . $name . ' is declared but empty';
                    }
                }
            }

            return $problems;
        }

        if ($driver === 'null') {
            return $problems;
        }

        $base = $hosting['baseUrl'] ?? null;
        if (!is_string($base) || trim($base) === '') {
            $problems[] = 'hosting.baseUrl is required for the ' . $driver . ' driver';
        } elseif (preg_match('#^https?://#i', $base) !== 1) {
            $problems[] = 'hosting.baseUrl must start with http:// or https://';
        } elseif (stripos($base, 'http://') === 0 && !preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?(/|$)#i', $base)) {
            $problems[] = 'hosting.baseUrl over plain http is only allowed on the loopback address';
        }

        $credential = $hosting['credential'] ?? null;
        if (!is_array($credential)) {
            $problems[] = 'hosting.credential is required and names keyId and secret';
            return $problems;
        }

        foreach (['keyId', 'secret'] as $field) {
            $value = $credential[$field] ?? null;
            if (!is_string($value) || trim($value) === '') {
                $problems[] = 'hosting.credential.' . $field . ' is empty, the repository never carries a real one';
            }
        }

        return $problems;
    }

    /** @return list<string> the reasons a publish would stop right now */
    public static function publishReadiness(array $data): array
    {
        return self::validate($data);
    }
}
