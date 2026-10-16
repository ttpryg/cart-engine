<?php

namespace Ttpryg\CartEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\Contracts\EventDispatcherInterface;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartCondition;
use Ttpryg\CartEngine\Events\CartCheckedOutEvent;
use Ttpryg\CartEngine\Events\ConditionAppliedEvent;
use Ttpryg\CartEngine\Events\ItemAddedToCartEvent;
use Ttpryg\CartEngine\Exceptions\CartNotFoundException;
use Ttpryg\CartEngine\Services\CartService;
use Ttpryg\CartEngine\Storage\MemoryCartStorage;

class CartServiceTest extends TestCase
{
    private MemoryCartStorage $memoryCartStorage;

    private CartService $cartService;

    protected function setUp(): void
    {
        $this->memoryCartStorage = new MemoryCartStorage;
        $this->cartService = new CartService($this->memoryCartStorage);
    }

    // POSITIVE CASE: Shopping E-Commerce Products (Item-Agnostic)
    public function test_add_shopping_products_and_calculate_totals(): void
    {
        $cartId = 'user_session_1';

        // Add Product Item 1
        $this->cartService->addItem($cartId, 'product', 101, 'Kemeja Formal', 200000.0, 2.0, ['color' => 'Blue']);
        // Add Product Item 2
        $this->cartService->addItem($cartId, 'product', 102, 'Celana Chino', 150000.0, 1.0);

        // Apply Voucher Condition (-10%)
        $this->cartService->applyCondition($cartId, new CartCondition('VOUCHER10', 'discount', '-10%'));

        $cartTotals = $this->cartService->getTotals($cartId);

        // Subtotal = (200,000 * 2) + 150,000 = 550,000
        // Discount = 10% of 550,000 = 55,000
        // GrandTotal = 495,000
        $this->assertEquals(550000.0, $cartTotals->subtotal);
        $this->assertEquals(55000.0, $cartTotals->discountTotal);
        $this->assertEquals(495000.0, $cartTotals->grandTotal);
    }

    // POSITIVE CASE: Booking / Reservation (Decimal Quantity for Nights/Hours)
    public function test_booking_reservation_flow(): void
    {
        $cartId = 'booking_sess_99';

        // Add Hotel Booking Item (3.5 days / nights, price 400,000 per night)
        $this->cartService->addItem(
            cartId: $cartId,
            itemType: 'hotel_room',
            itemId: 'deluxe_suite',
            name: 'Deluxe Suite Sea View',
            unitPrice: 400000.0,
            quantity: 3.5,
            attributes: ['check_in' => '2026-11-10 14:00', 'check_out' => '2026-11-14 02:00']
        );

        // Apply Tax (+11% PPN)
        $this->cartService->applyCondition($cartId, new CartCondition('PPN 11%', 'tax', '+11%'));

        $cartTotals = $this->cartService->getTotals($cartId);

        // Subtotal = 400,000 * 3.5 = 1,400,000
        // Tax = 11% of 1,400,000 = 154,000
        // GrandTotal = 1,554,000
        $this->assertEquals(1400000.0, $cartTotals->subtotal);
        $this->assertEquals(154000.0, $cartTotals->taxTotal);
        $this->assertEquals(1554000.0, $cartTotals->grandTotal);
    }

    // POSITIVE CASE: Events Dispatched on Action
    public function test_events_dispatched_on_item_added_and_condition_applied(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event): void {
                $this->assertTrue(
                    $event instanceof ItemAddedToCartEvent || $event instanceof ConditionAppliedEvent
                );
            });

        $cartService = new CartService($this->memoryCartStorage, $dispatcher);
        $cartService->addItem('cart_evt', 'service', 1, 'Cleaning Service', 100000.0);
        $cartService->applyCondition('cart_evt', new CartCondition('SERVICE_FEE', 'fee', '+10000'));
    }

    // POSITIVE CASE: Checkout marks cart completed and returns a snapshot
    public function test_checkout_marks_cart_completed_and_keeps_it_in_storage(): void
    {
        $this->cartService->addItem('co_1', 'product', 101, 'Sepatu', 250000.0);

        $snapshot = $this->cartService->checkout('co_1');

        $this->assertSame('completed', $snapshot['status']);
        $this->assertEquals(250000.0, $snapshot['totals']['grand_total']);

        // Cart is retained (not deleted) so history/audit remains possible
        $storedCart = $this->memoryCartStorage->get('co_1');
        $this->assertNotNull($storedCart);
        $this->assertSame(Cart::STATUS_COMPLETED, $storedCart->getStatus());
    }

    // POSITIVE CASE: Checkout is idempotent — one event, stable snapshot
    public function test_checkout_is_idempotent_and_dispatches_single_event(): void
    {
        $checkoutEvents = 0;
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$checkoutEvents): void {
                // addItem dispatches ItemAddedToCartEvent, checkout dispatches CartCheckedOutEvent
                if ($event instanceof CartCheckedOutEvent) {
                    $checkoutEvents++;
                }
            });

        $cartService = new CartService($this->memoryCartStorage, $dispatcher);
        $cartService->addItem('co_2', 'product', 101, 'Sepatu', 250000.0);

        $first = $cartService->checkout('co_2');
        $second = $cartService->checkout('co_2');

        $this->assertSame(1, $checkoutEvents);
        $this->assertEquals($first, $second);
        $this->assertSame('completed', $second['status']);
    }

    // NEGATIVE CASE: Checkout on a missing cart
    public function test_checkout_throws_when_cart_does_not_exist(): void
    {
        $this->expectException(CartNotFoundException::class);

        $this->cartService->checkout('missing_cart');
    }

    // POSITIVE CASE: Adding an item after checkout reactivates the cart
    public function test_adding_item_after_checkout_reactivates_cart(): void
    {
        $this->cartService->addItem('co_3', 'product', 101, 'Sepatu', 100000.0);
        $this->cartService->checkout('co_3');
        $this->assertSame(Cart::STATUS_COMPLETED, $this->cartService->getCart('co_3')->getStatus());

        $this->cartService->addItem('co_3', 'product', 102, 'Tas', 50000.0);

        $this->assertSame(Cart::STATUS_ACTIVE, $this->cartService->getCart('co_3')->getStatus());
    }

    // POSITIVE CASE: deleteCart removes the cart from storage
    public function test_delete_cart_removes_cart_from_storage(): void
    {
        $this->cartService->addItem('del_1', 'product', 101, 'Sepatu', 100000.0);
        $this->assertTrue($this->memoryCartStorage->exists('del_1'));

        $this->assertTrue($this->cartService->deleteCart('del_1'));
        $this->assertFalse($this->memoryCartStorage->exists('del_1'));
        $this->assertNull($this->memoryCartStorage->get('del_1'));
    }

    // POSITIVE CASE: Per-item condition through the service layer
    public function test_item_level_discount_through_service_layer(): void
    {
        $this->cartService->addItem('svc_item', 'product', 101, 'Kemeja', 100000.0, 2.0);
        $this->cartService->addItem('svc_item', 'product', 102, 'Celana', 50000.0, 1.0);

        $this->cartService->applyCondition(
            'svc_item',
            new CartCondition('KEMEJA10', 'discount', '-10%', target: 'item', itemId: '101')
        );

        $cartTotals = $this->cartService->getTotals('svc_item');
        $this->assertEquals(20000.0, $cartTotals->discountTotal);
        $this->assertEquals(230000.0, $cartTotals->grandTotal);
    }

    // POSITIVE CASE: updateQuantity and clearCart flows
    public function test_update_quantity_and_clear_cart(): void
    {
        $cartItem = $this->cartService->addItem('qty_1', 'product', 101, 'Kemeja', 100000.0, 2.0);
        $key = $cartItem->getKey();

        $this->cartService->updateQuantity('qty_1', $key, 3.0);
        $this->assertEquals(300000.0, $this->cartService->getTotals('qty_1')->subtotal);

        // Quantity of zero removes the item
        $this->cartService->updateQuantity('qty_1', $key, 0.0);
        $this->assertCount(0, $this->memoryCartStorage->get('qty_1')?->getItems() ?? []);

        $this->cartService->addItem('qty_1', 'product', 101, 'Kemeja', 100000.0);
        $this->cartService->applyCondition('qty_1', new CartCondition('X', 'fee', '+5000'));
        $cart = $this->cartService->clearCart('qty_1');
        $this->assertCount(0, $cart->getItems());
        $this->assertCount(0, $cart->getConditions());
    }
}
