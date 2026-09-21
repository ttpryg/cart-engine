<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Contracts;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}
