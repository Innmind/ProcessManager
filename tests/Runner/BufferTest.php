<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner\Buffer,
    Runner\SubProcess,
    Runner,
    Process\Fork,
    Exception\DomainException,
};
use Innmind\OperatingSystem\{
    CurrentProcess\Generic,
    Sockets,
};
use Innmind\Stream\Watch\Select;
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\TimeWarp\Halt;
use PHPUnit\Framework\TestCase;

class BufferTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Runner::class,
            new Buffer(
                1,
                $this->createMock(Runner::class),
                $this->createMock(Sockets::class),
            ),
        );
    }

    public function testThrowWhenBufferSizeTooLow()
    {
        $this->expectException(DomainException::class);

        new Buffer(
            0,
            $this->createMock(Runner::class),
            $this->createMock(Sockets::class),
        );
    }

    public function testInvokeDirectly()
    {
        $buffer = new Buffer(1, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), $this->createMock(Sockets::class));
        $start = \time();

        $process = $buffer(static function(): void {
            \sleep(10);
        });

        $this->assertInstanceOf(Fork::class, $process);
        $this->assertLessThanOrEqual(1, \time() - $start);
    }

    public function testBufferInvokation()
    {
        $buffer = new Buffer(2, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), $sockets = $this->createMock(Sockets::class));
        $sockets
            ->expects($this->once())
            ->method('watch')
            ->with(new ElapsedPeriod(1000))
            ->willReturn(Select::timeoutAfter(new ElapsedPeriod(1000)));
        $sleep = static function(): void {
            \sleep(5);
        };
        $start = \time();

        $buffer($sleep);
        $buffer($sleep);
        $process = $buffer($sleep);

        $this->assertInstanceOf(Fork::class, $process);
        $this->assertGreaterThanOrEqual(5, \time() - $start);
        $this->assertLessThanOrEqual(6, \time() - $start);
    }
}
