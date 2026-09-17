<?php

namespace Ttpryg\CartEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartItem;
use Ttpryg\CartEngine\Storage\FileCartStorage;

class FileCartStorageTest extends TestCase
{
    private string $tempDir;
    private FileCartStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/cart_test_' . uniqid();
        $this->storage = new FileCartStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) unlink($file);
            }
            rmdir($this->tempDir);
        }
    }

    // POSITIVE CASE: Save and Retrieve Cart from JSON Cache File
    public function testSaveAndGetCartFromFile(): void
    {
        $cart = new Cart(id: 'sess_123', currency: 'IDR');
        $item = new CartItem(
            itemType: 'booking',
            itemId: 'room_101',
            name: 'Deluxe Room Hotel',
            unitPrice: 500000.0,
            quantity: 2.0, // 2 nights
            attributes: ['check_in' => '2026-10-01', 'check_out' => '2026-10-03']
        );
        $cart->addItem($item);

        $saved = $this->storage->save($cart);
        $this->assertTrue($saved);
        $this->assertTrue($this->storage->exists('sess_123'));

        $retrieved = $this->storage->get('sess_123');
        $this->assertNotNull($retrieved);
        $this->assertEquals('sess_123', $retrieved->getId());
        $this->assertCount(1, $retrieved->getItems());
        $this->assertEquals(1000000.0, $retrieved->getSubTotal());
    }

    // NEGATIVE CASE: Get Non-Existent Cart returns NULL
    public function testGetNonExistentCartReturnsNull(): void
    {
        $this->assertNull($this->storage->get('unknown_cart_id'));
    }
}
