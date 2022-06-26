<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager\Pool,
    Manager,
    Runner,
    Runner\SameProcess,
    Runner\SubProcess,
    Process,
    Running,
};
use Innmind\OperatingSystem\{
    CurrentProcess\Generic,
    Sockets,
};
use Innmind\Stream\Watch\Select;
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\TimeWarp\Halt;
use Innmind\Immutable\{
    Either,
    SideEffect,
};
use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            Pool::of(
                3,
                $this->createMock(Runner::class),
                $this->createMock(Sockets::class),
            ),
        );
    }

    public function testSchedule()
    {
        $pool = Pool::of(
            3,
            $this->createMock(Runner::class),
            $this->createMock(Sockets::class),
        );

        $pool2 = $pool->schedule(static function() {});

        $this->assertInstanceOf(Pool::class, $pool2);
        $this->assertNotSame($pool, $pool2);
    }

    public function testInvokationWithoutScheduledCallables()
    {
        $pool = Pool::of(
            3,
            $runner = $this->createMock(Runner::class),
            $sockets = $this->createMock(Sockets::class),
        );
        $runner
            ->expects($this->never())
            ->method('__invoke');
        $sockets
            ->expects($this->never())
            ->method('watch');

        $running = $pool->start()->match(
            static fn($running) => $running,
            static fn() => null,
        );

        $this->assertInstanceOf(Running::class, $running);
    }

    public function testInvokation()
    {
        $sockets = $this->createMock(Sockets::class);
        $sockets
            ->expects($this->once())
            ->method('watch')
            ->with(new ElapsedPeriod(1000))
            ->willReturn(Select::timeoutAfter(new ElapsedPeriod(1000)));
        $start = \time();
        $pool = Pool::of(2, new SameProcess, $sockets)
            ->schedule(static function() {
                \sleep(10);
            })
            ->schedule(static function() {
                \sleep(5);
            })
            ->schedule(static function() {
                \sleep(3);
            })
            ->start()
            ->match(
                static fn($running) => $running->wait(),
                static fn() => null,
            );
        $delta = \time() - $start;

        $this->assertGreaterThanOrEqual(18, $delta);
    }

    public function testParallelInvokation()
    {
        $sockets = $this->createMock(Sockets::class);
        $sockets
            ->expects($this->once())
            ->method('watch')
            ->with(new ElapsedPeriod(1000))
            ->willReturn(Select::timeoutAfter(new ElapsedPeriod(1000)));
        $start = \time();
        Pool::of(2, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), $sockets)
            ->schedule(static function() {
                \sleep(10);
            })
            ->schedule(static function() {
                \sleep(5);
            })
            ->schedule(static function() {
                \sleep(3);
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

    /**
     * @dataProvider sizes
     */
    public function testInvokationIsAffectedByPoolSize($size, $expected)
    {
        $sockets = $this->createMock(Sockets::class);
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->with(new ElapsedPeriod(1000))
            ->willReturn(Select::timeoutAfter(new ElapsedPeriod(1000)));
        $start = \time();
        Pool::of($size, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), $sockets)
            ->schedule(static function() {
                \sleep(2);
            })
            ->schedule(static function() {
                \sleep(2);
            })
            ->schedule(static function() {
                \sleep(2);
            })
            ->schedule(static function() {
                \sleep(2);
            })
            ->schedule(static function() {
                \sleep(2);
            })
            ->schedule(static function() {
                \sleep(2);
            })
            ->start()
            ->match(
                static fn($running) => $running->wait(),
                static fn() => null,
            );
        $delta = \time() - $start;

        $this->assertGreaterThanOrEqual($expected, $delta);
        $this->assertLessThan($expected + 2, $delta);
    }

    public function testInvokationWhenPoolHigherThanScheduled()
    {
        $sockets = $this->createMock(Sockets::class);
        $start = \time();
        Pool::of(20, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), $sockets)
            ->schedule(static function() {
                \sleep(10);
            })
            ->schedule(static function() {
                \sleep(5);
            })
            ->schedule(static function() {
                \sleep(3);
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

    public function testReturnErrorWhenChildFailed()
    {
        $sockets = $this->createMock(Sockets::class);
        $sockets
            ->expects($this->any())
            ->method('watch')
            ->with(new ElapsedPeriod(1000))
            ->willReturn(Select::timeoutAfter(new ElapsedPeriod(1000)));
        $start = \time();
        $error = Pool::of(2, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), $sockets)
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
        $this->assertLessThanOrEqual(7, \time() - $start);
    }

    public function testKill()
    {
        $sockets = $this->createMock(Sockets::class);
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
            ->method('kill');
        $parallel = Pool::of(2, $runner, $sockets)
            ->schedule(static function() {})
            ->schedule(static function() {})
            ->start()
            ->match(
                static fn($running) => $running,
                static fn() => null,
            );

        $this->assertNull($parallel->kill());
    }

    public function testRealKill()
    {
        $start = \time();
        $parallel = Pool::of(2, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), Sockets\Unix::of())
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
        $this->assertNull($parallel->kill());

        $this->assertInstanceOf(SideEffect::class, $parallel->wait()->match(
            static fn($sideEffect) => $sideEffect,
            static fn() => null,
        ));
        $this->assertLessThan(2, \time() - $start);
    }

    public function sizes(): array
    {
        return [
            [1, 12],
            [2, 6],
            [3, 4],
            [4, 4],
            [5, 4],
            [6, 2],
        ];
    }
}
