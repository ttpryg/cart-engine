<?php

namespace Ttpryg\CartEngine\Contracts;

interface CartItemInterface
{
    public function getId(): int|string|null;
    public function getItemType(): string;
    public function getItemId(): string|int;
    public function getName(): string;
    public function getUnitPrice(): float;
    public function getQuantity(): float;
    public function getAttributes(): array;
    public function getMetadata(): array;
    public function getSubTotal(): float;
}
