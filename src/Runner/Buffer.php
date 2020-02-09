<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner,
    Process,
    Exception\DomainException,
};
use Innmind\OperatingSystem\Sockets;
use Innmind\Stream\{
    Stream\Bidirectional,
    Selectable,
    Watch\Select,
};
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\Immutable\{
    Map,
    Str,
};

final class Buffer implements Runner
{
    private int $size;
    private Runner $run;
    private Sockets $sockets;
    private Map $running;

    public function __construct(int $size, Runner $runner, Sockets $sockets)
    {
        if ($size < 1) {
            throw new DomainException;
        }

        $this->size = $size;
        $this->run = $runner;
        $this->sockets = $sockets;
        $this->running = Map::of(Selectable::class, Process::class);
    }

    public function __invoke(callable $callable): Process
    {
        $this->buffer();

        [$callable, $beacon] = $this->entangle($callable);
        $process = ($this->run)($callable);
        $this->running = ($this->running)($beacon, $process);

        return $process;
    }

    private function entangle(callable $callable): array
    {
        [$parent, $child] = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        );
        $parent = new Bidirectional($parent);
        $child = new Bidirectional($child);

        $callable = static function() use ($callable, $child): void {
            try {
                $callable();
            } finally {
                $child->write(Str::of('terminating'));
                $child->close();
            }
        };

        return [$callable, $parent];
    }

    private function buffer(): void
    {
        if ($this->running->size() < $this->size) {
            return;
        }

        $select = $this->running->reduce(
            $this->sockets->watch(new ElapsedPeriod(1000)), //1 second timeout
            static function(Select $select, Selectable $stream): Select {
                return $select->forRead($stream);
            }
        );

        do {
            $ready = $select();
        } while ($ready->toRead()->empty());

        $ready
            ->toRead()
            ->foreach(function(Selectable $stream): void {
                //truly wait the process to finish, as the stream is just a signal
                $this->running->get($stream)->wait();
            });
        $this->running = $ready
            ->toRead()
            ->reduce(
                $this->running,
                function(Map $carry, Selectable $stream): Map {
                    return $carry->remove($stream);
                }
            );
    }
}
