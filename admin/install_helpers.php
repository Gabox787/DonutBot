<?php

declare(strict_types=1);

/**
 * First-time / redeploy setup: DB in config.local.php, schema present, admin web password set.
 */

/** @param array<string, mixed> $config */
function dn_install_db_slice(array $config): array
{
    $db = $config['db'] ?? [];
    if (!is_array($db)) {
        return [];
    }

    return $db;
}

/** @param array<string, mixed> $config */
function dn_install_db_config_ready(array $config): bool
{
    $db = dn_install_db_slice($config);
    $name = trim((string) ($db['name'] ?? ''));
    $user = trim((string) ($db['user'] ?? ''));

    return $name !== '' && $user !== '';
}

/**
 * @param array<string, string|int> $db host, name, user, pass, optional charset
 * @return array{ok: bool, error: string, pdo: ?\PDO}
 */
function dn_install_try_pdo(array $db): array
{
    $host = trim((string) ($db['host'] ?? 'localhost'));
    $name = trim((string) ($db['name'] ?? ''));
    $user = trim((string) ($db['user'] ?? ''));
    $pass = (string) ($db['pass'] ?? '');
    $charset = trim((string) ($db['charset'] ?? 'utf8mb4'));
    if ($charset === '') {
        $charset = 'utf8mb4';
    }
    if ($name === '' || $user === '') {
        return ['ok' => false, 'error' => 'نام دیتابیس و نام کاربری الزامی است.', 'pdo' => null];
    }
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return ['ok' => true, 'error' => '', 'pdo' => $pdo];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'pdo' => null];
    }
}

function dn_install_schema_ok(\PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM app_settings LIMIT 1');

        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

function dn_install_effective_admin_password(\PDO $pdo, array $config): string
{
    try {
        $st = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $st->execute(['admin_web_password']);
        $row = $st->fetch();
        if ($row) {
            $v = trim((string) $row['setting_value']);
            if ($v !== '') {
                return $v;
            }
        }
    } catch (\Throwable $e) {
    }

    return trim((string) ($config['admin_web_password'] ?? ''));
}

/**
 * @param array<string, mixed> $config Merged config from config.php (+ config.local)
 */
function dn_install_is_complete(array $config): bool
{
    if (!dn_install_db_config_ready($config)) {
        return false;
    }
    $try = dn_install_try_pdo(dn_install_db_slice($config));
    if (!$try['ok'] || !$try['pdo'] instanceof \PDO) {
        return false;
    }
    $pdo = $try['pdo'];
    if (!dn_install_schema_ok($pdo)) {
        return false;
    }
    if (dn_install_effective_admin_password($pdo, $config) === '') {
        return false;
    }

    return true;
}

/**
 * @param array<string, string|int> $db
 */
function dn_install_write_config_local(string $projectRoot, array $db): bool
{
    $path = $projectRoot . DIRECTORY_SEPARATOR . 'config.local.php';
    $host = trim((string) ($db['host'] ?? 'localhost'));
    $name = trim((string) ($db['name'] ?? ''));
    $user = trim((string) ($db['user'] ?? ''));
    $pass = (string) ($db['pass'] ?? '');
    $charset = trim((string) ($db['charset'] ?? 'utf8mb4'));
    if ($charset === '') {
        $charset = 'utf8mb4';
    }
    $export = [
        'db' => [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'charset' => $charset,
        ],
    ];
    $body = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($export, true) . ";\n";
    if (@file_put_contents($path, $body) === false) {
        return false;
    }

    return true;
}
