# RegDom ChangeLog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.2-beta3] - 2026-02-08

### Bug Fixes
* Fix `getPublicSuffix()` PSL exception-rule handling — exception branch returned one label too many; now correctly returns the exception rule minus its leftmost label (e.g. `!city.kawasaki.jp` yields public suffix `kawasaki.jp`, not `city.kawasaki.jp`)
* Fix `normalizeHost()` corrupting IPv6 addresses — the port-stripping regex `/:\d+$/` mistakenly treated the last hextet of addresses like `::1` as a port, truncating them; IPv6 literals are now parsed bracket-aware
* Fix PSL cache storing IDN rules as Unicode — `bin/update-psl.php` now normalizes all rules to ASCII/punycode via `idn_to_ascii()`, matching the form used by `normalizeDomain()` at runtime; previously 457 Unicode keys were unreachable when `ext-intl` was loaded
* Fix `composer.json` PHP constraint from `"^7.4 || ^8.5"` to `"^7.4 || ^8.0"` (previously excluded PHP 8.0–8.4)
* Fix `normalizeDomain()` crash on empty string — `idn_to_ascii('')` throws `ValueError` on PHP 8.4+; added `$domain !== ''` guard
* Fix PSL exception tests referencing `parliament.uk` (removed from the PSL); replaced with stable entries (`www.ck`, `city.kawasaki.jp`)
* Fix `bin/update-psl.php` failing when run standalone on PHP 7.4 — `str_starts_with()` was called without loading the polyfill autoloader
* Remove legacy `bin/reloadpsl` wrapper — replaced by `bin/update-psl.php`
* Fix `getMetadata()` unreachable statement

### Security
* Add `opcache_invalidate()` after atomic cache writes in `bin/update-psl.php`
* Add `is_array()` validation for all three PSL cache keys (`NORMAL`, `WILDCARD`, `EXCEPTION`) in `loadRules()`
* Add file size sanity check (100KB–10MB) before `include` in `loadRules()` to reject corrupt or tampered cache files
* Replace suppressed `@unlink()` with explicit `file_exists()` guard on temp-file cleanup in `bin/update-psl.php`

### Changed
* Make `normalizeDomain()` a `private static` method (was instance method)
* Replace `strpos()`/`substr()` patterns with `str_contains()`/`str_starts_with()`/`str_ends_with()` via `symfony/polyfill-php80`
* Add `@throws PslCacheNotFoundException` PHPDoc tags to `PublicSuffixList::__construct()` and `loadRules()`
* Add XOOPS copyright headers to all source files
* Clarify `README.md` license section — library code is Apache-2.0, bundled PSL data is MPL-2.0

### Added
* Add `symfony/polyfill-php80` dependency for `str_contains`, `str_starts_with`, `str_ends_with` on PHP 7.4
* Add `ext-intl` to `suggest` in `composer.json`

### Tests
* Add PSL exception-rule regression tests — `sub.city.kawasaki.jp`, `city.kawasaki.jp`, `sub.www.ck`, `www.ck` for both `getPublicSuffix()` and `getRegisteredDomain()`
* Add IPv6 normalization tests — `[::1]`, `[::1]:443`, `[2001:db8::1]:8080` for `getRegisteredDomain()` and `domainMatches()`
* Add IDN/punycode PSL cache tests — `公司.cn`, `xn--55qx5d.cn` for `isPublicSuffix()` and `getPublicSuffix()`
* Add IDN integration tests — `test.公司.cn`, `test.xn--55qx5d.cn`, `公司.cn` through the full `RegisteredDomain` stack
* Add `PslCacheNotFoundExceptionTest` — exception class, message, code, previous exception
* Add edge case tests for `getRegisteredDomain()` — trailing dots, URLs with ports
* Add `PublicSuffixList` tests — empty string, IP addresses, `isException`, normalization (dots, case)
* Add `domainMatches()` tests for RFC 6265 cookie domain validation
* Expand `getMetadata()` assertions — `needs_update`, wildcard/exception counts

### Infrastructure
* Add `declare(strict_types=1)` to `bin/update-psl.php`
* Add Composer autoloader bootstrap to `bin/update-psl.php` for standalone execution
* Add `composer ci` script chaining lint + analyse + test
* Add `composer update-psl` and `auto-update-psl` scripts with `XOOPS_SKIP_PSL_UPDATE` support
* Add GitHub Actions CI workflow — PHP 7.4–8.5 matrix, lowest-deps on 7.4, coverage on 8.3
* Fix CI coverage matrix entry running tests twice — added `if: !matrix.coverage` guard
* Fix SonarCloud workflow using `secrets` context in step-level `if:` — moved to job-level `env`
* Add GitHub Copilot custom instructions (`.github/copilot-instructions.md`)
* Add Dependabot configuration for Composer and GitHub Actions
* Add Qodana static analysis workflow
* Add `.editorconfig` (UTF-8, LF, PSR-12 indentation)
* Remove `xsi:noNamespaceSchemaLocation` from `phpunit.xml.dist` to avoid XSD validation warnings on PHPUnit 9; `<source>` element works natively on PHPUnit 10+/11+
* Standardize `.gitattributes` with LF enforcement and export-ignore list
* Standardize `.gitignore` with local config overrides, build artifacts, PHPUnit cache
* Track `phpcs.xml` in version control (was previously untracked, causing CI lint failure)
* Fix PSR-4 autoload-dev mappings for `tests/unit/` and `tests/integration/`
* Rewrite `README.md` with current API documentation and usage examples
* Remove legacy config files (`.travis0.yml`, `phpcs.xml.dist`, `phpcs.xml0.dist`, `phpunit.xml0.dist`, `.scrutinizer0.yml`, `composer0.json`, `composer1.json`)

## [2.0.2-beta2] - 2025-10-01

### Changed
* Rewrite `PublicSuffixList` — flat-file PSL cache (`data/psl.cache.php`) replaces tree-based data structure; three-category rule lookup (`NORMAL`, `WILDCARD`, `EXCEPTION`) with `O(1)` hash lookups
* Rewrite `RegisteredDomain` — simplified API using `PublicSuffixList` for all PSL queries; added `domainMatches()` for RFC 6265 cookie domain validation
* Replace `bin/reloadpsl` with `bin/update-psl.php` — HTTP conditional downloads (ETag/Last-Modified), atomic file writes, metadata tracking
* Add custom exception `PslCacheNotFoundException` for missing/invalid cache files
* Require `symfony/polyfill-mbstring` `^1.33`

### Added
* Add `getMetadata()` to `PublicSuffixList` — cache age, rule counts, staleness warning
* Add `isException()` to `PublicSuffixList` — check if a domain is a PSL exception entry
* Add `isPublicSuffix()` and `getPublicSuffix()` public methods to `PublicSuffixList`
* Add integration test suite with real PSL data

### Infrastructure
* Add PHPStan static analysis (`phpstan.neon`, level max)
* Add PHP_CodeSniffer with PSR-12 standard
* Add `.scrutinizer.yml` configuration
* Add `phpunit.xml.dist` with unit and integration test suites
* Require PHPUnit `^9.6 || ^10.0 || ^11.0`

## [2.0.2-beta1] - 2025-09-10

### Added
* Add `.gitattributes` with export-ignore rules
* Add PHPStan configuration (`phpstan.neon`)
* Add PHP_CodeSniffer as a dev dependency

### Changed
* Add `phpstan/phpstan` and `squizlabs/php_codesniffer` to `require-dev`
* Remove backslash prefix from `isset()` calls in `PublicSuffixList`
* Update `composer.json` dependencies

## [2.0.1] - 2024-11-27

### Added
* Add `.github/CONTRIBUTING.md` with contribution guidelines
* Add `.github/ISSUE_TEMPLATE/bug-report.yml` with PHP version dropdown (7.4–8.4)
* Add `.github/ISSUE_TEMPLATE/feature-request.yml`

### Changed
* Add PHP 8.4 to Travis CI test matrix
* Update `composer.json` PHP constraint

## [2.0.0] - 2024-07-26

### Changed
* Bump `symfony/polyfill-mbstring` constraint from `^1.29.0` to `^1.30.0`

## [2.0.0-Alpha] - 2024-07-08

### Changed
* Rename namespace from `Geekwright\RegDom` to `Xoops\RegDom`
* Modernise codebase for PHP 7.4+ — short array syntax, type hints, Yoda conditions removed
* Replace `PHPUnit_Framework_TestCase` with `PHPUnit\Framework\TestCase`
* Improve `decodePunycode()` implementation
* Add `is_string()` guard to curl return value
* Remove redundant type casts and `else` keywords
* Remove `PHP_VERSION_ID < 70000` compatibility code
* Update Public Suffix List data

### Added
* Add GitHub Actions workflow for CI (`pr_tests.yml`)
* Add Scrutinizer CI configuration
* Add `symfony/polyfill-mbstring` `^1.29.0` as a runtime dependency
* Add PSR-4 autoloading via Composer

### Removed
* Remove `/archive` directory with legacy files
* Remove Travis CI configuration (`.travis.yml`)

## Pre-release History

### 2023-04-30
* Tweak PHP version check for pre-7.0 compatibility (geekwright)
* Fix `unserialize()` — limit allowed classes for security (geekwright)

### 2022-04-08
* Limit `unserialize()` to prevent object injection (geekwright)
* Add Scrutinizer CI configuration (geekwright)

### 2019-08-30
* Remove array access with curly brackets for PHP 7.4 compatibility (geekwright)
* Fix build for PHP 5.4 and 5.5 (geekwright)

### 2018-02-07
* Restructure unit tests (geekwright)
* Simplify Travis CI configuration (geekwright)

### 2017-10-03
* Add PHP 7.2 support (geekwright)
* Update Public Suffix List data (geekwright)
* Fix CI configuration (geekwright)

### 2017-02-03 — Initial OO Rewrite
* Rewrite as OO library under `Geekwright\RegDom` namespace (geekwright)
* Add Composer support with PSR-4 autoloading (geekwright)
* Add `PublicSuffixList` class for PSL data management (geekwright)
* Add `RegisteredDomain` class for domain extraction (geekwright)
* Add `bin/reloadpsl` script for PSL updates (geekwright)
* Add unit tests with PHPUnit (geekwright)
* Add Scrutinizer CI and Travis CI configurations (geekwright)
* Add workaround for missing `ext-intl` (geekwright)

### 2012-10-02 — Original Import
* Import Florian Sager's regdom-php library (Synchro/Marcus Bointon)
* PHP include file for domain registration data (Synchro/Marcus Bointon)

[2.0.2-beta3]: https://github.com/XOOPS/RegDom/compare/v2.0.2-beta2...HEAD
[2.0.2-beta2]: https://github.com/XOOPS/RegDom/compare/v2.0.2-beta1...v2.0.2-beta2
[2.0.2-beta1]: https://github.com/XOOPS/RegDom/compare/v2.0.1...v2.0.2-beta1
[2.0.1]: https://github.com/XOOPS/RegDom/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/XOOPS/RegDom/compare/v2.0.0-Alpha...v2.0.0
[2.0.0-Alpha]: https://github.com/XOOPS/RegDom/releases/tag/v2.0.0-Alpha
