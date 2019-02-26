<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner\Buffer,
    Runner\SubProcess,
    Runner,
    Process\Fork,
};
use Innmind\OperatingSystem\CurrentProcess\Generic;
use Innmind\TimeContinuum\TimeContinuumInterface;
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
                $this->createMock(Runner::class)
            )
        );
    }

    /**
     * @expectedException Innmind\ProcessManager\Exception\DomainException
     */
    public function testThrowWhenBufferSizeTooLow()
    {
        new Buffer(
            0,
            $this->createMock(Runner::class)
        );
    }

    public function testInvokeDirectly()
    {
        $buffer = new Buffer(1, new SubProcess(new Generic(
            $this->createMock(TimeContinuumInterface::class),
            $this->createMock(Halt::class)
        )));
        $start = time();

        $process = $buffer(function(): void {
            sleep(10);
        });

        $this->assertInstanceOf(Fork::class, $process);
        $this->assertTrue(time() - $start < 1);
    }

    public function testBufferInvokation()
    {
        $buffer = new Buffer(2, new SubProcess(new Generic(
            $this->createMock(TimeContinuumInterface::class),
            $this->createMock(Halt::class)
        )));
        $sleep = function(): void {
            sleep(5);
        };
        $start = time();

        $buffer($sleep);
        $buffer($sleep);
        $process = $buffer($sleep);

        $this->assertInstanceOf(Fork::class, $process);
        $this->assertTrue(time() - $start >= 5);
        $this->assertTrue(time() - $start < 6);
    }
}
