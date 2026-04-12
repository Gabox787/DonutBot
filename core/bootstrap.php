<?php

declare(strict_types=1);

require_once __DIR__ . '/BotPlatform.php';

$dnPlatform = defined('DN_PLATFORM') ? BotPlatform::normalize((string) constant('DN_PLATFORM')) : BotPlatform::TELEGRAM;

$config = require dirname(__DIR__) . '/config.php';

require_once __DIR__ . '/Db.php';
$pdo = Db::pdo($config['db']);

require_once __DIR__ . '/repo/SettingsRepository.php';
$settingsRepo = new SettingsRepository($pdo);
$config = SettingsRepository::mergeIntoConfig($config, $settingsRepo->allFlat());

$langDir = dirname(__DIR__) . '/lang';
$locale = BotPlatform::isBale($dnPlatform)
    ? (string) ($config['locale_bale'] ?? 'fa_bale')
    : (string) ($config['locale'] ?? 'fa');

$i18nMerge = [];
$i18nKey = BotPlatform::isBale($dnPlatform) ? 'i18n_json_bale' : 'i18n_json_telegram';
$i18nRaw = trim($settingsRepo->get($i18nKey));
if ($i18nRaw !== '') {
    $decoded = json_decode($i18nRaw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $i18nMerge[$k] = $v;
            }
        }
    }
}

require_once __DIR__ . '/I18n.php';
try {
    I18n::boot($locale, $langDir, $i18nMerge !== [] ? $i18nMerge : null);
} catch (\Throwable $e) {
    error_log('dn i18n boot failed (' . $locale . '): ' . $e->getMessage());
    $fallback = BotPlatform::isBale($dnPlatform) ? 'fa_bale' : 'fa';
    I18n::boot($fallback, $langDir, $i18nMerge !== [] ? $i18nMerge : null);
}

require_once __DIR__ . '/Log.php';
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/MessengerApi.php';

/** @var array<string, MessengerApi> */
$messengerApis = [];
$tTok = trim((string) ($config['bot_token'] ?? ''));
if ($tTok !== '') {
    $messengerApis[BotPlatform::TELEGRAM] = new MessengerApi(
        $tTok,
        (string) ($config['telegram_api_base'] ?? MessengerApi::BASE_TELEGRAM)
    );
}
$bTok = trim((string) ($config['bale_bot_token'] ?? ''));
if ($bTok !== '') {
    $messengerApis[BotPlatform::BALE] = new MessengerApi(
        $bTok,
        (string) ($config['bale_api_base'] ?? MessengerApi::BASE_BALE),
        true
    );
}
if (!isset($messengerApis[$dnPlatform])) {
    http_response_code(500);
    echo 'config: bot token missing for platform ' . $dnPlatform . ' (set in config.local.php or admin → تنظیمات)';
    exit;
}

require_once __DIR__ . '/repo/UserRepository.php';
require_once __DIR__ . '/repo/StateRepository.php';
require_once __DIR__ . '/repo/ProductRepository.php';
require_once __DIR__ . '/repo/ProductStockRepository.php';
require_once __DIR__ . '/repo/TopupRepository.php';
require_once __DIR__ . '/repo/OrderRepository.php';
require_once __DIR__ . '/repo/ReferralRepository.php';
require_once __DIR__ . '/PurchaseService.php';
require_once __DIR__ . '/BotKernel.php';

$usersRepo = new UserRepository($pdo);
$orders = new OrderRepository($pdo);
$productStock = new ProductStockRepository($pdo);
$referrals = new ReferralRepository($pdo);
$purchase = new PurchaseService($pdo, $orders, $productStock, $usersRepo, $referrals, $dnPlatform, $config, $messengerApis);
$kernel = new BotKernel(
    $messengerApis,
    $dnPlatform,
    $usersRepo,
    new StateRepository($pdo),
    new ProductRepository($pdo),
    new TopupRepository($pdo),
    $orders,
    $productStock,
    $purchase,
    $referrals,
    $config,
);

function handleUpdate(?array $update): void
{
    global $kernel;
    $kernel->handle($update);
}
