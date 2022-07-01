<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

use Innmind\Immutable\Either;

interface Manager
{
    /**
     * @return Either<Process\InitFailed, Running>
     */
    public function start(): Either;

    /**
     * @param callable(): void $callable
     */
    public function schedule(callable $callable): self;
}
