<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

interface Running
{
    public function wait(): void;
    public function kill(): void;
}
