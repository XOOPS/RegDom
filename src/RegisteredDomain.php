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

    public function __construct(?PublicSuffixList $psl = null)
    {
        $this->psl = $psl ?? new PublicSuffixList();
    }

    public function getRegisteredDomain(string $host): ?string
    {
        $normalizedHost = self::normalizeDomainForMatching($host);
        if (empty($normalizedHost)) {
            return null;
        }

        $publicSuffix = $this->psl->getPublicSuffix($normalizedHost);

        if ($publicSuffix === null || $normalizedHost === $publicSuffix) {
            return null;
        }

        $hostWithoutSuffix = substr($normalizedHost, 0, -strlen($publicSuffix) - 1);
        $parts = explode('.', $hostWithoutSuffix);
        $domainLabel = array_pop($parts);

        return $domainLabel . '.' . $publicSuffix;
    }

    public static function domainMatches(string $host, string $domain): bool
    {
        $host   = self::normalizeDomainForMatching($host);
        $domain = self::normalizeDomainForMatching(ltrim($domain, '.'));

        if ($domain === '') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) || filter_var($domain, FILTER_VALIDATE_IP)) {
            return false;
        }

        $psl = new PublicSuffixList();
        if ($psl->isPublicSuffix($domain)) {
            return false;
        }

        if (function_exists('idn_to_ascii')) {
            $host   = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;
            $domain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
        }

        if ($host === $domain) {
            return true;
        }

        return (substr_compare($host, '.' . $domain, -1 - strlen($domain)) === 0);
    }

    private static function normalizeDomainForMatching(string $input): string
    {
        if ($input === '') {
            return '';
        }

        $host = (strpos($input, '/') !== false) ? parse_url($input, PHP_URL_HOST) : $input;
        if (!is_string($host)) {
            $host = '';
        }

        $host = trim(strtolower($host));

        if ($host !== '' && $host[0] === '[') {
            $host = trim($host, '[]');
        }

        // FINAL: Add a null-coalescing operator to handle a potential null return from preg_replace.
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return rtrim($host, '.');
    }
}
