<?php declare(strict_types=1);

namespace Xoops\RegDom\Tests;

use Xoops\RegDom\PublicSuffixList;
use PHPUnit\Framework\TestCase;

class PublicSuffixListTest extends TestCase
{
    private PublicSuffixList $psl;

    protected function setUp(): void
    {
        $this->psl = new PublicSuffixList();
    }

    public function testIsPublicSuffix(): void
    {
        $this->assertTrue($this->psl->isPublicSuffix('com'));
        $this->assertTrue($this->psl->isPublicSuffix('co.uk'));
        $this->assertFalse($this->psl->isPublicSuffix('example.com'));
        $this->assertFalse($this->psl->isPublicSuffix('parliament.uk')); // Exception rule
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

    public function testGetMetadata(): void
    {
        $metadata = $this->psl->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('active_cache', $metadata);
        $this->assertArrayHasKey('last_updated', $metadata);
        $this->assertArrayHasKey('days_old', $metadata);
        $this->assertArrayHasKey('rule_counts', $metadata);
        $this->assertGreaterThan(1000, $metadata['rule_counts']['normal']);
    }
}
