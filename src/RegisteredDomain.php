<?php

declare(strict_types=1);

namespace Xoops\RegDom;

/**
 * Class RegisteredDomain
 *
 * Determine the registrable domain portion of a URL, respecting the public suffix list conventions
 *
 * @package   Xoops\RegDom
 * @author    Florian Sager, 06.08.2008, <sager@agitos.de>
 * @author    Marcus Bointon (https://github.com/Synchro/regdom-php)
 * @author    Richard Griffith <richard@geekwright.com>
 * @author    Michael Beck <mamba@xoops.org>
 * @license   Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

class RegisteredDomain
{
    private PublicSuffixList $psl;
    private static ?PublicSuffixList $pslInstance = null;
    private static ?self $regdomInstance = null;

    /**
     * @param PublicSuffixList|null $psl Optional PublicSuffixList instance for dependency injection.
     */
    public function __construct(?PublicSuffixList $psl = null)
    {
        $this->psl = $psl ?? new PublicSuffixList();
    }

    /**
     * Extracts the registrable domain from a host string.
     *
     * @param string $host The host string to process (e.g., "sub.example.co.uk").
     * @param bool   $utf8 If true, returns a human-readable UTF-8 string. If false, returns ASCII/Punycode.
     * @return string|null The registrable domain (e.g., "example.co.uk") or null if invalid.
     * @example getRegisteredDomain('www.münchen.de') → 'münchen.de'
     * @example getRegisteredDomain('co.uk') → null
     */
    public function getRegisteredDomain(string $host, bool $utf8 = true): ?string
    {
        $normalizedHost = self::normalizeHost($host);
        if ($normalizedHost === '') {
            return null;
        }

        $hostAscii = self::toAscii($normalizedHost);

        $publicSuffix = $this->psl->getPublicSuffix($hostAscii);
        if ($publicSuffix === null || $hostAscii === $publicSuffix) {
            return null;
        }

        $hostWithoutSuffix = substr($hostAscii, 0, -strlen($publicSuffix) - 1);
        $dotPos = strrpos($hostWithoutSuffix, '.');
        $domainLabel = ($dotPos === false) ? $hostWithoutSuffix : substr($hostWithoutSuffix, $dotPos + 1);

        $registrableAscii = $domainLabel . '.' . $publicSuffix;

        if ($utf8 && function_exists('idn_to_utf8')) {
            return idn_to_utf8($registrableAscii, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $registrableAscii;
        }
        return $registrableAscii;
    }

    /**
     * Validates if a cookie domain is appropriate for a given host per RFC 6265 & PSL rules.
     *
     * @param string $host   The current request host (e.g., "www.example.com").
     * @param string $domain The cookie domain to validate (e.g., "example.com").
     * @return bool True if the domain is valid for the host.
     * @example domainMatches('www.example.com', 'example.com') → true
     * @example domainMatches('example.com', 'co.uk') → false
     * @example domainMatches('localhost', 'localhost') → false
     */
    public static function domainMatches(string $host, string $domain): bool
    {
        $host   = self::normalizeHost($host);
        $domain = self::normalizeHost(ltrim($domain, '.'));

        if ($domain === '') return true;
        if ($domain === 'localhost') return false;

        if (filter_var($host, FILTER_VALIDATE_IP) || filter_var($domain, FILTER_VALIDATE_IP)) {
            return false;
        }

        $usePSL = !defined('XOOPS_COOKIE_DOMAIN_USE_PSL') || XOOPS_COOKIE_DOMAIN_USE_PSL;
        if ($usePSL) {
            self::$pslInstance ??= new PublicSuffixList();
            self::$regdomInstance ??= new self(self::$pslInstance);

            if (self::$pslInstance->isPublicSuffix($domain)) {
                return false;
            }

            $hostRegisteredDomain = self::$regdomInstance->getRegisteredDomain($host, false);
            $domainRegisteredDomain = self::$regdomInstance->getRegisteredDomain($domain, false);

            if ($hostRegisteredDomain && $domainRegisteredDomain && $hostRegisteredDomain !== $domainRegisteredDomain) {
                return false;
            }
        }

        $host   = self::toAscii($host);
        $domain = self::toAscii($domain);

        if ($host === $domain) return true;

        // Use substr_compare for PHP 7.4 compatibility
        return (strlen($host) > strlen($domain))
            && (substr_compare($host, '.' . $domain, -1 - strlen($domain)) === 0);
    }

    /**
     * Normalizes a host string for comparison.
     */
    private static function normalizeHost(string $input): string
    {
        $host = trim(strtolower($input));
        if ($host !== '' && $host[0] === '[') $host = trim($host, '[]');
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        return rtrim($host, '.');
    }

    /**
     * Converts a host string to its ASCII representation (Punycode).
     */
    private static function toAscii(string $host): string
    {
        if ($host === '') {
            return '';
        }
        return function_exists('idn_to_ascii')
            ? (idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host)
            : $host;
    }
}
