<?php

namespace App\Support;

use Closure;

/**
 * Premium authors' own domains: what counts as one, and whether its DNS
 * points at this server. A domain points here when it resolves to one of
 * the addresses the reader site's own host resolves to (a CNAME to it does
 * exactly that), or to CUSTOM_DOMAIN_IP, for a bare domain that can only
 * have an A record.
 */
class CustomDomain
{
    /** A host name of at least two labels with a letters-only TLD. */
    private const PATTERN = '/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    /** @var (Closure(string): list<string>)|null */
    private static ?Closure $resolver = null;

    public static function enabled(): bool
    {
        return (bool) config('services.custom_domains.enabled');
    }

    /** The reader site's host, e.g. posts.uz: what a CNAME points to. */
    public static function target(): string
    {
        return (string) (parse_url((string) config('app.frontend_url'), PHP_URL_HOST) ?: 'localhost');
    }

    /** The server's address for an A record, when one is configured. */
    public static function ip(): ?string
    {
        $ip = trim((string) config('services.custom_domains.ip'));

        return $ip !== '' ? $ip : null;
    }

    /**
     * What an author typed, as a bare lowercase host: a pasted address loses
     * its scheme, path, port and trailing dot. Null when nothing is left.
     */
    public static function normalize(?string $value): ?string
    {
        $host = strtolower(trim((string) $value));
        $host = preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $host);
        $host = preg_split('~[/?#]~', (string) $host)[0];
        $host = preg_replace('~:\d+$~', '', $host);
        $host = rtrim((string) $host, '.');

        return $host !== '' ? $host : null;
    }

    /**
     * Why this host can't be an author's own domain, or null if it can. The
     * platform's own domain and its subdomains are its own.
     */
    public static function problem(string $host): ?string
    {
        if (! preg_match(self::PATTERN, $host)) {
            return 'That is not a domain. Type it like blog.yourname.uz, without https://.';
        }

        $root = self::target();
        if ($host === $root || str_ends_with($host, '.'.$root)) {
            return "Addresses under {$root} are the platform's own; your subdomain already is one.";
        }

        return null;
    }

    public static function pointsHere(string $host): bool
    {
        $found = self::resolve($host);
        if ($found === []) {
            return false;
        }

        $expected = self::resolve(self::target());
        if (self::ip() !== null) {
            $expected[] = self::ip();
        }

        return array_intersect($found, $expected) !== [];
    }

    /** @return list<string> IPv4 addresses, following CNAMEs. */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }

        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : array_values($addresses);
    }

    /** For tests: answer lookups without DNS. Null puts DNS back. */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }
}
