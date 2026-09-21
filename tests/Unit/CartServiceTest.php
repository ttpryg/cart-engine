<?php

namespace Ttpryg\CartEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\Contracts\EventDispatcherInterface;
use Ttpryg\CartEngine\Entities\CartCondition;
use Ttpryg\CartEngine\Events\ConditionAppliedEvent;
use Ttpryg\CartEngine\Events\ItemAddedToCartEvent;
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
}
