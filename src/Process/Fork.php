<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Process;

use Innmind\ProcessManager\{
    Process,
    Exception\CouldNotFork,
    Exception\SubProcessFailed
};

final class Fork implements Process
{
    private $callable;
    private $pid;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
        $pid = pcntl_fork();

        switch ($pid) {
            case -1:
                throw new CouldNotFork($callable);

            case 0:
                try {
                    $this->registerSignalHandlers();

                    $callable();
                    exit(0);
                } catch (\Throwable $e) {
                    exit(1);
                }

            default:
                $this->pid = $pid;
                break;
        }
    }

    public function running(): bool
    {
        return is_int(posix_getpgid($this->pid));
    }

    /**
     * {@inheritdoc}
     */
    public function wait(): void
    {
        pcntl_waitpid($this->pid, $status);
        $exitCode = pcntl_wexitstatus($status);

        if ($exitCode !== 0) {
            throw new SubProcessFailed(
                $this->callable,
                $exitCode
            );
        }
    }

    public function kill(): void
    {
        posix_kill($this->pid, SIGTERM);
    }

    public function pid(): int
    {
        return $this->pid;
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
