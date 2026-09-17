<?php

namespace Ttpryg\CartEngine\Exceptions;

class CartItemNotFoundException extends CartEngineException
{
    public static function byKey(string $key): self
    {
        return new self("Cart item with key '{$key}' was not found in the cart.");
    }
}
