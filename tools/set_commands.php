<?php

declare(strict_types=1);

/**
 * Register Bot Menu commands (Telegram + Bale if tokens exist).
 * Open: https://your-domain/tools/set_commands.php?key=YOUR_KEY
 */
$config = require dirname(__DIR__) . '/config.php';

require_once dirname(__DIR__) . '/core/Db.php';
require_once dirname(__DIR__) . '/core/repo/SettingsRepository.php';
require_once dirname(__DIR__) . '/core/BotPlatform.php';
require_once dirname(__DIR__) . '/core/MessengerApi.php';

$pdo = Db::pdo($config['db']);
$settingsRepo = new SettingsRepository($pdo);
$config = SettingsRepository::mergeIntoConfig($config, $settingsRepo->allFlat());

$key = (string) ($config['commands_setup_key'] ?? '');
if ($key === '' || ($_GET['key'] ?? '') !== $key) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$commands = [
    ['command' => 'start', 'description' => 'خانه — منوی اصلی'],
    ['command' => 'buy', 'description' => 'خرید'],
    ['command' => 'test', 'description' => 'تست'],
    ['command' => 'wallet', 'description' => 'کیف پول'],
    ['command' => 'configs', 'description' => 'سفارش‌ها / کانفیگ‌ها'],
    ['command' => 'income', 'description' => 'کسب درآمد — معرفی'],
    ['command' => 'support', 'description' => 'پشتیبانی'],
    ['command' => 'faq', 'description' => 'سوالات'],
];

$out = [];
$payload = ['commands' => json_encode($commands, JSON_UNESCAPED_UNICODE)];

$tTok = trim((string) ($config['bot_token'] ?? ''));
if ($tTok !== '') {
    $tg = new MessengerApi($tTok, (string) ($config['telegram_api_base'] ?? MessengerApi::BASE_TELEGRAM));
    $out['telegram'] = $tg->api('setMyCommands', $payload);
}

$bTok = trim((string) ($config['bale_bot_token'] ?? ''));
if ($bTok !== '') {
    $bale = new MessengerApi($bTok, (string) ($config['bale_api_base'] ?? MessengerApi::BASE_BALE));
    $out['bale'] = $bale->api('setMyCommands', $payload);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
