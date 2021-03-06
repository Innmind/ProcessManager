<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Exception;

class SubProcessFailed extends RuntimeException
{
    /** @var callable */
    private $callable;
    private int $exitCode;

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
