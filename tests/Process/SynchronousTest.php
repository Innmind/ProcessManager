<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Process;

use Innmind\ProcessManager\{
    Process\Synchronous,
    Process
};
use Innmind\Immutable\SideEffect;
use PHPUnit\Framework\TestCase;

class SynchronousTest extends TestCase
{
    public function testInterface()
    {
        $start = \time();
        $process = Synchronous::run(static function() {
            \sleep(1);
        })->match(
            static fn($process) => $process,
            static fn() => null,
        );

        $this->assertGreaterThanOrEqual(1, \time() - $start);
        $this->assertInstanceOf(Process::class, $process);
        $this->assertFalse($process->running());
        $this->assertInstanceOf(SideEffect::class, $process->wait()->match(
            static fn($sideEffect) => $sideEffect,
            static fn() => null,
        ));
        $this->assertInstanceOf(SideEffect::class, $process->kill()->match(
            static fn($sideEffect) => $sideEffect,
            static fn() => null,
        ));
    }
}
