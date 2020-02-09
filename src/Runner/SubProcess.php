<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner,
    Process,
    Process\Fork,
};
use Innmind\OperatingSystem\CurrentProcess;
use Innmind\Signals\Signal;
use Innmind\Immutable\Set;

final class SubProcess implements Runner
{
    private CurrentProcess $process;
    private Set $processes;

    public function __construct(CurrentProcess $process)
    {
        $this->process = $process;
        $this->processes = new Set(Fork::class);
        $this->registerSignalHandlers($process);
    }

    public function __invoke(callable $callable): Process
    {
        $process = new Fork($this->process, $callable);
        $this->processes = $this->processes->add($process);

        return $process;
    }

    private function registerSignalHandlers(CurrentProcess $process): void
    {
        $forward = function(Signal $signal): void {
            $this
                ->processes
                ->filter(static function(Fork $process): bool {
                    return $process->running();
                })
                ->foreach(static function(Fork $process) use ($signal): void {
                    \posix_kill($process->pid(), $signal->toInt());
                });
        };

        $process->signals()->listen(Signal::hangup(), $forward);
        $process->signals()->listen(Signal::interrupt(), $forward);
        $process->signals()->listen(Signal::abort(), $forward);
        $process->signals()->listen(Signal::terminate(), $forward);
    }
}
