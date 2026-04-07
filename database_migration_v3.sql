-- DonutNetBot v3: test configs, custom GB, order metadata, remark (re-runnable)
SET NAMES utf8mb4;

-- plans: custom GB + test product
SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'allow_custom_gb') > 0,
    'SELECT 1',
    'ALTER TABLE plans ADD COLUMN allow_custom_gb TINYINT(1) NOT NULL DEFAULT 0'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'gb_min') > 0,
    'SELECT 1',
    'ALTER TABLE plans ADD COLUMN gb_min INT UNSIGNED NOT NULL DEFAULT 1'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'gb_max') > 0,
    'SELECT 1',
    'ALTER TABLE plans ADD COLUMN gb_max INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0 = use plans.gb as max'''
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'test_enabled') > 0,
    'SELECT 1',
    'ALTER TABLE plans ADD COLUMN test_enabled TINYINT(1) NOT NULL DEFAULT 0'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'test_price_toman') > 0,
    'SELECT 1',
    'ALTER TABLE plans ADD COLUMN test_price_toman BIGINT UNSIGNED NOT NULL DEFAULT 0'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- orders_config
SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders_config' AND COLUMN_NAME = 'order_kind') > 0,
    'SELECT 1',
    "ALTER TABLE orders_config ADD COLUMN order_kind ENUM('standard','test') NOT NULL DEFAULT 'standard'"
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders_config' AND COLUMN_NAME = 'gb_ordered') > 0,
    'SELECT 1',
    'ALTER TABLE orders_config ADD COLUMN gb_ordered INT UNSIGNED NULL'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders_config' AND COLUMN_NAME = 'user_remark') > 0,
    'SELECT 1',
    'ALTER TABLE orders_config ADD COLUMN user_remark VARCHAR(512) NULL'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders_config' AND COLUMN_NAME = 'test_expires_at') > 0,
    'SELECT 1',
    'ALTER TABLE orders_config ADD COLUMN test_expires_at TIMESTAMP NULL DEFAULT NULL'
  )
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
