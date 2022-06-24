<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager\KillOnError,
    Manager,
    Running,
};
use PHPUnit\Framework\TestCase;

class KillOnErrorTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            new KillOnError($this->createMock(Manager::class)),
        );
    }

    public function testSchedule()
    {
        $inner = $this->createMock(Manager::class);
        $fn = static function() {};
        $inner
            ->expects($this->once())
            ->method('schedule')
            ->with($fn);
        $manager = new KillOnError($inner);
        $manager2 = $manager->schedule($fn);

        $this->assertInstanceOf(KillOnError::class, $manager2);
        $this->assertNotSame($manager, $manager2);
    }

    public function testInvokation()
    {
        $inner = $this->createMock(Manager::class);
        $inner
            ->expects($this->once())
            ->method('start');
        $manager = new KillOnError($inner);
        $running = $manager->start();

        $this->assertInstanceOf(Running\KillOnError::class, $running);
    }
}
