<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner\SubProcess,
    Runner,
    Process\Fork,
};
use Innmind\OperatingSystem\CurrentProcess;
use Innmind\TimeContinuum\Clock;
use Innmind\TimeWarp\Halt;
use PHPUnit\Framework\TestCase;

class SubProcessTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Runner::class,
            new SubProcess($this->createMock(CurrentProcess::class))
        );
    }

    public function testInvokation()
    {
        $run = new SubProcess(new CurrentProcess\Generic(
            $this->createMock(Clock::class),
            $this->createMock(Halt::class)
        ));

        $process = $run($fn = static function() {
            \sleep(1);
        });

        $this->assertInstanceOf(Fork::class, $process);
        $this->assertNotSame($process, $run($fn));
    }
}
