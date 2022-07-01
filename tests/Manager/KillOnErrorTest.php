<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager\KillOnError,
    Manager,
    Running,
};
use Innmind\Immutable\Either;
use PHPUnit\Framework\TestCase;

class KillOnErrorTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Manager::class,
            KillOnError::of($this->createMock(Manager::class)),
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
        $manager = KillOnError::of($inner);
        $manager2 = $manager->schedule($fn);

        $this->assertInstanceOf(KillOnError::class, $manager2);
        $this->assertNotSame($manager, $manager2);
    }

    public function testInvokation()
    {
        $inner = $this->createMock(Manager::class);
        $inner
            ->expects($this->once())
            ->method('start')
            ->willReturn(Either::right($this->createMock(Running::class)));
        $manager = KillOnError::of($inner);
        $running = $manager->start()->match(
            static fn($running) => $running,
            static fn() => null,
        );

        $this->assertInstanceOf(Running\KillOnError::class, $running);
    }
}
