<?php

declare(strict_types=1);

namespace Raips\Domains;

/**
 * The contract a registrar driver keeps. A search takes a name, a list of
 * extensions or both, and answers one Lookup per candidate.
 */
interface Registrar
{
    /**
     * @param list<string> $tlds extensions to try, each without the leading dot
     * @return list<Lookup>
     */
    public function search(string $name, array $tlds): array;
}
