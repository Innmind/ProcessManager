<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Process;

use Innmind\ProcessManager\{
    Process,
    Exception\CouldNotFork,
    Exception\SubProcessFailed
};
use Innmind\OperatingSystem\{
    CurrentProcess,
    Exception\ForkFailed,
};

final class Fork implements Process
{
    private $callable;
    private $child;

    public function __construct(CurrentProcess $process, callable $callable)
    {
        $this->callable = $callable;
        try {
            $side = $process->fork();

            if (!$side->parent()) {
                try {
                    $this->registerSignalHandlers();

                    $callable();
                    exit(0);
                } catch (\Throwable $e) {
                    exit(1);
                }
            }
        } catch (ForkFailed $e) {
            throw new CouldNotFork($callable);
        }

        $this->child = $process->children()->get($side->child());
    }

    public function running(): bool
    {
        return $this->child->running();
    }

    /**
     * {@inheritdoc}
     */
    public function wait(): void
    {
        $exitCode = $this->child->wait();

        if ($exitCode->toInt() !== 0) {
            throw new SubProcessFailed(
                $this->callable,
                $exitCode->toInt()
            );
        }
    }

    public function kill(): void
    {
        $this->child->terminate();
    }

    public function pid(): int
    {
        return $this->child->id()->toInt();
    }

    private function registerSignalHandlers(): void
    {
        pcntl_async_signals(true);
        $exit = function(int $signal): void {
            exit(1);
        };

        pcntl_signal(SIGHUP, $exit);
        pcntl_signal(SIGINT, $exit);
        pcntl_signal(SIGABRT, $exit);
        pcntl_signal(SIGTERM, $exit);
    }
}
