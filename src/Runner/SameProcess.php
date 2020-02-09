<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner,
    Process,
};

final class SameProcess implements Runner
{
    public function __invoke(callable $callable): Process
    {
        return new Process\Synchronous($callable);
    }
}
