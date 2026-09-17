<?php

namespace Ttpryg\CartEngine\Storage;

use Ttpryg\CartEngine\Contracts\CartStorageInterface;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartCondition;
use Ttpryg\CartEngine\Entities\CartItem;

class FileCartStorage implements CartStorageInterface
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = rtrim($storagePath ?? sys_get_temp_dir() . '/cart_engine_cache', '/');
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
    }

    public function get(string $cartId): ?Cart
    {
        $filePath = $this->getFilePath($cartId);
        if (!file_exists($filePath)) {
            return null;
        }

        $json = file_get_contents($filePath);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return $this->unserializeCart($data);
    }

    public function save(Cart $cart): bool
    {
        $filePath = $this->getFilePath($cart->getId());
        $data = $cart->toArray();
        $json = json_encode($data, JSON_PRETTY_PRINT);

        return file_put_contents($filePath, $json) !== false;
    }

    public function delete(string $cartId): bool
    {
        $filePath = $this->getFilePath($cartId);
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return true;
    }

    public function exists(string $cartId): bool
    {
        return file_exists($this->getFilePath($cartId));
    }

    private function getFilePath(string $cartId): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cartId);
        return $this->storagePath . '/cart_' . $safeId . '.json';
    }

    private function unserializeCart(array $data): Cart
    {
        $items = [];
        if (!empty($data['items'])) {
            foreach ($data['items'] as $key => $itemData) {
                $items[$key] = new CartItem(
                    itemType: $itemData['item_type'],
                    itemId: $itemData['item_id'],
                    name: $itemData['name'],
                    unitPrice: (float) $itemData['unit_price'],
                    quantity: (float) $itemData['quantity'],
                    attributes: $itemData['attributes'] ?? [],
                    metadata: $itemData['metadata'] ?? [],
                    id: $itemData['id'] ?? null
                );
            }
        }

        $conditions = [];
        if (!empty($data['conditions'])) {
            foreach ($data['conditions'] as $name => $condData) {
                $conditions[$name] = new CartCondition(
                    name: $condData['name'],
                    type: $condData['type'],
                    value: $condData['value'],
                    target: $condData['target'] ?? 'cart',
                    itemId: $condData['item_id'] ?? null,
                    attributes: $condData['attributes'] ?? []
                );
            }
        }

        return new Cart(
            id: $data['id'],
            userId: $data['user_id'] ?? null,
            items: $items,
            conditions: $conditions,
            currency: $data['currency'] ?? 'IDR',
            metadata: $data['metadata'] ?? [],
            status: $data['status'] ?? 'active'
        );
    }
}
