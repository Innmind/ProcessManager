<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Running,
    Process,
};
use Innmind\Immutable\{
    Sequence,
    Either,
};

final class Parallel implements Manager
{
    private Runner $run;
    /** @var Sequence<callable(): void> */
    private Sequence $scheduled;

    /**
     * @param Sequence<callable(): void> $scheduled
     */
    private function __construct(Runner $run, Sequence $scheduled)
    {
        $this->run = $run;
        $this->scheduled = $scheduled;
    }

    public static function of(Runner $runner): self
    {
        /** @var Sequence<callable(): void> */
        $scheduled = Sequence::of();

        return new self($runner, $scheduled);
    }

    public function start(): Either
    {
        /** @var Either<Process\InitFailed, Sequence<Process>> */
        $started = Either::right(Sequence::of());

        /** @var Either<Process\InitFailed, Running> */
        return $this
            ->scheduled
            ->reduce(
                $started,
                $this->startProcess(...),
            )
            ->map(Running\Parallel::start(...));
    }

    public function schedule(callable $callable): Manager
    {
        return new self($this->run, ($this->scheduled)($callable));
    }

    /**
     * @param Either<Process\InitFailed, Sequence<Process>> $started
     * @param callable(): void $callable
     *
     * @return Either<Process\InitFailed, Sequence<Process>>
     */
    private function startProcess(Either $started, callable $callable): Either
    {
        return $started->flatMap(
            fn($processes) => ($this->run)($callable)->map(
                static fn($process) => ($processes)($process),
            ),
        );
    }
}
