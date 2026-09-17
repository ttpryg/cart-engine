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
    private MemoryCartStorage $storage;
    private CartService $service;

    protected function setUp(): void
    {
        $this->storage = new MemoryCartStorage();
        $this->service = new CartService($this->storage);
    }

    // POSITIVE CASE: Shopping E-Commerce Products (Item-Agnostic)
    public function testAddShoppingProductsAndCalculateTotals(): void
    {
        $cartId = 'user_session_1';

        // Add Product Item 1
        $this->service->addItem($cartId, 'product', 101, 'Kemeja Formal', 200000.0, 2.0, ['color' => 'Blue']);
        // Add Product Item 2
        $this->service->addItem($cartId, 'product', 102, 'Celana Chino', 150000.0, 1.0);

        // Apply Voucher Condition (-10%)
        $this->service->applyCondition($cartId, new CartCondition('VOUCHER10', 'discount', '-10%'));

        $totals = $this->service->getTotals($cartId);

        // Subtotal = (200,000 * 2) + 150,000 = 550,000
        // Discount = 10% of 550,000 = 55,000
        // GrandTotal = 495,000
        $this->assertEquals(550000.0, $totals->subtotal);
        $this->assertEquals(55000.0, $totals->discountTotal);
        $this->assertEquals(495000.0, $totals->grandTotal);
    }

    // POSITIVE CASE: Booking / Reservation (Decimal Quantity for Nights/Hours)
    public function testBookingReservationFlow(): void
    {
        $cartId = 'booking_sess_99';

        // Add Hotel Booking Item (3.5 days / nights, price 400,000 per night)
        $this->service->addItem(
            cartId: $cartId,
            itemType: 'hotel_room',
            itemId: 'deluxe_suite',
            name: 'Deluxe Suite Sea View',
            unitPrice: 400000.0,
            quantity: 3.5,
            attributes: ['check_in' => '2026-11-10 14:00', 'check_out' => '2026-11-14 02:00']
        );

        // Apply Tax (+11% PPN)
        $this->service->applyCondition($cartId, new CartCondition('PPN 11%', 'tax', '+11%'));

        $totals = $this->service->getTotals($cartId);

        // Subtotal = 400,000 * 3.5 = 1,400,000
        // Tax = 11% of 1,400,000 = 154,000
        // GrandTotal = 1,554,000
        $this->assertEquals(1400000.0, $totals->subtotal);
        $this->assertEquals(154000.0, $totals->taxTotal);
        $this->assertEquals(1554000.0, $totals->grandTotal);
    }

    // POSITIVE CASE: Events Dispatched on Action
    public function testEventsDispatchedOnItemAddedAndConditionApplied(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) {
                $this->assertTrue(
                    $event instanceof ItemAddedToCartEvent || $event instanceof ConditionAppliedEvent
                );
            });

        $service = new CartService($this->storage, $dispatcher);
        $service->addItem('cart_evt', 'service', 1, 'Cleaning Service', 100000.0);
        $service->applyCondition('cart_evt', new CartCondition('SERVICE_FEE', 'fee', '+10000'));
    }
}
