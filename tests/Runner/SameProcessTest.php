<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner\SameProcess,
    Runner,
    Process\Synchronous
};
use PHPUnit\Framework\TestCase;

class SameProcessTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(Runner::class, new SameProcess);
    }

    public function testInvokation()
    {
        $run = new SameProcess;

        $process = $run($fn = static function() {
            \sleep(1);
        })->match(
            static fn($process) => $process,
            static fn() => null,
        );

        $this->assertInstanceOf(Synchronous::class, $process);
        $this->assertNotSame($process, $run($fn));
    }
}
