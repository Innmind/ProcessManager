<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner\SubProcess,
    Runner,
    Process\Fork,
};
use Innmind\OperatingSystem\CurrentProcess;
use Innmind\TimeWarp\Halt;
use PHPUnit\Framework\TestCase;

class SubProcessTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Runner::class,
            new SubProcess($this->createMock(CurrentProcess::class)),
        );
    }

    public function testInvokation()
    {
        $run = new SubProcess(CurrentProcess\Generic::of(
            $this->createMock(Halt::class),
        ));

        $process = $run($fn = static function() {
            \sleep(1);
        })->match(
            static fn($process) => $process,
            static fn() => null,
        );

        $this->assertInstanceOf(Fork::class, $process);
        $this->assertNotSame($process, $run($fn));
    }
}
