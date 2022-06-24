<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Running;

use Innmind\ProcessManager\{
    Running,
    Runner,
    Process,
    Runner\Buffer,
};
use Innmind\OperatingSystem\Sockets;
use Innmind\Immutable\Sequence;

final class Pool implements Running
{
    private Buffer $buffer;
    /** @var Sequence<callable(): void> */
    private Sequence $scheduled;
    /** @var Sequence<Process> */
    private Sequence $processes;

    /**
     * @param int<1, max> $size
     * @param Sequence<callable(): void> $scheduled
     */
    private function __construct(
        int $size,
        Runner $runner,
        Sockets $sockets,
        Sequence $scheduled,
    ) {
        $this->buffer = new Buffer($size, $runner, $sockets);
        $this->processes = $scheduled
            ->take($size)
            ->map($this->buffer);
        $this->scheduled = $scheduled->drop($size);
    }

    /**
     * @param int<1, max> $size
     * @param Sequence<callable(): void> $scheduled
     */
    public static function start(
        int $size,
        Runner $runner,
        Sockets $sockets,
        Sequence $scheduled,
    ): self {
        return new self($size, $runner, $sockets, $scheduled);
    }

    public function wait(): void
    {
        $_ = $this
            ->processes
            ->append($this->scheduled->map($this->buffer))
            ->foreach(static fn($process) => $process->wait());
    }

    public function kill(): void
    {
        $_ = $this
            ->processes
            ->filter(static fn($process) => $process->running())
            ->foreach(static fn($process) => $process->kill());
    }
}
