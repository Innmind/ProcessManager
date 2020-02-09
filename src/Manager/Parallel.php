<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Process,
};
use Innmind\Immutable\Sequence;

final class Parallel implements Manager
{
    private Runner $run;
    /** @var Sequence<callable> */
    private Sequence $scheduled;
    /** @var Sequence<Process> */
    private Sequence $processes;

    public function __construct(Runner $run)
    {
        $this->run = $run;
        /** @var Sequence<callable> */
        $this->scheduled = Sequence::of('callable');
        /** @var Sequence<Process> */
        $this->processes = Sequence::of(Process::class);
    }

    public function schedule(callable $callable): Manager
    {
        $self = clone $this;
        $self->scheduled = ($self->scheduled)($callable);
        $self->processes = $self->processes->clear();

        return $self;
    }

    public function __invoke(): Manager
    {
        $self = clone $this;
        $self->processes = $this->scheduled->mapTo(
            Process::class,
            fn(callable $callable): Process => ($this->run)($callable),
        );

        return $self;
    }

    public function wait(): void
    {
        $this->processes->foreach(static function(Process $process): void {
            $process->wait();
        });
    }

    public function kill(): void
    {
        $this
            ->processes
            ->filter(static function(Process $process): bool {
                return $process->running();
            })
            ->foreach(static function(Process $process): void {
                $process->kill();
            });
        }
}
