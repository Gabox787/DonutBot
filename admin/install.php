<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/config.php';
require_once __DIR__ . '/install_helpers.php';

if (dn_install_is_complete($config)) {
    header('Location: index.php');
    exit;
}

$msg = '';
$err = '';
$dbDefaults = dn_install_db_slice($config);
$host = (string) ($dbDefaults['host'] ?? 'localhost');
$name = (string) ($dbDefaults['name'] ?? '');
$user = (string) ($dbDefaults['user'] ?? '');
$pass = (string) ($dbDefaults['pass'] ?? '');
$charset = (string) ($dbDefaults['charset'] ?? 'utf8mb4');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'test_db') {
        $host = trim((string) ($_POST['db_host'] ?? ''));
        $name = trim((string) ($_POST['db_name'] ?? ''));
        $user = trim((string) ($_POST['db_user'] ?? ''));
        $pass = (string) ($_POST['db_pass'] ?? '');
        $charset = trim((string) ($_POST['db_charset'] ?? 'utf8mb4'));
        if ($charset === '') {
            $charset = 'utf8mb4';
        }
        $try = dn_install_try_pdo([
            'host' => $host !== '' ? $host : 'localhost',
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'charset' => $charset,
        ]);
        if ($try['ok']) {
            $msg = 'اتصال به دیتابیس برقرار شد.';
        } else {
            $err = 'اتصال ناموفق: ' . htmlspecialchars($try['error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    } elseif ($action === 'save_db') {
        $host = trim((string) ($_POST['db_host'] ?? ''));
        $name = trim((string) ($_POST['db_name'] ?? ''));
        $user = trim((string) ($_POST['db_user'] ?? ''));
        $pass = (string) ($_POST['db_pass'] ?? '');
        $charset = trim((string) ($_POST['db_charset'] ?? 'utf8mb4'));
        if ($charset === '') {
            $charset = 'utf8mb4';
        }
        if ($host === '') {
            $host = 'localhost';
        }
        $dbTry = [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'charset' => $charset,
        ];
        $try = dn_install_try_pdo($dbTry);
        if (!$try['ok']) {
            $err = 'اتصال ناموفق؛ فایل ذخیره نشد: ' . htmlspecialchars($try['error'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } elseif (!dn_install_write_config_local($root, $dbTry)) {
            $err = 'نوشتن config.local.php ناموفق بود (دسترسی نوشتن در ریشهٔ پروژه را بررسی کنید).';
        } else {
            header('Location: install.php?saved=db');
            exit;
        }
    } elseif ($action === 'set_admin_password') {
        $pw = (string) ($_POST['admin_password'] ?? '');
        $pw2 = (string) ($_POST['admin_password_confirm'] ?? '');
        if ($pw === '' || $pw !== $pw2) {
            $err = 'رمزها یکسان نیستند یا خالی است.';
        } elseif (strlen($pw) < 8) {
            $err = 'حداقل ۸ کاراکتر برای رمز پنل لازم است.';
        } elseif (!dn_install_db_config_ready($config)) {
            $err = 'تنظیمات دیتابیس ناقص است.';
        } else {
            $try = dn_install_try_pdo(dn_install_db_slice($config));
            if (!$try['ok'] || !$try['pdo'] instanceof PDO) {
                $err = 'اتصال به دیتابیس برقرار نشد.';
            } elseif (!dn_install_schema_ok($try['pdo'])) {
                $err = 'جدول app_settings وجود ندارد؛ database.sql را اجرا کنید.';
            } else {
                require_once $root . '/core/repo/SettingsRepository.php';
                $repo = new SettingsRepository($try['pdo']);
                $repo->set('admin_web_password', $pw);
                header('Location: index.php?p=login&installed=1');
                exit;
            }
        }
    }
}

if (isset($_GET['saved']) && $_GET['saved'] === 'db') {
    $msg = 'تنظیمات دیتابیس در config.local.php ذخیره شد. اگر هنوز database.sql را اجرا نکرده‌اید، الان اجرا کنید؛ بعد این صفحه را رفرش کنید تا مرحلهٔ رمز پنل باز شود.';
}

$config = require $root . '/config.php';

$showDbStep = !dn_install_db_config_ready($config);
$pdoOk = null;
$schemaOk = false;
if (!$showDbStep) {
    $try = dn_install_try_pdo(dn_install_db_slice($config));
    $pdoOk = $try['ok'];
    if ($pdoOk && $try['pdo'] instanceof PDO) {
        $schemaOk = dn_install_schema_ok($try['pdo']);
    }
    if ($pdoOk === false) {
        $showDbStep = true;
        if ($err === '') {
            $err = 'با تنظیمات فعلی به دیتابیس وصل نشد. مقادیر را اصلاح کنید.';
        }
    }
}

$showPasswordStep = !$showDbStep && $pdoOk && $schemaOk;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>نصب — TG Donut Bot</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login">
<div class="install-wrap">
    <h1 class="install-title">راه‌اندازی پنل مدیریت</h1>
    <p class="install-lead">پس از هر استقرار تازه، ابتدا دیتابیس و رمز پنل را اینجا تکمیل کنید؛ بقیهٔ تنظیمات را از منوی «تنظیمات» انجام دهید.</p>

    <?php if ($msg !== '') { ?>
        <p class="ok-banner"><?php echo htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php } ?>
    <?php if ($err !== '') { ?>
        <p class="err"><?php echo $err; ?></p>
    <?php } ?>

    <?php if ($showDbStep) { ?>
        <form class="card install-card" method="post" action="install.php">
            <h2>۱ — اتصال دیتابیس</h2>
            <label>میزبان (host)
                <input type="text" name="db_host" value="<?php echo htmlspecialchars($host, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <label>نام دیتابیس
                <input type="text" name="db_name" value="<?php echo htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required autocomplete="off">
            </label>
            <label>نام کاربری
                <input type="text" name="db_user" value="<?php echo htmlspecialchars($user, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required autocomplete="username">
            </label>
            <label>رمز عبور
                <input type="password" name="db_pass" value="<?php echo htmlspecialchars($pass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="current-password">
            </label>
            <label>Charset
                <input type="text" name="db_charset" value="<?php echo htmlspecialchars($charset, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <p class="hint">فایل اسکیما: <code dir="ltr">database.sql</code> در ریشهٔ پروژه را روی همین دیتابیس اجرا کنید (قبل یا بعد از ذخیره؛ برای ادامه جدول <code dir="ltr">app_settings</code> لازم است).</p>
            <div class="install-actions">
                <button type="submit" name="action" value="test_db" class="btn secondary">آزمایش اتصال</button>
                <button type="submit" name="action" value="save_db">ذخیره و ادامه</button>
            </div>
        </form>
    <?php } elseif (!$schemaOk) { ?>
        <div class="card install-card">
            <h2>دیتابیس وصل است اما جداول نیست</h2>
            <p class="hint">روی سرور MySQL/MariaDB فایل <code dir="ltr">database.sql</code> را برای همین دیتابیس اجرا کنید، سپس این صفحه را رفرش کنید.</p>
            <p><a class="btn" href="install.php">بررسی دوباره</a></p>
        </div>
    <?php } elseif ($showPasswordStep) { ?>
        <form class="card install-card" method="post" action="install.php">
            <input type="hidden" name="action" value="set_admin_password">
            <h2>۲ — رمز ورود به پنل وب</h2>
            <p class="hint">این رمز در دیتابیس (<code dir="ltr">app_settings</code>) ذخیره می‌شود. بعداً از بخش تنظیمات هم می‌توانید عوضش کنید.</p>
            <label>رمز پنل
                <input type="password" name="admin_password" minlength="8" required autocomplete="new-password">
            </label>
            <label>تکرار رمز
                <input type="password" name="admin_password_confirm" minlength="8" required autocomplete="new-password">
            </label>
            <button type="submit">پایان نصب و ورود</button>
        </form>
    <?php } ?>
</div>
<style>
.install-wrap { max-width: 420px; width: 100%; padding: 1rem; }
.install-title { font-size: 1.35rem; margin: 0 0 0.5rem; color: var(--accent); }
.install-lead { color: var(--muted); font-size: 0.9rem; line-height: 1.5; margin: 0 0 1.25rem; }
.install-card h2 { font-size: 1.05rem; margin: 0 0 1rem; }
.install-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
.ok-banner { background: rgba(61, 220, 151, 0.12); border: 1px solid rgba(61, 220, 151, 0.35); padding: 0.75rem 1rem; border-radius: 8px; color: var(--accent2); font-size: 0.9rem; }
.btn.secondary { background: transparent; border: 1px solid var(--border); color: var(--text); }
</style>
</body>
</html>
