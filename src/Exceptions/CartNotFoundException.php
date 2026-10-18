<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Exceptions;

class CartNotFoundException extends CartEngineException
{
    public static function byId(string $id): self
    {
        return new self("Cart with ID '{$id}' was not found.");
    }
}
