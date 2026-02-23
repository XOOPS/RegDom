<?php declare(strict_types=1);

namespace Xoops\RegDom\Tests;

use Xoops\RegDom\PublicSuffixList;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Unit tests for PublicSuffixList edge-case behavior.
 *
 * These tests validate input handling (empty strings, IP addresses)
 * rather than real PSL data. For tests that verify behavior against actual PSL
 * entries, see tests/integration/PublicSuffixListIntegrationTest.php.
 */
class PublicSuffixListTest extends TestCase
{
    private PublicSuffixList $psl;

    protected function setUp(): void
    {
        $this->psl = new PublicSuffixList();
    }

    protected function tearDown(): void
    {
        // Reset static PSL cache to prevent cross-test leakage
        $ref = new ReflectionProperty(PublicSuffixList::class, 'rules');
        $ref->setValue(null, null);
    }

    public function testIsPublicSuffixWithEmptyString(): void
    {
        $this->assertFalse($this->psl->isPublicSuffix(''));
    }

    public function testIsPublicSuffixWithIpAddress(): void
    {
        $this->assertFalse($this->psl->isPublicSuffix('192.168.1.1'));
    }

    public function testGetPublicSuffixWithEmptyString(): void
    {
        $this->assertNull($this->psl->getPublicSuffix(''));
    }

    public function testGetPublicSuffixWithIpAddress(): void
    {
        $this->assertNull($this->psl->getPublicSuffix('192.168.1.1'));
    }
}
