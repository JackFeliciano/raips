<?php

declare(strict_types=1);

namespace Raips;

/**
 * Carries the list of things wrong with a page or a site, each a short
 * sentence a member can read.
 */
final class BuildError extends \RuntimeException
{
    /** @var list<string> */
    public readonly array $problems;

    /** @param list<string> $problems */
    public function __construct(array $problems)
    {
        $this->problems = $problems;
        parent::__construct(implode("\n", $problems));
    }
}
