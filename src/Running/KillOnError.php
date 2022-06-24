<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Running;

use Innmind\ProcessManager\Running;

final class KillOnError implements Running
{
    private Running $running;

    public function __construct(Running $running)
    {
        $this->running = $running;
    }

    public function wait(): void
    {
        try {
            $this->running->wait();
        } catch (\Throwable $e) {
            $this->kill();

            throw $e;
        }
    }

    public function kill(): void
    {
        $this->running->kill();
    }
}
