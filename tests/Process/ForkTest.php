<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Process;

use Innmind\ProcessManager\{
    Process\Fork,
    Process,
    Exception\SubProcessFailed
};
use PHPUnit\Framework\TestCase;

class ForkTest extends TestCase
{
    public function testInterface()
    {
        $start = time();
        $process = new Fork(static function() {
            sleep(2);
        });

        $this->assertTrue((time() - $start) < 1);
        $this->assertInstanceOf(Process::class, $process);
        $this->assertTrue(is_int($process->pid()));
        $this->assertTrue($process->pid() > getmypid());
        $this->assertTrue($process->running());
        $this->assertNull($process->wait());
        $this->assertTrue((time() - $start) >= 2);
    }

    public function testThrowWhenCallableFails()
    {
        $process = new Fork($fn = static function() {
            sleep(2);
            throw new \Exception;
        });

        try {
            $process->wait();
            $this->fail('it should throw');
        } catch (SubProcessFailed $e) {
            $this->assertSame($fn, $e->callable());
            $this->assertSame(1, $e->exitCode());
        }
    }

    public function testKill()
    {
        $start = time();
        $process = new Fork(static function() {
            sleep(10);
        });

        $this->assertNull($process->kill());
        $this->assertNull($process->wait());
        $this->assertFalse($process->running());
        $this->assertTrue(time() - $start < 2);
    }
}
