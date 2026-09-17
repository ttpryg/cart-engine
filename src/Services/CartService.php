<?php

namespace Ttpryg\CartEngine\Services;

use Ttpryg\CartEngine\Contracts\CartStorageInterface;
use Ttpryg\CartEngine\Contracts\EventDispatcherInterface;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartCondition;
use Ttpryg\CartEngine\Entities\CartItem;
use Ttpryg\CartEngine\Events\CartClearedEvent;
use Ttpryg\CartEngine\Events\ConditionAppliedEvent;
use Ttpryg\CartEngine\Events\ItemAddedToCartEvent;
use Ttpryg\CartEngine\Events\ItemRemovedFromCartEvent;
use Ttpryg\CartEngine\ValueObjects\CartTotals;

class CartService
{
    public function __construct(
        private CartStorageInterface $storage,
        private ?EventDispatcherInterface $eventDispatcher = null
    ) {}

    public function getCart(string $cartId, int|string|null $userId = null, string $currency = 'IDR'): Cart
    {
        $cart = $this->storage->get($cartId);
        if (!$cart) {
            $cart = new Cart(id: $cartId, userId: $userId, currency: $currency);
            $this->storage->save($cart);
        }

        return $cart;
    }

    public function addItem(
        string $cartId,
        string $itemType,
        string|int $itemId,
        string $name,
        float $unitPrice,
        float $quantity = 1.0,
        array $attributes = [],
        array $metadata = [],
        int|string|null $userId = null
    ): CartItem {
        $cart = $this->getCart($cartId, $userId);

        $item = new CartItem(
            itemType: $itemType,
            itemId: $itemId,
            name: $name,
            unitPrice: $unitPrice,
            quantity: $quantity,
            attributes: $attributes,
            metadata: $metadata
        );

        $cart->addItem($item);
        $this->storage->save($cart);

        $this->eventDispatcher?->dispatch(new ItemAddedToCartEvent($cart, $item));

        return $item;
    }

    public function updateQuantity(string $cartId, string $itemKey, float $quantity): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->updateQuantity($itemKey, $quantity);

        $this->storage->save($cart);
        return $cart;
    }

    public function removeItem(string $cartId, string $itemKey): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->removeItem($itemKey);

        $this->storage->save($cart);
        $this->eventDispatcher?->dispatch(new ItemRemovedFromCartEvent($cart, $itemKey));

        return $cart;
    }

    public function applyCondition(string $cartId, CartCondition $condition): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->addCondition($condition);

        $this->storage->save($cart);
        $this->eventDispatcher?->dispatch(new ConditionAppliedEvent($cart, $condition));

        return $cart;
    }

    public function removeCondition(string $cartId, string $conditionName): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->removeCondition($conditionName);

        $this->storage->save($cart);
        return $cart;
    }

    public function clearCart(string $cartId): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->clear();

        $this->storage->save($cart);
        $this->eventDispatcher?->dispatch(new CartClearedEvent($cart));

        return $cart;
    }

    public function getTotals(string $cartId): CartTotals
    {
        $cart = $this->getCart($cartId);
        return $cart->getTotals();
    }

    public function checkout(string $cartId): array
    {
        $cart = $this->getCart($cartId);
        $snapshot = $cart->toArray();

        // Optionally clear or mark cart
        $this->storage->delete($cartId);

        return $snapshot;
    }
}
