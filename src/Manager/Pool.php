<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Process,
    Runner\Buffer,
    Running,
};
use Innmind\OperatingSystem\Sockets;
use Innmind\Immutable\Sequence;

final class Pool implements Manager
{
    /** @var int<1, max> */
    private int $size;
    private Runner $runner;
    private Sockets $sockets;
    /** @var Sequence<callable(): void> */
    private Sequence $scheduled;

    /**
     * @param int<1, max> $size
     */
    public function __construct(int $size, Runner $runner, Sockets $sockets)
    {
        $this->size = $size;
        $this->runner = $runner;
        $this->sockets = $sockets;
        /** @var Sequence<callable(): void> */
        $this->scheduled = Sequence::of();
    }

    public function start(): Running
    {
        return new Running\Pool(
            $this->size,
            $this->runner,
            $this->sockets,
            $this->scheduled,
        );
    }

    public function schedule(callable $callable): Manager
    {
        $self = clone $this;
        $self->scheduled = ($self->scheduled)($callable);

        return $self;
    }
}
