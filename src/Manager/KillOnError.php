<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Running,
};

final class KillOnError implements Manager
{
    private Manager $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public function start(): Running
    {
        return new Running\KillOnError($this->manager->start());
    }

    public function schedule(callable $callable): Manager
    {
        return new self($this->manager->schedule($callable));
    }
}
