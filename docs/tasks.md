# RegDom Improvement Tasks

Actionable checklist of improvements based on a full code review, static analysis
(PHPStan level max - clean), linting (PHPCS PSR-12 - clean), and test run
(40 tests, 63 assertions - all passing on PHP 8.4).

---

## 1 - Critical Bugs & Configuration Fixes

- [x] 1.1 - **Fix PHP version constraint in `composer.json`** - Change `"php": "^7.4 || ^8.5"` to `"php": "^7.4 || ^8.0"`. The current constraint excludes PHP 8.0-8.4 entirely, which means `composer install` fails on the most common PHP 8.x versions.
- [x] 1.2 - **Add PHP 8.5 to CI matrix** - The GitHub Actions `ci.yml` matrix only tests up to PHP 8.4. Add `8.5` to the matrix once available, or use `nightly` to future-proof.
- [x] 1.3 - **Fix PSR-4 autoloading for test namespaces** - Composer emits warnings: `Class Xoops\RegDom\Tests\PublicSuffixListTest located in ./tests/unit/PublicSuffixListTest.php does not comply with psr-4`. Either change `autoload-dev` to map sub-namespaces (`"Xoops\\RegDom\\Tests\\": "tests/unit/"`, `"Xoops\\RegDom\\Tests\\Integration\\": "tests/integration/"`) or flatten the test directory structure so the namespace path matches.

## 2 - PHPUnit & Test Infrastructure

- [x] 2.1 - **Upgrade `phpunit.xml.dist` for PHPUnit 10+/11+ compatibility** - The current `<coverage>` configuration uses PHPUnit 9 syntax (`processUncoveredFiles`, nested `<report>` under `<coverage>`). PHPUnit 11 reports 5 deprecations. Migrate to the `<source>` element format for PHPUnit 10+ while maintaining a PHPUnit 9.6 fallback or pin to `^10.0 || ^11.0` only.
- [x] 2.2 - **Remove duplicate `phpunit.xml`** - Both `phpunit.xml` and `phpunit.xml.dist` exist with identical content. The `.dist` file is the convention for version-controlled config; `phpunit.xml` (local override) should be in `.gitignore` and removed from the repo.
- [x] 2.3 - **Add `PslCacheNotFoundException` unit test** - The custom exception class `src/Exception/PslCacheNotFoundException.php` has no dedicated test. Add a test that verifies the exception message and that it extends `\RuntimeException`.
- [ ] 2.4 - **Add unit test for `PublicSuffixList::loadRules()` failure path** - No test exercises the `PslCacheNotFoundException` throw. Create a test that provides no valid cache paths and asserts the exception is thrown.
- [x] 2.5 - **Add tests for edge cases in `normalizeHost()`** - Missing coverage for: IPv6 bracket stripping (`[::1]`), hosts with ports (`example.com:8080` in non-URL context), empty parse_url results, and extremely long hostnames.
- [x] 2.6 - **Add tests for `getMetadata()` edge cases** - No test covers the guard clause when `self::$rules === null`. Also test stale cache detection (days_old > 180) and missing cache file scenarios.
- [x] 2.7 - **Add negative test for `domainMatches()` cross-registered-domain rejection** - The unit test mocks `isPublicSuffix` but doesn't mock `getPublicSuffix`/`getRegisteredDomain` for the cross-domain check path (lines 90-94 in `RegisteredDomain.php`). This code path is only tested in integration tests.
- [ ] 2.8 - **Add `update-psl.php` script tests** - The PSL updater script has no tests. Consider refactoring it into a class with testable methods (parse, validate, write) and add unit tests for each step.

## 3 - Code Quality & Modernization

- [x] 3.1 - **Replace `strpos() !== false` with `str_contains()` on PHP 8.0+** - Multiple occurrences in `RegisteredDomain::normalizeHost()` (line 106) and `bin/update-psl.php` (lines 41, 67-70). Use a polyfill or conditional wrapper to maintain PHP 7.4 compat.
- [x] 3.2 - **Replace `substr()` comparisons with `str_starts_with()` / `str_ends_with()`** - In `bin/update-psl.php` lines 67-70 (`strpos($line, '!') === 0`, `strpos($line, '*.') === 0`) and `RegisteredDomainTest.php`. Use polyfill for PHP 7.4.
- [x] 3.3 - **Use `substr_compare()` or `str_ends_with()` instead of `substr()` suffix matching** - In `RegisteredDomainTest.php` line 62: `substr($h, -strlen($suffix)) === $suffix` can be replaced with `str_ends_with($h, $suffix)`.
- [x] 3.4 - **Add `@throws` tags to all methods that throw** - `PublicSuffixList::__construct()` can throw `PslCacheNotFoundException` but this is not documented in its PHPDoc. Same for `loadRules()`.
- [x] 3.5 - **Add XOOPS copyright header to all source files** - Per XOOPS coding conventions, every source file should begin with the standard XOOPS copyright header block. Currently none of the `src/` files have it.
- [x] 3.6 - **Make `PublicSuffixList::normalizeDomain()` static** - The method does not use `$this`; it only calls static functions. Making it `private static` is more semantically correct and allows calling from static contexts.
- [ ] 3.7 - **Extract IDN conversion to a shared utility** - Both `RegisteredDomain::toAscii()` and `PublicSuffixList::normalizeDomain()` contain duplicate `idn_to_ascii()` logic with the same fallback pattern. Extract to a shared private static method or a small `Idn` utility class.
- [ ] 3.8 - **Add return type declarations to data providers** - `realWorldDataProvider()` and other data providers should declare `array` return types for PHPStan strictness.
- [ ] 3.9 - **Consider making `PublicSuffixList` accept an injected cache path** - Currently the cache paths are hardcoded in `loadRules()`. Accepting optional `$cachePaths` in the constructor would improve testability and allow custom cache locations without defining XOOPS constants.

## 4 - Security Hardening

- [ ] 4.1 - **Validate PSL cache file with `realpath()` boundary check** - In `PublicSuffixList::loadRules()`, the file paths should be validated with `realpath()` and checked against an allowed directory to prevent directory traversal if `XOOPS_VAR_PATH` is ever user-influenced.
- [x] 4.2 - **Add `opcache_invalidate()` after atomic cache write** - In `bin/update-psl.php`, after the `rename()`, call `opcache_invalidate($cachePath, true)` to ensure OPcache serves the fresh file. Without this, the old cached PHP array may be served indefinitely.
- [x] 4.3 - **Validate `EXCEPTION` key is an array in `loadRules()`** - The validation checks `is_array($rules['NORMAL'])` and `is_array($rules['WILDCARD'])` but does not verify `is_array($rules['EXCEPTION'])`. A corrupt cache could pass validation.
- [x] 4.4 - **Add file size sanity check before `include`** - Before including the cache file in `loadRules()`, check that the file size is within a reasonable range (e.g., 100KB-10MB) to prevent loading a truncated or bloated file.
- [x] 4.5 - **Harden `bin/update-psl.php` temp file cleanup** - The temp file cleanup on write failure uses `file_exists()` + `unlink()` which is susceptible to TOCTOU. Use `@unlink()` with error suppression instead.

## 5 - CI/CD & Tooling

- [x] 5.1 - **Remove legacy/duplicate config files** - Clean up `.travis0.yml`, `phpcs.xml0.dist`, `phpunit.xml0.dist`, `.scrutinizer0.yml`, `composer0.json`, `composer1.json`, `.github/workflows/pr_tests0.yml`. These appear to be backup files from refactoring and should not be in version control.
- [x] 5.2 - **Add `phpunit.xml` and `phpcs.xml` to `.gitignore`** - The convention is to track only `.dist` files. Local overrides (`phpunit.xml`, `phpcs.xml`) should be gitignored.
- [x] 5.3 - **Add `.phpcs.xml.dist` or reconcile with `phpcs.xml`** - The status shows `.phpcs.xml.dist` was deleted and `phpcs.xml` + `phpcs.xml.dist` added. Settle on one canonical file name and remove the others.
- [x] 5.4 - **Run Scrutinizer CI checks (not just platform-reqs)** - The `.scrutinizer.yml` build nodes for PHP 7.4-8.4 only run `composer validate`, `composer update`, and `composer check-platform-reqs`. They should also run `composer ci` (lint + analyse + test) for meaningful CI results.
- [x] 5.5 - **Add Dependabot or Renovate configuration** - Automate dependency update PRs for both Composer and GitHub Actions to keep dependencies current and secure.
- [ ] 5.6 - **Pin GitHub Actions to SHA hashes** - `actions/checkout@v5`, `shivammathur/setup-php@v2`, etc. should be pinned to commit SHAs for supply chain security (e.g., `actions/checkout@<sha>`).
- [ ] 5.7 - **Add a `composer.lock` to the repository** - For a library this is optional, but having it ensures reproducible CI runs and prevents dependency resolution differences across environments.

## 6 - Documentation

- [x] 6.1 - **Update `README.md` to match current API** - The README documents methods that no longer exist (`$psl->setURL()`, `$psl->getTree()`, `$psl->clearDataDirectory()`). The constructor signature has also changed (no longer accepts a URL). Rewrite the Usage section to reflect the current `PublicSuffixList` (constructor, `isPublicSuffix()`, `getPublicSuffix()`, `isException()`, `getMetadata()`) and `RegisteredDomain` API (`getRegisteredDomain()`, `domainMatches()`).
- [x] 6.2 - **Document `domainMatches()` in README** - This important static method for RFC 6265 cookie validation is not mentioned in the README at all.
- [x] 6.3 - **Document `XOOPS_COOKIE_DOMAIN_USE_PSL` constant** - Explain the behavior toggle in the README or a separate configuration doc.
- [x] 6.4 - **Document `XOOPS_SKIP_PSL_UPDATE` environment variable** - Explain when and why to use it (CI environments, restricted networks).
- [ ] 6.5 - **Add a CHANGELOG.md** - Track version changes, breaking changes, and migration notes. Start from the current version.
- [ ] 6.6 - **Add inline code examples to PHPDoc** - Add `@example` tags or usage snippets to the main public methods for IDE tooltip assistance.

## 7 - Performance

- [ ] 7.1 - **Consider lazy-loading PSL rules per category** - Currently all three rule sets (NORMAL, WILDCARD, EXCEPTION) are loaded at once. For simple `isPublicSuffix()` checks that only need NORMAL rules, loading all categories is unnecessary overhead. Profile to determine if this matters in practice.
- [ ] 7.2 - **Cache `normalizeDomain()` results** - Repeated calls with the same domain (e.g., in batch processing) re-run `strtolower()`, `trim()`, and `idn_to_ascii()`. A small LRU cache (e.g., `SplFixedArray` or simple array with size limit) could help for bulk operations.
- [ ] 7.3 - **Profile `var_export()` output size in PSL cache** - The `var_export()` format is human-readable but verbose. Evaluate whether a more compact serialization format (e.g., `serialize()` or JSON) would reduce cache file size and load time while maintaining PHP 7.4 compat.

## 8 - Architecture & Extensibility

- [ ] 8.1 - **Introduce a `PslCacheLoaderInterface`** - Abstract the cache loading logic behind an interface to support alternative cache backends (Redis, APCu, PSR-6/PSR-16) without modifying `PublicSuffixList` directly.
- [ ] 8.2 - **Make `PublicSuffixList::$rules` non-static (or add `reset()`)** - The static `$rules` property means the PSL cannot be reloaded within a single request (e.g., after an update). Add a `public static function resetRules(): void` method for testing and runtime reloading, and consider making rules non-static with a singleton accessor.
- [ ] 8.3 - **Refactor `bin/update-psl.php` into an `UpdateCommand` class** - The updater script is procedural and untestable. Refactor into a class with `download()`, `parse()`, `validate()`, and `write()` methods that can be unit-tested independently.
- [ ] 8.4 - **Add a `DomainInfo` value object** - Instead of returning a plain string from `getRegisteredDomain()`, consider returning a value object that exposes `getRegistrableDomain()`, `getPublicSuffix()`, `getSubdomain()`, and `isIDN()` for richer domain analysis.
- [ ] 8.5 - **Support PSR-3 logging** - Replace `echo` statements in `bin/update-psl.php` and any `trigger_error()` calls with PSR-3 `LoggerInterface` injection for better integration with application logging.

## 9 - Compatibility & Future-Proofing

- [ ] 9.1 - **Graceful degradation when `intl` extension is missing** - `RegisteredDomain::toAscii()` and `PublicSuffixList::normalizeDomain()` guard with `function_exists('idn_to_ascii')`, but if `intl` is absent, IDN domains silently fail. Consider requiring `symfony/polyfill-intl-idn` or logging a warning on first IDN encounter.
- [x] 9.2 - **Add `ext-intl` to `composer.json` suggest section** - Document the recommendation: `"suggest": {"ext-intl": "Required for internationalized domain name (IDN) support"}`.
- [ ] 9.3 - **Prepare for PHPUnit 12** - PHPUnit 11 deprecations indicate upcoming changes. Review the PHPUnit 12 migration guide and address deprecations proactively.
- [x] 9.4 - **Add `declare(strict_types=1)` to `bin/update-psl.php`** - The updater script is the only PHP file without strict types. Add it for consistency and type safety.
- [ ] 9.5 - **Evaluate dropping PHP 7.4 support** - PHP 7.4 reached EOL in November 2022. Dropping it would allow use of PHP 8.0+ features (union types, `match`, named arguments, `str_contains()`, `str_starts_with()`, `str_ends_with()`, `Stringable`, constructor promotion). If XOOPS 2.5.x still requires 7.4, maintain a separate branch.

## 10 - Cleanup & Hygiene

- [x] 10.1 - **Remove `data/cached_1273351598b0a7510ff4c1d2ea53e039`** - Legacy cache file with opaque name. No code references it. Should be deleted.
- [x] 10.2 - **Add `build/` directory to `.gitignore`** - The `build/` directory (coverage reports, logs) should not be tracked.
- [x] 10.3 - **Add `data/public_suffix_list.dat` to `.gitignore`** - The raw PSL file is 330KB and can be re-downloaded. Only the cache PHP file needs to be bundled.
- [x] 10.4 - **Remove or update `bin/reloadpsl`** - Check if this shell script still works with the refactored codebase. It references old class methods that may no longer exist.
- [x] 10.5 - **Normalize line endings** - Ensure `.gitattributes` enforces LF line endings for all PHP, YAML, JSON, and XML files to prevent cross-platform issues.
- [x] 10.6 - **Add `.editorconfig`** - Define consistent indentation (4 spaces for PHP, 2 for YAML/JSON) and encoding settings for contributors using different editors.
