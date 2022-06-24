<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Running,
};
use Innmind\Immutable\Sequence;

final class Parallel implements Manager
{
    private Runner $run;
    /** @var Sequence<callable(): void> */
    private Sequence $scheduled;

    public function __construct(Runner $run)
    {
        $this->run = $run;
        /** @var Sequence<callable(): void> */
        $this->scheduled = Sequence::of();
    }

    public function start(): Running
    {
        return new Running\Parallel($this->scheduled->map(
            fn($callable) => ($this->run)($callable),
        ));
    }

    public function schedule(callable $callable): Manager
    {
        $self = clone $this;
        $self->scheduled = ($self->scheduled)($callable);

        return $self;
    }
}
