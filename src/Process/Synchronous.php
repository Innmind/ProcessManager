<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Process;

use Innmind\ProcessManager\Process;

final class Synchronous implements Process
{
    /**
     * @param callable(): void $callable
     */
    public function __construct(callable $callable)
    {
        $callable();
    }

    public function running(): bool
    {
        return false;
    }

    public function wait(): void
    {
    }

    public function kill(): void
    {
    }
}
