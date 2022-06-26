<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager;

use Innmind\Immutable\{
    Either,
    SideEffect,
};

interface Running
{
    /**
     * @return Either<Process\Failed, SideEffect>
     */
    public function wait(): Either;
    public function kill(): void;
}
