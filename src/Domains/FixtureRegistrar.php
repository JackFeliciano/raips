<?php

declare(strict_types=1);

namespace Raips\Domains;

/**
 * A registrar that answers from a table on disk. The tests use it, and a
 * member uses it to try the panel without an account.
 */
final class FixtureRegistrar implements Registrar
{
    /** @param array<string, array{available: ?bool, price?: ?string, currency?: ?string, premium?: bool}> $table */
    public function __construct(private readonly array $table)
    {
    }

    public function search(string $name, array $tlds): array
    {
        $name = Dynadot::normalise($name);
        $out = [];

        foreach ($tlds as $tld) {
            $tld = Dynadot::normaliseTld($tld);
            if ($tld === '') {
                continue;
            }
            $domain = $name . '.' . $tld;
            $row = $this->table[$domain] ?? null;
            $out[] = new Lookup(
                domain: $domain,
                available: $row['available'] ?? null,
                currency: $row['currency'] ?? null,
                price: $row['price'] ?? null,
                premium: (bool) ($row['premium'] ?? false),
                reason: $row === null ? 'the fixture holds no answer for this name' : null,
            );
        }

        return $out;
    }
}
