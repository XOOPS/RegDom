<?php declare(strict_types=1);

namespace Xoops\RegDom\Tests;

use Xoops\RegDom\PublicSuffixList;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PublicSuffixList edge-case behavior.
 *
 * These tests validate input handling (empty strings, IP addresses, normalization)
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

    public function testNormalizeDomainHandlesLeadingAndTrailingDots(): void
    {
        // Leading/trailing dots should be stripped during normalization
        $this->assertTrue($this->psl->isPublicSuffix('.com.'));
        $this->assertTrue($this->psl->isPublicSuffix('COM'));
    }
}
