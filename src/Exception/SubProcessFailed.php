<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Exception;

class SubProcessFailed extends RuntimeException
{
    private $callable;
    private $exitCode;

    public function __construct(callable $callable, int $exitCode)
    {
        parent::__construct();
        $this->callable = $callable;
        $this->exitCode = $exitCode;
    }

    public function callable(): callable
    {
        return $this->callable;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }
}
