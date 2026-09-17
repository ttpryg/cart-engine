<?php

namespace Ttpryg\CartEngine\Events;

use Ttpryg\CartEngine\Entities\Cart;

class ItemRemovedFromCartEvent
{
    public function __construct(
        public readonly Cart $cart,
        public readonly string $itemKey
    ) {}
}
