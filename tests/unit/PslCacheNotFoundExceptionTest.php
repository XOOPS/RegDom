<?php declare(strict_types=1);

namespace Xoops\RegDom\Tests;

use Xoops\RegDom\Exception\PslCacheNotFoundException;
use PHPUnit\Framework\TestCase;

class PslCacheNotFoundExceptionTest extends TestCase
{
    public function testExtendsRuntimeException(): void
    {
        $exception = new PslCacheNotFoundException('test message');
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testMessage(): void
    {
        $message = 'No valid PSL cache found.';
        $exception = new PslCacheNotFoundException($message);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testDefaultCode(): void
    {
        $exception = new PslCacheNotFoundException('test');
        $this->assertSame(0, $exception->getCode());
    }

    public function testPreviousException(): void
    {
        $previous = new \RuntimeException('previous');
        $exception = new PslCacheNotFoundException('test', 0, $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }
}
