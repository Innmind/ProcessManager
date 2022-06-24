<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Process,
    Runner\Buffer,
    Exception\DomainException,
};
use Innmind\OperatingSystem\Sockets;
use Innmind\Immutable\Sequence;

final class Pool implements Manager
{
    /** @var int<1, max> */
    private int $size;
    private Runner $run;
    private Sockets $sockets;
    private ?Buffer $buffer = null;
    /** @var Sequence<callable(): void> */
    private Sequence $scheduled;
    /** @var Sequence<Process> */
    private Sequence $processes;

    /**
     * @param int<1, max> $size
     */
    public function __construct(int $size, Runner $run, Sockets $sockets)
    {
        $this->size = $size;
        $this->run = $run;
        $this->sockets = $sockets;
        /** @var Sequence<callable(): void> */
        $this->scheduled = Sequence::of();
        /** @var Sequence<Process> */
        $this->processes = Sequence::of();
    }

    public function __invoke(): Manager
    {
        $buffer = new Buffer($this->size, $this->run, $this->sockets);
        $self = clone $this;
        $self->buffer = $buffer;
        $self->processes = $self
            ->scheduled
            ->take($self->size)
            ->map($buffer);

        return $self;
    }

    public function schedule(callable $callable): Manager
    {
        $self = clone $this;
        $self->scheduled = ($self->scheduled)($callable);
        $self->processes = $self->processes->clear();
        $self->buffer = null;

        return $self;
    }

    public function wait(): void
    {
        if (\is_null($this->buffer)) {
            return; //do not wait if not even started
        }

        /** @var Sequence<Process> */
        $processes = $this
            ->scheduled
            ->drop($this->size)
            ->reduce(
                $this->processes,
                function(Sequence $carry, callable $callable): Sequence {
                    /** @psalm-suppress PossiblyNullFunctionCall */
                    return ($carry)(
                        ($this->buffer)($callable),
                    );
                },
            );
        $_ = $processes->foreach(static function(Process $process): void {
            $process->wait();
        });
    }

    public function kill(): void
    {
        $_ = $this
            ->processes
            ->filter(static fn(Process $process): bool => $process->running())
            ->foreach(static function(Process $process): void {
                $process->kill();
            });
    }
}
