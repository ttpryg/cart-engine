<?php

namespace Ttpryg\CartEngine\Storage;

use Ttpryg\CartEngine\Contracts\CartStorageInterface;
use Ttpryg\CartEngine\Entities\Cart;

class MemoryCartStorage implements CartStorageInterface
{
    private array $carts = [];

    public function get(string $cartId): ?Cart
    {
        return $this->carts[$cartId] ?? null;
    }

    public function save(Cart $cart): bool
    {
        $this->carts[$cart->getId()] = $cart;
        return true;
    }

    public function delete(string $cartId): bool
    {
        unset($this->carts[$cartId]);
        return true;
    }

    public function exists(string $cartId): bool
    {
        return isset($this->carts[$cartId]);
    }
}
