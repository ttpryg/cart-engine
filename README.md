# CartEngine Library

`ttpryg/cart-engine` is a framework-agnostic standalone PHP library for item-agnostic shopping carts and booking/reservation systems. It is completely decoupled from any specific product schema or database table.

## 🌟 Key Features

- **Framework Agnostic**: Compatible with any PHP 8.1+ project (Vanilla PHP, Slim, Laravel, Symfony, CodeIgniter).
- **Item-Agnostic / Polymorphic**: Supports physical products, digital items, hotel room bookings, car rentals, service appointments, or event tickets without hardcoded database coupling.
- **Decimal Quantities**: Supports fractional or decimal quantities (e.g. `2.5` hours, `3` nights, `1.5` kg).
- **Multiple Storage Drivers**:
  - `FileCartStorage`: Local JSON cache files (`storage/carts/` or `/tmp/carts/`).
  - `PdoCartStorage`: Relational database via PDO (MySQL, MariaDB, SQLite).
  - `MemoryCartStorage`: In-memory storage for unit testing.
  - `SessionCartStorage`: PHP `$_SESSION` wrapper.
- **Cart Conditions**: Apply discounts (`-10%`, `-50000`), taxes (`+11%`), shipping fees (`+20000`), or booking deposits to the cart.
- **Domain Events**: `ItemAddedToCartEvent`, `ItemRemovedFromCartEvent`, `CartClearedEvent`, `ConditionAppliedEvent`.

---

## 🗄️ Database Schema (Optional - for PDO Storage Driver)

Run the SQL script from `database/schema.sql` or use `DatabaseMigrator`:

```sql
CREATE TABLE IF NOT EXISTS carts (
    id VARCHAR(64) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    status ENUM('active', 'completed', 'abandoned') NOT NULL DEFAULT 'active',
    currency VARCHAR(3) NOT NULL DEFAULT 'IDR',
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id VARCHAR(64) NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    item_id VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    unit_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00,
    attributes JSON NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cart_conditions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id VARCHAR(64) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(30) NOT NULL,
    target ENUM('cart', 'item') NOT NULL DEFAULT 'cart',
    item_id BIGINT UNSIGNED NULL,
    value VARCHAR(50) NOT NULL,
    attributes JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_conditions_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
);
```

---

## 🚀 Quick Usage Examples

### 1. E-Commerce Product Shopping Cart (File Cache Storage)

```php
use Ttpryg\CartEngine\Services\CartService;
use Ttpryg\CartEngine\Storage\FileCartStorage;
use Ttpryg\CartEngine\Entities\CartCondition;

// Initialize File Cache Storage
$storage = new FileCartStorage(__DIR__ . '/storage/carts');
$cartService = new CartService($storage);

$cartId = 'user_session_abc123';

// Add Product to Cart
$cartService->addItem(
    cartId: $cartId,
    itemType: 'product',
    itemId: 101,
    name: 'Nike Running Shoes',
    unitPrice: 1500000.0,
    quantity: 1,
    attributes: ['color' => 'Black', 'size' => '42']
);

// Apply Discount Voucher (-10%)
$cartService->applyCondition($cartId, new CartCondition('VOUCHER10', 'discount', '-10%'));

// Get Totals
$totals = $cartService->getTotals($cartId);
echo $totals->grandTotal; // 1,350,000
```

### 2. Hotel Room Booking Cart (PDO Storage)

```php
use PDO;
use Ttpryg\CartEngine\Services\CartService;
use Ttpryg\CartEngine\Storage\PdoCartStorage;
use Ttpryg\CartEngine\Entities\CartCondition;

$pdo = new PDO("mysql:host=localhost;dbname=my_db", "root", "secret");
$storage = new PdoCartStorage($pdo);
$cartService = new CartService($storage);

$cartId = 'booking_session_xyz';

// Add Hotel Room Reservation (3.5 Nights)
$cartService->addItem(
    cartId: $cartId,
    itemType: 'hotel_room',
    itemId: 'deluxe_suite',
    name: 'Deluxe Suite Sea View',
    unitPrice: 500000.0,
    quantity: 3.5,
    attributes: ['check_in' => '2026-10-01 14:00', 'check_out' => '2026-10-05 02:00']
);

// Apply PPN Tax (+11%)
$cartService->applyCondition($cartId, new CartCondition('PPN 11%', 'tax', '+11%'));

// Checkout & Export Snapshot
$orderSnapshot = $cartService->checkout($cartId);
```

---

## 📄 License
MIT License.
