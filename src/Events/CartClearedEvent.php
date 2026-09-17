<?php

namespace Ttpryg\CartEngine\Events;

use Ttpryg\CartEngine\Entities\Cart;

class CartClearedEvent
{
    public function __construct(
        public readonly Cart $cart
    ) {}
}
