<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartItem;

class CartStatusTest extends TestCase
{
    public function test_default_status_is_active(): void
    {
        $cart = new Cart(id: 'status_1');

        $this->assertSame(Cart::STATUS_ACTIVE, $cart->getStatus());
    }

    public function test_status_can_be_changed_to_valid_values(): void
    {
        $cart = new Cart(id: 'status_2');

        $cart->setStatus(Cart::STATUS_COMPLETED);
        $this->assertSame(Cart::STATUS_COMPLETED, $cart->getStatus());

        $cart->setStatus(Cart::STATUS_ABANDONED);
        $this->assertSame(Cart::STATUS_ABANDONED, $cart->getStatus());
    }

    public function test_invalid_status_throws_exception(): void
    {
        $cart = new Cart(id: 'status_3');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid cart status 'paid'");

        $cart->setStatus('paid');
    }

    public function test_invalid_status_in_constructor_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cart(id: 'status_4', status: 'bogus');
    }

    public function test_any_mutation_reactivates_a_completed_cart(): void
    {
        $cart = new Cart(id: 'status_5');
        $cart->setStatus(Cart::STATUS_COMPLETED);

        $cart->addItem(new CartItem(itemType: 'product', itemId: 1, name: 'Item', unitPrice: 1000.0));
        $this->assertSame(Cart::STATUS_ACTIVE, $cart->getStatus());

        $cart->setStatus(Cart::STATUS_COMPLETED);
        $cart->updateQuantity(array_key_first($cart->getItems()), 5.0);
        $this->assertSame(Cart::STATUS_ACTIVE, $cart->getStatus());

        $cart->setStatus(Cart::STATUS_COMPLETED);
        $cart->clear();
        $this->assertSame(Cart::STATUS_ACTIVE, $cart->getStatus());
    }

    public function test_removing_unknown_item_does_not_reactivate_completed_cart(): void
    {
        $cart = new Cart(id: 'status_6');
        $cart->setStatus(Cart::STATUS_COMPLETED);

        $cart->removeItem('nonexistent_key');

        $this->assertSame(Cart::STATUS_COMPLETED, $cart->getStatus());
    }
}
