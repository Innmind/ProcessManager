<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Process;

use Innmind\ProcessManager\{
    Process,
    Exception\SubProcessFailed,
};
use Innmind\OperatingSystem\{
    CurrentProcess,
    CurrentProcess\Child,
    CurrentProcess\ForkFailed,
};
use Innmind\Signals\Signal;
use Innmind\Immutable\{
    Either,
    SideEffect,
};

final class Fork implements Process
{
    private \Closure $callable;
    private Child $child;

    /**
     * @param callable(): void $callable
     */
    private function __construct(Child $child, callable $callable)
    {
        $this->callable = \Closure::fromCallable($callable);
        $this->child = $child;
    }

    /**
     * @param callable(): void $callable
     *
     * @return Either<InitFailed, self>
     */
    public static function start(CurrentProcess $process, callable $callable): Either
    {
        /**
         * @psalm-suppress NoValue as self::execute never returns
         * @var Either<InitFailed, self>
         */
        return $process
            ->fork()
            ->match(
                static fn() => self::execute($process, $callable),
                static fn($side) => match (true) {
                    $side instanceof ForkFailed => Either::left(new InitFailed),
                    $side instanceof Child => Either::right(new self($side, $callable)),
                },
            );
    }

    public function running(): bool
    {
        return $this->child->running();
    }

    public function wait(): Either
    {
        $exitCode = $this->child->wait();

        if ($exitCode->successful()) {
            return Either::right(new SideEffect);
        }

        return Either::left(new Failed);
    }

    public function kill(): void
    {
        $this->child->kill();
    }

    public function pid(): int
    {
        return $this->child->id()->toInt();
    }

    private static function registerSignalHandlers(CurrentProcess $process): void
    {
        $exit = static function(): void {
            exit(1);
        };

        $process->signals()->listen(Signal::hangup, $exit);
        $process->signals()->listen(Signal::interrupt, $exit);
        $process->signals()->listen(Signal::abort, $exit);
        $process->signals()->listen(Signal::terminate, $exit);
    }

    /**
     * @param callable(): void $callable
     */
    private static function execute(
        CurrentProcess $process,
        callable $callable,
    ): never {
        try {
            self::registerSignalHandlers($process);

            $callable();

            exit(0);
        } catch (\Throwable) {
            exit(1);
        }
    }
}
