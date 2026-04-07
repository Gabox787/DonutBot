<?php

declare(strict_types=1);

session_start();

$app = require __DIR__ . '/init.php';
require_once dirname(__DIR__) . '/core/BotPlatform.php';

/** @var array<string, mixed> $config */
$config = $app['config'];
$pdo = $app['pdo'];
$users = $app['users'];
$orders = $app['orders'];
$plans = $app['plans'];
$configs = $app['configs'];
$topups = $app['topups'];
$purchase = $app['purchase'];
/** @var array<string, MessengerApi> $messengerApis */
$messengerApis = $app['messengerApis'];
$settingsRepo = $app['settingsRepo'];

$adminPass = (string) ($config['admin_web_password'] ?? '');
if ($adminPass === '') {
    http_response_code(503);
    echo 'Configure admin_web_password in config.local.php';
    exit;
}

$p = (string) ($_GET['p'] ?? 'dashboard');
$msg = '';
$err = '';

function admin_auth_ok(string $expect, string $got): bool
{
    return hash_equals($expect, $got);
}

if ($p === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php?p=login');
    exit;
}

$logged = !empty($_SESSION['dn_admin']);

if (!$logged && $p !== 'login') {
    header('Location: index.php?p=login');
    exit;
}

if ($p === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $pairs = [];
    foreach (['telegram_bot_token', 'bale_bot_token', 'admin_web_password'] as $secretKey) {
        $v = trim((string) ($_POST[$secretKey] ?? ''));
        if ($v !== '') {
            $pairs[$secretKey] = $v;
        }
    }
    $textKeys = [
        'telegram_bot_username', 'bale_bot_username',
        'referral_link_template_telegram', 'referral_link_template_bale',
        'required_channel_username', 'required_channel_username_bale',
        'channel_join_url_telegram', 'channel_join_url_bale',
        'telegram_api_base', 'bale_api_base',
        'admin_telegram_ids', 'admin_bale_ids',
        'payment_card_number', 'payment_card_holder', 'pay_window_minutes',
        'support_username', 'timezone', 'bot_brand_name', 'faq_text_key',
        'help_text_key', 'help_links_raw',
        'commands_setup_key', 'referral_percent_of_sale',
        'locale_telegram', 'locale_bale',
    ];
    foreach ($textKeys as $k) {
        $pairs[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $settingsRepo->setMany($pairs);
    $msg = 'تنظیمات ذخیره شد.';
    $config = SettingsRepository::mergeIntoConfig(require dirname(__DIR__) . '/config.php', $settingsRepo->allFlat());
    $app['config'] = $config;
}

if ($p === 'i18n' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_i18n'])) {
    require_once __DIR__ . '/i18n_ui.inc.php';
    $msg = dn_admin_i18n_save($settingsRepo, $config, $_POST);
}

if ($p === 'active_access' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_access'])) {
    $oid = (int) ($_POST['order_id'] ?? 0);
    $preset = trim((string) ($_POST['reason_preset'] ?? ''));
    $custom = trim((string) ($_POST['reason_custom'] ?? ''));
    $reason = $custom !== '' ? $custom : $preset;
    if ($reason === '') {
        $reason = 'دسترسی سرویس شما توسط مدیریت غیرفعال شد.';
    }
    $row = $orders->deactivateAccessWithReason($oid, $reason);
    if ($row === null) {
        $err = 'غیرفعال‌سازی ناموفق (سفارش نیست یا قبلاً غیرفعال شده).';
    } else {
        $plat = BotPlatform::normalize((string) ($row['platform'] ?? BotPlatform::TELEGRAM));
        $api = $messengerApis[$plat] ?? null;
        if ($api !== null) {
            $api->sendMessage(
                (int) $row['user_id'],
                I18n::fmt('user_access_revoked', [
                    'order' => Util::e((string) ($row['public_id'] ?? '')),
                    'reason' => Util::e($reason),
                ]),
                null,
                'HTML'
            );
            $msg = 'دسترسی غیرفعال شد و به کاربر اعلام شد.';
        } else {
            $msg = 'دسترسی در دیتابیس غیرفعال شد؛ توکن ربات این پلتفرم نیست و پیامی ارسال نشد.';
        }
    }
}

if ($p === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pw = (string) ($_POST['password'] ?? '');
        if (admin_auth_ok($adminPass, $pw)) {
            $_SESSION['dn_admin'] = 1;
            header('Location: index.php');
            exit;
        }
        $err = 'رمز نادرست است.';
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود پنل</title><link rel="stylesheet" href="assets/admin.css"></head><body class="login">';
    echo '<form class="card" method="post" action="index.php?p=login">';
    echo '<h1>DonutNet — پنل وب</h1>';
    if ($err !== '') {
        echo '<p class="err">' . htmlspecialchars($err) . '</p>';
    }
    echo '<label>رمز عبور<input type="password" name="password" autocomplete="current-password" required></label>';
    echo '<button type="submit">ورود</button></form></body></html>';
    exit;
}

if ($p === 'plans' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_plan'])) {
    $slug = trim((string) ($_POST['slug'] ?? ''));
    if ($slug === '') {
        $slug = 'p' . substr(bin2hex(random_bytes(5)), 0, 10);
    }
    $row = [
        'slug' => $slug,
        'title' => (string) ($_POST['title'] ?? ''),
        'title_bale' => (string) ($_POST['title_bale'] ?? ''),
        'description' => (string) ($_POST['description'] ?? ''),
        'description_bale' => (string) ($_POST['description_bale'] ?? ''),
        'gb' => (int) ($_POST['gb'] ?? 0),
        'price_toman' => (int) ($_POST['price_toman'] ?? 0),
        'sort_order' => (int) ($_POST['sort_order'] ?? $plans->nextSortOrder()),
        'is_featured' => !empty($_POST['is_featured']),
        'is_active' => !empty($_POST['is_active']),
        'allow_custom_gb' => !empty($_POST['allow_custom_gb']),
        'gb_min' => (int) ($_POST['gb_min'] ?? 1),
        'gb_max' => (int) ($_POST['gb_max'] ?? 0),
        'test_enabled' => !empty($_POST['test_enabled']),
        'test_price_toman' => (int) ($_POST['test_price_toman'] ?? 0),
        'test_config_url' => (string) ($_POST['test_config_url'] ?? ''),
        'user_limit' => (string) ($_POST['user_limit'] ?? ''),
        'duration_days' => (string) ($_POST['duration_days'] ?? ''),
        'config_template' => (string) ($_POST['config_template'] ?? ''),
    ];
    $id = $plans->insertFullFromWeb($row);
    $msg = 'پلن ایجاد شد — id ' . $id;
    $p = 'plan';
    $_GET['id'] = (string) $id;
}

if ($p === 'plan' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $row = [
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'title' => (string) ($_POST['title'] ?? ''),
            'title_bale' => (string) ($_POST['title_bale'] ?? ''),
            'description' => (string) ($_POST['description'] ?? ''),
            'description_bale' => (string) ($_POST['description_bale'] ?? ''),
            'gb' => (int) ($_POST['gb'] ?? 0),
            'price_toman' => (int) ($_POST['price_toman'] ?? 0),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_featured' => !empty($_POST['is_featured']),
            'is_active' => !empty($_POST['is_active']),
            'allow_custom_gb' => !empty($_POST['allow_custom_gb']),
            'gb_min' => (int) ($_POST['gb_min'] ?? 1),
            'gb_max' => (int) ($_POST['gb_max'] ?? 0),
            'test_enabled' => !empty($_POST['test_enabled']),
            'test_price_toman' => (int) ($_POST['test_price_toman'] ?? 0),
            'test_config_url' => (string) ($_POST['test_config_url'] ?? ''),
            'user_limit' => (string) ($_POST['user_limit'] ?? ''),
            'duration_days' => (string) ($_POST['duration_days'] ?? ''),
            'config_template' => (string) ($_POST['config_template'] ?? ''),
        ];
        if ($plans->saveFullFromWeb($id, $row)) {
            $msg = 'ذخیره شد.';
        } else {
            $err = 'ذخیره ناموفق.';
        }
    }
}

if ($p === 'inventory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = (int) ($_POST['plan_id'] ?? 0);
    $raw = (string) ($_POST['lines'] ?? '');
    $lines = array_filter(array_map('trim', preg_split('/\R/u', $raw) ?: []));
    if ($planId <= 0 || $lines === []) {
        $err = 'پلن و حداقل یک خط لازم است.';
    } else {
        $n = $configs->bulkInsertLines($planId, $lines);
        $planRow = $plans->getByIdAny($planId);
        if ($planRow === null) {
            $planRow = ['id' => $planId, 'title' => '', 'gb' => 0];
        }
        $fulfilled = $purchase->drainPendingForPlan($planId, $planRow);
        foreach ($fulfilled as $row) {
            $plat = BotPlatform::normalize((string) ($row['platform'] ?? BotPlatform::TELEGRAM));
            $api = $messengerApis[$plat] ?? null;
            if ($api === null) {
                continue;
            }
            $uid = (int) $row['user_id'];
            $extra = '';
            if (($row['order_kind'] ?? '') === 'test' && !empty($row['test_expires_at'])) {
                $extra = "\n⏳ اعتبار تست: " . htmlspecialchars((string) $row['test_expires_at'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $payload = (string) $row['payload'];
            if (BotPlatform::isBale($plat)) {
                $body = I18n::fmt('order_fulfilled_notify_bale', [
                    'order_id' => Util::e((string) $row['public_id']),
                ]) . $extra;
                $api->sendMessage($uid, $body, null, 'HTML');
                $plain = Util::payloadPlainForFile($payload);
                $fn = 'order-' . substr(hash('sha256', $plain), 0, 10) . '.txt';
                $api->sendDocument($uid, $fn, $plain, 'سفارش شما', null, 'HTML');
            } else {
                $body = I18n::fmt('order_fulfilled_notify', [
                    'order_id' => Util::e((string) $row['public_id']),
                    'payload' => Util::e($payload),
                ]) . $extra;
                $api->sendMessage($uid, $body, null, 'HTML');
            }
        }
        $msg = $n . ' خط اضافه شد؛ ' . count($fulfilled) . ' تکمیل شد (تلگرام / بله با توکن فعال).';
    }
}

if ($logged && $p === 'i18n' && isset($_GET['export'])) {
    require_once __DIR__ . '/i18n_ui.inc.php';
    dn_admin_i18n_export_json($settingsRepo, (($_GET['tab'] ?? '') === 'bale') ? 'bale' : 'telegram');
}

$stats = $orders->adminStats();

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DonutNet پنل</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<aside class="nav">
    <div class="brand">DonutNet</div>
    <a href="index.php" class="<?php echo $p === 'dashboard' ? 'active' : ''; ?>">داشبورد</a>
    <a href="index.php?p=plans" class="<?php echo $p === 'plans' || $p === 'plan' ? 'active' : ''; ?>">پلن‌ها</a>
    <a href="index.php?p=users" class="<?php echo $p === 'users' ? 'active' : ''; ?>">کاربران</a>
    <a href="index.php?p=orders" class="<?php echo $p === 'orders' ? 'active' : ''; ?>">سفارش‌ها</a>
    <a href="index.php?p=active_access" class="<?php echo $p === 'active_access' ? 'active' : ''; ?>">دسترسی فعال</a>
    <a href="index.php?p=inventory" class="<?php echo $p === 'inventory' ? 'active' : ''; ?>">انبار کانفیگ</a>
    <a href="index.php?p=topups" class="<?php echo $p === 'topups' ? 'active' : ''; ?>">شارژها</a>
    <a href="index.php?p=settings" class="<?php echo $p === 'settings' ? 'active' : ''; ?>">تنظیمات</a>
    <a href="index.php?p=i18n" class="<?php echo $p === 'i18n' ? 'active' : ''; ?>">متن‌های ربات</a>
    <a href="index.php?p=logout" class="muted">خروج</a>
</aside>
<main class="main<?php echo $p === 'i18n' ? ' main--i18n' : ''; ?>">
<?php
if ($msg !== '') {
    echo '<div class="flash ok">' . Util::e($msg) . '</div>';
}
if ($err !== '') {
    echo '<div class="flash err">' . Util::e($err) . '</div>';
}

if ($p === 'i18n') {
    require_once __DIR__ . '/i18n_ui.inc.php';
    dn_admin_i18n_render($settingsRepo, $config, (($_GET['tab'] ?? '') === 'bale') ? 'bale' : 'telegram');
}

if ($p === 'dashboard') {
    echo '<h1>داشبورد</h1><div class="grid">';
    echo '<div class="stat"><span>فروش نهایی</span><strong>' . (int) $stats['orders_fulfilled'] . '</strong></div>';
    echo '<div class="stat"><span>سفارش باز</span><strong>' . (int) $stats['orders_pending'] . '</strong></div>';
    echo '<div class="stat"><span>درآمد (تومان)</span><strong>' . number_format((int) $stats['revenue_toman']) . '</strong></div>';
    echo '<div class="stat"><span>کانفیگ آزاد</span><strong>' . (int) $stats['stock_available'] . '</strong></div>';
    echo '</div>';
    echo '<p class="hint">وب‌هوک تلگرام: <code dir="ltr">index.php</code> — بله: <code dir="ltr">hook_bale.php</code> (هر دو باید به این هاست اشاره کنند). تأیید رسید و دکمه‌های ادمین هنوز از خود ربات تلگرام/بله می‌آید.</p>';
}

if ($p === 'settings') {
    $g = static fn (string $k, string $d = '') => Util::e($settingsRepo->get($k, $d));
    echo '<h1>تنظیمات</h1><p class="hint">توکن‌ها و رمز پنل را فقط وقتی عوض می‌کنید پر کنید؛ خالی = همان مقدار قبلی می‌ماند. بقیه فیلدها با ذخیره همان‌طور که هست در دیتابیس ذخیره می‌شوند.</p>';
    echo '<form method="post" class="form settings-form">';
    echo '<input type="hidden" name="save_settings" value="1">';
    echo '<h3>ربات و API</h3>';
    echo '<label>توکن تلگرام<input type="password" name="telegram_bot_token" autocomplete="off" placeholder="خالی = بدون تغییر در DB" dir="ltr"></label>';
    echo '<label>توکن بله<input type="password" name="bale_bot_token" autocomplete="off" placeholder="خالی = بدون تغییر" dir="ltr"></label>';
    echo '<div class="row2"><label>پایه API تلگرام<input name="telegram_api_base" dir="ltr" value="' . $g('telegram_api_base', (string) ($config['telegram_api_base'] ?? '')) . '"></label>';
    echo '<label>پایه API بله<input name="bale_api_base" dir="ltr" value="' . $g('bale_api_base', (string) ($config['bale_api_base'] ?? '')) . '"></label></div>';
    echo '<div class="row2"><label>یوزرنیم ربات تلگرام (بدون @)<input name="telegram_bot_username" value="' . $g('telegram_bot_username', (string) ($config['telegram_bot_username'] ?? '')) . '"></label>';
    echo '<label>یوزرنیم ربات بله (بدون @)<input name="bale_bot_username" value="' . $g('bale_bot_username', (string) ($config['bale_bot_username'] ?? '')) . '"></label></div>';
    echo '<label>قالب لینک معرفی تلگرام — <code dir="ltr">{id}</code> و <code dir="ltr">{bot}</code><textarea name="referral_link_template_telegram" rows="2" dir="ltr" placeholder="https://t.me/{bot}?start=ref_{id}">' . $g('referral_link_template_telegram') . '</textarea></label>';
    echo '<label>قالب لینک معرفی بله<textarea name="referral_link_template_bale" rows="2" dir="ltr" placeholder="https://ble.ir/{bot}?start=ref_{id}">' . $g('referral_link_template_bale') . '</textarea></label>';
    echo '<h3>ادمین و کانال</h3>';
    echo '<label>آیدی عددی ادمین‌های تلگرام (با کاما)<input name="admin_telegram_ids" dir="ltr" value="' . $g('admin_telegram_ids') . '"></label>';
    echo '<label>آیدی عددی ادمین‌های بله (با کاما)<input name="admin_bale_ids" dir="ltr" value="' . $g('admin_bale_ids') . '"></label>';
    echo '<div class="row2"><label>کانال اجباری تلگرام (بدون @)<input name="required_channel_username" value="' . $g('required_channel_username', (string) ($config['required_channel_username'] ?? '')) . '"></label>';
    echo '<label>کانال اجباری بله<input name="required_channel_username_bale" value="' . $g('required_channel_username_bale') . '"></label></div>';
    echo '<label>لینک عضویت تلگرام (اختیاری)<input name="channel_join_url_telegram" dir="ltr" value="' . $g('channel_join_url_telegram') . '"></label>';
    echo '<label>لینک عضویت بله (اختیاری)<input name="channel_join_url_bale" dir="ltr" value="' . $g('channel_join_url_bale') . '"></label>';
    echo '<h3>پرداخت و متن</h3>';
    echo '<div class="row2"><label>شماره کارت<input name="payment_card_number" dir="ltr" value="' . $g('payment_card_number', (string) ($config['payment']['card_number'] ?? '')) . '"></label>';
    echo '<label>صاحب کارت<input name="payment_card_holder" value="' . $g('payment_card_holder', (string) ($config['payment']['card_holder'] ?? '')) . '"></label></div>';
    echo '<label>مدت پنجره پرداخت (دقیقه)<input type="number" name="pay_window_minutes" value="' . $g('pay_window_minutes', (string) (int) ($config['payment']['pay_window_minutes'] ?? 10)) . '"></label>';
    echo '<label>پشتیبانی @username<input name="support_username" value="' . $g('support_username', (string) ($config['support_username'] ?? '')) . '"></label>';
    echo '<div class="row2"><label>Timezone<input name="timezone" dir="ltr" value="' . $g('timezone', (string) ($config['timezone'] ?? 'Asia/Tehran')) . '"></label>';
    echo '<label>نام برند روی کانفیگ<input name="bot_brand_name" value="' . $g('bot_brand_name', (string) ($config['bot_brand_name'] ?? '')) . '"></label></div>';
    echo '<label>درصد پاداش معرفی<input type="text" name="referral_percent_of_sale" inputmode="decimal" value="' . $g('referral_percent_of_sale', (string) ($config['referral_percent_of_sale'] ?? 5)) . '"></label>';
    echo '<div class="row2"><label>زبان تلگرام (فایل در lang/)<input name="locale_telegram" dir="ltr" value="' . $g('locale_telegram', (string) ($config['locale'] ?? 'fa')) . '"></label>';
    echo '<label>زبان بله<input name="locale_bale" dir="ltr" value="' . $g('locale_bale', (string) ($config['locale_bale'] ?? 'fa_bale')) . '"></label></div>';
    echo '<label>کلید متن قدیمی FAQ (سازگاری)<input name="faq_text_key" value="' . $g('faq_text_key', (string) ($config['faq_text_key'] ?? 'faq_body')) . '"></label>';
    echo '<label>کلید متن «راهنما» در زبان (مثلاً help_body)<input name="help_text_key" value="' . $g('help_text_key', (string) ($config['help_text_key'] ?? 'help_body')) . '"></label>';
    echo '<label>لینک‌های آموزشی راهنما — هر خط: <code dir="ltr">عنوان | https://...</code><textarea name="help_links_raw" rows="4" dir="ltr" style="text-align:left" placeholder="آموزش تلگرام | https://t.me/...">' . $g('help_links_raw', (string) ($config['help_links_raw'] ?? '')) . '</textarea></label>';
    echo '<p class="hint">متن‌های ربات (تلگرام و بله) را از صفحهٔ <a href="index.php?p=i18n"><b>متن‌های ربات</b></a> ویرایش کنید — بدون JSON.</p>';
    echo '<h3>پنل وب</h3>';
    echo '<label>رمز پنل (فقط برای تغییر)<input type="password" name="admin_web_password" autocomplete="new-password"></label>';
    echo '<label>کلید tools/set_commands.php<input name="commands_setup_key" dir="ltr" value="' . $g('commands_setup_key', (string) ($config['commands_setup_key'] ?? '')) . '"></label>';
    echo '<button type="submit">ذخیرهٔ تنظیمات</button></form>';
}

if ($p === 'plans') {
    echo '<h1>پلن‌ها</h1><a class="btn" href="index.php?p=plan&id=0">➕ پلن جدید</a>';
    echo '<table class="tbl"><thead><tr><th>id</th><th>عنوان</th><th>گیگ</th><th>قیمت</th><th>تست</th><th>فعال</th><th></th></tr></thead><tbody>';
    foreach ($plans->listAllForWeb() as $pl) {
        echo '<tr>';
        echo '<td>' . (int) $pl['id'] . '</td>';
        echo '<td>' . Util::e((string) $pl['title']) . '</td>';
        echo '<td>' . (int) $pl['gb'] . '</td>';
        echo '<td>' . number_format((int) $pl['price_toman']) . '</td>';
        echo '<td>' . (!empty($pl['test_enabled']) ? number_format((int) $pl['test_price_toman']) : '—') . '</td>';
        echo '<td>' . (!empty($pl['is_active']) ? '✓' : '✗') . '</td>';
        echo '<td><a href="index.php?p=plan&id=' . (int) $pl['id'] . '">ویرایش</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

if ($p === 'plan') {
    $id = (int) ($_GET['id'] ?? 0);
    $pl = $id > 0 ? $plans->getByIdAny($id) : null;
    $isNew = $pl === null;
    echo '<h1>' . ($isNew ? 'پلن جدید' : 'ویرایش پلن #' . $id) . '</h1>';
    echo '<form method="post" class="form">';
    if (!$isNew) {
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<input type="hidden" name="save_plan" value="1">';
    } else {
        echo '<input type="hidden" name="new_plan" value="1">';
    }
    $f = static fn (string $k, $def = '') => $pl !== null && isset($pl[$k]) ? $pl[$k] : $def;
    echo '<label>slug<input name="slug" value="' . Util::e((string) $f('slug')) . '"></label>';
    echo '<label>عنوان<input name="title" required value="' . Util::e((string) $f('title')) . '"></label>';
    echo '<label>عنوان نمایش در بله (دونات، اختیاری)<input name="title_bale" value="' . Util::e((string) $f('title_bale')) . '"></label>';
    echo '<label>توضیح<textarea name="description" rows="3">' . Util::e((string) $f('description')) . '</textarea></label>';
    echo '<label>توضیح بله / دونات<textarea name="description_bale" rows="5" placeholder="متن طعم‌های مزه‌دار برای بله">' . Util::e((string) $f('description_bale')) . '</textarea></label>';
    echo '<div class="row2"><label>گیگ مرجع<input type="number" name="gb" value="' . (int) $f('gb', 20) . '"></label>';
    echo '<label>قیمت تومان<input type="number" name="price_toman" value="' . (int) $f('price_toman', 0) . '"></label></div>';
    echo '<div class="row2"><label>sort<input type="number" name="sort_order" value="' . (int) $f('sort_order', $plans->nextSortOrder()) . '"></label></div>';
    echo '<label class="chk"><input type="checkbox" name="is_active" value="1"' . (!empty($f('is_active', 1)) ? ' checked' : '') . '> فعال</label>';
    echo '<label class="chk"><input type="checkbox" name="is_featured" value="1"' . (!empty($f('is_featured')) ? ' checked' : '') . '> پیشنهادی</label>';
    echo '<hr><h3>حجم شناور</h3>';
    echo '<label class="chk"><input type="checkbox" name="allow_custom_gb" value="1"' . (!empty($f('allow_custom_gb')) ? ' checked' : '') . '> کاربر گیگ را انتخاب کند</label>';
    echo '<div class="row2"><label>حداقل گیگ<input type="number" name="gb_min" value="' . (int) $f('gb_min', 1) . '"></label>';
    echo '<label>حداکثر گیگ (۰=مرجع از فیلد گیگ)<input type="number" name="gb_max" value="' . (int) $f('gb_max', 0) . '"></label></div>';
    echo '<hr><h3>سقف پلن (اختیاری)</h3>';
    echo '<p class="hint">خالی یا 0 یعنی <b>نامحدود</b>. برای «اقتصادی» معمولاً 1 کاربر و 30 روز.</p>';
    echo '<div class="row2"><label>حداکثر کاربر همزمان (خالی=نامحدود)<input type="number" name="user_limit" min="0" placeholder="مثلاً 1" value="' . Util::e($pl !== null && $pl['user_limit'] !== null && (int) $pl['user_limit'] > 0 ? (string) (int) $pl['user_limit'] : '') . '"></label>';
    echo '<label>مدت سرویس پس از فعال‌سازی — روز (خالی=نامحدود)<input type="number" name="duration_days" min="0" placeholder="مثلاً 30" value="' . Util::e($pl !== null && $pl['duration_days'] !== null && (int) $pl['duration_days'] > 0 ? (string) (int) $pl['duration_days'] : '') . '"></label></div>';
    echo '<hr><h3>تست کانفیگ (لینک ثابت)</h3>';
    echo '<p class="hint">اگر تست فعال است، یک <b>لینک مستقیم vless/vmess</b> بگذارید؛ بدون خط از انبار، بلافاصله بعد از پرداخت به کاربر داده می‌شود.</p>';
    echo '<label class="chk"><input type="checkbox" name="test_enabled" value="1"' . (!empty($f('test_enabled')) ? ' checked' : '') . '> تست فعال</label>';
    echo '<label>قیمت تست (تومان)<input type="number" name="test_price_toman" value="' . (int) $f('test_price_toman', 0) . '"></label>';
    echo '<label>URL تست (vless/vmess/…) <input name="test_config_url" dir="ltr" style="text-align:left" value="' . Util::e((string) $f('test_config_url')) . '" placeholder="vless://..."></label>';
    echo '<label>قالب config فروش عادی (اختیاری)<textarea name="config_template" rows="2">' . Util::e((string) $f('config_template')) . '</textarea></label>';
    echo '<button type="submit">' . ($isNew ? 'ایجاد' : 'ذخیره') . '</button>';
    echo '</form>';
}

if ($p === 'users') {
    echo '<h1>کاربران</h1><table class="tbl"><thead><tr><th>پلتفرم</th><th>آیدی</th><th>یوزرنیم</th><th>موجودی</th><th>آخرین به‌روز</th></tr></thead><tbody>';
    foreach ($users->listRecent(120) as $u) {
        echo '<tr>';
        echo '<td>' . Util::e((string) ($u['platform'] ?? 'telegram')) . '</td>';
        echo '<td>' . (int) $u['telegram_id'] . '</td>';
        echo '<td>' . Util::e((string) ($u['username'] ?? '')) . '</td>';
        echo '<td>' . number_format((int) ($u['balance_toman'] ?? 0)) . '</td>';
        echo '<td>' . Util::e((string) ($u['updated_at'] ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

if ($p === 'orders') {
    echo '<h1>آخرین سفارش‌ها</h1><table class="tbl"><thead><tr><th>کد</th><th>پلتفرم</th><th>کاربر</th><th>پلن</th><th>مبلغ</th><th>نوع</th><th>وضعیت</th><th>تاریخ</th></tr></thead><tbody>';
    foreach ($orders->listAllRecent(150) as $o) {
        echo '<tr>';
        echo '<td><code>' . Util::e((string) $o['public_id']) . '</code></td>';
        echo '<td>' . Util::e((string) ($o['platform'] ?? 'telegram')) . '</td>';
        echo '<td>' . (int) $o['user_id'] . '</td>';
        echo '<td>' . Util::e((string) ($o['plan_title'] ?? '')) . '</td>';
        echo '<td>' . number_format((int) $o['price_paid_toman']) . '</td>';
        echo '<td>' . Util::e((string) ($o['order_kind'] ?? '')) . '</td>';
        echo '<td>' . Util::e((string) $o['status']) . '</td>';
        echo '<td>' . Util::e((string) ($o['created_at'] ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

if ($p === 'inventory') {
    echo '<h1>افزودن کانفیگ به انبار</h1><form method="post" class="form">';
    echo '<label>پلن<select name="plan_id" required>';
    foreach ($plans->listAllForWeb() as $pl) {
        echo '<option value="' . (int) $pl['id'] . '">' . Util::e((string) $pl['title']) . ' (#' . (int) $pl['id'] . ')</option>';
    }
    echo '</select></label>';
    echo '<label>هر خط یک کانفیگ (vless/vmess/…)<textarea name="lines" rows="12" required placeholder="vmess://..."></textarea></label>';
    echo '<button type="submit">ثبت و تلاش برای تکمیل سفارش‌های معلق</button>';
    echo '</form>';
    echo '<h2>آخرین ردیف‌های انبار</h2><table class="tbl"><thead><tr><th>id</th><th>پلن</th><th>وضعیت</th><th>payload</th></tr></thead><tbody>';
    foreach ($configs->listRecentAll(40) as $c) {
        $pay = mb_substr((string) ($c['payload'] ?? ''), 0, 72);
        echo '<tr><td>' . (int) $c['id'] . '</td><td>' . Util::e((string) ($c['plan_title'] ?? '')) . '</td>';
        echo '<td>' . Util::e((string) $c['status']) . '</td><td><code>' . Util::e($pay) . '…</code></td></tr>';
    }
    echo '</tbody></table>';
}

if ($p === 'active_access') {
    echo '<h1>کانفیگ‌های فعال (استاندارد)</h1>';
    echo '<p class="hint">سفارش‌های تکمیل‌شده با دسترسی <b>فعال</b>؛ غیرفعال‌سازی دلیل را به کاربر در همان پلتفرم می‌فرستد.</p>';
    $opts = [
        '' => '— از لیست زیر یا متن دلخواه —',
        'مدت یا حجم سرویس به پایان رسیده است.' => 'پایان مدت / حجم',
        'اشتراک شما توسط مدیریت غیرفعال شد.' => 'غیرفعال توسط مدیریت',
        'محدودیت فنی یا تعمیرات موقت.' => 'محدودیت فنی',
    ];
    $activeRows = $orders->listActiveStandardAccess(250);
    foreach ($activeRows as $o) {
        $oid = (int) ($o['id'] ?? 0);
        $pub = Util::e((string) ($o['public_id'] ?? ''));
        $plat = Util::e((string) ($o['platform'] ?? ''));
        $uid = (int) ($o['user_id'] ?? 0);
        $title = Util::e((string) ($o['plan_title'] ?? ''));
        $gbOrd = (int) ($o['gb_ordered'] ?? 0);
        $gbRef = (int) ($o['plan_gb_ref'] ?? 0);
        $gb = $gbOrd > 0 ? $gbOrd : $gbRef;
        echo '<div class="card" style="margin-bottom:12px">';
        echo '<div><strong>#' . $oid . '</strong> <code>' . $pub . '</code> — ' . $plat . ' — کاربر <code>' . $uid . '</code></div>';
        echo '<div class="muted">' . $title . ' — گیگ سفارش: ' . ($gb > 0 ? $gb : '—') . '</div>';
        echo '<form method="post" class="form" style="margin-top:8px" action="index.php?p=active_access">';
        echo '<input type="hidden" name="deactivate_access" value="1">';
        echo '<input type="hidden" name="order_id" value="' . $oid . '">';
        echo '<div class="row2"><label>علت آماده<select name="reason_preset">';
        foreach ($opts as $val => $lab) {
            echo '<option value="' . Util::e($val) . '">' . Util::e($lab) . '</option>';
        }
        echo '</select></label>';
        echo '<label>علت دلخواه (اولویت بر لیست)<input name="reason_custom" placeholder="اختیاری"></label></div>';
        echo '<button type="submit" class="danger">غیرفعال کردن دسترسی</button></form></div>';
    }
    if ($activeRows === []) {
        echo '<p class="hint">موردی نیست.</p>';
    }
}

if ($p === 'topups') {
    echo '<h1>شارژها</h1><table class="tbl"><thead><tr><th>کد</th><th>پلتفرم</th><th>کاربر</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead><tbody>';
    foreach ($topups->listRecent(100) as $t) {
        echo '<tr>';
        echo '<td><code>' . Util::e((string) $t['public_id']) . '</code></td>';
        echo '<td>' . Util::e((string) ($t['platform'] ?? 'telegram')) . '</td>';
        echo '<td>' . (int) $t['user_id'] . '</td>';
        echo '<td>' . number_format((int) $t['amount_toman']) . '</td>';
        echo '<td>' . Util::e((string) $t['status']) . '</td>';
        echo '<td>' . Util::e((string) ($t['created_at'] ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
?>
</main>
</body>
</html>
