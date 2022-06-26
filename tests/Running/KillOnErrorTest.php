<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Running;

use Innmind\ProcessManager\{
    Running\KillOnError,
    Running,
    Process,
};
use Innmind\Immutable\{
    Either,
    SideEffect,
};
use PHPUnit\Framework\TestCase;

class KillOnErrorTest extends TestCase
{
    public function testWait()
    {
        $inner = $this->createMock(Running::class);
        $inner
            ->expects($this->once())
            ->method('wait')
            ->willReturn($expected = Either::right(new SideEffect));
        $running = KillOnError::of($inner);

        $this->assertEquals($expected, $running->wait());
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
            ->willReturn($expected = Either::left(new Process\Failed));
        $inner
            ->expects($this->once())
            ->method('kill');
        $running = KillOnError::of($inner);

        $this->assertEquals($expected, $running->wait());
    }
}
