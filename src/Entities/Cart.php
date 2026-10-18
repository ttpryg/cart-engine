<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Entities;

use Ttpryg\CartEngine\Contracts\CartInterface;
use Ttpryg\CartEngine\ValueObjects\CartTotals;

class Cart implements CartInterface
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABANDONED = 'abandoned';

    public function __construct(private readonly string $id, private int|string|null $userId = null, private array $items = [], private array $conditions = [], private readonly string $currency = 'IDR', private readonly array $metadata = [], private string $status = self::STATUS_ACTIVE)
    {
        $this->assertValidStatus($status);
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @phpstan-impure Status can change through mutations (addItem, setStatus, ...).
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->assertValidStatus($status);
        $this->status = $status;

        return $this;
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

        $this->markActive();

        return $this;
    }

    public function removeItem(string $key): self
    {
        if (isset($this->items[$key])) {
            unset($this->items[$key]);
            $this->markActive();
        }

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

            $this->markActive();
        }

        return $this;
    }

    public function clear(): self
    {
        $this->items = [];
        $this->conditions = [];
        $this->markActive();

        return $this;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function addCondition(CartCondition $cartCondition): self
    {
        $this->conditions[$cartCondition->getName()] = $cartCondition;
        $this->markActive();

        return $this;
    }

    public function removeCondition(string $name): self
    {
        if (isset($this->conditions[$name])) {
            unset($this->conditions[$name]);
            $this->markActive();
        }

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

        [$discountTotal, $itemDiscounts] = $this->calculateDiscounts($subtotal);
        [$taxTotal, $feeTotal] = $this->calculateTaxesAndFees($subtotal, $discountTotal, $itemDiscounts);

        $grandTotal = max(0.0, $subtotal - $discountTotal + $taxTotal + $feeTotal);

        return new CartTotals(
            subtotal: round($subtotal, 2),
            discountTotal: round($discountTotal, 2),
            taxTotal: round($taxTotal, 2),
            feeTotal: round($feeTotal, 2),
            grandTotal: round($grandTotal, 2),
            currency: $this->currency
        );
    }

    /**
     * Discount always applies first: cart-level on the cart subtotal,
     * item-level on each matching item's subtotal.
     *
     * @return array{0: float, 1: array<string, float>} [total discount, discount per item key]
     */
    private function calculateDiscounts(float $subtotal): array
    {
        $discountTotal = 0.0;
        $itemDiscounts = [];

        foreach ($this->conditions as $condition) {
            if ($condition->getType() !== 'discount') {
                continue;
            }

            if ($condition->getTarget() === 'item') {
                foreach ($this->matchingItems($condition) as $key => $item) {
                    $amount = abs($condition->calculate($item->getSubTotal()));
                    $itemDiscounts[$key] = ($itemDiscounts[$key] ?? 0.0) + $amount;
                    $discountTotal += $amount;
                }

                continue;
            }

            $discountTotal += abs($condition->calculate($subtotal));
        }

        return [$discountTotal, $itemDiscounts];
    }

    /**
     * Tax applies on the discounted base (cart-level discount is pro-rated
     * across items for item-level taxes). Fees and shipping keep their
     * natural base (subtotal / item subtotal).
     *
     * @param  array<string, float>  $itemDiscounts
     */
    private function calculateTaxesAndFees(float $subtotal, float $discountTotal, array $itemDiscounts): array
    {
        $itemDiscountTotal = array_sum($itemDiscounts);
        $cartDiscount = max(0.0, $discountTotal - $itemDiscountTotal);

        $taxTotal = 0.0;
        $feeTotal = 0.0;

        foreach ($this->conditions as $condition) {
            $type = $condition->getType();

            if (! in_array($type, ['tax', 'fee', 'shipping'], strict: true)) {
                continue;
            }

            if ($condition->getTarget() === 'item') {
                foreach ($this->matchingItems($condition) as $key => $item) {
                    $base = $type === 'tax'
                        ? $this->itemTaxBase($item->getSubTotal(), $subtotal, $itemDiscounts[$key] ?? 0.0, $cartDiscount)
                        : $item->getSubTotal();

                    $amount = abs($condition->calculate($base));

                    if ($type === 'tax') {
                        $taxTotal += $amount;
                    } else {
                        $feeTotal += $amount;
                    }
                }

                continue;
            }

            $base = $type === 'tax' ? max(0.0, $subtotal - $discountTotal) : $subtotal;
            $amount = abs($condition->calculate($base));

            if ($type === 'tax') {
                $taxTotal += $amount;
            } else {
                $feeTotal += $amount;
            }
        }

        return [$taxTotal, $feeTotal];
    }

    /**
     * Item tax base: item subtotal minus its own discounts and its
     * pro-rated share of cart-level discounts.
     */
    private function itemTaxBase(float $itemSubtotal, float $subtotal, float $itemDiscount, float $cartDiscount): float
    {
        $proRata = $subtotal > 0.0 ? $cartDiscount * ($itemSubtotal / $subtotal) : 0.0;

        return max(0.0, $itemSubtotal - $itemDiscount - $proRata);
    }

    /**
     * Items matched by an item-level condition: a condition without
     * item_id applies to every item, otherwise it must match the item's
     * source id, key, or persisted id.
     *
     * @return array<string, CartItem>
     */
    private function matchingItems(CartCondition $cartCondition): array
    {
        $conditionItemId = $cartCondition->getItemId();
        if ($conditionItemId === null) {
            return $this->items;
        }

        $matches = [];
        foreach ($this->items as $key => $item) {
            $matchesItem = $conditionItemId === (string) $item->getItemId()
                || $conditionItemId === $key
                || ($item->getId() !== null && $conditionItemId === (string) $item->getId());

            if ($matchesItem) {
                $matches[$key] = $item;
            }
        }

        return $matches;
    }

    private function markActive(): void
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            $this->status = self::STATUS_ACTIVE;
        }
    }

    private function assertValidStatus(string $status): void
    {
        if (! in_array($status, [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_ABANDONED], strict: true)) {
            throw new \InvalidArgumentException(
                "Invalid cart status '{$status}'. Allowed: active, completed, abandoned."
            );
        }
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
