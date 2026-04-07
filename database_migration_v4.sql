-- v4: static test URL, plan limits, order subscription fields, referrals, payouts
SET NAMES utf8mb4;

-- plans.test_config_url, user_limit, duration_days
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'test_config_url') > 0, 'SELECT 1', 'ALTER TABLE plans ADD COLUMN test_config_url TEXT NULL'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'user_limit') > 0, 'SELECT 1', 'ALTER TABLE plans ADD COLUMN user_limit INT UNSIGNED NULL COMMENT ''NULL = نامحدود کاربر'''));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'duration_days') > 0, 'SELECT 1', 'ALTER TABLE plans ADD COLUMN duration_days INT UNSIGNED NULL COMMENT ''NULL = نامحدود مدت'''));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users.referred_by
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'referred_by') > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN referred_by BIGINT UNSIGNED NULL'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_referrer') > 0, 'SELECT 1', 'ALTER TABLE users ADD INDEX idx_users_referrer (referred_by)'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- orders_config subscription
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders_config' AND COLUMN_NAME = 'access_status') > 0, 'SELECT 1', "ALTER TABLE orders_config ADD COLUMN access_status ENUM('active','inactive') NOT NULL DEFAULT 'active'"));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders_config' AND COLUMN_NAME = 'service_started_at') > 0, 'SELECT 1', 'ALTER TABLE orders_config ADD COLUMN service_started_at TIMESTAMP NULL DEFAULT NULL'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders_config' AND COLUMN_NAME = 'service_ends_at') > 0, 'SELECT 1', 'ALTER TABLE orders_config ADD COLUMN service_ends_at TIMESTAMP NULL DEFAULT NULL'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders_config' AND COLUMN_NAME = 'user_limit_snapshot') > 0, 'SELECT 1', 'ALTER TABLE orders_config ADD COLUMN user_limit_snapshot INT UNSIGNED NULL'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS referral_payouts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    referrer_id BIGINT UNSIGNED NOT NULL,
    buyer_id BIGINT UNSIGNED NOT NULL,
    order_public_id CHAR(12) NOT NULL,
    amount_toman BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ref_pay_referrer (referrer_id),
    INDEX idx_ref_pay_buyer (buyer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
