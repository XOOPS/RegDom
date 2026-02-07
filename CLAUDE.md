# CLAUDE.md — RegDom

## Project Overview

**Package:** `xoops/regdom`
**Type:** PHP library (Composer package)
**Purpose:** Domain parsing via the Mozilla Public Suffix List (PSL); determines the registrable domain from any hostname and validates cookie domains per RFC 6265.
**License:** Apache-2.0 OR MPL-2.0

## Build & Test Commands

```bash
composer install          # Install dependencies (auto-updates PSL cache)
composer ci               # Run all CI checks: lint + analyse + test
composer test             # Run PHPUnit tests only
composer lint             # Check code style (PSR-12 via PHPCS)
composer fix              # Auto-fix code style issues (PHPCBF)
composer analyse          # Run PHPStan static analysis
composer update-psl       # Force-update the PSL cache from publicsuffix.org
```

Set `XOOPS_SKIP_PSL_UPDATE=1` to skip automatic PSL updates during `composer install/update`.

## PHP Version Compatibility

- **Supported range:** PHP 7.4 through PHP 8.5
- **Constraint in composer.json:** `"^7.4 || ^8.0"`
- **Polyfills:** `symfony/polyfill-php80` provides `str_contains`, `str_starts_with`, `str_ends_with`

### Strict Rules

- **No PHP 8.0+ language features:** Do not use union types, named arguments, `match` expressions, enums, `readonly`, constructor promotion, fibers, or intersection types.
- **Always use polyfill functions** (`str_contains`, `str_starts_with`, `str_ends_with`) instead of `strpos`/`substr` patterns.
- **Guard `idn_to_ascii()` calls** with both `function_exists('idn_to_ascii')` and `$domain !== ''` (PHP 8.4 throws `ValueError` on empty string).
- **`declare(strict_types=1)`** is required in every PHP file.

## Code Quality Standards

### PHPCS — PSR-12

- Config: `phpcs.xml`
- Standard: PSR-12 (line-length check disabled)
- Scope: `src/` only (tests excluded)
- Run: `composer lint` / `composer fix`

### PHPStan — Level Max

- Config: `phpstan.neon`
- Level: `max`
- Scope: `src/` only
- `treatPhpDocTypesAsCertain: false`
- Run: `composer analyse`

### PHPUnit

- Config: `phpunit.xml.dist`
- Versions: `^9.6 || ^10.0 || ^11.0` (PHPUnit 9.6 on PHP 7.4/8.0, PHPUnit 10 on 8.1, PHPUnit 11 on 8.2+)
- Test suites: `Unit` (`tests/unit/`), `Integration` (`tests/integration/`)
- Current stats: 54 tests, 85 assertions
- 4 expected `@dataProvider` deprecation warnings on PHPUnit 11 (required for PHP 7.4 compat — do not convert to attributes)

## Project Structure

```
src/
  RegisteredDomain.php         # Main API — getRegisteredDomain(), domainMatches()
  PublicSuffixList.php          # PSL cache loader and query engine
  Exception/
    PslCacheNotFoundException.php  # Thrown when no valid PSL cache found
tests/
  unit/                        # Unit tests (Xoops\RegDom\Tests\)
  integration/                 # Integration tests (Xoops\RegDom\Tests\Integration\)
bin/
  update-psl.php               # Downloads PSL from publicsuffix.org, builds cache
  reloadpsl                    # Convenience wrapper for update-psl.php
data/
  psl.cache.php                # Bundled PSL cache (PHP array, ~200KB)
```

## Architecture & Key Patterns

### PSL Cache Format

The cache file is a PHP array with three keys: `NORMAL`, `WILDCARD`, `EXCEPTION`. Each maps domain labels to `true`. Loaded via `include` for performance.

### Rule Matching Priority

Exception rules > Wildcard rules > Normal rules (per PSL specification).

### Dual Cache Paths

1. **Runtime path** (preferred): `XOOPS_VAR_PATH/cache/regdom/psl.cache.php`
2. **Bundled fallback**: `data/psl.cache.php`

### Static Rules Property

`PublicSuffixList::$rules` is `static` — shared across all instances per request. Keep this in mind when testing.

### Atomic Cache Writes

`bin/update-psl.php` writes to a temp file first, then `rename()` for atomicity, followed by `opcache_invalidate()`.

## PSR-4 Autoloading

| Namespace | Directory |
|---|---|
| `Xoops\RegDom\` | `src/` |
| `Xoops\RegDom\Tests\` | `tests/unit/` |
| `Xoops\RegDom\Tests\Integration\` | `tests/integration/` |

## CI Pipeline

GitHub Actions (`.github/workflows/ci.yml`):
- Matrix: PHP 7.4–8.5 (8.5 as experimental), latest deps + lowest deps on 7.4
- Coverage: Xdebug on PHP 8.3, uploaded to Codecov
- Each run executes: `composer ci` (lint + analyse + test)

## XOOPS Integration Constants

- `XOOPS_VAR_PATH` — base path for runtime cache
- `XOOPS_COOKIE_DOMAIN_USE_PSL` — enables PSL-based cookie domain validation
- `XOOPS_SKIP_PSL_UPDATE` — skips auto-update of PSL during `composer install/update`

## Common Pitfalls

1. **`idn_to_ascii('')`** throws `ValueError` on PHP 8.4+ — always guard with `$domain !== ''`
2. **PSL entries change** — never hardcode PSL entries in tests; use entries known to be stable or mock the cache
3. **PSR-12 blank line rule** — requires a blank line between `<?php` and any comment block; run `composer fix` after adding file headers
4. **PHPUnit config** — uses `<source>` element only (no `<coverage>`); works across PHPUnit 9.3.4+; no `cacheDirectory` attribute (PHPUnit 10+ only)
5. **`PublicSuffixList::$rules` is static** — tests that modify or reset rules affect all subsequent tests in the same process
