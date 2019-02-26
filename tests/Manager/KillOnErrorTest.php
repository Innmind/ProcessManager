<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager\KillOnError,
    Manager
};
use PHPUnit\Framework\TestCase;

class KillOnErrorTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            new KillOnError($this->createMock(Manager::class))
        );
    }

    public function testSchedule()
    {
        $inner = $this->createMock(Manager::class);
        $fn = function(){};
        $inner
            ->expects($this->once())
            ->method('schedule')
            ->with($fn);
        $inner
            ->expects($this->never())
            ->method('kill');
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
            ->method('__invoke');
        $inner
            ->expects($this->never())
            ->method('kill');
        $manager = new KillOnError($inner);
        $manager2 = $manager();

        $this->assertInstanceOf(KillOnError::class, $manager2);
        $this->assertNotSame($manager, $manager2);
    }

    public function testKillOnInvokationError()
    {
        $inner = $this->createMock(Manager::class);
        $inner
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new \Exception));
        $inner
            ->expects($this->once())
            ->method('kill');
        $manager = new KillOnError($inner);

        $this->expectException(\Exception::class);

        $manager();
    }

    public function testWait()
    {
        $inner = $this->createMock(Manager::class);
        $inner
            ->expects($this->once())
            ->method('wait');
        $inner
            ->expects($this->never())
            ->method('kill');
        $manager = new KillOnError($inner);

        $this->assertNull($manager->wait());
    }

    public function testKillOnWaitError()
    {
        $inner = $this->createMock(Manager::class);
        $inner
            ->expects($this->once())
            ->method('wait')
            ->will($this->throwException(new \Exception));
        $inner
            ->expects($this->once())
            ->method('kill');
        $manager = new KillOnError($inner);

        $this->expectException(\Exception::class);

        $manager->wait();
    }

    public function testKill()
    {
        $inner = $this->createMock(Manager::class);
        $inner
            ->expects($this->once())
            ->method('kill');
        $manager = new KillOnError($inner);

        $this->assertNull($manager->kill());
    }
}
