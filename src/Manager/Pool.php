<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Runner,
    Process,
    Runner\Buffer,
    Running,
};
use Innmind\OperatingSystem\Sockets;
use Innmind\Immutable\{
    Sequence,
    Either,
};

final class Pool implements Manager
{
    /** @var int<1, max> */
    private int $size;
    private Runner $runner;
    private Sockets $sockets;
    /** @var Sequence<callable(): void> */
    private Sequence $scheduled;

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
        $this->size = $size;
        $this->runner = $runner;
        $this->sockets = $sockets;
        $this->scheduled = $scheduled;
    }

    /**
     * @param int<1, max> $size
     */
    public static function of(
        int $size,
        Runner $runner,
        Sockets $sockets,
    ): self {
        /** @var Sequence<callable(): void> */
        $scheduled = Sequence::of();

        return new self($size, $runner, $sockets, $scheduled);
    }

    public function start(): Either
    {
        return Running\Pool::start(
            $this->size,
            $this->runner,
            $this->sockets,
            $this->scheduled,
        );
    }

    public function schedule(callable $callable): Manager
    {
        return new self(
            $this->size,
            $this->runner,
            $this->sockets,
            ($this->scheduled)($callable),
        );
    }
}
