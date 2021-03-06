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
    private int $size;
    private Runner $run;
    private Sockets $sockets;
    private ?Buffer $buffer = null;
    /** @var Sequence<callable> */
    private Sequence $scheduled;
    /** @var Sequence<Process> */
    private Sequence $processes;

    public function __construct(int $size, Runner $run, Sockets $sockets)
    {
        if ($size < 1) {
            throw new DomainException((string) $size);
        }

        $this->size = $size;
        $this->run = $run;
        $this->sockets = $sockets;
        /** @var Sequence<callable> */
        $this->scheduled = Sequence::of('callable');
        /** @var Sequence<Process> */
        $this->processes = Sequence::of(Process::class);
    }

    public function __invoke(): Manager
    {
        $buffer = new Buffer($this->size, $this->run, $this->sockets);
        $self = clone $this;
        $self->buffer = $buffer;
        $self->processes = $self
            ->scheduled
            ->take($self->size)
            ->mapTo(
                Process::class,
                static fn(callable $callable): Process => $buffer($callable),
            );

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
        $processes->foreach(static function(Process $process): void {
            $process->wait();
        });
    }

    public function kill(): void
    {
        $this
            ->processes
            ->filter(static fn(Process $process): bool => $process->running())
            ->foreach(static function(Process $process): void {
                $process->kill();
            });
    }
}
