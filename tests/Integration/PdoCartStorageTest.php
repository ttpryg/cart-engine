<?php

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
    private PdoCartStorage $storage;

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
                item_id INT NULL,
                value VARCHAR(50) NOT NULL,
                attributes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $this->storage = new PdoCartStorage($this->pdo);
    }

    // POSITIVE CASE: Save and Retrieve Cart via PDO
    public function testSaveAndRetrieveCartFromDatabase(): void
    {
        $cart = new Cart(id: 'db_cart_100', userId: 42, currency: 'IDR');
        $item = new CartItem(
            itemType: 'car_rental',
            itemId: 'avanza_01',
            name: 'Rental Toyota Avanza',
            unitPrice: 350000.0,
            quantity: 3.0,
            attributes: ['with_driver' => true]
        );
        $cart->addItem($item);
        $cart->addCondition(new CartCondition('DEPOSIT', 'fee', '+100000'));

        $saved = $this->storage->save($cart);
        $this->assertTrue($saved);
        $this->assertTrue($this->storage->exists('db_cart_100'));

        $retrieved = $this->storage->get('db_cart_100');
        $this->assertNotNull($retrieved);
        $this->assertEquals(42, $retrieved->getUserId());
        $this->assertCount(1, $retrieved->getItems());
        $this->assertCount(1, $retrieved->getConditions());
        $this->assertEquals(1150000.0, $retrieved->getTotals()->grandTotal);
    }

    // NEGATIVE CASE: Delete Cart
    public function testDeleteCartFromDatabase(): void
    {
        $cart = new Cart(id: 'db_cart_to_delete');
        $this->storage->save($cart);

        $this->assertTrue($this->storage->exists('db_cart_to_delete'));
        $this->storage->delete('db_cart_to_delete');
        $this->assertFalse($this->storage->exists('db_cart_to_delete'));
        $this->assertNull($this->storage->get('db_cart_to_delete'));
    }
}
