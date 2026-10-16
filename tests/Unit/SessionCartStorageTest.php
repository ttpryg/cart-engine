<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartItem;
use Ttpryg\CartEngine\Storage\SessionCartStorage;

class SessionCartStorageTest extends TestCase
{
    private const SESSION_KEY = '__cart_engine_test';

    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
        unset($_SESSION[self::SESSION_KEY]);
    }

    protected function tearDown(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    private function createCart(string $id): Cart
    {
        $cart = new Cart(id: $id, currency: 'IDR');
        $cart->addItem(new CartItem(itemType: 'product', itemId: 101, name: 'Kemeja', unitPrice: 100000.0));

        return $cart;
    }

    // POSITIVE CASE: Save and retrieve cart from session
    public function test_save_and_get_cart_from_session(): void
    {
        $sessionCartStorage = new SessionCartStorage(self::SESSION_KEY);

        $this->assertTrue($sessionCartStorage->save($this->createCart('sess_1')));
        $this->assertTrue($sessionCartStorage->exists('sess_1'));

        $retrieved = $sessionCartStorage->get('sess_1');
        $this->assertNotNull($retrieved);
        $this->assertEquals('sess_1', $retrieved->getId());
        $this->assertCount(1, $retrieved->getItems());
    }

    // POSITIVE CASE: Session keys are isolated per storage instance key
    public function test_carts_are_isolated_by_session_key(): void
    {
        $storage = new SessionCartStorage(self::SESSION_KEY);
        $otherStorage = new SessionCartStorage('__cart_engine_other');

        $storage->save($this->createCart('sess_2'));

        $this->assertTrue($storage->exists('sess_2'));
        $this->assertFalse($otherStorage->exists('sess_2'));

        unset($_SESSION['__cart_engine_other']);
    }

    // POSITIVE CASE: Delete removes the cart
    public function test_delete_cart_from_session(): void
    {
        $sessionCartStorage = new SessionCartStorage(self::SESSION_KEY);
        $sessionCartStorage->save($this->createCart('sess_3'));

        $this->assertTrue($sessionCartStorage->delete('sess_3'));
        $this->assertFalse($sessionCartStorage->exists('sess_3'));
        $this->assertNull($sessionCartStorage->get('sess_3'));
    }

    // NEGATIVE CASE: Unknown cart returns null
    public function test_get_unknown_cart_returns_null(): void
    {
        $sessionCartStorage = new SessionCartStorage(self::SESSION_KEY);

        $this->assertNull($sessionCartStorage->get('missing_cart'));
        $this->assertFalse($sessionCartStorage->exists('missing_cart'));
    }
}
