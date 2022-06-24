<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Process;

use Innmind\ProcessManager\Process;

final class Synchronous implements Process
{
    /**
     * @param callable(): void $callable
     */
    private function __construct(callable $callable)
    {
        $callable();
    }

    /**
     * @param callable(): void $callable
     */
    public static function run(callable $callable): self
    {
        return new self($callable);
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
