<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

interface Runner
{
    /**
     * @param callable(): void $callable
     */
    public function __invoke(callable $callable): Process;
}
