<?php

declare(strict_types=1);

namespace Raips\Hosting;

/**
 * Raised when the host refuses a publish. The detail carries what the host
 * answered so a member can read the reason.
 */
final class PublishError extends \RuntimeException
{
    /** @param array<string, mixed> $detail */
    public function __construct(
        string $message,
        public readonly array $detail = [],
        int $code = 0,
    ) {
        parent::__construct($message, $code);
    }
}
