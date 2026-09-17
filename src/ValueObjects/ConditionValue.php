<?php

namespace Ttpryg\CartEngine\ValueObjects;

use InvalidArgumentException;

class ConditionValue
{
    private string $value;
    private bool $isPercentage;
    private float $amount;

    public function __construct(string $value)
    {
        $value = trim($value);
        if (empty($value)) {
            throw new InvalidArgumentException("Condition value cannot be empty.");
        }

        $this->value = $value;
        $this->isPercentage = str_ends_with($value, '%');

        $numericString = rtrim($value, '%');
        if (!is_numeric($numericString)) {
            throw new InvalidArgumentException("Invalid condition numeric value: {$value}");
        }

        $this->amount = (float) $numericString;
    }

    public function isPercentage(): bool
    {
        return $this->isPercentage;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function calculate(float $baseAmount): float
    {
        if ($this->isPercentage) {
            return round(($baseAmount * ($this->amount / 100)), 2);
        }

        return $this->amount;
    }

    public function getValueString(): string
    {
        return $this->value;
    }
}
