<?php

namespace Ttpryg\CartEngine\Contracts;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}
