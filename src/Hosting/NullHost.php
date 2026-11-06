<?php

declare(strict_types=1);

namespace Raips\Hosting;

/**
 * Takes a site and drops it. A build step uses it to prove the pipeline runs
 * without touching an account.
 */
final class NullHost implements Host
{
    public function publish(array $files): Receipt
    {
        $bytes = 0;
        foreach ($files as $file) {
            $bytes += strlen($file['body']);
        }

        return new Receipt(
            host: 'null',
            files: count($files),
            bytes: $bytes,
            root: '',
        );
    }
}
