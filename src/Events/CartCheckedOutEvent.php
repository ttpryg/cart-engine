<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Events;

use Ttpryg\CartEngine\Entities\Cart;

class CartCheckedOutEvent
{
    public function __construct(
        public readonly Cart $cart,
        public readonly array $snapshot
    ) {}
}
