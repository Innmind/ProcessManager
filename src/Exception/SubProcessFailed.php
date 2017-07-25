<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Exception;

class SubProcessFailed extends RuntimeException
{
    private $exitCode;

    public function __construct(int $exitCode)
    {
        parent::__construct();
        $this->exitCode = $exitCode;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }
}
