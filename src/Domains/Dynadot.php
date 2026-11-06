<?php

declare(strict_types=1);

namespace Raips\Domains;

/**
 * Asks a registrar whether a name is free. The key travels in the query
 * string, as the registrar's legacy interface expects, so the address that
 * reaches a log line is redacted before it is kept.
 */
final class Dynadot implements Registrar
{
    public const ENDPOINT = 'https://api.dynadot.com/api3.json';
    public const SANDBOX = 'https://api-sandbox.dynadot.com/api3.json';

    /** The registrar takes one name per call on a regular account. */
    public const MAX_NAMES = 100;

    public function __construct(
        private readonly string $key,
        private readonly string $endpoint = self::ENDPOINT,
        private readonly int $timeout = 15,
        private readonly bool $showPrice = true,
    ) {
    }

    public function search(string $name, array $tlds): array
    {
        $name = self::normalise($name);
        if ($name === '') {
            throw new \InvalidArgumentException('a search needs a name');
        }
        if (count($tlds) > self::MAX_NAMES) {
            throw new \InvalidArgumentException('a search takes at most ' . self::MAX_NAMES . ' extensions');
        }

        $query = ['key' => $this->key, 'command' => 'search'];
        $i = 0;
        foreach (array_values($tlds) as $tld) {
            $tld = self::normaliseTld($tld);
            if ($tld === '') {
                continue;
            }
            $query['domain' . $i] = $name . '.' . $tld;
            $i++;
        }

        if ($i === 0) {
            return [];
        }

        if ($this->showPrice) {
            $query['show_price'] = '1';
            $query['currency'] = 'USD';
        }

        $url = $this->endpoint . '?' . http_build_query($query);
        $raw = $this->get($url);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('the registrar answered something that is not JSON');
        }

        $out = [];
        foreach (self::walk($data) as $header) {
            $domain = is_string($header['DomainName'] ?? null) ? $header['DomainName'] : '';
            if ($domain === '') {
                continue;
            }
            $available = null;
            if (isset($header['Available']) && is_string($header['Available'])) {
                $available = match (strtolower($header['Available'])) {
                    'yes', 'true', '1' => true,
                    'no', 'false', '0' => false,
                    default => null,
                };
            }
            $price = null;
            $currency = null;
            $premium = false;
            if (is_string($header['Price'] ?? null)) {
                $premium = stripos((string) $header['Price'], 'premium') !== false
                    && stripos((string) $header['Price'], 'not premium') === false;
                if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', (string) $header['Price'], $m) === 1) {
                    $price = $m[1];
                }
                if (preg_match('/\bin\s+([A-Z]{3})\b/', (string) $header['Price'], $m) === 1) {
                    $currency = $m[1];
                }
            }

            $out[] = new Lookup(
                domain: $domain,
                available: $available,
                currency: $currency,
                price: $price,
                premium: $premium,
                language: is_string($header['Language'] ?? null) ? $header['Language'] : null,
                reason: is_string($header['Error'] ?? null) ? $header['Error'] : null,
            );
        }

        return $out;
    }

    /**
     * The answer nests its headers a few ways depending on how many names went
     * out, so this walks whatever it finds and collects the ones that name a
     * domain.
     *
     * @return list<array<string, mixed>>
     */
    private static function walk(mixed $node): array
    {
        if (!is_array($node)) {
            return [];
        }

        $found = [];
        if (isset($node['DomainName'])) {
            $found[] = $node;
        }
        foreach ($node as $child) {
            foreach (self::walk($child) as $header) {
                $found[] = $header;
            }
        }

        return $found;
    }

    private function get(string $url): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('cannot open a request to the registrar');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('the registrar did not answer, ' . self::scrub($error, $this->key));
        }
        if ($status !== 200) {
            throw new \RuntimeException('the registrar answered ' . $status);
        }

        return (string) $body;
    }

    /** A name as the registrar wants it: lower case, letters, digits, hyphens. */
    public static function normalise(string $name): string
    {
        $name = strtolower(trim($name));
        $name = (string) preg_replace('/[^a-z0-9-]/', '', $name);

        return trim($name, '-');
    }

    public static function normaliseTld(string $tld): string
    {
        $tld = strtolower(ltrim(trim($tld), '.'));
        $tld = (string) preg_replace('/[^a-z0-9.\-]/', '', $tld);

        return trim($tld, '.-');
    }

    private static function scrub(string $text, string $secret): string
    {
        return $secret === '' ? $text : str_replace($secret, '[redacted]', $text);
    }
}
