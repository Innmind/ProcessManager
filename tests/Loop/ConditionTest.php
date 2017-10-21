<?php
declare(strict_types = 1);

namespace Tests\Innmind\ProcessManager\Loop;

use Innmind\ProcessManager\Loop\Condition;
use Innmind\Socket\Loop\Strategy;
use PHPUnit\Framework\TestCase;

class ConditionTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            Strategy::class,
            new Condition(function(){})
        );
    }

    public function testInvokation()
    {
        $condition = new Condition(function() {
            return true;
        });

        $this->assertTrue($condition());
        $this->assertTrue($condition());

        $condition = new Condition(function() {
            return false;
        });

        $this->assertFalse($condition());
        $this->assertFalse($condition());
    }
}
