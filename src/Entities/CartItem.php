<?php

namespace Ttpryg\CartEngine\Entities;

use Ttpryg\CartEngine\Contracts\CartItemInterface;
use Ttpryg\CartEngine\ValueObjects\CartItemKey;

class CartItem implements CartItemInterface
{
    private readonly string $key;

    public function __construct(
        private readonly string $itemType,
        private readonly string|int $itemId,
        private string $name,
        private float $unitPrice,
        private float $quantity = 1.0,
        private readonly array $attributes = [],
        private readonly array $metadata = [],
        private int|string|null $id = null
    ) {
        $this->key = CartItemKey::generate($this->itemType, $this->itemId, $this->attributes);
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function setId(int|string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function getItemId(): string|int
    {
        return $this->itemId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSubTotal(): float
    {
        return round($this->unitPrice * $this->quantity, 2);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'item_type' => $this->itemType,
            'item_id' => $this->itemId,
            'name' => $this->name,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'attributes' => $this->attributes,
            'metadata' => $this->metadata,
            'subtotal' => $this->getSubTotal(),
        ];
    }
}
