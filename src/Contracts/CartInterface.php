<?php

namespace Ttpryg\CartEngine\Contracts;

use Ttpryg\CartEngine\ValueObjects\CartTotals;

interface CartInterface
{
    public function getId(): string;
    public function getUserId(): int|string|null;
    public function getItems(): array;
    public function getConditions(): array;
    public function getCurrency(): string;
    public function getSubTotal(): float;
    public function getTotals(): CartTotals;
}
