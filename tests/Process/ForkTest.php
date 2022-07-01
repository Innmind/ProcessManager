<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Process;

use Innmind\ProcessManager\{
    Process\Fork,
    Process,
};
use Innmind\OperatingSystem\{
    CurrentProcess\Generic,
    CurrentProcess,
    CurrentProcess\ForkFailed,
};
use Innmind\TimeWarp\Halt;
use Innmind\Immutable\{
    Either,
    SideEffect,
};
use PHPUnit\Framework\TestCase;

class ForkTest extends TestCase
{
    public function testInterface()
    {
        $start = \time();
        $process = Fork::start(
            Generic::of(
                $this->createMock(Halt::class),
            ),
            static function() {
                \sleep(2);
            },
        )->match(
            static fn($process) => $process,
            static fn() => null,
        );

        $this->assertLessThan(1, \time() - $start);
        $this->assertInstanceOf(Process::class, $process);
        $this->assertTrue(\is_int($process->pid()));
        $this->assertTrue($process->pid() > \getmypid());
        $this->assertTrue($process->running());
        $this->assertInstanceOf(SideEffect::class, $process->wait()->match(
            static fn($sideEffect) => $sideEffect,
            static fn() => null,
        ));
        $this->assertGreaterThanOrEqual(2, \time() - $start);
    }

    public function testReturnErrorWhenCallableFails()
    {
        $process = Fork::start(
            Generic::of(
                $this->createMock(Halt::class),
            ),
            $fn = static function() {
                \sleep(2);

                throw new \Exception;
            },
        )->match(
            static fn($process) => $process,
            static fn() => null,
        );

        $error = $process->wait()->match(
            static fn() => null,
            static fn($e) => $e,
        );

        $this->assertInstanceOf(Process\Failed::class, $error);
    }

    public function testKill()
    {
        $process = Fork::start(
            Generic::of(
                $this->createMock(Halt::class),
            ),
            static function() {
                \sleep(10);
            },
        )->match(
            static fn($process) => $process,
            static fn() => null,
        );

        $this->assertInstanceOf(SideEffect::class, $process->kill()->match(
            static fn($sideEffect) => $sideEffect,
            static fn() => null,
        ));
    }

    public function testReturnErrorWhenForkFailed()
    {
        $process = $this->createMock(CurrentProcess::class);
        $process
            ->expects($this->once())
            ->method('fork')
            ->willReturn(Either::left(new ForkFailed));

        $error = Fork::start($process, $fn = static function() {})->match(
            static fn() => null,
            static fn($e) => $e,
        );

        $this->assertInstanceOf(Process\InitFailed::class, $error);
    }
}
