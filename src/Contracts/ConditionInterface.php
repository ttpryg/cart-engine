<?php

namespace Ttpryg\CartEngine\Contracts;

interface ConditionInterface
{
    public function getName(): string;
    public function getType(): string; // discount, tax, fee, shipping
    public function getTarget(): string; // cart, item
    public function getValue(): string; // -10%, +11%, -50000, +15000
    public function calculate(float $baseAmount): float;
}
