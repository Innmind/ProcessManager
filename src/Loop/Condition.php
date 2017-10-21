<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Loop;

use Innmind\Socket\Loop\Strategy;

final class Condition implements Strategy
{
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function __invoke(): bool
    {
        return ($this->callable)();
    }
}
