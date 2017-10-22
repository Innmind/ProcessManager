<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Process;

use Innmind\ProcessManager\Process;

final class Synchronous implements Process
{
    public function __construct(callable $callable)
    {
        $callable();
    }

    public function running(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function wait(): void
    {
    }

    public function kill(): void
    {
    }
}
