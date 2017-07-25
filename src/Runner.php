<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

interface Runner
{
    public function __invoke(callable $callable): Process;
}
