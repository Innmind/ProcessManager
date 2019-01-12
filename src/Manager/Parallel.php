<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Process,
};
use Innmind\Immutable\Stream;

final class Parallel implements Manager
{
    private $run;
    private $scheduled;
    private $processes;

    public function __construct(Runner $run)
    {
        $this->run = $run;
        $this->scheduled = new Stream('callable');
        $this->processes = new Stream(Process::class);
    }

    public function schedule(callable $callable): Manager
    {
        $self = clone $this;
        $self->scheduled = $self->scheduled->add($callable);
        $self->processes = $self->processes->clear();

        return $self;
    }

    public function __invoke(): Manager
    {
        $self = clone $this;
        $self->processes = $this
            ->scheduled
            ->reduce(
                $self->processes->clear(),
                function(Stream $carry, callable $callable): Stream {
                    return $carry->add(
                        ($this->run)($callable)
                    );
                }
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
