<?php

namespace Ttpryg\CartEngine\Services;

use Ttpryg\CartEngine\Contracts\CartStorageInterface;
use Ttpryg\CartEngine\Contracts\EventDispatcherInterface;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartCondition;
use Ttpryg\CartEngine\Entities\CartItem;
use Ttpryg\CartEngine\Events\CartClearedEvent;
use Ttpryg\CartEngine\Events\CartCheckedOutEvent;
use Ttpryg\CartEngine\Events\ConditionAppliedEvent;
use Ttpryg\CartEngine\Events\ItemAddedToCartEvent;
use Ttpryg\CartEngine\Events\ItemRemovedFromCartEvent;
use Ttpryg\CartEngine\Exceptions\CartNotFoundException;
use Ttpryg\CartEngine\ValueObjects\CartTotals;

class CartService
{
    public function __construct(
        private readonly CartStorageInterface $cartStorage,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    ) {}

    public function getCart(string $cartId, int|string|null $userId = null, string $currency = 'IDR'): Cart
    {
        $cart = $this->cartStorage->get($cartId);
        if (! $cart instanceof Cart) {
            $cart = new Cart(id: $cartId, userId: $userId, currency: $currency);
            $this->cartStorage->save($cart);
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

        $cartItem = new CartItem(
            itemType: $itemType,
            itemId: $itemId,
            name: $name,
            unitPrice: $unitPrice,
            quantity: $quantity,
            attributes: $attributes,
            metadata: $metadata
        );

        $cart->addItem($cartItem);
        $this->cartStorage->save($cart);

        $this->eventDispatcher?->dispatch(new ItemAddedToCartEvent($cart, $cartItem));

        return $cartItem;
    }

    public function updateQuantity(string $cartId, string $itemKey, float $quantity): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->updateQuantity($itemKey, $quantity);

        $this->cartStorage->save($cart);

        return $cart;
    }

    public function removeItem(string $cartId, string $itemKey): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->removeItem($itemKey);

        $this->cartStorage->save($cart);
        $this->eventDispatcher?->dispatch(new ItemRemovedFromCartEvent($cart, $itemKey));

        return $cart;
    }

    public function applyCondition(string $cartId, CartCondition $cartCondition): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->addCondition($cartCondition);

        $this->cartStorage->save($cart);
        $this->eventDispatcher?->dispatch(new ConditionAppliedEvent($cart, $cartCondition));

        return $cart;
    }

    public function removeCondition(string $cartId, string $conditionName): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->removeCondition($conditionName);

        $this->cartStorage->save($cart);

        return $cart;
    }

    public function clearCart(string $cartId): Cart
    {
        $cart = $this->getCart($cartId);
        $cart->clear();

        $this->cartStorage->save($cart);
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
        $cart = $this->cartStorage->get($cartId);
        if (! $cart instanceof Cart) {
            throw CartNotFoundException::byId($cartId);
        }

        // Idempotent: an already completed cart returns the same snapshot
        // without dispatching a second checkout event.
        if ($cart->getStatus() !== Cart::STATUS_COMPLETED) {
            $cart->setStatus(Cart::STATUS_COMPLETED);
            $this->cartStorage->save($cart);

            $snapshot = $cart->toArray();
            $this->eventDispatcher?->dispatch(new CartCheckedOutEvent($cart, $snapshot));
        } else {
            $snapshot = $cart->toArray();
        }

        return $snapshot;
    }

    public function deleteCart(string $cartId): bool
    {
        return $this->cartStorage->delete($cartId);
    }
}
