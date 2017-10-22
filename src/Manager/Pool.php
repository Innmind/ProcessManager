<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Process,
    Runner\SubProcess,
    Runner\Buffer,
    Exception\DomainException
};
use Innmind\Immutable\Stream;

final class Pool implements Manager
{
    private $run;
    private $buffer;
    private $scheduled;
    private $processes;

    public function __construct(int $size, Runner $run = null)
    {
        if ($size < 1) {
            throw new DomainException;
        }

        $this->size = $size;
        $this->run = $run ?? new SubProcess;
        $this->scheduled = new Stream('callable');
        $this->processes = new Stream(Process::class);
    }

    public function schedule(callable $callable): Manager
    {
        $self = clone $this;
        $self->scheduled = $self->scheduled->add($callable);
        $self->processes = $self->processes->clear();
        $self->buffer = null;

        return $self;
    }

    public function __invoke(): Manager
    {
        $self = clone $this;
        $self->buffer = new Buffer($this->size, $this->run);
        $self->processes = $self
            ->scheduled
            ->take($self->size)
            ->reduce(
                $self->processes->clear(),
                static function(Stream $carry, callable $callable) use ($self): Stream {
                    return $carry->add(
                        ($self->buffer)($callable)
                    );
                }
            );

        return $self;
    }

    public function wait(): void
    {
        if (is_null($this->buffer)) {
            return; //do not wait if not even started
        }

        $this
            ->scheduled
            ->drop($this->size)
            ->reduce(
                $this->processes,
                function(Stream $carry, callable $callable): Stream {
                    return $carry->add(
                        ($this->buffer)($callable)
                    );
                }
            )
            ->foreach(static function(Process $process): void {
                $process->wait();
            });
    }
}
