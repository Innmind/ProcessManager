<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Running;

use Innmind\ProcessManager\{
    Running,
    Runner,
    Process,
    Runner\Buffer,
};
use Innmind\OperatingSystem\Sockets;
use Innmind\Immutable\{
    Sequence,
    Either,
    SideEffect,
};

final class Pool implements Running
{
    private Buffer $buffer;
    /** @var Sequence<callable(): void> */
    private Sequence $scheduled;
    /** @var Sequence<Process> */
    private Sequence $processes;

    /**
     * @param Sequence<Process> $processes
     * @param Sequence<callable(): void> $scheduled
     */
    private function __construct(
        Buffer $buffer,
        Sequence $processes,
        Sequence $scheduled,
    ) {
        $this->buffer = $buffer;
        $this->processes = $processes;
        $this->scheduled = $scheduled;
    }

    /**
     * @param int<1, max> $size
     * @param Sequence<callable(): void> $scheduled
     *
     * @return Either<Process\InitFailed, Running>
     */
    public static function start(
        int $size,
        Runner $runner,
        Sockets $sockets,
        Sequence $scheduled,
    ): Either {
        $buffer = new Buffer($size, $runner, $sockets);

        /** @var Either<Process\InitFailed, Running> */
        return self::tryStart($buffer, $scheduled->take($size))->map(
            static fn($processes) => new self(
                $buffer,
                $processes,
                $scheduled->drop($size),
            ),
        );
    }

    public function wait(): Either
    {
        return self::tryStart($this->buffer, $this->scheduled)
            ->leftMap(static fn() => new Process\Failed)
            ->map(fn($processes) => $this->processes->append($processes))
            ->flatMap(self::doWait(...));
    }

    public function kill(): void
    {
        $_ = $this
            ->processes
            ->filter(static fn($process) => $process->running())
            ->foreach(static fn($process) => $process->kill());
    }

    /**
     * @param Sequence<callable(): void> $scheduled
     *
     * @return Either<Process\InitFailed, Sequence<Process>>
     */
    private static function tryStart(Buffer $buffer, Sequence $scheduled): Either
    {
        /** @var Either<Process\InitFailed, Sequence<Process>> */
        $started = Either::right(Sequence::of());

        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         * @var Either<Process\InitFailed, Sequence<Process>>
         */
        return $scheduled->reduce(
            $started,
            static fn(Either $started, callable $callable): Either => $started->flatMap(
                static fn(Sequence $processes) => $buffer($callable)->map(
                    static fn(Process $process) => ($processes)($process),
                ),
            ),
        );
    }

    /**
     * @param Sequence<Process> $processes
     *
     * @return Either<Process\Failed, SideEffect>
     */
    private static function doWait(Sequence $processes): Either
    {
        /** @var Either<Process\Failed, SideEffect> */
        return $processes->reduce(
            Either::right(new SideEffect),
            static fn(Either $either, Process $process): Either => $either->flatMap(
                static fn(): Either => $process->wait(),
            ),
        );
    }
}
