<?php

namespace Ttpryg\CartEngine\Contracts;

use Ttpryg\CartEngine\Entities\Cart;

interface CartStorageInterface
{
    public function get(string $cartId): ?Cart;
    public function save(Cart $cart): bool;
    public function delete(string $cartId): bool;
    public function exists(string $cartId): bool;
}
