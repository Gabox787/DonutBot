-- TG Donut Bot — MySQL 5.7+ / MariaDB (utf8mb4). Fresh production install.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(96) NOT NULL PRIMARY KEY,
    setting_value MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    platform ENUM('telegram','bale') NOT NULL DEFAULT 'telegram',
    telegram_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(64) NULL,
    first_name VARCHAR(128) NOT NULL DEFAULT '',
    balance_toman BIGINT UNSIGNED NOT NULL DEFAULT 0,
    hub_chat_id BIGINT NULL,
    hub_message_id INT NULL,
    kb_anchor_message_id INT NULL COMMENT 'Thin message used only to refresh reply keyboard',
    referred_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (platform, telegram_id),
    INDEX idx_users_updated (updated_at),
    INDEX idx_users_referrer (platform, referred_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS states (
    platform ENUM('telegram','bale') NOT NULL DEFAULT 'telegram',
    user_id BIGINT UNSIGNED NOT NULL,
    state VARCHAR(64) NOT NULL DEFAULT '',
    data MEDIUMTEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (platform, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(48) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL,
    title_bale VARCHAR(255) NULL COMMENT 'Bale presentation title; NULL = use title',
    description TEXT NULL,
    description_bale TEXT NULL,
    base_qty INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Pricing step size in qty_unit (e.g. 1 kg)',
    qty_unit VARCHAR(16) NOT NULL DEFAULT 'kg' COMMENT 'Unit code: kg, g, piece, box, ...',
    price_toman BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    allow_custom_qty TINYINT(1) NOT NULL DEFAULT 0,
    qty_min INT UNSIGNED NOT NULL DEFAULT 1,
    qty_max INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = cap at base_qty',
    test_enabled TINYINT(1) NOT NULL DEFAULT 0,
    test_price_toman BIGINT UNSIGNED NOT NULL DEFAULT 0,
    test_sample_payload TEXT NULL COMMENT 'Sample delivery text or URL for trial orders',
    user_limit INT UNSIGNED NULL COMMENT 'NULL = unlimited concurrent “slots” on this product',
    duration_days INT UNSIGNED NULL COMMENT 'NULL = unlimited after fulfillment',
    delivery_template TEXT NULL COMMENT 'Optional internal notes; stock lines deliver payloads',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_products_slug (slug),
    INDEX idx_products_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_stock (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    payload TEXT NOT NULL COMMENT 'Per-unit delivery: tracking link, pickup code, URL, etc.',
    status ENUM('available','assigned') NOT NULL DEFAULT 'available',
    assigned_order_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stock_product_avail (product_id, status),
    CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders_config (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('telegram','bale') NOT NULL DEFAULT 'telegram',
    public_id CHAR(12) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    price_paid_toman BIGINT UNSIGNED NOT NULL,
    payload TEXT NULL,
    status ENUM('pending','fulfilled') NOT NULL DEFAULT 'pending',
    stock_item_id BIGINT UNSIGNED NULL,
    order_kind ENUM('standard','test') NOT NULL DEFAULT 'standard',
    qty_ordered INT UNSIGNED NULL,
    user_remark VARCHAR(512) NULL,
    test_expires_at TIMESTAMP NULL DEFAULT NULL,
    access_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    access_revoked_at TIMESTAMP NULL DEFAULT NULL,
    access_revoke_reason VARCHAR(512) NULL,
    service_started_at TIMESTAMP NULL DEFAULT NULL,
    service_ends_at TIMESTAMP NULL DEFAULT NULL,
    user_limit_snapshot INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_public (public_id),
    INDEX idx_orders_user_plat (platform, user_id),
    INDEX idx_orders_pending_product (product_id, status),
    CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wallet_topups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('telegram','bale') NOT NULL DEFAULT 'telegram',
    public_id CHAR(12) NOT NULL COMMENT 'short id for user display',
    user_id BIGINT UNSIGNED NOT NULL,
    amount_toman BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    receipt_file_id VARCHAR(255) NULL,
    receipt_file_unique VARCHAR(128) NULL,
    admin_note VARCHAR(255) NULL,
    admin_notify_handles MEDIUMTEXT NULL COMMENT 'JSON: [{platform, chat_id, message_id, is_photo}]',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_topup_public_plat (public_id, platform),
    INDEX idx_topup_user_status (platform, user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_payouts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('telegram','bale') NOT NULL DEFAULT 'telegram',
    referrer_id BIGINT UNSIGNED NOT NULL,
    buyer_id BIGINT UNSIGNED NOT NULL,
    order_public_id CHAR(12) NOT NULL,
    amount_toman BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ref_pay_referrer (platform, referrer_id),
    INDEX idx_ref_pay_buyer (platform, buyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO products (
    slug, title, title_bale, description, description_bale, base_qty, qty_unit, price_toman, sort_order, is_featured, is_active,
    allow_custom_qty, qty_min, qty_max, test_enabled, test_price_toman, test_sample_payload, user_limit, duration_days, delivery_template
) VALUES
(
    'classic-dozen',
    '🍩 کلاسیک — دونات شکلاتی (۱۲ عدد)',
    '🍩 دونات کلاسیک',
    'یک بستهٔ ۱۲تایی دونات تازه.\nارسال همان روز در تهران (مناطق ۱–۱۲).\nبرای وزن سفارشی از گزینهٔ «انتخاب مقدار» استفاده کنید.',
    'یک جعبهٔ دوازده‌تایی دونات تازه 🍩',
    1,
    'kg',
    450000,
    1,
    0,
    1,
    1,
    1,
    50,
    1,
    50000,
    'نمونه: کد تحویل TEST-DONUT-001 — فقط برای امتحان طعم!',
    NULL,
    NULL,
    ''
),
(
    'premium-kilo',
    '⭐ پریمیوم — هر کیلو',
    '⭐ دونات پریمیوم (کیلویی)',
    'دونات‌های دست‌ساز با تاپینگ ویژه.\nقیمت به ازای هر کیلو؛ امکان سفارش وزن دلخواه.',
    'دونات دست‌ساز ویژه — هر کیلو 🍩✨',
    1,
    'kg',
    890000,
    2,
    1,
    1,
    1,
    1,
    20,
    1,
    75000,
    'نمونه طعم: لینک رهگیری نمونه https://example.com/track/demo',
    NULL,
    NULL,
    ''
),
(
    'party-box',
    '🎉 باکس مهمانی',
    '🎉 باکس مهمانی',
    'مناسب جشن و جمع‌های کوچک — حدود ۳ کیلو مخلوط طعم‌ها.',
    'باکس مهمانی — مخلوط طعم‌ها 🎉',
    3,
    'kg',
    2400000,
    3,
    0,
    1,
    0,
    1,
    0,
    0,
    0,
    NULL,
    NULL,
    NULL,
    ''
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    title_bale = VALUES(title_bale),
    description = VALUES(description),
    description_bale = VALUES(description_bale),
    base_qty = VALUES(base_qty),
    qty_unit = VALUES(qty_unit),
    price_toman = VALUES(price_toman),
    sort_order = VALUES(sort_order),
    is_featured = VALUES(is_featured),
    is_active = VALUES(is_active),
    allow_custom_qty = VALUES(allow_custom_qty),
    qty_min = VALUES(qty_min),
    qty_max = VALUES(qty_max),
    test_enabled = VALUES(test_enabled),
    test_price_toman = VALUES(test_price_toman),
    test_sample_payload = VALUES(test_sample_payload),
    user_limit = VALUES(user_limit),
    duration_days = VALUES(duration_days),
    delivery_template = VALUES(delivery_template);
