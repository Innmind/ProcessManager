<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Running;

use Innmind\ProcessManager\Running;

final class KillOnError implements Running
{
    private Running $running;

    private function __construct(Running $running)
    {
        $this->running = $running;
    }

    public static function of(Running $running): self
    {
        return new self($running);
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
