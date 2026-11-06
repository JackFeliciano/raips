<?php

declare(strict_types=1);

namespace Raips\Tests;

/**
 * A runner with no dependency behind it. It collects a case per file, runs
 * every case and prints one line per failure and a count at the end.
 */
final class Runner
{
    private int $passed = 0;
    /** @var list<string> */
    private array $failures = [];
    private string $group = '';

    public static function main(array $files): int
    {
        $runner = new self();
        foreach ($files as $file) {
            $runner->group = basename($file, '.php');
            $register = require $file;
            if (is_callable($register)) {
                $register($runner);
            }
        }

        return $runner->report();
    }

    public function test(string $name, callable $body): void
    {
        try {
            $body($this);
            $this->passed++;
        } catch (\Throwable $e) {
            $this->failures[] = $this->group . ': ' . $name . ' -> ' . $e->getMessage();
        }
    }

    public function assertTrue(bool $value, string $say = 'expected true'): void
    {
        if (!$value) {
            throw new \RuntimeException($say);
        }
    }

    public function assertFalse(bool $value, string $say = 'expected false'): void
    {
        $this->assertTrue(!$value, $say);
    }

    public function assertSame(mixed $expected, mixed $actual, string $say = ''): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                ($say === '' ? '' : $say . ', ') . 'expected ' . var_export($expected, true)
                . ' and read ' . var_export($actual, true)
            );
        }
    }

    public function assertContains(string $needle, string $haystack, string $say = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException(
                ($say === '' ? '' : $say . ', ') . 'looked for ' . $needle . ' and did not find it'
            );
        }
    }

    public function assertNotContains(string $needle, string $haystack, string $say = ''): void
    {
        if (str_contains($haystack, $needle)) {
            throw new \RuntimeException(
                ($say === '' ? '' : $say . ', ') . 'found ' . $needle . ' and wanted it gone'
            );
        }
    }

    public function assertCount(int $expected, array $actual, string $say = ''): void
    {
        if (count($actual) !== $expected) {
            throw new \RuntimeException(
                ($say === '' ? '' : $say . ', ') . 'expected ' . $expected . ' and counted ' . count($actual)
            );
        }
    }

    /** @param class-string<\Throwable> $class */
    public function assertThrows(string $class, callable $body, string $say = ''): \Throwable
    {
        try {
            $body($this);
        } catch (\Throwable $e) {
            if (!$e instanceof $class) {
                throw new \RuntimeException(
                    ($say === '' ? '' : $say . ', ') . 'expected ' . $class . ' and caught ' . $e::class
                );
            }

            return $e;
        }

        throw new \RuntimeException(($say === '' ? '' : $say . ', ') . 'expected ' . $class . ' and nothing was thrown');
    }

    public function report(): int
    {
        foreach ($this->failures as $failure) {
            fwrite(STDERR, 'FAIL ' . $failure . "\n");
        }

        $total = $this->passed + count($this->failures);
        fwrite(STDOUT, $this->passed . ' passed, ' . count($this->failures) . ' failed, ' . $total . " checks\n");

        return $this->failures === [] ? 0 : 1;
    }
}
