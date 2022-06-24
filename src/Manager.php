<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

interface Manager
{
    public function start(): Running;

    /**
     * @param callable(): void $callable
     */
    public function schedule(callable $callable): self;
}
