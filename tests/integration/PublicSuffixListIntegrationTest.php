<?php declare(strict_types=1);

namespace Xoops\RegDom\Tests\Integration;

use Xoops\RegDom\PublicSuffixList;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for PublicSuffixList that validate behavior against real PSL data.
 *
 * These tests depend on the bundled PSL cache containing actual public suffix entries.
 * For isolated unit tests that don't depend on PSL data, see tests/unit/PublicSuffixListTest.php.
 */
class PublicSuffixListIntegrationTest extends TestCase
{
    private PublicSuffixList $psl;

    protected function setUp(): void
    {
        if (!file_exists(__DIR__ . '/../../data/psl.cache.php')) {
            $this->markTestSkipped('Bundled PSL cache not found. Run: composer update-psl');
        }
        $this->psl = new PublicSuffixList();
    }

    protected function tearDown(): void
    {
        // Reset static PSL cache to prevent cross-test leakage
        $ref = new \ReflectionProperty(PublicSuffixList::class, 'rules');
        $ref->setAccessible(true);
        $ref->setValue(null, null);
    }

    public function testIsPublicSuffix(): void
    {
        $this->assertTrue($this->psl->isPublicSuffix('com'));
        $this->assertTrue($this->psl->isPublicSuffix('co.uk'));
        $this->assertFalse($this->psl->isPublicSuffix('example.com'));
        $this->assertFalse($this->psl->isPublicSuffix('parliament.uk')); // Regular domain, not a public suffix
        $this->assertTrue($this->psl->isPublicSuffix('anything.ck'));    // Wildcard rule
    }

    public function testGetPublicSuffix(): void
    {
        $this->assertSame('com', $this->psl->getPublicSuffix('example.com'));
        $this->assertSame('co.uk', $this->psl->getPublicSuffix('www.example.co.uk'));
        $this->assertSame('uk', $this->psl->getPublicSuffix('example.parliament.uk'));
        $this->assertSame('something.ck', $this->psl->getPublicSuffix('sub.something.ck'));
        $this->assertSame('com', $this->psl->getPublicSuffix('com'));
    }

    public function testGetPublicSuffixWithExceptionRules(): void
    {
        // PSL exception: !city.kawasaki.jp -> public suffix is kawasaki.jp
        $this->assertSame('kawasaki.jp', $this->psl->getPublicSuffix('sub.city.kawasaki.jp'));
        $this->assertSame('kawasaki.jp', $this->psl->getPublicSuffix('city.kawasaki.jp'));

        // PSL exception: !www.ck -> public suffix is ck
        $this->assertSame('ck', $this->psl->getPublicSuffix('sub.www.ck'));
        $this->assertSame('ck', $this->psl->getPublicSuffix('www.ck'));
    }

    public function testGetMetadata(): void
    {
        $metadata = $this->psl->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('active_cache', $metadata);
        $this->assertArrayHasKey('last_updated', $metadata);
        $this->assertArrayHasKey('days_old', $metadata);
        $this->assertArrayHasKey('rule_counts', $metadata);
        $this->assertArrayHasKey('needs_update', $metadata);
        $this->assertGreaterThan(1000, $metadata['rule_counts']['normal']);
        $this->assertGreaterThan(0, $metadata['rule_counts']['wildcard']);
        $this->assertGreaterThan(0, $metadata['rule_counts']['exception']);
    }

    public function testIsException(): void
    {
        $this->assertTrue($this->psl->isException('www.ck'));
        $this->assertTrue($this->psl->isException('city.kawasaki.jp'));
        $this->assertFalse($this->psl->isException('com'));
        $this->assertFalse($this->psl->isException('example.com'));
        $this->assertFalse($this->psl->isException(''));
    }

    public function testIsPublicSuffixWithPunycodeDomains(): void
    {
        // Punycode form works regardless of ext-intl
        $this->assertTrue($this->psl->isPublicSuffix('xn--55qx5d.cn'));     // 公司.cn in punycode
    }

    /**
     * @requires extension intl
     */
    public function testIsPublicSuffixWithUnicodeIdnDomains(): void
    {
        // Unicode input requires ext-intl for idn_to_ascii() conversion
        $this->assertTrue($this->psl->isPublicSuffix('公司.cn'));            // xn--55qx5d.cn
        $this->assertFalse($this->psl->isPublicSuffix('test.公司.cn'));      // not a PS itself
    }

    public function testGetPublicSuffixWithPunycodeDomains(): void
    {
        // Punycode form works regardless of ext-intl
        $this->assertSame('xn--55qx5d.cn', $this->psl->getPublicSuffix('test.xn--55qx5d.cn'));
    }

    /**
     * @requires extension intl
     */
    public function testGetPublicSuffixWithUnicodeIdnDomains(): void
    {
        // Unicode input requires ext-intl for idn_to_ascii() conversion
        $this->assertSame('xn--55qx5d.cn', $this->psl->getPublicSuffix('test.公司.cn'));
    }

    public function testNormalizeDomainHandlesLeadingAndTrailingDots(): void
    {
        // Leading/trailing dots should be stripped during normalization
        $this->assertTrue($this->psl->isPublicSuffix('.com.'));
        $this->assertTrue($this->psl->isPublicSuffix('COM'));
    }
}
