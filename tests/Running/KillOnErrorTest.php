<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Running;

use Innmind\ProcessManager\{
    Running\KillOnError,
    Running,
};
use PHPUnit\Framework\TestCase;

class KillOnErrorTest extends TestCase
{
    public function testWait()
    {
        $inner = $this->createMock(Running::class);
        $inner
            ->expects($this->once())
            ->method('wait');
        $running = KillOnError::of($inner);

        $this->assertNull($running->wait());
    }

    public function testKill()
    {
        $inner = $this->createMock(Running::class);
        $inner
            ->expects($this->once())
            ->method('kill');
        $running = KillOnError::of($inner);

        $this->assertNull($running->kill());
    }

    public function testKillOnErrorWhenWaiting()
    {
        $inner = $this->createMock(Running::class);
        $inner
            ->expects($this->once())
            ->method('wait')
            ->will($this->throwException($expected = new \Exception));
        $inner
            ->expects($this->once())
            ->method('kill');
        $running = KillOnError::of($inner);

        try {
            $running->wait();
            $this->fail('it should throw');
        } catch (\Throwable $e) {
            $this->assertSame($expected, $e);
        }
    }
}
