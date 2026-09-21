<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Entities;

use Ttpryg\CartEngine\Contracts\ConditionInterface;
use Ttpryg\CartEngine\ValueObjects\ConditionValue;

class CartCondition implements ConditionInterface
{
    private readonly string $type; // discount, tax, fee, shipping

    private readonly string $target;

    private readonly ConditionValue $conditionValue;

    public function __construct(
        private readonly string $name,
        string $type,
        string $value,
        string $target = 'cart',
        private readonly ?string $itemId = null,
        private readonly array $attributes = []
    ) {
        $this->type = strtolower($type);
        $this->target = strtolower($target);
        $this->conditionValue = new ConditionValue($value);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getItemId(): ?string
    {
        return $this->itemId;
    }

    public function getValue(): string
    {
        return $this->conditionValue->getValueString();
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function calculate(float $baseAmount): float
    {
        return $this->conditionValue->calculate($baseAmount);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'target' => $this->target,
            'item_id' => $this->itemId,
            'value' => $this->getValue(),
            'attributes' => $this->attributes,
        ];
    }
}
