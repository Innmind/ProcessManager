<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Running;

use Innmind\ProcessManager\{
    Running,
    Process,
};
use Innmind\Immutable\{
    Sequence,
    Either,
    SideEffect,
};

final class Parallel implements Running
{
    /** @var Sequence<Process> */
    private Sequence $processes;

    /**
     * @param Sequence<Process> $processes
     */
    private function __construct(Sequence $processes)
    {
        $this->processes = $processes;
    }

    /**
     * @param Sequence<Process> $processes
     */
    public static function start(Sequence $processes): self
    {
        return new self($processes);
    }

    public function wait(): Either
    {
        return $this->processes->reduce(
            Either::right(new SideEffect),
            $this->doWait(...),
        );
    }

    public function kill(): void
    {
        $_ = $this
            ->processes
            ->filter(static fn(Process $process): bool => $process->running())
            ->foreach(static function(Process $process): void {
                $process->kill();
            });
    }

    /**
     * @param Either<Process\Failed, SideEffect> $either
     *
     * @return Either<Process\Failed, SideEffect>
     */
    private function doWait(Either $either, Process $process): Either
    {
        return $either->flatMap(static fn() => $process->wait());
    }
}
