<?php
declare(strict_types = 1);

namespace Innmind\ProcessManager\Manager;

use Innmind\ProcessManager\{
    Manager,
    Running,
    Process,
};
use Innmind\Immutable\Either;

final class KillOnError implements Manager
{
    private Manager $manager;

    private function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public static function of(Manager $manager): self
    {
        return new self($manager);
    }

    public function start(): Either
    {
        /** @var Either<Process\InitFailed, Running> */
        return $this->manager->start()->map(Running\KillOnError::of(...));
    }

    public function schedule(callable $callable): Manager
    {
        return new self($this->manager->schedule($callable));
    }
}
