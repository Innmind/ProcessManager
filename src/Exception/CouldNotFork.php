<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Exception;

class CouldNotFork extends RuntimeException
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function callable(): callable
    {
        return $this->callable;
    }
}
