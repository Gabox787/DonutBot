<?php

declare(strict_types=1);

/**
 * Base config — edited for Mac Mini M4 local setup.
 */
return [
    'bot_token' => '8618099968:AAE_-JdQI4y3qoGbxHgSM-iDRukeQn1uUQY',
    'bale_bot_token' => '',
    'admin_telegram_ids' => [715162339],
    'admin_bale_ids' => [],
    'telegram_api_base' => 'https://api.telegram.org',
    'bale_api_base' => 'https://tapi.bale.ai',
    'db' => [
        'host' => '127.0.0.1', // Принудительно используем IP для Mac
        'name' => 'donut_bot',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'locale' => 'en',
    'locale_bale' => 'fa_bale',
    'payment' => [
        'card_number' => '',
        'card_holder' => '',
        'pay_window_minutes' => 10,
    ],
    'support_username' => '',
    'support_username_telegram' => '',
    'support_username_bale' => '',
    'required_channel_username' => '',
    'required_channel_username_bale' => '',
    'channel_join_url_telegram' => '',
    'channel_join_url_bale' => '',
    'referral_link_template_telegram' => '',
    'referral_link_template_bale' => '',
    'telegram_bot_username' => '',
    'bale_bot_username' => '',
    'referral_percent_of_sale' => 5,
    'faq_text_key' => 'faq_body',
    'help_text_key' => 'help_body',
    'bot_brand_name' => 'My Paper Trading Bot',
    'timezone' => 'Europe/Kyiv',
    'admin_web_password' => 'admin123',
    'log_file' => __DIR__ . '/storage/logs/bot.log',
    'commands_setup_key' => 'setup123',
];
