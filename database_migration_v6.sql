-- v6: admin top-up message sync handles; order access revoke metadata
SET NAMES utf8mb4;

ALTER TABLE wallet_topups
    ADD COLUMN admin_notify_handles MEDIUMTEXT NULL COMMENT 'JSON: [{platform, chat_id, message_id, is_photo}]' AFTER admin_note;

ALTER TABLE orders_config
    ADD COLUMN access_revoked_at TIMESTAMP NULL DEFAULT NULL AFTER access_status,
    ADD COLUMN access_revoke_reason VARCHAR(512) NULL AFTER access_revoked_at;
