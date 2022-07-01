<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager\Parallel,
    Manager,
    Runner,
    Runner\SameProcess,
    Runner\SubProcess,
    Process,
    Running,
};
use Innmind\OperatingSystem\CurrentProcess\Generic;
use Innmind\TimeWarp\Halt;
use Innmind\Immutable\{
    Either,
    SideEffect,
};
use PHPUnit\Framework\TestCase;

class ParallelTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            Parallel::of($this->createMock(Runner::class)),
        );
    }

    public function testSchedule()
    {
        $parallel = Parallel::of(new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )));

        $parallel2 = $parallel->schedule(static function() {});

        $this->assertInstanceOf(Parallel::class, $parallel2);
        $this->assertNotSame($parallel2, $parallel);
    }

    public function testInvokationWithoutScheduledCallables()
    {
        $parallel = Parallel::of(
            $runner = $this->createMock(Runner::class),
        );
        $runner
            ->expects($this->never())
            ->method('__invoke');

        $running = $parallel->start()->match(
            static fn($running) => $running,
            static fn() => null,
        );

        $this->assertInstanceOf(Running::class, $running);
    }

    public function testInvokation()
    {
        $parallel = Parallel::of(new SameProcess);
        $start = \time();
        $parallel = $parallel->schedule(static function() {
            \sleep(1);
        });
        $parallel = $parallel->schedule(static function() {
            \sleep(1);
        });

        $this->assertLessThan(2, \time() - $start);

        $parallel = $parallel->start()->match(
            static fn($running) => $running,
            static fn() => null,
        );

        $this->assertGreaterThanOrEqual(2, \time() - $start);
        $this->assertInstanceOf(SideEffect::class, $parallel->wait()->match(
            static fn($sideEffect) => $sideEffect,
            static fn() => null,
        ));
    }

    public function testParallelInvokation()
    {
        $start = \time();
        $parallel = Parallel::of(new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )))
            ->schedule(static function() {
                \sleep(10);
            })
            ->schedule(static function() {
                \sleep(5);
            })
            ->start()
            ->match(
                static fn($running) => $running->wait(),
                static fn() => null,
            );
        $delta = \time() - $start;

        $this->assertGreaterThanOrEqual(10, $delta);
        $this->assertLessThan(12, $delta);
    }

    public function testReturnErrorWhenSubProcessFailed()
    {
        $start = \time();
        $error = Parallel::of(new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )))
            ->schedule(static function() {
                \sleep(10);
            })
            ->schedule(static function() {
                \sleep(5);
            })
            ->schedule(static function() {
                throw new \Exception;
            })
            ->schedule(static function() {
                \sleep(30);
            })
            ->start()
            ->flatMap(static fn($running) => $running->wait())
            ->match(
                static fn() => null,
                static fn($e) => $e,
            );
        $this->assertInstanceOf(Process\Failed::class, $error);
        $this->assertGreaterThanOrEqual(5, \time() - $start);
        $this->assertLessThanOrEqual(10, \time() - $start);
        //it finishes executing the first callable because we wait in the
        //order of the schedules
    }

    public function testKill()
    {
        $runner = $this->createMock(Runner::class);
        $runner
            ->expects($this->exactly(2))
            ->method('__invoke')
            ->will($this->onConsecutiveCalls(
                Either::right($process1 = $this->createMock(Process::class)),
                Either::right($process2 = $this->createMock(Process::class)),
            ));
        $process1
            ->expects($this->once())
            ->method('running')
            ->willReturn(false);
        $process1
            ->expects($this->never())
            ->method('kill');
        $process2
            ->expects($this->once())
            ->method('running')
            ->willReturn(true);
        $process2
            ->expects($this->once())
            ->method('kill')
            ->willReturn($expected = Either::right(new SideEffect));
        $parallel = Parallel::of($runner)
            ->schedule(static function() {})
            ->schedule(static function() {})
            ->start()
            ->match(
                static fn($running) => $running,
                static fn() => null,
            );

        $this->assertEquals($expected, $parallel->kill());
    }

    public function testRealKill()
    {
        $start = \time();
        $parallel = Parallel::of(new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )))
            ->schedule(static function() {
                \sleep(10);
            })
            ->schedule(static function() {
                \sleep(5);
            })
            ->start()
            ->match(
                static fn($running) => $running,
                static fn() => null,
            );
        $this->assertInstanceOf(SideEffect::class, $parallel->kill()->match(
            static fn($sideEffect) => $sideEffect,
            static fn() => null,
        ));

        $this->assertInstanceOf(SideEffect::class, $parallel->wait()->match(
            static fn($sideEffect) => $sideEffect,
            static fn() => null,
        ));
        $this->assertLessThan(2, \time() - $start);
    }
}
