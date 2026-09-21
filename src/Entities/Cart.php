<?php

namespace Ttpryg\CartEngine\Entities;

use Ttpryg\CartEngine\Contracts\CartInterface;
use Ttpryg\CartEngine\ValueObjects\CartTotals;

class Cart implements CartInterface
{
    public function __construct(private readonly string $id, private int|string|null $userId = null, private array $items = [], private array $conditions = [], private readonly string $currency = 'IDR', private readonly array $metadata = [], private readonly string $status = 'active') {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): int|string|null
    {
        return $this->userId;
    }

    public function setUserId(int|string|null $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getItem(string $key): ?CartItem
    {
        return $this->items[$key] ?? null;
    }

    public function addItem(CartItem $cartItem): self
    {
        $key = $cartItem->getKey();
        if (isset($this->items[$key])) {
            $existing = $this->items[$key];
            $existing->setQuantity($existing->getQuantity() + $cartItem->getQuantity());
        } else {
            $this->items[$key] = $cartItem;
        }

        return $this;
    }

    public function removeItem(string $key): self
    {
        unset($this->items[$key]);

        return $this;
    }

    public function updateQuantity(string $key, float $quantity): self
    {
        if (isset($this->items[$key])) {
            if ($quantity <= 0) {
                unset($this->items[$key]);
            } else {
                $this->items[$key]->setQuantity($quantity);
            }
        }

        return $this;
    }

    public function clear(): self
    {
        $this->items = [];
        $this->conditions = [];

        return $this;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function addCondition(CartCondition $cartCondition): self
    {
        $this->conditions[$cartCondition->getName()] = $cartCondition;

        return $this;
    }

    public function removeCondition(string $name): self
    {
        unset($this->conditions[$name]);

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getSubTotal(): float
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $subtotal += $item->getSubTotal();
        }

        return round($subtotal, 2);
    }

    public function getTotals(): CartTotals
    {
        $subtotal = $this->getSubTotal();
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $feeTotal = 0.0;

        foreach ($this->conditions as $condition) {
            $amount = $condition->calculate($subtotal);
            switch ($condition->getType()) {
                case 'discount':
                    $discountTotal += abs($amount);
                    break;
                case 'tax':
                    $taxTotal += abs($amount);
                    break;
                case 'fee':
                case 'shipping':
                    $feeTotal += abs($amount);
                    break;
            }
        }

        $grandTotal = max(0, $subtotal - $discountTotal + $taxTotal + $feeTotal);

        return new CartTotals(
            subtotal: round($subtotal, 2),
            discountTotal: round($discountTotal, 2),
            taxTotal: round($taxTotal, 2),
            feeTotal: round($feeTotal, 2),
            grandTotal: round($grandTotal, 2),
            currency: $this->currency
        );
    }

    public function toArray(): array
    {
        $itemsArray = [];
        foreach ($this->items as $key => $item) {
            $itemsArray[$key] = $item->toArray();
        }

        $conditionsArray = [];
        foreach ($this->conditions as $name => $cond) {
            $conditionsArray[$name] = $cond->toArray();
        }

        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'currency' => $this->currency,
            'status' => $this->status,
            'items' => $itemsArray,
            'conditions' => $conditionsArray,
            'totals' => $this->getTotals()->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
