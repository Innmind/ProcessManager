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
    Exception\SubProcessFailed,
};
use Innmind\OperatingSystem\CurrentProcess\Generic;
use Innmind\TimeContinuum\Clock;
use Innmind\TimeWarp\Halt;
use PHPUnit\Framework\TestCase;

class ParallelTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            new Parallel($this->createMock(Runner::class)),
        );
    }

    public function testSchedule()
    {
        $parallel = new Parallel(new SubProcess(new Generic(
            $this->createMock(Clock::class),
            $this->createMock(Halt::class),
        )));

        $parallel2 = $parallel->schedule(static function() {});

        $this->assertInstanceOf(Parallel::class, $parallel2);
        $this->assertNotSame($parallel2, $parallel);
    }

    public function testInvokationWithoutScheduledCallables()
    {
        $parallel = new Parallel(
            $runner = $this->createMock(Runner::class),
        );
        $runner
            ->expects($this->never())
            ->method('__invoke');

        $parallel2 = $parallel();

        $this->assertInstanceOf(Parallel::class, $parallel2);
        $this->assertNotSame($parallel2, $parallel);
    }

    public function testInvokation()
    {
        $parallel = new Parallel(new SameProcess);
        $start = \time();
        $parallel = $parallel->schedule(static function() {
            \sleep(1);
        });
        $parallel = $parallel->schedule(static function() {
            \sleep(1);
        });

        $this->assertLessThan(2, \time() - $start);

        $parallel = $parallel();

        $this->assertGreaterThanOrEqual(2, \time() - $start);
        $this->assertNull($parallel->wait());
    }

    public function testParallelInvokation()
    {
        $start = \time();
        $parallel = (new Parallel(new SubProcess(new Generic(
            $this->createMock(Clock::class),
            $this->createMock(Halt::class),
        ))))
            ->schedule(static function() {
                \sleep(10);
            })
            ->schedule(static function() {
                \sleep(5);
            })()
            ->wait();
        $delta = \time() - $start;

        $this->assertGreaterThanOrEqual(10, $delta);
        $this->assertLessThan(12, $delta);
    }

    public function testDoesntWaitWhenNotInvoked()
    {
        $parallel = new Parallel(new SubProcess(new Generic(
            $this->createMock(Clock::class),
            $this->createMock(Halt::class),
        )));
        $parallel = $parallel->schedule(static function() {
            \sleep(1);
        });

        $start = \time();
        $this->assertNull($parallel->wait());
        $this->assertLessThan(1, \time() - $start);
    }

    public function testThrowWhenSubProcessFailed()
    {
        $this->expectException(SubProcessFailed::class);

        try {
            $start = \time();
            (new Parallel(new SubProcess(new Generic(
                $this->createMock(Clock::class),
                $this->createMock(Halt::class),
            ))))
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
                })()
                ->wait();
        } finally {
            $this->assertGreaterThanOrEqual(5, \time() - $start);
            $this->assertLessThanOrEqual(10, \time() - $start);
            //it finishes executing the first callable because we wait in the
            //order of the schedules
        }
    }

    public function testKill()
    {
        $runner = $this->createMock(Runner::class);
        $runner
            ->expects($this->exactly(2))
            ->method('__invoke')
            ->will($this->onConsecutiveCalls(
                $process1 = $this->createMock(Process::class),
                $process2 = $this->createMock(Process::class),
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
            ->method('kill');
        $parallel = (new Parallel($runner))
            ->schedule(static function() {})
            ->schedule(static function() {})();

        $this->assertNull($parallel->kill());
    }

    public function testRealKill()
    {
        $start = \time();
        $parallel = (new Parallel(new SubProcess(new Generic(
            $this->createMock(Clock::class),
            $this->createMock(Halt::class),
        ))))
            ->schedule(static function() {
                \sleep(10);
            })
            ->schedule(static function() {
                \sleep(5);
            })();
        $this->assertNull($parallel->kill());

        try {
            $this->assertNull($parallel->wait());
        } catch (\Throwable $e) {
            //pass
        }
        $this->assertLessThan(2, \time() - $start);
    }
}
