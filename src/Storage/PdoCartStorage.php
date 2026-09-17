<?php

namespace Ttpryg\CartEngine\Storage;

use PDO;
use Ttpryg\CartEngine\Contracts\CartStorageInterface;
use Ttpryg\CartEngine\Entities\Cart;
use Ttpryg\CartEngine\Entities\CartCondition;
use Ttpryg\CartEngine\Entities\CartItem;

class PdoCartStorage implements CartStorageInterface
{
    private PDO $pdo;
    private string $cartsTable;
    private string $itemsTable;
    private string $conditionsTable;

    public function __construct(
        PDO $pdo,
        string $cartsTable = 'carts',
        string $itemsTable = 'cart_items',
        string $conditionsTable = 'cart_conditions'
    ) {
        $this->pdo = $pdo;
        $this->cartsTable = $cartsTable;
        $this->itemsTable = $itemsTable;
        $this->conditionsTable = $conditionsTable;
    }

    public function get(string $cartId): ?Cart
    {
        $sql = "SELECT * FROM {$this->cartsTable} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $cartId]);
        $cartRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cartRow) {
            return null;
        }

        // Fetch Items
        $sqlItems = "SELECT * FROM {$this->itemsTable} WHERE cart_id = :cart_id";
        $stmtItems = $this->pdo->prepare($sqlItems);
        $stmtItems->execute(['cart_id' => $cartId]);

        $items = [];
        while ($row = $stmtItems->fetch(PDO::FETCH_ASSOC)) {
            $attrs = !empty($row['attributes']) ? json_decode($row['attributes'], true) : [];
            $meta = !empty($row['metadata']) ? json_decode($row['metadata'], true) : [];

            $item = new CartItem(
                itemType: $row['item_type'],
                itemId: $row['item_id'],
                name: $row['name'],
                unitPrice: (float) $row['unit_price'],
                quantity: (float) $row['quantity'],
                attributes: is_array($attrs) ? $attrs : [],
                metadata: is_array($meta) ? $meta : [],
                id: $row['id']
            );

            $items[$item->getKey()] = $item;
        }

        // Fetch Conditions
        $sqlConds = "SELECT * FROM {$this->conditionsTable} WHERE cart_id = :cart_id";
        $stmtConds = $this->pdo->prepare($sqlConds);
        $stmtConds->execute(['cart_id' => $cartId]);

        $conditions = [];
        while ($row = $stmtConds->fetch(PDO::FETCH_ASSOC)) {
            $attrs = !empty($row['attributes']) ? json_decode($row['attributes'], true) : [];

            $condition = new CartCondition(
                name: $row['name'],
                type: $row['type'],
                value: $row['value'],
                target: $row['target'],
                itemId: $row['item_id'],
                attributes: is_array($attrs) ? $attrs : []
            );

            $conditions[$condition->getName()] = $condition;
        }

        $metadata = !empty($cartRow['metadata']) ? json_decode($cartRow['metadata'], true) : [];

        return new Cart(
            id: $cartRow['id'],
            userId: $cartRow['user_id'],
            items: $items,
            conditions: $conditions,
            currency: $cartRow['currency'] ?? 'IDR',
            metadata: is_array($metadata) ? $metadata : [],
            status: $cartRow['status'] ?? 'active'
        );
    }

    public function save(Cart $cart): bool
    {
        $this->pdo->beginTransaction();
        try {
            // Upsert Cart Header
            $sqlCart = "INSERT INTO {$this->cartsTable} (id, user_id, status, currency, metadata) 
                        VALUES (:id, :user_id, :status, :currency, :metadata)
                        ON DUPLICATE KEY UPDATE 
                        user_id = VALUES(user_id), 
                        status = VALUES(status), 
                        currency = VALUES(currency), 
                        metadata = VALUES(metadata)";

            // Note: For SQLite compatibility in integration tests, handle ON CONFLICT
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $sqlCart = "INSERT INTO {$this->cartsTable} (id, user_id, status, currency, metadata) 
                            VALUES (:id, :user_id, :status, :currency, :metadata)
                            ON CONFLICT(id) DO UPDATE SET 
                            user_id = excluded.user_id, 
                            status = excluded.status, 
                            currency = excluded.currency, 
                            metadata = excluded.metadata";
            }

            $stmtCart = $this->pdo->prepare($sqlCart);
            $stmtCart->execute([
                'id' => $cart->getId(),
                'user_id' => $cart->getUserId(),
                'status' => 'active',
                'currency' => $cart->getCurrency(),
                'metadata' => json_encode($cart->getMetadata()),
            ]);

            // Replace Items
            $sqlDeleteItems = "DELETE FROM {$this->itemsTable} WHERE cart_id = :cart_id";
            $stmtDeleteItems = $this->pdo->prepare($sqlDeleteItems);
            $stmtDeleteItems->execute(['cart_id' => $cart->getId()]);

            $sqlInsertItem = "INSERT INTO {$this->itemsTable} (cart_id, item_type, item_id, name, unit_price, quantity, attributes, metadata) 
                              VALUES (:cart_id, :item_type, :item_id, :name, :unit_price, :quantity, :attributes, :metadata)";
            $stmtInsertItem = $this->pdo->prepare($sqlInsertItem);

            foreach ($cart->getItems() as $item) {
                $stmtInsertItem->execute([
                    'cart_id' => $cart->getId(),
                    'item_type' => $item->getItemType(),
                    'item_id' => $item->getItemId(),
                    'name' => $item->getName(),
                    'unit_price' => $item->getUnitPrice(),
                    'quantity' => $item->getQuantity(),
                    'attributes' => json_encode($item->getAttributes()),
                    'metadata' => json_encode($item->getMetadata()),
                ]);
            }

            // Replace Conditions
            $sqlDeleteConds = "DELETE FROM {$this->conditionsTable} WHERE cart_id = :cart_id";
            $stmtDeleteConds = $this->pdo->prepare($sqlDeleteConds);
            $stmtDeleteConds->execute(['cart_id' => $cart->getId()]);

            $sqlInsertCond = "INSERT INTO {$this->conditionsTable} (cart_id, name, type, target, item_id, value, attributes) 
                              VALUES (:cart_id, :name, :type, :target, :item_id, :value, :attributes)";
            $stmtInsertCond = $this->pdo->prepare($sqlInsertCond);

            foreach ($cart->getConditions() as $condition) {
                $stmtInsertCond->execute([
                    'cart_id' => $cart->getId(),
                    'name' => $condition->getName(),
                    'type' => $condition->getType(),
                    'target' => $condition->getTarget(),
                    'item_id' => $condition->getItemId(),
                    'value' => $condition->getValue(),
                    'attributes' => json_encode($condition->getAttributes()),
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(string $cartId): bool
    {
        $sql = "DELETE FROM {$this->cartsTable} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $cartId]);
    }

    public function exists(string $cartId): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->cartsTable} WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $cartId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
