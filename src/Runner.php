<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

use Innmind\Immutable\Either;

interface Runner
{
    /**
     * @param callable(): void $callable
     *
     * @return Either<Process\InitFailed, Process>
     */
    public function __invoke(callable $callable): Either;
}
