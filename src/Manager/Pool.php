<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Process,
    Runner\SubProcess,
    Loop\Condition,
    Exception\DomainException
};
use Innmind\Socket\{
    Server\Unix,
    Client\Unix as Client,
    Address\Unix as Address,
    Loop,
    Event\ConnectionClosed
};
use Innmind\EventBus\EventBus;
use Innmind\TimeContinuum\ElapsedPeriod;
use Innmind\Immutable\{
    Stream,
    Map,
    SetInterface,
    Set
};
use Ramsey\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

final class Pool implements Manager
{
    private $run;
    private $scheduled;
    private $socket;
    private $address;

    public function __construct(int $size, Runner $run = null)
    {
        if ($size < 1) {
            throw new DomainException;
        }

        $this->size = $size;
        $this->run = $run ?? new SubProcess;
        $this->scheduled = new Stream('callable');
        $this->processes = new Map('callable', Process::class);
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
        (new Filesystem)->mkdir('/tmp/php/innmind/process-manager');
        $self->socket = Unix::recoverable($self->address = new Address(
            '/tmp/php/innmind/process-manager/'.Uuid::uuid4()
        ));
        $self->launch($this->size);

        return $self;
    }

    public function wait(): void
    {
        if ($this->processes->size() === 0) {
            return; //do not wait if not even started
        }

        $loop = new Loop(
            new EventBus(
                (new Map('string', SetInterface::class))
                    ->put(
                        ConnectionClosed::class,
                        (new Set('callable'))->add(function(): void {
                            $this->launch(1);
                        })
                    )
                    ->put(
                        \Throwable::class,
                        (new Set('callable'))->add(function(\Throwable $e): void {
                            throw $e;
                        })
                    )
            ),
            new ElapsedPeriod(1000), //1 minute
            new Condition(function() {
                if ($this->processes->size() < $this->scheduled->size()) {
                    return true;
                }

                $stillRunning = $this
                    ->processes
                    ->filter(function(callable $callable, Process $process): bool {
                        return $process->running();
                    });

                return $stillRunning->size() > 0;
            })
        );

        try {
            $loop($this->socket);
        } finally {
            $this->socket->close();
        }
    }

    private function launch(int $size): void
    {
        $this
            ->processes
            ->filter(static function(callable $callable, Process $process): bool {
                return !$process->running();
            })
            ->foreach(static function(callable $callable, Process $process): void {
                $process->wait(); //done to detect if process finished without error
            });

        $this->processes = $this
            ->scheduled
            ->filter(function(callable $callable): bool {
                return !$this->processes->contains($callable);
            })
            ->take($size)
            ->reduce(
                $this->processes,
                function(Map $processes, callable $callable): Map {
                    $process = ($this->run)(function() use ($callable): void {
                        $socket = new Client($this->address);

                        try {
                            $callable();
                        } finally {
                            $socket->close();
                        }
                    });

                    return $processes->put($callable, $process);
                }
            );
    }
}
