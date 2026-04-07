-- v5: dual platform (telegram + bale), app_settings, plan Bale copy, referrals/topups scoped by platform
SET NAMES utf8mb4;

-- Plans: Bale-specific titles/descriptions (nullable = use main title/description)
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'title_bale') > 0, 'SELECT 1', 'ALTER TABLE plans ADD COLUMN title_bale VARCHAR(255) NULL'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @s := (SELECT IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'description_bale') > 0, 'SELECT 1', 'ALTER TABLE plans ADD COLUMN description_bale TEXT NULL'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- App settings (key-value for admin panel + JSON blobs)
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(96) NOT NULL PRIMARY KEY,
    setting_value MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users: composite PK (platform, telegram_id) — telegram_id stores messenger numeric id for both platforms
SET @has_plat := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'platform');
SET @s := IF(@has_plat > 0, 'SELECT 1', 'ALTER TABLE users ADD COLUMN platform ENUM(\'telegram\',\'bale\') NOT NULL DEFAULT \'telegram\' FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY (platform, telegram_id)');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- States
SET @has_plat := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'states' AND COLUMN_NAME = 'platform');
SET @s := IF(@has_plat > 0, 'SELECT 1', 'ALTER TABLE states ADD COLUMN platform ENUM(\'telegram\',\'bale\') NOT NULL DEFAULT \'telegram\' FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY (platform, user_id)');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Orders
SET @has_plat := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders_config' AND COLUMN_NAME = 'platform');
SET @s := IF(@has_plat > 0, 'SELECT 1', 'ALTER TABLE orders_config ADD COLUMN platform ENUM(\'telegram\',\'bale\') NOT NULL DEFAULT \'telegram\' AFTER id');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Wallet topups: unique per (public_id, platform)
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wallet_topups' AND INDEX_NAME = 'uq_topup_public');
SET @s := IF(@idx > 0, 'ALTER TABLE wallet_topups DROP INDEX uq_topup_public', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_plat := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wallet_topups' AND COLUMN_NAME = 'platform');
SET @s := IF(@has_plat > 0, 'SELECT 1', 'ALTER TABLE wallet_topups ADD COLUMN platform ENUM(\'telegram\',\'bale\') NOT NULL DEFAULT \'telegram\' AFTER id');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx2 := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wallet_topups' AND INDEX_NAME = 'uq_topup_public_plat');
SET @s := IF(@idx2 > 0, 'SELECT 1', 'ALTER TABLE wallet_topups ADD UNIQUE KEY uq_topup_public_plat (public_id, platform)');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Referral payouts scoped by platform
SET @has_plat := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'referral_payouts' AND COLUMN_NAME = 'platform');
SET @s := IF(@has_plat > 0, 'SELECT 1', 'ALTER TABLE referral_payouts ADD COLUMN platform ENUM(\'telegram\',\'bale\') NOT NULL DEFAULT \'telegram\' AFTER id');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
