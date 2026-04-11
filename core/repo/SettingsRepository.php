<?php

declare(strict_types=1);

final class SettingsRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    private static function langBaseDir(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lang';
    }

    private static function isValidLocaleFile(string $locale): bool
    {
        $locale = trim($locale);
        if ($locale === '' || !preg_match('/^[a-z0-9_-]+$/i', $locale)) {
            return false;
        }

        return is_file(self::langBaseDir() . DIRECTORY_SEPARATOR . $locale . '.php');
    }

    /** @return array<string, string> */
    public function allFlat(): array
    {
        $st = $this->pdo->query('SELECT setting_key, setting_value FROM app_settings');
        $rows = $st->fetchAll(\PDO::FETCH_KEY_PAIR);

        return is_array($rows) ? array_map('strval', $rows) : [];
    }

    public function get(string $key, string $default = ''): string
    {
        $st = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        $row = $st->fetch();

        return $row ? (string) $row['setting_value'] : $default;
    }

    public function set(string $key, string $value): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $st->execute([$key, $value]);
    }

    /** @param array<string, string> $pairs */
    public function setMany(array $pairs): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($pairs as $k => $v) {
            $st->execute([$k, $v]);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $db
     * @return array<string, mixed>
     */
    public static function mergeIntoConfig(array $config, array $db): array
    {
        $out = $config;
        if (($v = trim((string) ($db['telegram_bot_token'] ?? ''))) !== '') {
            $out['bot_token'] = $v;
        }
        if (($v = trim((string) ($db['bale_bot_token'] ?? ''))) !== '') {
            $out['bale_bot_token'] = $v;
        }
        foreach (
            [
                'required_channel_username_bale' => 'required_channel_username_bale',
                'channel_join_url_telegram' => 'channel_join_url_telegram',
                'channel_join_url_bale' => 'channel_join_url_bale',
                'referral_link_template_telegram' => 'referral_link_template_telegram',
                'referral_link_template_bale' => 'referral_link_template_bale',
            ] as $dk => $ck
        ) {
            $v = trim((string) ($db[$dk] ?? ''));
            if ($v !== '') {
                $out[$ck] = $v;
            }
        }
        if (($v = trim((string) ($db['telegram_api_base'] ?? ''))) !== '') {
            $out['telegram_api_base'] = $v;
        }
        if (($v = trim((string) ($db['bale_api_base'] ?? ''))) !== '') {
            $out['bale_api_base'] = $v;
        }
        foreach (['payment_card_number' => ['payment', 'card_number'], 'payment_card_holder' => ['payment', 'card_holder']] as $dk => $path) {
            $v = trim((string) ($db[$dk] ?? ''));
            if ($v !== '') {
                $out[$path[0]][$path[1]] = $v;
            }
        }
        if (($v = trim((string) ($db['pay_window_minutes'] ?? ''))) !== '' && ctype_digit($v)) {
            $out['payment']['pay_window_minutes'] = (int) $v;
        }
        foreach (
            [
                'support_username' => 'support_username',
                'support_username_telegram' => 'support_username_telegram',
                'support_username_bale' => 'support_username_bale',
                'timezone' => 'timezone',
                'bot_brand_name' => 'bot_brand_name',
                'telegram_bot_username' => 'telegram_bot_username',
                'bale_bot_username' => 'bale_bot_username',
                'required_channel_username' => 'required_channel_username',
                'required_channel_username_bale' => 'required_channel_username_bale',
                'channel_join_url_telegram' => 'channel_join_url_telegram',
                'channel_join_url_bale' => 'channel_join_url_bale',
                'referral_link_template_telegram' => 'referral_link_template_telegram',
                'referral_link_template_bale' => 'referral_link_template_bale',
                'admin_web_password' => 'admin_web_password',
                'commands_setup_key' => 'commands_setup_key',
            ] as $dk => $ck
        ) {
            $v = trim((string) ($db[$dk] ?? ''));
            if ($v !== '') {
                $out[$ck] = $v;
            }
        }
        $helpKey = trim((string) ($db['help_text_key'] ?? ''));
        if ($helpKey === '') {
            $helpKey = trim((string) ($db['faq_text_key'] ?? ''));
        }
        if ($helpKey !== '') {
            $out['help_text_key'] = $helpKey;
        }
        if (($v = trim((string) ($db['referral_percent_of_sale'] ?? ''))) !== '' && is_numeric($v)) {
            $out['referral_percent_of_sale'] = (float) $v;
        }
        if (($v = trim((string) ($db['admin_telegram_ids'] ?? ''))) !== '') {
            $out['admin_telegram_ids'] = self::parseIdList($v);
        }
        if (($v = trim((string) ($db['admin_bale_ids'] ?? ''))) !== '') {
            $out['admin_bale_ids'] = self::parseIdList($v);
        }
        if (($v = trim((string) ($db['locale_telegram'] ?? ''))) !== '') {
            $out['locale'] = self::isValidLocaleFile($v) ? $v : (string) ($out['locale'] ?? 'fa');
        }
        if (($v = trim((string) ($db['locale_bale'] ?? ''))) !== '') {
            $out['locale_bale'] = self::isValidLocaleFile($v) ? $v : (string) ($out['locale_bale'] ?? 'fa_bale');
        }

        if (trim((string) ($out['bot_token'] ?? '')) === '') {
            $out['bot_token'] = trim((string) ($config['bot_token'] ?? ''));
        }
        if (trim((string) ($out['bale_bot_token'] ?? '')) === '') {
            $out['bale_bot_token'] = trim((string) ($config['bale_bot_token'] ?? ''));
        }

        return $out;
    }

    /** @return list<int> */
    private static function parseIdList(string $csv): array
    {
        $parts = preg_split('/[\s,]+/', $csv, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if (ctype_digit((string) $p)) {
                $out[] = (int) $p;
            }
        }

        return $out;
    }
}
