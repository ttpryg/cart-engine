<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\ValueObjects\ConditionValue;

class ConditionValueTest extends TestCase
{
    // POSITIVE CASE: Percentage Calculation
    public function test_percentage_condition_calculation(): void
    {
        $conditionValue = new ConditionValue('-10%');
        $this->assertTrue($conditionValue->isPercentage());
        $this->assertEquals(-10.0, $conditionValue->getAmount());
        $this->assertEquals(-15000.0, $conditionValue->calculate(150000.0));
    }

    // POSITIVE CASE: Fixed Amount Calculation
    public function test_fixed_amount_condition_calculation(): void
    {
        $conditionValue = new ConditionValue('-50000');
        $this->assertFalse($conditionValue->isPercentage());
        $this->assertEquals(-50000.0, $conditionValue->getAmount());
        $this->assertEquals(-50000.0, $conditionValue->calculate(200000.0));
    }

    // NEGATIVE CASE: Invalid Numeric Value Throws Exception
    public function test_invalid_numeric_value_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ConditionValue('INVALID_VALUE');
    }
}
