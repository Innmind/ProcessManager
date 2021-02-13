<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

interface Manager
{
    public function __invoke(): self;
    public function schedule(callable $callable): self;
    public function wait(): void;
    public function kill(): void;
}
