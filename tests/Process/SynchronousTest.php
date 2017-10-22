<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Process;

use Innmind\ProcessManager\{
    Process\Synchronous,
    Process
};
use PHPUnit\Framework\TestCase;

class SynchronousTest extends TestCase
{
    public function testInterface()
    {
        $start = time();
        $process = new Synchronous(static function() {
            sleep(1);
        });

        $this->assertTrue((time() - $start) >= 1);
        $this->assertInstanceOf(Process::class, $process);
        $this->assertFalse($process->running());
        $this->assertNull($process->wait());
        $this->assertNull($process->kill());
    }
}
