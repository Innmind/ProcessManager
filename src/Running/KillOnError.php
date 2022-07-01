<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Running;

use Innmind\ProcessManager\Running;
use Innmind\Immutable\Either;

final class KillOnError implements Running
{
    private Running $running;

    private function __construct(Running $running)
    {
        $this->running = $running;
    }

    public static function of(Running $running): self
    {
        return new self($running);
    }

    public function wait(): Either
    {
        return $this->running->wait()->leftMap(
            fn($e) => $this->kill()->match(
                static fn() => $e,
                static fn() => $e,
            ),
        );
    }

    public function kill(): Either
    {
        return $this->running->kill();
    }
}
