<?php

declare(strict_types=1);

namespace Raips\Hosting;

/**
 * The contract every hosting driver keeps. A driver takes the list of files
 * the builder produced and answers with a receipt, or throws a PublishError.
 */
interface Host
{
    /**
     * @param list<array{path: string, body: string, type: string}> $files
     */
    public function publish(array $files): Receipt;
}
