<?php

declare(strict_types=1);

namespace Ttpryg\CartEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ttpryg\CartEngine\ValueObjects\CartItemKey;

class CartItemKeyTest extends TestCase
{
    // POSITIVE CASE: Same Item & Same Attributes produces Same Key
    public function test_same_item_produces_same_key(): void
    {
        $key1 = CartItemKey::generate('product', 10, ['color' => 'red', 'size' => 'L']);
        $key2 = CartItemKey::generate('product', 10, ['size' => 'L', 'color' => 'red']);

        $this->assertEquals($key1, $key2);
    }

    // POSITIVE CASE: Different Attributes produces Different Key
    public function test_different_attributes_produces_different_key(): void
    {
        $key1 = CartItemKey::generate('product', 10, ['size' => 'L']);
        $key2 = CartItemKey::generate('product', 10, ['size' => 'M']);

        $this->assertNotEquals($key1, $key2);
    }
}
