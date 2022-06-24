<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Process;

use Innmind\ProcessManager\{
    Process,
    Exception\CouldNotFork,
    Exception\SubProcessFailed,
};
use Innmind\OperatingSystem\{
    CurrentProcess,
    CurrentProcess\Child,
    CurrentProcess\ForkFailed,
};
use Innmind\Signals\Signal;

final class Fork implements Process
{
    private \Closure $callable;
    private Child $child;

    public function __construct(CurrentProcess $process, callable $callable)
    {
        $this->callable = \Closure::fromCallable($callable);

        /** @psalm-suppress PossiblyNullPropertyAssignmentValue */
        $this->child = $process
            ->fork()
            ->match(
                function() use ($process, $callable) {
                    try {
                        $this->registerSignalHandlers($process);

                        $callable();

                        exit(0);
                    } catch (\Throwable $e) {
                        exit(1);
                    }
                },
                static fn($side) => match (true) {
                    $side instanceof ForkFailed => throw new CouldNotFork($callable),
                    $side instanceof Child => $side,
                },
            );
    }

    public function running(): bool
    {
        return $this->child->running();
    }

    public function wait(): void
    {
        $exitCode = $this->child->wait();

        if ($exitCode->toInt() !== 0) {
            throw new SubProcessFailed(
                $this->callable,
                $exitCode->toInt(),
            );
        }
    }

    public function kill(): void
    {
        $this->child->kill();
    }

    public function pid(): int
    {
        return $this->child->id()->toInt();
    }

    private function registerSignalHandlers(CurrentProcess $process): void
    {
        $exit = static function(): void {
            exit(1);
        };

        $process->signals()->listen(Signal::hangup, $exit);
        $process->signals()->listen(Signal::interrupt, $exit);
        $process->signals()->listen(Signal::abort, $exit);
        $process->signals()->listen(Signal::terminate, $exit);
    }
}
