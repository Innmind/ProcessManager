<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner,
    Process,
};
use Innmind\Immutable\Either;

final class SameProcess implements Runner
{
    public function __invoke(callable $callable): Either
    {
        return Process\Synchronous::run($callable);
    }
}
