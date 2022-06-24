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
    Exception\SubProcessFailed,
};
use Innmind\OperatingSystem\{
    CurrentProcess\Generic,
    Sockets,
};
use Innmind\Stream\Watch\Select;
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\TimeWarp\Halt;
use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            new Pool(
                3,
                $this->createMock(Runner::class),
                $this->createMock(Sockets::class),
            ),
        );
    }

    public function testSchedule()
    {
        $pool = new Pool(
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
        $pool = new Pool(
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

        $running = $pool->start();

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
        $pool = (new Pool(2, new SameProcess, $sockets))
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
            ->wait();
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
        (new Pool(2, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), $sockets))
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
            ->wait();
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
        (new Pool($size, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), $sockets))
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
            ->wait();
        $delta = \time() - $start;

        $this->assertGreaterThanOrEqual($expected, $delta);
        $this->assertLessThan($expected + 2, $delta);
    }

    public function testInvokationWhenPoolHigherThanScheduled()
    {
        $sockets = $this->createMock(Sockets::class);
        $start = \time();
        (new Pool(20, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), $sockets))
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
            ->wait();
        $delta = \time() - $start;

        $this->assertGreaterThanOrEqual(10, $delta);
        $this->assertLessThan(12, $delta);
    }

    public function testThrowWhenChildFailed()
    {
        $this->expectException(SubProcessFailed::class);

        try {
            $sockets = $this->createMock(Sockets::class);
            $sockets
                ->expects($this->any())
                ->method('watch')
                ->with(new ElapsedPeriod(1000))
                ->willReturn(Select::timeoutAfter(new ElapsedPeriod(1000)));
            $start = \time();
            (new Pool(2, new SubProcess(Generic::of(
                $this->createMock(Halt::class),
            )), $sockets))
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
                ->wait();
        } finally {
            $this->assertGreaterThanOrEqual(5, \time() - $start);
            $this->assertLessThanOrEqual(7, \time() - $start);
        }
    }

    public function testKill()
    {
        $sockets = $this->createMock(Sockets::class);
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
        $parallel = (new Pool(2, $runner, $sockets))
            ->schedule(static function() {})
            ->schedule(static function() {})
            ->start();

        $this->assertNull($parallel->kill());
    }

    public function testRealKill()
    {
        $start = \time();
        $parallel = (new Pool(2, new SubProcess(Generic::of(
            $this->createMock(Halt::class),
        )), Sockets\Unix::of()))
            ->schedule(static function() {
                \sleep(10);
            })
            ->schedule(static function() {
                \sleep(5);
            })
            ->start();
        $this->assertNull($parallel->kill());

        try {
            $this->assertNull($parallel->wait());
        } catch (\Throwable $e) {
            //pass
        }
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
