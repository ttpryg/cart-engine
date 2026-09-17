<?php

namespace Ttpryg\CartEngine\Events;

use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartItem;

class ItemAddedToCartEvent
{
    public function __construct(
        public readonly Cart $cart,
        public readonly CartItem $item
    ) {}
}
