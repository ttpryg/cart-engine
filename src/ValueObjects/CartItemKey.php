<?php

namespace Ttpryg\CartEngine\ValueObjects;

class CartItemKey
{
    public static function generate(string $itemType, string|int $itemId, array $attributes = []): string
    {
        ksort($attributes);
        return md5($itemType . ':' . $itemId . ':' . json_encode($attributes));
    }
}
