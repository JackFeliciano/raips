<?php

declare(strict_types=1);

namespace Raips\Domains;

/**
 * What a search found for one name.
 */
final class Lookup
{
    public function __construct(
        public readonly string $domain,
        public readonly ?bool $available,
        public readonly ?string $currency = null,
        public readonly ?string $price = null,
        public readonly bool $premium = false,
        public readonly ?string $language = null,
        public readonly ?string $reason = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'available' => $this->available,
            'price' => $this->price,
            'currency' => $this->currency,
            'premium' => $this->premium,
            'language' => $this->language,
            'reason' => $this->reason,
        ];
    }
}
