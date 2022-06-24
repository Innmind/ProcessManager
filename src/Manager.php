<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

interface Manager
{
    public function start(): self;

    /**
     * @param callable(): void $callable
     */
    public function schedule(callable $callable): self;
    public function wait(): void;
    public function kill(): void;
}
