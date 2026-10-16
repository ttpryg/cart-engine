<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartCondition;
use Ttpryg\CartEngine\Entities\CartItem;
use Ttpryg\CartEngine\Storage\PdoCartStorage;

class PdoCartStorageTest extends TestCase
{
    private PDO $pdo;

    private PdoCartStorage $pdoCartStorage;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create SQLite Tables
        $this->pdo->exec("
            CREATE TABLE carts (
                id VARCHAR(64) PRIMARY KEY,
                user_id INT NULL,
                status VARCHAR(20) DEFAULT 'active',
                currency VARCHAR(3) DEFAULT 'IDR',
                metadata TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE cart_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cart_id VARCHAR(64) NOT NULL,
                item_type VARCHAR(50) NOT NULL,
                item_id VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                unit_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
                quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
                attributes TEXT NULL,
                metadata TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE cart_conditions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cart_id VARCHAR(64) NOT NULL,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(30) NOT NULL,
                target VARCHAR(20) DEFAULT 'cart',
                item_id VARCHAR(100) NULL,
                value VARCHAR(50) NOT NULL,
                attributes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->pdoCartStorage = new PdoCartStorage($this->pdo);
    }

    // POSITIVE CASE: Save and Retrieve Cart via PDO
    public function test_save_and_retrieve_cart_from_database(): void
    {
        $cart = new Cart(id: 'db_cart_100', userId: 42, currency: 'IDR');
        $cartItem = new CartItem(
            itemType: 'car_rental',
            itemId: 'avanza_01',
            name: 'Rental Toyota Avanza',
            unitPrice: 350000.0,
            quantity: 3.0,
            attributes: ['with_driver' => true]
        );
        $cart->addItem($cartItem);
        $cart->addCondition(new CartCondition('DEPOSIT', 'fee', '+100000'));

        $saved = $this->pdoCartStorage->save($cart);
        $this->assertTrue($saved);
        $this->assertTrue($this->pdoCartStorage->exists('db_cart_100'));

        $retrieved = $this->pdoCartStorage->get('db_cart_100');
        $this->assertNotNull($retrieved);
        $this->assertEquals(42, $retrieved->getUserId());
        $this->assertCount(1, $retrieved->getItems());
        $this->assertCount(1, $retrieved->getConditions());
        $this->assertEquals(1150000.0, $retrieved->getTotals()->grandTotal);
    }

    // NEGATIVE CASE: Delete Cart
    public function test_delete_cart_from_database(): void
    {
        $cart = new Cart(id: 'db_cart_to_delete');
        $this->pdoCartStorage->save($cart);

        $this->assertTrue($this->pdoCartStorage->exists('db_cart_to_delete'));
        $this->pdoCartStorage->delete('db_cart_to_delete');
        $this->assertFalse($this->pdoCartStorage->exists('db_cart_to_delete'));
        $this->assertNull($this->pdoCartStorage->get('db_cart_to_delete'));
    }

    // POSITIVE CASE: Cart status is persisted, not forced back to active
    public function test_completed_status_is_persisted(): void
    {
        $cart = new Cart(id: 'db_status_cart');
        $cart->setStatus(Cart::STATUS_COMPLETED);

        $this->pdoCartStorage->save($cart);

        $retrieved = $this->pdoCartStorage->get('db_status_cart');
        $this->assertNotNull($retrieved);
        $this->assertSame(Cart::STATUS_COMPLETED, $retrieved->getStatus());
    }

    // POSITIVE CASE: save() inside an already-open transaction does not throw
    public function test_save_inside_existing_transaction_does_not_throw(): void
    {
        $cart = new Cart(id: 'db_nested_cart');
        $cart->addItem(new CartItem(itemType: 'product', itemId: 101, name: 'Kemeja', unitPrice: 100000.0));

        $this->pdo->beginTransaction();
        try {
            $this->pdoCartStorage->save($cart);
            // Outer transaction must remain open — storage must not commit it
            $this->assertTrue($this->pdo->inTransaction());
        } finally {
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        }

        $this->assertTrue($this->pdoCartStorage->exists('db_nested_cart'));
        $retrieved = $this->pdoCartStorage->get('db_nested_cart');
        $this->assertNotNull($retrieved);
        $this->assertCount(1, $retrieved->getItems());
    }

    // NEGATIVE CASE: Item-level condition round trips with string item_id
    public function test_item_level_condition_round_trips_with_string_item_id(): void
    {
        $cart = new Cart(id: 'db_item_cond');
        $cart->addItem(new CartItem(itemType: 'product', itemId: 101, name: 'Kemeja', unitPrice: 100000.0, quantity: 2.0));
        $cart->addCondition(new CartCondition('KEMEJA10', 'discount', '-10%', target: 'item', itemId: '101'));

        $this->pdoCartStorage->save($cart);

        $retrieved = $this->pdoCartStorage->get('db_item_cond');
        $this->assertNotNull($retrieved);
        $condition = $retrieved->getConditions()[array_key_first($retrieved->getConditions())];
        $this->assertSame('item', $condition->getTarget());
        $this->assertSame('101', $condition->getItemId());

        $cartTotals = $retrieved->getTotals();
        $this->assertEquals(20000.0, $cartTotals->discountTotal);
        $this->assertEquals(180000.0, $cartTotals->grandTotal);
    }
}
