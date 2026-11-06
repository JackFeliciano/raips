<?php

declare(strict_types=1);

namespace Raips\Domains;

/**
 * Checks a name and a list of extensions and answers only the names that are
 * usable: a name that is free, or a name with a price worth reading.
 */
final class Panel
{
    public const DEFAULT_TLDS = ['com', 'net', 'org', 'io', 'dev'];

    public function __construct(
        private readonly Registrar $registrar,
        private readonly array $tlds = self::DEFAULT_TLDS,
    ) {
    }

    /**
     * @param list<string> $tlds
     * @return list<array<string, mixed>>
     */
    public function search(string $name, array $tlds = []): array
    {
        $tlds = $tlds === [] ? $this->tlds : $tlds;
        $rows = [];
        foreach ($this->registrar->search($name, $tlds) as $lookup) {
            $rows[] = $lookup->toArray();
        }

        return $rows;
    }

    /**
     * The same search, ordered for a person reading it: free names first, then
     * the ones carrying a price, then the taken names.
     *
     * @param list<string> $tlds
     * @return list<array<string, mixed>>
     */
    public function suggestions(string $name, array $tlds = []): array
    {
        $rows = $this->search($name, $tlds);

        usort($rows, static function (array $a, array $b): int {
            $rank = static fn (array $row): int => match (true) {
                ($row['available'] ?? null) === true => 0,
                ($row['price'] ?? null) !== null => 1,
                ($row['available'] ?? null) === false => 2,
                default => 3,
            };

            return [$rank($a), $a['domain']] <=> [$rank($b), $b['domain']];
        });

        return $rows;
    }

    /**
     * The names a member can act on with a page: free, or carrying a price.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function usable(array $rows): array
    {
        return array_values(array_filter($rows, static function (array $row): bool {
            return ($row['available'] ?? null) === true || ($row['price'] ?? null) !== null;
        }));
    }
}
