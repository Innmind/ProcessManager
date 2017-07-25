<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager\Parallel,
    Manager,
    Runner,
    Runner\SameProcess
};
use PHPUnit\Framework\TestCase;

class ParallelTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            new Parallel(
                $this->createMock(Runner::class)
            )
        );
    }

    public function testSchedule()
    {
        $parallel = new Parallel($this->createMock(Runner::class));

        $parallel2 = $parallel->schedule(function(){});

        $this->assertInstanceOf(Parallel::class, $parallel2);
        $this->assertNotSame($parallel2, $parallel);
    }

    public function testInvokationWithoutScheduledCallables()
    {
        $parallel = new Parallel(
            $runner = $this->createMock(Runner::class)
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
        $start = time();
        $parallel = $parallel->schedule(static function() {
            sleep(1);
        });
        $parallel = $parallel->schedule(static function() {
            sleep(1);
        });

        $this->assertTrue((time() - $start) < 2);

        $parallel = $parallel();

        $this->assertTrue((time() - $start) >= 2);
        $this->assertNull($parallel->wait());
    }

    public function testDoesntWaitWhenNotInvoked()
    {
        $parallel = new Parallel($this->createMock(Runner::class));
        $parallel = $parallel->schedule(static function() {
            sleep(1);
        });

        $start = time();
        $this->assertNull($parallel->wait());
        $this->assertTrue((time() - $start) < 1);
    }
}
