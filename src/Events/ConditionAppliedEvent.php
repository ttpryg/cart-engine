<?php

namespace Ttpryg\CartEngine\Events;

use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartCondition;

class ConditionAppliedEvent
{
    public function __construct(
        public readonly Cart $cart,
        public readonly CartCondition $condition
    ) {}
}
