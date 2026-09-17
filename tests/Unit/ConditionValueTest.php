<?php

namespace Ttpryg\CartEngine\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\ValueObjects\ConditionValue;

class ConditionValueTest extends TestCase
{
    // POSITIVE CASE: Percentage Calculation
    public function testPercentageConditionCalculation(): void
    {
        $cond = new ConditionValue('-10%');
        $this->assertTrue($cond->isPercentage());
        $this->assertEquals(-10.0, $cond->getAmount());
        $this->assertEquals(-15000.0, $cond->calculate(150000.0));
    }

    // POSITIVE CASE: Fixed Amount Calculation
    public function testFixedAmountConditionCalculation(): void
    {
        $cond = new ConditionValue('-50000');
        $this->assertFalse($cond->isPercentage());
        $this->assertEquals(-50000.0, $cond->getAmount());
        $this->assertEquals(-50000.0, $cond->calculate(200000.0));
    }

    // NEGATIVE CASE: Invalid Numeric Value Throws Exception
    public function testInvalidNumericValueThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConditionValue('INVALID_VALUE');
    }
}
