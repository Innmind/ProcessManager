<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner,
    Process,
    Process\Fork
};
use Innmind\Immutable\Set;

final class SubProcess implements Runner
{
    private $processes;

    public function __construct()
    {
        $this->processes = new Set(Fork::class);
        $this->registerSignalHandlers();
    }

    public function __invoke(callable $callable): Process
    {
        $process = new Fork($callable);
        $this->processes = $this->processes->add($process);

        return $process;
    }

    private function registerSignalHandlers(): void
    {
        pcntl_async_signals(true);
        $forward = function(int $signal): void {
            $this
                ->processes
                ->filter(static function(Fork $process): bool {
                    return $process->running();
                })
                ->foreach(static function(Fork $process) use ($signal): void {
                    posix_kill($process->pid(), $signal);
                });
        };

        pcntl_signal(SIGHUP, $forward);
        pcntl_signal(SIGINT, $forward);
        pcntl_signal(SIGABRT, $forward);
        pcntl_signal(SIGTERM, $forward);
    }
}
