<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Running,
};
use Innmind\Immutable\Sequence;

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

    public function start(): Running
    {
        return Running\Parallel::start($this->scheduled->map(
            fn($callable) => ($this->run)($callable),
        ));
    }

    public function schedule(callable $callable): Manager
    {
        return new self($this->run, ($this->scheduled)($callable));
    }
}
