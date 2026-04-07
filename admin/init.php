<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';

require_once dirname(__DIR__) . '/core/BotPlatform.php';
require_once dirname(__DIR__) . '/core/Db.php';

$pdo = Db::pdo($config['db']);

require_once dirname(__DIR__) . '/core/repo/SettingsRepository.php';
$settingsRepo = new SettingsRepository($pdo);
$config = SettingsRepository::mergeIntoConfig($config, $settingsRepo->allFlat());

require_once dirname(__DIR__) . '/core/I18n.php';
$langDir = dirname(__DIR__) . '/lang';
$i18nMerge = [];
$i18nRaw = trim($settingsRepo->get('i18n_json_telegram'));
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
try {
    I18n::boot((string) ($config['locale'] ?? 'fa'), $langDir, $i18nMerge !== [] ? $i18nMerge : null);
} catch (Throwable $e) {
    error_log('dn admin i18n boot failed: ' . $e->getMessage());
    I18n::boot('fa', $langDir, $i18nMerge !== [] ? $i18nMerge : null);
}

require_once dirname(__DIR__) . '/core/Log.php';
require_once dirname(__DIR__) . '/core/Util.php';
require_once dirname(__DIR__) . '/core/MessengerApi.php';
require_once dirname(__DIR__) . '/core/repo/UserRepository.php';
require_once dirname(__DIR__) . '/core/repo/StateRepository.php';
require_once dirname(__DIR__) . '/core/repo/PlanRepository.php';
require_once dirname(__DIR__) . '/core/repo/PlanConfigRepository.php';
require_once dirname(__DIR__) . '/core/repo/TopupRepository.php';
require_once dirname(__DIR__) . '/core/repo/OrderRepository.php';
require_once dirname(__DIR__) . '/core/repo/ReferralRepository.php';
require_once dirname(__DIR__) . '/core/PurchaseService.php';

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

$users = new UserRepository($pdo);
$orders = new OrderRepository($pdo);
$plans = new PlanRepository($pdo);
$configs = new PlanConfigRepository($pdo);
$topups = new TopupRepository($pdo);
$referrals = new ReferralRepository($pdo);
$purchase = new PurchaseService($pdo, $orders, $configs, $users, $referrals, BotPlatform::TELEGRAM, $config, $messengerApis);

return [
    'config' => $config,
    'pdo' => $pdo,
    'settingsRepo' => $settingsRepo,
    'users' => $users,
    'orders' => $orders,
    'plans' => $plans,
    'configs' => $configs,
    'topups' => $topups,
    'referrals' => $referrals,
    'purchase' => $purchase,
    'messengerApis' => $messengerApis,
];
