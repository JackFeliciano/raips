<?php

declare(strict_types=1);

namespace Raips;

/**
 * Everything a template writes into a page goes through here.
 */
final class Html
{
    /** Escapes text for an element or an attribute value. */
    public static function esc(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Returns the URL when it is safe to put in an attribute and null when it
     * is not. A protocol relative address is refused because it borrows the
     * scheme of the page that holds it.
     */
    public static function safeUrl(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        foreach (["\0", "\n", "\r", "\t"] as $bad) {
            if (str_contains($value, $bad)) {
                return null;
            }
        }

        $url = trim($value);
        if ($url === '') {
            return null;
        }

        if (preg_match('#^//#', $url) === 1) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1) {
            $scheme = strtolower((string) strstr($url, ':', true));

            return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $url : null;
        }

        return $url;
    }

    /**
     * Joins attribute pairs. A null value drops the pair, which keeps
     * optional attributes out of the markup instead of printing empty ones.
     *
     * @param array<string, string|int|bool|null> $pairs
     */
    public static function attrs(array $pairs): string
    {
        $out = [];
        foreach ($pairs as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $out[] = $name;
                continue;
            }
            $out[] = $name . '="' . self::esc($value) . '"';
        }

        return $out === [] ? '' : ' ' . implode(' ', $out);
    }

    /** Joins a list of rendered lines into a block. */
    public static function lines(string ...$parts): string
    {
        return implode("\n", array_filter($parts, static fn (string $p): bool => $p !== ''));
    }
}
