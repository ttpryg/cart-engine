-- CartEngine Database Schema
-- Standard MySQL / MariaDB DDL

CREATE TABLE IF NOT EXISTS carts (
    id VARCHAR(64) PRIMARY KEY COMMENT 'UUID atau Session ID atau user_id',
    user_id BIGINT UNSIGNED NULL COMMENT 'ID User jika sudah login',
    status ENUM('active', 'completed', 'abandoned') NOT NULL DEFAULT 'active',
    currency VARCHAR(3) NOT NULL DEFAULT 'IDR',
    metadata JSON NULL COMMENT 'Catatan pembeli, instruksi khusus',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id VARCHAR(64) NOT NULL,
    item_type VARCHAR(50) NOT NULL COMMENT 'product, booking, service, ticket, dll',
    item_id VARCHAR(100) NOT NULL COMMENT 'ID item dari sistem asal',
    name VARCHAR(255) NOT NULL COMMENT 'Snapshot nama item saat dimasukkan ke keranjang',
    unit_price DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Snapshot harga satuan',
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 1.00 COMMENT 'Kuantitas (hari/jam/porsi)',
    attributes JSON NULL COMMENT 'Context: tanggal booking, varian warna/ukuran',
    metadata JSON NULL COMMENT 'Catatan item, info garansi',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cart_item_lookup (cart_id, item_type, item_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_conditions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id VARCHAR(64) NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'Kupon, PPN 11%, Biaya Layanan',
    type VARCHAR(30) NOT NULL COMMENT 'discount, tax, fee, shipping',
    target ENUM('cart', 'item') NOT NULL DEFAULT 'cart',
    item_id BIGINT UNSIGNED NULL COMMENT 'Jika target = item',
    value VARCHAR(50) NOT NULL COMMENT '-10%, +11%, -50000, +20000',
    attributes JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_conditions_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
