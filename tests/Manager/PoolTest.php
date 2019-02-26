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
    Exception\DomainException,
    Exception\SubProcessFailed,
};
use Innmind\OperatingSystem\CurrentProcess\Generic;
use Innmind\TimeContinuum\TimeContinuumInterface;
use Innmind\TimeWarp\Halt;
use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            new Pool(3, $this->createMock(Runner::class))
        );
    }

    public function testThrowWhenPoolLowerThanOne()
    {
        $this->expectException(DomainException::class);

        new Pool(0, $this->createMock(Runner::class));
    }

    public function testSchedule()
    {
        $pool = new Pool(3, $this->createMock(Runner::class));

        $pool2 = $pool->schedule(function(){});

        $this->assertInstanceOf(Pool::class, $pool2);
        $this->assertNotSame($pool, $pool2);
    }

    public function testInvokationWithoutScheduledCallables()
    {
        $pool = new Pool(
            3,
            $runner = $this->createMock(Runner::class)
        );
        $runner
            ->expects($this->never())
            ->method('__invoke');

        $pool2 = $pool();

        $this->assertInstanceOf(Pool::class, $pool2);
        $this->assertNotSame($pool2, $pool);
    }

    public function testInvokation()
    {
        $start = time();
        $pool = (new Pool(2, new SameProcess))
            ->schedule(static function() {
                sleep(10);
            })
            ->schedule(static function() {
                sleep(5);
            })
            ->schedule(static function() {
                sleep(3);
            })()
            ->wait();
        $delta = time() - $start;

        $this->assertTrue($delta >= 18);
    }

    public function testParallelInvokation()
    {
        $start = time();
        (new Pool(2, new SubProcess(new Generic(
            $this->createMock(TimeContinuumInterface::class),
            $this->createMock(Halt::class)
        ))))
            ->schedule(static function() {
                sleep(10);
            })
            ->schedule(static function() {
                sleep(5);
            })
            ->schedule(static function() {
                sleep(3);
            })()
            ->wait();
        $delta = time() - $start;

        $this->assertTrue($delta >= 10);
        $this->assertTrue($delta < 12);
    }

    /**
     * @dataProvider sizes
     */
    public function testInvokationIsAffectedByPoolSize($size, $expected)
    {
        $start = time();
        (new Pool($size, new SubProcess(new Generic(
            $this->createMock(TimeContinuumInterface::class),
            $this->createMock(Halt::class)
        ))))
            ->schedule(static function() {
                sleep(2);
            })
            ->schedule(static function() {
                sleep(2);
            })
            ->schedule(static function() {
                sleep(2);
            })
            ->schedule(static function() {
                sleep(2);
            })
            ->schedule(static function() {
                sleep(2);
            })
            ->schedule(static function() {
                sleep(2);
            })()
            ->wait();
        $delta = time() - $start;

        $this->assertTrue($delta >= $expected);
        $this->assertTrue($delta < ($expected + 2));
    }

    public function testInvokationWhenPoolHigherThanScheduled()
    {
        $start = time();
        (new Pool(20, new SubProcess(new Generic(
            $this->createMock(TimeContinuumInterface::class),
            $this->createMock(Halt::class)
        ))))
            ->schedule(static function() {
                sleep(10);
            })
            ->schedule(static function() {
                sleep(5);
            })
            ->schedule(static function() {
                sleep(3);
            })()
            ->wait();
        $delta = time() - $start;

        $this->assertTrue($delta >= 10);
        $this->assertTrue($delta < 12);
    }

    public function testDoesntWaitWhenNotInvoked()
    {
        $pool = new Pool(3, new SubProcess(new Generic(
            $this->createMock(TimeContinuumInterface::class),
            $this->createMock(Halt::class)
        )));
        $pool = $pool->schedule(static function() {
            sleep(1);
        });

        $start = time();
        $this->assertNull($pool->wait());
        $this->assertTrue((time() - $start) < 1);
    }

    public function testThrowWhenChildFailed()
    {
        $this->expectException(SubProcessFailed::class);

        try {
            $start = time();
            (new Pool(2, new SubProcess(new Generic(
                $this->createMock(TimeContinuumInterface::class),
                $this->createMock(Halt::class)
            ))))
                ->schedule(static function() {
                    sleep(10);
                })
                ->schedule(static function() {
                    sleep(5);
                })
                ->schedule(static function() {
                    throw new \Exception;
                })
                ->schedule(static function() {
                    sleep(30);
                })()
                ->wait();
        } finally {
            $this->assertTrue(time() - $start >= 5);
            $this->assertTrue(time() - $start <= 7);
        }
    }

    public function testKill()
    {
        $runner = $this->createMock(Runner::class);
        $runner
            ->expects($this->at(0))
            ->method('__invoke')
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('running')
            ->willReturn(false);
        $process
            ->expects($this->never())
            ->method('kill');
        $runner
            ->expects($this->at(1))
            ->method('__invoke')
            ->willReturn($process = $this->createMock(Process::class));
        $process
            ->expects($this->once())
            ->method('running')
            ->willReturn(true);
        $process
            ->expects($this->once())
            ->method('kill');
        $parallel = (new Pool(2, $runner))
            ->schedule(function(){})
            ->schedule(function(){})();

        $this->assertNull($parallel->kill());
    }

    public function testRealKill()
    {
        $start = time();
        $parallel = (new Pool(2, new SubProcess(new Generic(
            $this->createMock(TimeContinuumInterface::class),
            $this->createMock(Halt::class)
        ))))
            ->schedule(function(){
                sleep(10);
            })
            ->schedule(function(){
                sleep(5);
            })();
        $this->assertNull($parallel->kill());
        try {
            $this->assertNull($parallel->wait());
        } catch (\Throwable $e) {
            //pass
        }
        $this->assertTrue(time() - $start < 2);
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
