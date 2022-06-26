<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner,
    Process,
};
use Innmind\OperatingSystem\Sockets;
use Innmind\Stream\{
    Stream\Bidirectional,
    Selectable,
    Watch,
};
use Innmind\TimeContinuum\Earth\ElapsedPeriod;
use Innmind\Immutable\{
    Map,
    Str,
    Set,
    Either,
    SideEffect,
};

final class Buffer implements Runner
{
    /** @var int<1, max> */
    private int $size;
    private Runner $run;
    private Sockets $sockets;
    /** @var Map<Selectable, Process> */
    private Map $running;

    /**
     * @param int<1, max> $size
     */
    public function __construct(int $size, Runner $runner, Sockets $sockets)
    {
        $this->size = $size;
        $this->run = $runner;
        $this->sockets = $sockets;
        /** @var Map<Selectable, Process> */
        $this->running = Map::of();
    }

    public function __invoke(callable $callable): Either
    {
        [$callable, $beacon] = $this->entangle($callable);

        return $this
            ->buffer()
            ->leftMap(static fn() => new Process\InitFailed)
            ->flatMap(fn() => ($this->run)($callable))
            ->map(function($process) use ($beacon) {
                $this->running = ($this->running)($beacon, $process);

                return $process;
            });
    }

    /**
     * @return array{0: callable(): void, 1: Bidirectional}
     */
    private function entangle(callable $callable): array
    {
        [$parent, $child] = \stream_socket_pair(
            \STREAM_PF_UNIX,
            \STREAM_SOCK_STREAM,
            \STREAM_IPPROTO_IP,
        );
        $parent = Bidirectional::of($parent);
        $child = Bidirectional::of($child);

        $callable = static function() use ($callable, $child): void {
            try {
                $callable();
            } finally {
                // we discard any error as we cannot do anything to ping the
                // parent if we cannot write to the stream
                $_ = $child
                    ->write(Str::of('terminating'))
                    ->flatMap(static fn($child) => $child->close())
                    ->match(
                        static fn() => null,
                        static fn() => null,
                    );
            }
        };

        return [$callable, $parent];
    }

    /**
     * @return Either<Process\Failed, SideEffect>
     */
    private function buffer(): Either
    {
        if ($this->running->size() < $this->size) {
            return Either::right(new SideEffect);
        }

        $watch = $this->running->reduce(
            $this->sockets->watch(new ElapsedPeriod(1000)), //1 second timeout
            static function(Watch $watch, Selectable $stream): Watch {
                /** @psalm-suppress InvalidArgument */
                return $watch->forRead($stream);
            },
        );

        do {
            /** @var Set<Selectable> */
            $toRead = $watch()->match(
                static fn($ready) => $ready->toRead(),
                static fn() => Set::of(),
            );
        } while ($toRead->empty());

        $finished = $this->running->filter(
            static fn($beacon) => $toRead->contains($beacon),
        );
        $this->running = $this->running->filter(
            static fn($beacon) => !$finished->contains($beacon),
        );

        // truly wait the process to finish, as the stream is just a signal
        return $finished
            ->values()
            ->reduce(
                Either::right(new SideEffect),
                self::wait(...),
            );
    }

    /**
     * @param Either<Process\Failed, SideEffect> $either
     *
     * @return Either<Process\Failed, SideEffect>
     */
    private static function wait(Either $either, Process $process): Either
    {
        return $either->flatMap(static fn() => $process->wait());
    }
}
