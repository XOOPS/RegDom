<?php declare(strict_types=1);

namespace Xoops\RegDom\Tests;

use Xoops\RegDom\PublicSuffixList;
use Xoops\RegDom\RegisteredDomain;
use PHPUnit\Framework\TestCase;

class RegisteredDomainTest extends TestCase
{
    private PublicSuffixList $pslMock;

    protected function setUp(): void
    {
        $this->pslMock = $this->createMock(PublicSuffixList::class);
    }

    /**
     * @dataProvider getRegisteredDomainProvider
     */
    public function testGetRegisteredDomain(string $host, ?string $expected): void
    {
        $this->pslMock->method('getPublicSuffix')->willReturnCallback(function (string $h) {
            $suffixes = [
                'co.uk' => 'co.uk',
                'com.cn' => 'com.cn',
                'xn--55qx5d.cn' => 'xn--55qx5d.cn', // 公司.cn
                'ck' => 'ck',
                'com' => 'com',
                'net' => 'net',
                'org' => 'org',
                'cn' => 'cn',
                'de' => 'de',
                'uk' => 'uk',
            ];
            // Find the longest matching suffix using PHP 7.4 compatible code
            foreach ($suffixes as $suffix => $result) {
                if (substr($h, -strlen($suffix)) === $suffix) {
                    if ($suffix === 'ck' && $h !== 'www.ck') {
                        return $h;
                    }
                    return $result;
                }
            }
            return null;
        });

        $regdom = new RegisteredDomain($this->pslMock);
        $this->assertSame($expected, $regdom->getRegisteredDomain($host));
    }

    /**
     * @dataProvider domainMatchesProvider
     */
    public function testDomainMatches(string $host, string $domain, bool $expected): void
    {
        $this->pslMock->method('isPublicSuffix')->willReturnCallback(fn(string $d) => in_array($d, ['com', 'co.uk', 'ck'], true));
        self::setStaticPslInstance($this->pslMock);
        $this->assertSame($expected, RegisteredDomain::domainMatches($host, $domain));
        self::setStaticPslInstance(null);
    }

    public static function getRegisteredDomainProvider(): array
    {
        return [
            'valid simple' => ['example.com', 'example.com'],
            'valid subdomain' => ['sub.example.com', 'example.com'],
            'valid multi-level suffix' => ['www.example.co.uk', 'example.co.uk'],
            'url with path' => ['https://example.com/path', 'example.com'],
            'public suffix itself' => ['com', null],
            'unlisted tld' => ['example.example', null],
            'wildcard exception' => ['www.ck', 'www.ck'],
            'IDN simple' => ['食狮.com.cn', '食狮.com.cn'],
            'IDN multi-level' => ['www.食狮.公司.cn', '食狮.公司.cn'],
            'IDN punycode' => ['www.xn--85x722f.xn--55qx5d.cn', '食狮.公司.cn'],
            'IDN public suffix' => ['公司.cn', null],
            'empty string' => ['', null],
            'localhost' => ['localhost', null],
            'IP address' => ['192.168.1.1', null],
        ];
    }

    public static function domainMatchesProvider(): array
    {
        return [
            'exact match' => ['example.com', 'example.com', true],
            'subdomain match' => ['sub.example.com', 'example.com', true],
            'host-only' => ['example.com', '', true],
            'ip host-only' => ['192.168.0.1', '', true],
            'ip with domain' => ['192.168.0.1', '192.168.0.1', false],
            'localhost host-only' => ['localhost', '', true],
            'localhost with domain' => ['localhost', 'localhost', false],
            'public suffix rejected' => ['example.com', 'com', false],
            'case insensitive' => ['WWW.EXAMPLE.COM', 'example.com', true],
            'leading dot ignored' => ['example.com', '.example.com', true],
            'port stripped' => ['example.com:8080', 'example.com', true],
            'idn match' => ['münchen.de', 'münchen.de', true],
        ];
    }

    // FINAL FIX: The polyfill has been completely removed from this method.
    private static function setStaticPslInstance(?PublicSuffixList $psl): void
    {
        $reflection = new \ReflectionClass(RegisteredDomain::class);
        $property = $reflection->getProperty('pslInstance');
        $property->setAccessible(true);
        $property->setValue(null, $psl);
    }
}
