<?php

namespace Ttpryg\CartEngine\Entities;

use Ttpryg\CartEngine\Contracts\ConditionInterface;
use Ttpryg\CartEngine\ValueObjects\ConditionValue;

class CartCondition implements ConditionInterface
{
    private string $name;
    private string $type; // discount, tax, fee, shipping
    private string $target; // cart, item
    private ?string $itemId;
    private ConditionValue $value;
    private array $attributes;

    public function __construct(
        string $name,
        string $type,
        string $value,
        string $target = 'cart',
        ?string $itemId = null,
        array $attributes = []
    ) {
        $this->name = $name;
        $this->type = strtolower($type);
        $this->target = strtolower($target);
        $this->itemId = $itemId;
        $this->value = new ConditionValue($value);
        $this->attributes = $attributes;
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
        return $this->value->getValueString();
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function calculate(float $baseAmount): float
    {
        return $this->value->calculate($baseAmount);
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
