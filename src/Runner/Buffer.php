<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner,
    Process,
    Exception\DomainException
};
use Innmind\Stream\{
    Stream\Bidirectional,
    Selectable,
    Select
};
use Innmind\TimeContinuum\ElapsedPeriod;
use Innmind\Immutable\{
    Map,
    Str
};

final class Buffer implements Runner
{
    private $size;
    private $run;
    private $running;

    public function __construct(int $size, Runner $runner)
    {
        if ($size < 1) {
            throw new DomainException;
        }

        $this->size = $size;
        $this->run = $runner;
        $this->running = new Map(Selectable::class, Process::class);
    }

    public function __invoke(callable $callable): Process
    {
        $this->buffer();

        [$callable, $beacon] = $this->entangle($callable);
        $process = ($this->run)($callable);
        $this->running = $this->running->put($beacon, $process);

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
                $child
                    ->write(new Str('terminating'))
                    ->close();
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
            new Select(new ElapsedPeriod(1000)), //1 second timeout
            static function(Select $select, Selectable $stream): Select {
                return $select->forRead($stream);
            }
        );

        do {
            $streams = $select();
        } while($streams->get('read')->size() === 0);

        $this->running = $streams
            ->get('read')
            ->foreach(function(Selectable $stream): void {
                //truly wait the process to finish, as the stream is just a signal
                $this->running->get($stream)->wait();
            })
            ->reduce(
                $this->running,
                function(Map $carry, Selectable $stream): Map {
                    return $carry->remove($stream);
                }
            );
    }
}
