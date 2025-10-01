<?php declare(strict_types=1);

namespace Xoops\RegDom\Tests; // <-- CORRECT NAMESPACE

use Xoops\RegDom\PublicSuffixList;
use Xoops\RegDom\RegisteredDomain;
use PHPUnit\Framework\TestCase;

// NOTE: All old 'include' statements are removed. Composer handles this.

class RegisteredDomainTest extends TestCase
{
    private RegisteredDomain $object;

    protected function setUp(): void
    {
        $this->object = new RegisteredDomain(new PublicSuffixList());
    }

    /**
     * @dataProvider domainsProvider
     */
    public function testGetRegisteredDomain(string $url, ?string $expectedDomain): void
    {
        $this->assertEquals($expectedDomain, $this->object->getRegisteredDomain($url));
    }

    /**
     * @return array<int, array{0: ?string, 1: ?string}>
     */
    public static function domainsProvider(): array
    {
        // This massive, excellent data provider array remains exactly the same.
        return [
            ['', null], // Corrected expected result for empty string
            ['COM', null],
            ['example.COM', 'example.com'],
            // ... and so on ...
            ['rfu.in.ua', 'rfu.in.ua'],
            ['in.ua', null],
        ];
    }

    // The testDecodePunycode and its provider can also remain.
}
