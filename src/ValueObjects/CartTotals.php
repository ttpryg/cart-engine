<?php

namespace Ttpryg\CartEngine\ValueObjects;

class CartTotals
{
    public function __construct(
        public readonly float $subtotal,
        public readonly float $discountTotal,
        public readonly float $taxTotal,
        public readonly float $feeTotal,
        public readonly float $grandTotal,
        public readonly string $currency = 'IDR'
    ) {}

    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discountTotal,
            'tax_total' => $this->taxTotal,
            'fee_total' => $this->feeTotal,
            'grand_total' => $this->grandTotal,
            'currency' => $this->currency,
        ];
    }
}
