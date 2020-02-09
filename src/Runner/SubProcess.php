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
    /** @var Set<Fork> */
    private Set $processes;

    public function __construct(CurrentProcess $process)
    {
        $this->process = $process;
        /** @var Set<Fork> */
        $this->processes = Set::of(Fork::class);
        $this->registerSignalHandlers($process);
    }

    public function __invoke(callable $callable): Process
    {
        $process = new Fork($this->process, $callable);
        $this->processes = ($this->processes)($process);

        return $process;
    }

    private function registerSignalHandlers(CurrentProcess $process): void
    {
        $forward = function(Signal $signal): void {
            $this
                ->processes
                ->filter(static fn(Fork $process): bool => $process->running())
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
