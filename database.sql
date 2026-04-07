-- DonutNetBot — MySQL 5.7+ / MariaDB (utf8mb4). Fresh install: v5 schema (Telegram + Bale, app_settings).
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

CREATE TABLE IF NOT EXISTS plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(48) NOT NULL DEFAULT '',
    title VARCHAR(255) NOT NULL,
    title_bale VARCHAR(255) NULL COMMENT 'Presentation title on Bale; NULL = use title',
    description TEXT NULL,
    description_bale TEXT NULL COMMENT 'Donut-themed copy for Bale',
    gb INT UNSIGNED NOT NULL DEFAULT 0,
    price_toman BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    allow_custom_gb TINYINT(1) NOT NULL DEFAULT 0,
    gb_min INT UNSIGNED NOT NULL DEFAULT 1,
    gb_max INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = use gb column as max',
    test_enabled TINYINT(1) NOT NULL DEFAULT 0,
    test_price_toman BIGINT UNSIGNED NOT NULL DEFAULT 0,
    test_config_url TEXT NULL COMMENT 'Static vless/vmess URL when test is on',
    user_limit INT UNSIGNED NULL COMMENT 'NULL = unlimited concurrent users',
    duration_days INT UNSIGNED NULL COMMENT 'NULL = unlimited period after activation',
    config_template TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plans_slug (slug),
    INDEX idx_plans_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_configs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    payload TEXT NOT NULL,
    status ENUM('available','assigned') NOT NULL DEFAULT 'available',
    assigned_order_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pc_plan_avail (plan_id, status),
    CONSTRAINT fk_pc_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders_config (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    platform ENUM('telegram','bale') NOT NULL DEFAULT 'telegram',
    public_id CHAR(12) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    price_paid_toman BIGINT UNSIGNED NOT NULL,
    payload TEXT NULL,
    status ENUM('pending','fulfilled') NOT NULL DEFAULT 'pending',
    plan_config_id BIGINT UNSIGNED NULL,
    order_kind ENUM('standard','test') NOT NULL DEFAULT 'standard',
    gb_ordered INT UNSIGNED NULL,
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
    INDEX idx_orders_pending_plan (plan_id, status),
    CONSTRAINT fk_orders_plan FOREIGN KEY (plan_id) REFERENCES plans(id)
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

INSERT INTO plans (
    slug, title, title_bale, description, description_bale, gb, price_toman, sort_order, is_featured, is_active,
    allow_custom_gb, gb_min, gb_max, test_enabled, test_price_toman, test_config_url, user_limit, duration_days, config_template
) VALUES
(
    'economy',
    '💸 پلن اقتصادی (Economy)',
    '💸 پلن اقتصادی (Economy)',
    '🚀 فروش ویژه سرویس‌های پرسرعت VPN 🚀\nبا بهترین کیفیت، مناسب هر نوع مصرف 👇\n\n💸 پلن اقتصادی (Economy)\n📌 حجم: 10 گیگ – مدت: 1 ماه\n📌 مناسب کارهای روزمره (تلگرام، واتساپ، وب‌گردی…)\n📌 سرعت معمولی و پایدار\n⚠️ دارای قطعی روزانه (حدود 2 ساعت)\n🌙 از ساعت 12 شب تا 8 صبح سرعت بسیار بهتر\n❌ بدون ضمانت بازگشت وجه',
    '🍩 فروش ویژه دونات‌های خوشمزه و پرسرعت!\n\n💸 پلن اقتصادی (Economy)\n📌 مناسب خوردن روزمره (تلگرام، واتساپ، وب‌گردی و...)\n📌 طعم معمولی و پایدار\n📌 لذت < 500ms 🟠\n⚠️ گاهی دونات‌ها کمی از دسترس خارج میشن (حدود 2 ساعت)\n🌙 شب‌ها بین 12 تا 8 صبح خوشمزه‌تر و پرسرعت‌تر (مناسب دانلود و استریم)\n\n❌ ضمانت بازگشت وجه نداره\n💰 قیمت: 10 کیلو 850.000 تومان',
    10,
    850000,
    1,
    0,
    1,
    0,
    1,
    0,
    1,
    150000,
    NULL,
    1,
    30,
    ''
),
(
    'vip',
    '🔥 پلن VIP (سرور تانل)',
    '🔥 پلن VIP (سرور تانل)',
    '🔥 پلن VIP (سرور تانل)\n📌 سرعت بسیار بالا ⚡️\n📌 بدون قطعی (فقط در موارد نادر مثل آپدیت سرور)\n📌 مناسب کارهای سبک و نیمه‌سنگین\n📌 قابلیت سفارش حجم دلخواه\n📌 پینگ زیر 200 🟢\n✅ دارای ضمانت بازگشت وجه\n💰 قیمت: هر گیگ 800.000 تومان',
    '🔥 پلن VIP (سرور تانل)\n📌 سرعت خیلی بالا ⚡️\n📌 بدون قطعی (فقط گاهی موقع آپدیت ممکنه قطع بشه)\n📌 مناسب خوردن سبک و نیمه‌سنگین\n📌 قابلیت سفارش وزن دلخواه\n📌 لذت < 100ms 🟢\n\n✅ ضمانت بازگشت وجه داره\n💰 قیمت: 1 کیلو 800.000 تومان',
    1,
    800000,
    2,
    0,
    1,
    1,
    1,
    500,
    1,
    150000,
    NULL,
    NULL,
    NULL,
    ''
),
(
    'vip_plus',
    '💎 پلن VIP Plus',
    '💎 پلن VIP Plus',
    '💎 پلن VIP Plus (حرفه‌ای‌تر از VIP)\n📌 سرعت فوق‌العاده بالا 🚀\n📌 پایدارتر و قوی‌تر از VIP\n📌 مناسب کارهای نیمه‌سنگین و سنگین\n📌 قابلیت سفارش حجم دلخواه\n📌 پینگ زیر 100 🟢\n✅ دارای ضمانت بازگشت وجه\n💰 قیمت: هر گیگ 1.250.000 تومان',
    '💎 پلن VIP Plus (حرفه‌ای‌تر از VIP)\n📌 سرعت فوق‌العاده بالا 🚀\n📌 خوشمزه‌تر و پایدارتر از VIP\n📌 مناسب خوردن نیمه‌سنگین و سنگین\n📌 قابلیت سفارش وزن دلخواه\n📌 لذت < 100ms 🟢\n\n✅ ضمانت بازگشت وجه داره\n💰 قیمت: 1 کیلو 1.250.000 تومان',
    1,
    1250000,
    3,
    0,
    1,
    1,
    1,
    500,
    1,
    200000,
    NULL,
    NULL,
    NULL,
    ''
),
(
    'star',
    '🌟 پلن STAR (استارلینک)',
    '🌟 پلن ستاره‌ای ✨',
    '🌟 پلن STAR (استارلینک - نهایت سرعت)\n📌 بالاترین کیفیت و سرعت ممکن 🚀🔥\n📌 مناسب کارهای سنگین، استریم، دانلود حرفه‌ای\n📌 قابلیت سفارش حجم دلخواه\n📌 پینگ زیر 100 🟢\n📌 تمام ویژگی‌های VIP Plus + کیفیت بالاتر\n✅ دارای ضمانت بازگشت وجه\n💰 قیمت: هر گیگ 2.000.000 تومان',
    '🌟 پلن ستاره‌ای ✨\n📌 بالاترین کیفیت و سرعت 🚀🔥\n📌 مناسب خوردن سنگین، استریم و دانلود حرفه‌ای\n📌 تمام ویژگی‌های VIP Plus + طعم بهتر 😋\n📌 لذت < 100ms 🟢\n\n✅ ضمانت بازگشت وجه داره\n💰 قیمت: 1 کیلو 2.000.000 تومان',
    1,
    2000000,
    4,
    1,
    1,
    1,
    1,
    500,
    1,
    250000,
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
    gb = VALUES(gb),
    price_toman = VALUES(price_toman),
    sort_order = VALUES(sort_order),
    is_featured = VALUES(is_featured),
    is_active = VALUES(is_active),
    allow_custom_gb = VALUES(allow_custom_gb),
    gb_min = VALUES(gb_min),
    gb_max = VALUES(gb_max),
    test_enabled = VALUES(test_enabled),
    test_price_toman = VALUES(test_price_toman),
    test_config_url = VALUES(test_config_url),
    user_limit = VALUES(user_limit),
    duration_days = VALUES(duration_days),
    config_template = VALUES(config_template);
