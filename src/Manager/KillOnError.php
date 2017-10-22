<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\Manager;

final class KillOnError implements Manager
{
    private $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public function schedule(callable $callable): Manager
    {
        return new self($this->manager->schedule($callable));
    }

    public function __invoke(): Manager
    {
        try {
            return new self(($this->manager)());
        } catch (\Throwable $e) {
            $this->kill();
            throw $e;
        }
    }

    public function wait(): void
    {
        try {
            $this->manager->wait();
        } catch (\Throwable $e) {
            $this->kill();
            throw $e;
        }
    }

    public function kill(): void
    {
        $this->manager->kill();
    }
}
