<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Process;

use Innmind\ProcessManager\Process;
use Innmind\Immutable\{
    Either,
    SideEffect,
};

final class Synchronous implements Process
{
    /**
     * @param callable(): void $callable
     */
    private function __construct(callable $callable)
    {
        $callable();
    }

    /**
     * @param callable(): void $callable
     *
     * @return Either<InitFailed, Process>
     */
    public static function run(callable $callable): Either
    {
        try {
            /** @var Either<InitFailed, Process> */
            return Either::right(new self($callable));
        } catch (\Throwable $e) {
            /** @var Either<InitFailed, Process> */
            return Either::left(new InitFailed);
        }
    }

    public function running(): bool
    {
        return false;
    }

    public function wait(): Either
    {
        return Either::right(new SideEffect);
    }

    public function kill(): Either
    {
        return Either::right(new SideEffect);
    }
}
