<?php declare(strict_types=1);

namespace Xoops\RegDom\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Xoops\RegDom\PublicSuffixList;
use Xoops\RegDom\RegisteredDomain;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('intl')]
class IntegrationTest extends TestCase
{
    private RegisteredDomain $regdom;

    protected function setUp(): void
    {
        // Skip this entire test suite if the PSL cache is missing
        if (!file_exists(__DIR__ . '/../../data/psl.cache.php')) {
            $this->markTestSkipped('Bundled PSL cache not found. Run: composer update-psl');
        }
        // Use the real, live classes
        $this->regdom = new RegisteredDomain(new PublicSuffixList());
    }

    #[DataProvider('realWorldDataProvider')]
    public function testGetRegisteredDomainWithRealPsl(string $host, ?string $expected): void
    {
        $this->assertSame($expected, $this->regdom->getRegisteredDomain($host));
    }

    public function testDomainMatchesWithRealPsl(): void
    {
        // Cross-domain rejection
        $this->assertFalse(RegisteredDomain::domainMatches('google.com', 'facebook.com'));
        // Public suffix rejection
        $this->assertFalse(RegisteredDomain::domainMatches('example.com', 'com'));
        $this->assertFalse(RegisteredDomain::domainMatches('example.co.uk', 'co.uk'));
        // Valid subdomain matching
        $this->assertTrue(RegisteredDomain::domainMatches('www.google.com', 'google.com'));
        // Different registered domains with same TLD
        $this->assertFalse(RegisteredDomain::domainMatches('google.co.uk', 'amazon.co.uk'));
        // Host-only cookies
        $this->assertTrue(RegisteredDomain::domainMatches('localhost', ''));
        // IP address restrictions
        $this->assertTrue(RegisteredDomain::domainMatches('192.168.1.1', ''));
        $this->assertFalse(RegisteredDomain::domainMatches('192.168.1.1', '192.168.1.1'));
    }

    public function testMetadataWithRealPsl(): void
    {
        $psl = new PublicSuffixList();
        $metadata = $psl->getMetadata();
        $this->assertArrayHasKey('active_cache', $metadata);
        $this->assertArrayHasKey('rule_counts', $metadata);
        // Verify we have real PSL data
        $this->assertGreaterThan(5000, $metadata['rule_counts']['normal']);
        $this->assertGreaterThan(150, $metadata['rule_counts']['wildcard']); // A more realistic lower bound
    }

    public static function realWorldDataProvider(): array
    {
        return [
            'UK standard' => ['www.bbc.co.uk', 'bbc.co.uk'],
            'UK exception' => ['www.parliament.uk', 'parliament.uk'],
            'Japan exception' => ['city.kawasaki.jp', 'city.kawasaki.jp'],
            'Japan wildcard' => ['a.b.c.kobe.jp', 'b.c.kobe.jp'],
            'Unicode' => ['www.münchen.de', 'münchen.de'],
            'Punycode' => ['www.xn--mnchen-3ya.de', 'münchen.de'],
            'Public Suffix' => ['co.uk', null],
            'IDN PS Unicode' => ['test.公司.cn', 'test.公司.cn'],        // 公司.cn is a PS
            'IDN PS punycode' => ['test.xn--55qx5d.cn', 'test.公司.cn'], // punycode PS
            'IDN PS itself' => ['公司.cn', null],                        // PS alone → null
            'Japan exception sub' => ['sub.city.kawasaki.jp', 'city.kawasaki.jp'],
            'CK exception sub' => ['sub.www.ck', 'www.ck'],
        ];
    }
}
