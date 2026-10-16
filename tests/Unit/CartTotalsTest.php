<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartCondition;
use Ttpryg\CartEngine\Entities\CartItem;

class CartTotalsTest extends TestCase
{
    private function createTwoItemCart(string $id = 'totals_cart'): Cart
    {
        $cart = new Cart(id: $id);
        $cart->addItem(new CartItem(itemType: 'product', itemId: 101, name: 'Kemeja', unitPrice: 100000.0, quantity: 2.0));
        $cart->addItem(new CartItem(itemType: 'product', itemId: 102, name: 'Celana', unitPrice: 50000.0, quantity: 1.0));

        return $cart;
    }

    public function test_tax_is_calculated_after_cart_discount(): void
    {
        $cart = $this->createTwoItemCart();
        $cart->addCondition(new CartCondition('DISC', 'discount', '-10%'));
        $cart->addCondition(new CartCondition('PPN', 'tax', '+11%'));

        $totals = $cart->getTotals();

        $this->assertEquals(250000.0, $totals->subtotal);
        $this->assertEquals(25000.0, $totals->discountTotal);
        // 11% of (250,000 - 25,000) = 24,750
        $this->assertEquals(24750.0, $totals->taxTotal);
        $this->assertEquals(249750.0, $totals->grandTotal);
    }

    public function test_item_level_discount_only_applies_to_matching_item(): void
    {
        $cart = $this->createTwoItemCart();
        $cart->addCondition(new CartCondition('SHOE10', 'discount', '-10%', target: 'item', itemId: '101'));

        $totals = $cart->getTotals();

        // 10% of item 101 only (200,000)
        $this->assertEquals(20000.0, $totals->discountTotal);
        $this->assertEquals(230000.0, $totals->grandTotal);
    }

    public function test_item_level_discount_without_item_id_applies_to_all_items(): void
    {
        $cart = $this->createTwoItemCart();
        $cart->addCondition(new CartCondition('ALL10', 'discount', '-10%', target: 'item'));

        $totals = $cart->getTotals();

        // 10% of 200,000 + 10% of 50,000
        $this->assertEquals(25000.0, $totals->discountTotal);
        $this->assertEquals(225000.0, $totals->grandTotal);
    }

    public function test_item_level_discount_matches_by_item_key(): void
    {
        $cart = $this->createTwoItemCart();
        $key = array_key_first($cart->getItems());
        $cart->addCondition(new CartCondition('KEY', 'discount', '-5000', target: 'item', itemId: $key));

        $this->assertEquals(5000.0, $cart->getTotals()->discountTotal);
    }

    public function test_item_level_discount_matches_item_id_as_string(): void
    {
        $cart = $this->createTwoItemCart();
        $cart->addCondition(new CartCondition('STRID', 'discount', '-1000', target: 'item', itemId: '101'));

        $this->assertEquals(1000.0, $cart->getTotals()->discountTotal);
    }

    public function test_item_level_tax_uses_discounted_pro_rated_base(): void
    {
        $cart = $this->createTwoItemCart();
        $cart->addCondition(new CartCondition('DISC', 'discount', '-10%'));
        $cart->addCondition(new CartCondition('PPN', 'tax', '+11%', target: 'item', itemId: '101'));

        $totals = $cart->getTotals();

        $this->assertEquals(25000.0, $totals->discountTotal);
        // Item 101 base: 200,000 - pro-rata cart discount (25,000 * 200k/250k = 20,000) = 180,000
        // 11% of 180,000 = 19,800
        $this->assertEquals(19800.0, $totals->taxTotal);
        $this->assertEquals(244800.0, $totals->grandTotal);
    }

    public function test_fee_and_shipping_use_subtotal_as_base(): void
    {
        $cart = $this->createTwoItemCart();
        $cart->addCondition(new CartCondition('DISC', 'discount', '-10%'));
        $cart->addCondition(new CartCondition('SERVICE', 'fee', '+10%'));

        $totals = $cart->getTotals();

        // Fee is 10% of the raw subtotal, not the discounted amount
        $this->assertEquals(25000.0, $totals->feeTotal);
        $this->assertEquals(250000.0, $totals->grandTotal);
    }

    public function test_grand_total_never_goes_negative(): void
    {
        $cart = new Cart(id: 'clamp_cart');
        $cart->addItem(new CartItem(itemType: 'product', itemId: 1, name: 'Item', unitPrice: 10000.0, quantity: 1.0));
        $cart->addCondition(new CartCondition('BIG', 'discount', '-50000'));

        $totals = $cart->getTotals();

        $this->assertEquals(50000.0, $totals->discountTotal);
        $this->assertEquals(0.0, $totals->grandTotal);
    }
}
