<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Runner;

use Innmind\ProcessManager\{
    Runner\SubProcess,
    Runner,
    Process\Fork
};
use PHPUnit\Framework\TestCase;

class SubProcessTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(Runner::class, new SubProcess);
    }

    public function testInvokation()
    {
        $run = new SubProcess;

        $process = $run($fn = static function() {
            sleep(1);
        });

        $this->assertInstanceOf(Fork::class, $process);
        $this->assertNotSame($process, $run($fn));
    }
}
