<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartItem;
use Ttpryg\CartEngine\Storage\FileCartStorage;

class FileCartStorageTest extends TestCase
{
    private string $tempDir;

    private FileCartStorage $fileCartStorage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/cart_test_'.uniqid();
        $this->fileCartStorage = new FileCartStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir.'/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    // POSITIVE CASE: Save and Retrieve Cart from JSON Cache File
    public function test_save_and_get_cart_from_file(): void
    {
        $cart = new Cart(id: 'sess_123', currency: 'IDR');
        $cartItem = new CartItem(
            itemType: 'booking',
            itemId: 'room_101',
            name: 'Deluxe Room Hotel',
            unitPrice: 500000.0,
            quantity: 2.0, // 2 nights
            attributes: ['check_in' => '2026-10-01', 'check_out' => '2026-10-03']
        );
        $cart->addItem($cartItem);

        $saved = $this->fileCartStorage->save($cart);
        $this->assertTrue($saved);
        $this->assertTrue($this->fileCartStorage->exists('sess_123'));

        $retrieved = $this->fileCartStorage->get('sess_123');
        $this->assertNotNull($retrieved);
        $this->assertEquals('sess_123', $retrieved->getId());
        $this->assertCount(1, $retrieved->getItems());
        $this->assertEquals(1000000.0, $retrieved->getSubTotal());
    }

    // NEGATIVE CASE: Get Non-Existent Cart returns NULL
    public function test_get_non_existent_cart_returns_null(): void
    {
        $this->assertNull($this->fileCartStorage->get('unknown_cart_id'));
    }

    // REGRESSION: Sanitized cart IDs must not collide (e.g. "a/b" vs "a_b")
    public function test_sanitized_cart_ids_do_not_collide(): void
    {
        $cartA = new Cart(id: 'a/b', currency: 'IDR');
        $cartA->addItem(new CartItem(itemType: 'product', itemId: 1, name: 'A', unitPrice: 10000.0));

        $cartB = new Cart(id: 'a_b', currency: 'IDR');
        $cartB->addItem(new CartItem(itemType: 'product', itemId: 2, name: 'B', unitPrice: 20000.0));

        $this->assertTrue($this->fileCartStorage->save($cartA));
        $this->assertTrue($this->fileCartStorage->save($cartB));

        $retrievedA = $this->fileCartStorage->get('a/b');
        $retrievedB = $this->fileCartStorage->get('a_b');

        $this->assertNotNull($retrievedA);
        $this->assertNotNull($retrievedB);
        $this->assertEquals('a/b', $retrievedA->getId());
        $this->assertEquals('a_b', $retrievedB->getId());
        $this->assertCount(1, $retrievedA->getItems());
        $this->assertCount(1, $retrievedB->getItems());
        $this->assertEquals('A', $retrievedA->getItems()[array_key_first($retrievedA->getItems())]->getName());
        $this->assertEquals('B', $retrievedB->getItems()[array_key_first($retrievedB->getItems())]->getName());
    }

    // POSITIVE CASE: Atomic save leaves no temporary files behind
    public function test_save_leaves_no_temp_files_behind(): void
    {
        $cart = new Cart(id: 'atomic_1', currency: 'IDR');
        $cart->addItem(new CartItem(itemType: 'product', itemId: 1, name: 'Item', unitPrice: 1000.0));

        $this->fileCartStorage->save($cart);
        $this->fileCartStorage->save($cart);

        $tempFiles = glob($this->tempDir.'/*.tmp');
        $this->assertSame([], is_array($tempFiles) ? $tempFiles : []);
    }

    // POSITIVE CASE: Status is preserved through save/load round trip
    public function test_completed_status_survives_round_trip(): void
    {
        $cart = new Cart(id: 'status_rt', currency: 'IDR');
        $cart->setStatus(Cart::STATUS_COMPLETED);

        $this->fileCartStorage->save($cart);

        $retrieved = $this->fileCartStorage->get('status_rt');
        $this->assertNotNull($retrieved);
        $this->assertSame(Cart::STATUS_COMPLETED, $retrieved->getStatus());
    }
}
