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
     * This method is automatically called by PHPUnit after each test.
     * It's the perfect place to clean up the static state.
     */
    protected function tearDown(): void
    {
        // Always reset the static instance to prevent test leakage
        RegisteredDomain::setTestPslInstance(null);
        parent::tearDown();
    }

    /**
     * @dataProvider getRegisteredDomainProvider
     */
    public function testGetRegisteredDomain(string $host, ?string $expected): void
    {
        $this->pslMock->method('getPublicSuffix')->willReturnCallback(function (string $h) {
            // Handle invalid inputs
            if ($h === '' || filter_var($h, FILTER_VALIDATE_IP) || $h === 'localhost') {
                return null;
            }

            // Exception rules: the public suffix is the exception minus its leftmost label
            if ($h === 'city.kawasaki.jp' || $h === 'sub.city.kawasaki.jp') {
                return 'kawasaki.jp';
            }
            if ($h === 'www.ck' || $h === 'sub.www.ck') {
                return 'ck';
            }

            // Standard suffix map
            $suffixes = [
                'co.uk' => 'co.uk',
                'com.cn' => 'com.cn',
                'xn--55qx5d.cn' => 'xn--55qx5d.cn', // 公司.cn
                'com' => 'com',
                'jp' => 'jp',
                'de' => 'de',
            ];

            // Find longest matching suffix
            $longestMatch = null;
            foreach ($suffixes as $suffix => $result) {
                if (str_ends_with($h, $suffix)) {
                    if ($longestMatch === null || strlen($suffix) > strlen($longestMatch)) {
                        $longestMatch = $result;
                    }
                }
            }

            return $longestMatch;
        });

        $regdom = new RegisteredDomain($this->pslMock);
        $this->assertSame($expected, $regdom->getRegisteredDomain($host));
    }

    /**
     * @dataProvider domainMatchesProvider
     */
    public function testDomainMatches(string $host, string $domain, bool $expected): void
    {
        $this->pslMock->method('isPublicSuffix')->willReturnCallback(
            fn(string $d) => in_array($d, ['com', 'co.uk', 'ck'], true)
        );

        // Set the mock for this specific test
        RegisteredDomain::setTestPslInstance($this->pslMock);

        // The try...finally block is no longer needed because tearDown() will handle cleanup
        $this->assertSame($expected, RegisteredDomain::domainMatches($host, $domain));
    }

    public static function getRegisteredDomainProvider(): array
    {
        return [
            'valid simple' => ['example.com', 'example.com'],
            'valid subdomain' => ['sub.example.com', 'example.com'],
            'valid multi-level suffix' => ['www.example.co.uk', 'example.co.uk'],
            'url with path' => ['https://example.com/path', 'example.com'],
            'mixed case URL' => ['HTTPS://WWW.EXAMPLE.COM', 'example.com'],
            'public suffix itself' => ['com', null],
            'unlisted tld' => ['example.example', null],
            'wildcard exception' => ['www.ck', 'www.ck'],
            'PSL exception rule' => ['city.kawasaki.jp', 'city.kawasaki.jp'],
            'PSL exception subdomain' => ['sub.city.kawasaki.jp', 'city.kawasaki.jp'],
            'PSL exception subdomain ck' => ['sub.www.ck', 'www.ck'],
            'IDN simple' => ['食狮.com.cn', '食狮.com.cn'],
            'IDN multi-level' => ['www.食狮.公司.cn', '食狮.公司.cn'],
            'IDN punycode' => ['www.xn--85x722f.xn--55qx5d.cn', '食狮.公司.cn'],
            'IDN public suffix' => ['公司.cn', null],
            'empty string' => ['', null],
            'localhost' => ['localhost', null],
            'IP address' => ['192.168.1.1', null],
            'trailing dot' => ['example.com.', 'example.com'],
            'url with port' => ['https://example.com:8080/path', 'example.com'],
            'host with port no scheme' => ['example.com:443', 'example.com'],
            'IPv6 bracketed' => ['[::1]', null],
            'IPv6 bracketed with port' => ['[::1]:443', null],
            'IPv6 full form' => ['[2001:db8::1]', null],
            'IPv6 full form with port' => ['[2001:db8::1]:8080', null],
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
            'IPv6 host rejected' => ['[::1]', '::1', false],
            'IPv6 host with port rejected' => ['[::1]:443', '::1', false],
        ];
    }
}
