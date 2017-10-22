<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager\Pool,
    Manager,
    Runner,
    Runner\SameProcess
};
use PHPUnit\Framework\TestCase;

class PoolTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            new Pool(3)
        );
    }

    /**
     * @expectedException Innmind\ProcessManager\Exception\DomainException
     */
    public function testThrowWhenPoolLowerThanOne()
    {
        new Pool(0);
    }

    public function testSchedule()
    {
        $pool = new Pool(3);

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
        (new Pool(2))
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
        (new Pool($size))
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
        $this->assertTrue($delta < ($expected + 1));
    }

    public function testInvokationWhenPoolHigherThanScheduled()
    {
        $start = time();
        (new Pool(20))
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
        $pool = new Pool(3);
        $pool = $pool->schedule(static function() {
            sleep(1);
        });

        $start = time();
        $this->assertNull($pool->wait());
        $this->assertTrue((time() - $start) < 1);
    }

    /**
     * @expectedException Innmind\ProcessManager\Exception\SubProcessFailed
     */
    public function testThrowWhenChildFailed()
    {
        try {
            $start = time();
            (new Pool(2))
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
            $this->assertTrue(time() - $start <= 10);
            //it finishes executing the first callable because when we receive
            //the connection close of the second it still says the process is
            //running so we can't yet detect it has failed
        }
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
