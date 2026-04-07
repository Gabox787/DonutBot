<?php

declare(strict_types=1);

/**
 * Base config — override with config.local.php (copy from config.local.example.php).
 */
return array_replace_recursive(
    [
        'bot_token' => '',
        /** Bale bot token (optional; can be set only in DB admin settings). */
        'bale_bot_token' => '',
        'admin_telegram_ids' => [],
        /** Admin user IDs on Bale (optional). */
        'admin_bale_ids' => [],
        'telegram_api_base' => 'https://api.telegram.org',
        'bale_api_base' => 'https://tapi.bale.ai',
        'db' => [
            'host' => 'localhost',
            'name' => '',
            'user' => '',
            'pass' => '',
            'charset' => 'utf8mb4',
        ],
        'locale' => 'fa',
        /** Language file for Bale (donut-themed copy in lang/fa_bale.php). */
        'locale_bale' => 'fa_bale',
        'payment' => [
            'card_number' => '',
            'card_holder' => '',
            'pay_window_minutes' => 10,
        ],
        'support_username' => '',
        /** Telegram @username of channel users must join (no @ prefix ok). Empty = no gate. */
        'required_channel_username' => '',
        /** Bale channel @username for membership gate (separate from Telegram). */
        'required_channel_username_bale' => '',
        /** Full URLs for “join channel” button if t.me links are wrong on Bale. */
        'channel_join_url_telegram' => '',
        'channel_join_url_bale' => '',
        /** Placeholders: {id} messenger user id, {bot} username */
        'referral_link_template_telegram' => '',
        'referral_link_template_bale' => '',
        /** Bot username without @ — used for referral links https://t.me/{username}?start=ref_{id} */
        'telegram_bot_username' => '',
        /** Bale bot username without @ (default referral link uses ble.ir); optional if referral_link_template_bale is set */
        'bale_bot_username' => '',
        /** Percent of referred user's standard purchase (floor) credited to referrer wallet */
        'referral_percent_of_sale' => 5,
        'faq_text_key' => 'faq_body',
        /** I18n key for /help and «راهنما» (falls back to faq_text_key then help_body). */
        'help_text_key' => 'help_body',
        /** Multiline "عنوان|https://..." tutorial links appended in Help. */
        'help_links_raw' => '',
        /** Shown in default config URL fragment (#remark) */
        'bot_brand_name' => 'DonutNetBot',
        /** PHP timezone for “end of day” test access */
        'timezone' => 'Asia/Tehran',
        /** Plain password for /admin web panel (set in config.local.php) */
        'admin_web_password' => '',
        'log_file' => __DIR__ . '/storage/logs/bot.log',
        'v2ray_defaults' => [
            'server' => 'your-server.example.com',
            'port' => '443',
        ],
        /** Non-empty to unlock tools/set_commands.php?key=… */
        'commands_setup_key' => '',
    ],
    file_exists(__DIR__ . '/config.local.php')
        ? require __DIR__ . '/config.local.php'
        : []
);
