<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

interface Process
{
    public function running(): bool;
}
