# XOOPS Copilot Instructions - RegDom

## About This Repository

RegDom is a XOOPS library that parses domain names using the Mozilla Public Suffix List (PSL) to determine the registrable domain portion of URLs and validate cookie domains per RFC 6265.

## Project Layout

```text
src/                          # Library source code
  RegisteredDomain.php        # Main class: extracts registrable domains, validates cookie domains
  PublicSuffixList.php         # PSL cache loader and query engine (NORMAL/WILDCARD/EXCEPTION rules)
  Exception/
    PslCacheNotFoundException.php  # Thrown when no valid PSL cache is found
bin/
  update-psl.php              # Downloads and caches the Mozilla PSL (atomic writes, HTTP conditional)
data/
  psl.cache.php               # Bundled PSL cache (PHP array with NORMAL/WILDCARD/EXCEPTION keys)
  psl.meta.json               # HTTP metadata (ETag, Last-Modified) for conditional downloads
  public_suffix_list.dat      # Raw PSL source (reference copy)
tests/
  unit/                       # Isolated unit tests (mocked PSL)
  integration/                # Integration tests (real PSL cache, requires intl extension)
docs/                         # Documentation and improvement roadmaps
```

## Build & Test

```bash
composer install              # Install dependencies
composer test                 # Run PHPUnit tests
composer lint                 # Check code style (PSR-12)
composer fix                  # Auto-fix code style issues
composer analyse              # Run PHPStan (level max)
composer ci                   # Run all CI checks: lint -> analyse -> test
composer update-psl           # Download fresh Mozilla Public Suffix List
```

## PHP Compatibility

Code must run on PHP 8.2 through 8.5. PHP 8.2+ language features are available: union types, named arguments, `match` expressions, enums, `readonly`, constructor promotion, intersection types, `never` return type, first-class callable syntax, and `#[Attribute]` syntax.

## Key Architecture

- **PSL Cache Format**: A PHP array with three keys: `NORMAL`, `WILDCARD`, `EXCEPTION`. Each maps domain strings to `true`. The cache is `include`-d directly for fast loading.
- **Rule Matching Priority**: Exception rules take precedence over wildcard rules, which take precedence over normal rules. This follows the PSL specification algorithm.
- **Dual Cache Paths**: Runtime cache (`XOOPS_VAR_PATH/cache/regdom/psl.cache.php`) is preferred; bundled cache (`data/psl.cache.php`) is the fallback.
- **Static Rule Storage**: `PublicSuffixList::$rules` is static so the PSL is loaded only once per request, shared across all instances.
- **IDN Support**: Domains are converted to ASCII (Punycode) via `idn_to_ascii()` before PSL lookup, then optionally converted back to UTF-8 for output.
- **Cookie Validation**: `RegisteredDomain::domainMatches()` is a static method implementing RFC 6265 domain matching with PSL awareness to prevent supercookie attacks.

## XOOPS Coding Conventions

- Follow PSR-12 coding standard.
- Every source file begins with `declare(strict_types=1)`.
- Class docblocks include `@package`, `@author`, `@license`, and `@link` tags.
- Use `self::` for class constants (not `static::`). PHPStan level max cannot resolve late static binding on constants and reports `mixed`.
- Prefer `\Throwable` in catch blocks over `\Exception` to cover both exceptions and errors on PHP 7+.
- Use `trigger_error()` with `E_USER_WARNING` for non-fatal failures. Use `basename()` in error messages to avoid exposing server paths.
- Suppress PHP-native warnings with `@` only when a subsequent `=== false` check and explicit `trigger_error()` provide a cleaner error path.

## XOOPS Compatibility Layer

XOOPS has two major generations with different APIs. Code must support both:

- **XOOPS 2.6+**: Use `class_exists('Xoops', false)` to detect. Access via `\Xoops::getInstance()`.
- **XOOPS 2.5.x**: Fall back to globals (`$GLOBALS['xoopsModule']`, `$GLOBALS['xoopsConfig']`) and helper functions (`xoops_getHandler()`, `xoops_getModuleHandler()`).
- Never assume XOOPS is present at runtime - RegDom can be used as a standalone library.
- Use the `class_exists()` check with `false` as the second parameter to avoid triggering autoload.
- The constant `XOOPS_VAR_PATH` may or may not be defined; always guard with `defined()` and `is_string()` before use.
- The constant `XOOPS_COOKIE_DOMAIN_USE_PSL` controls PSL-based cookie validation; default behavior when undefined is `true`.

## Security Practices

- All user input must be filtered. Use `Xmf\Request::getVar()` or `Xmf\FilterInput::clean()` - never access `$_GET`, `$_POST`, or `$_REQUEST` directly.
- Escape all output with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` or use Smarty auto-escaping in templates.
- Use parameterized queries via XOOPS database handlers - never concatenate user input into SQL.
- Pass `['allowed_classes' => false]` to any `unserialize()` calls to prevent PHP Object Injection.
- Validate file paths with `realpath()` and boundary checks to prevent directory traversal.
- When generating PHP code (config files, caches), use `var_export()` - never string interpolation with user data.
- For file operations, follow the defensive pattern: exists -> size check -> readable check -> read -> verify not false.
- The PSL cache uses atomic writes (temp file + rename) to prevent corruption from concurrent access.

## Testing Guidelines

- Test classes extend `\PHPUnit\Framework\TestCase`.
- Tests must be fully isolated - no XOOPS installation required.
- Name test methods `test{MethodName}` or `test{MethodName}{Scenario}`.
- Use `try/finally` for temp file cleanup so files are removed even when assertions fail.
- Assert return values before using them (e.g., `$this->assertNotFalse($fh)` after `fopen()`).
- Suppress expected warnings with `@` in test calls (e.g., `@ClassName::methodThatTriggersError()`).
- Unit tests use mocked `PublicSuffixList` for isolation; integration tests use real PSL cache.
- Always reset static state in `tearDown()` (e.g., `RegisteredDomain::setTestPslInstance(null)`).

## Pull Request Checklist

1. Code follows PSR-12 and passes `composer lint`.
2. Static analysis passes `composer analyse` with no new errors.
3. Tests pass on all supported PHP versions (8.2-8.5).
4. New public methods have PHPDoc with `@param`, `@return`, and `@throws` tags.
5. New functionality has corresponding unit tests.
6. Changes are documented in the changelog.
7. No hardcoded encoding strings - use class constants or `_CHARSET`.
8. No direct superglobal access - use `Xmf\Request` or equivalent.
9. PSL cache modifications use atomic writes (temp file + rename).
10. IDN handling accounts for missing `intl` extension gracefully.
