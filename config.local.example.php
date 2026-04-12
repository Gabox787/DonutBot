<?php

declare(strict_types=1);

/**
 * Copy to config.local.php and fill in. Do not commit config.local.php.
 * Or open admin/install.php in the browser: it can create config.local.php (DB only) and set the panel password after you run database.sql.
 */
return [
    'bot_token' => 'YOUR_BOT_TOKEN',
    /* Optional: Bale bot — webhook hits hook_bale.php (same PHP codebase). */
    'bale_bot_token' => '',
    'bale_bot_username' => '',
    /* Numeric Telegram user IDs who receive top-up alerts and approve/reject buttons. */
    'admin_telegram_ids' => [123456789],
    'admin_bale_ids' => [],
    'db' => [
        'host' => 'localhost',
        'name' => 'your_database',
        'user' => 'your_user',
        'pass' => 'your_password',
    ],
    'payment' => [
        'card_number' => '6037-XXXX-XXXX-XXXX',
        'card_holder' => 'نام صاحب کارت',
        'pay_window_minutes' => 10,
    ],
    'support_username' => 'your_support_username',
    /* Optional: different @usernames per app; if omitted, support_username is used. */
    'support_username_telegram' => '',
    'support_username_bale' => '',
    /* e.g. donutnet — users must join before using the bot */
    'required_channel_username' => 'donutnet',
    /* Same handle as BotFather (no @) */
    'telegram_bot_username' => 'YourBotUserName',
    'referral_percent_of_sale' => 5,
    'admin_web_password' => 'change-this-strong-password',
    'bot_brand_name' => 'TG Donut Bot',
    'commands_setup_key' => 'change-me-long-random-string',
];
