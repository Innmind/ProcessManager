<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

interface Process
{
    public function running(): bool;

    /**
     * Wait until the process ends
     */
    public function wait(): void;
    public function kill(): void;
}
