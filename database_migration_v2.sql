-- DonutNetBot v1 → v2 migration (safe to re-run; skips existing columns/indexes/keys)
SET NAMES utf8mb4;

-- ---------- helpers: run dynamic DDL only when needed ----------
-- users.kb_anchor_message_id
SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'users'
       AND COLUMN_NAME = 'kb_anchor_message_id') > 0,
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN kb_anchor_message_id INT NULL'
  )
);
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders_config.public_id (nullable first; tightened below)
SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orders_config'
       AND COLUMN_NAME = 'public_id') > 0,
    'SELECT 1',
    'ALTER TABLE orders_config ADD COLUMN public_id CHAR(12) NULL'
  )
);
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders_config.status
SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orders_config'
       AND COLUMN_NAME = 'status') > 0,
    'SELECT 1',
    "ALTER TABLE orders_config ADD COLUMN status ENUM('pending','fulfilled') NOT NULL DEFAULT 'fulfilled'"
  )
);
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders_config.plan_config_id
SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orders_config'
       AND COLUMN_NAME = 'plan_config_id') > 0,
    'SELECT 1',
    'ALTER TABLE orders_config ADD COLUMN plan_config_id BIGINT UNSIGNED NULL'
  )
);
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- orders_config.idx_orders_pending_plan
SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orders_config'
       AND INDEX_NAME = 'idx_orders_pending_plan') > 0,
    'SELECT 1',
    'ALTER TABLE orders_config ADD INDEX idx_orders_pending_plan (plan_id, status)'
  )
);
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill public_id for legacy rows only (NOT NULL default on new `status` handles old orders)
UPDATE orders_config SET public_id = RIGHT(CONCAT('000000000000', id), 12) WHERE public_id IS NULL;

ALTER TABLE orders_config MODIFY payload TEXT NULL;
ALTER TABLE orders_config MODIFY public_id CHAR(12) NOT NULL;

-- orders_config.uq_order_public
SET @s = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'orders_config'
       AND CONSTRAINT_NAME = 'uq_order_public') > 0,
    'SELECT 1',
    'ALTER TABLE orders_config ADD UNIQUE KEY uq_order_public (public_id)'
  )
);
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE wallet_topups MODIFY COLUMN status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending';

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
