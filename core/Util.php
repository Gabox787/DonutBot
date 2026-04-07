<?php

declare(strict_types=1);

final class Util
{
    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function uuidV4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public static function publicId12(): string
    {
        return substr(bin2hex(random_bytes(8)), 0, 12);
    }

    /** Thousands grouping for prices, balances, counts (e.g. 1,250,000). */
    public static function formatNumber(int|float $n): string
    {
        return number_format((int) round((float) $n), 0, '.', ',');
    }

    public static function stripUrlFragment(string $url): string
    {
        $p = strpos($url, '#');

        return $p === false ? $url : substr($url, 0, $p);
    }

    public static function withUrlFragment(string $url, string $fragment): string
    {
        $base = self::stripUrlFragment($url);
        $f = trim($fragment);
        if ($f === '') {
            return $base;
        }

        return $base . '#' . $f;
    }

    /** @param array<string, mixed> $plan */
    public static function planGbMax(array $plan): int
    {
        $max = (int) ($plan['gb_max'] ?? 0);
        if ($max > 0) {
            return $max;
        }

        return max(1, (int) ($plan['gb'] ?? 1));
    }

    /** @param array<string, mixed> $plan */
    public static function planGbMin(array $plan): int
    {
        return max(1, (int) ($plan['gb_min'] ?? 1));
    }

    /**
     * @param array<string, mixed> $plan
     */
    public static function planPriceToman(array $plan, ?int $chosenGb): int
    {
        $baseGb = max(1, (int) ($plan['gb'] ?? 1));
        $price = (int) ($plan['price_toman'] ?? 0);
        if (empty($plan['allow_custom_gb'])) {
            return $price;
        }
        $g = $chosenGb ?? $baseGb;
        if ($g < self::planGbMin($plan)) {
            $g = self::planGbMin($plan);
        }
        $max = self::planGbMax($plan);
        if ($g > $max) {
            $g = $max;
        }

        return (int) ceil($price * $g / $baseGb);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $plan
     */
    /** @param array<string, mixed> $plan */
    public static function planUserLimitDisplay(array $plan): ?int
    {
        if (!array_key_exists('user_limit', $plan) || $plan['user_limit'] === null) {
            return null;
        }

        $n = (int) $plan['user_limit'];

        return $n > 0 ? $n : null;
    }

    /** @param array<string, mixed> $plan */
    public static function planDurationDaysDisplay(array $plan): ?int
    {
        if (!array_key_exists('duration_days', $plan) || $plan['duration_days'] === null) {
            return null;
        }

        $n = (int) $plan['duration_days'];

        return $n > 0 ? $n : null;
    }

    /**
     * @param array<string, mixed> $row Order row with status, order_kind, test_expires_at, access_status, service_ends_at
     */
    public static function orderEffectiveAccess(array $row): string
    {
        if (($row['status'] ?? '') === 'pending') {
            return 'pending';
        }
        $now = time();
        if (($row['order_kind'] ?? '') === 'test' && !empty($row['test_expires_at'])) {
            $t = strtotime((string) $row['test_expires_at']);

            if ($t !== false && $now > $t) {
                return 'inactive';
            }
        }
        if (($row['access_status'] ?? 'active') === 'inactive') {
            return 'inactive';
        }
        if (!empty($row['service_ends_at'])) {
            $t = strtotime((string) $row['service_ends_at']);

            if ($t !== false && $now > $t) {
                return 'inactive';
            }
        }

        return 'active';
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $plan
     */
    public static function defaultConfigRemark(array $user, array $plan, string $botBrand, bool $asciiOnly = false): string
    {
        if ($asciiOnly) {
            $mid = (string) (int) ($user['telegram_id'] ?? 0);
            $pid = (string) (int) ($plan['id'] ?? 0);
            $brand = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($botBrand)) ?: 'dn');
            $uname = isset($user['username']) && (string) $user['username'] !== ''
                ? strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '', ltrim((string) $user['username'], '@')) ?: 'u')
                : 'u' . $mid;

            return $brand . '-p' . $pid . '-' . $uname;
        }

        $brand = trim($botBrand);
        $title = trim((string) ($plan['title'] ?? ''));
        $uname = isset($user['username']) && (string) $user['username'] !== ''
            ? '@' . ltrim((string) $user['username'], '@')
            : trim((string) ($user['first_name'] ?? 'User'));
        if ($brand !== '') {
            return $brand . ' ' . $title . ' | ' . $uname;
        }

        return $title . ' | ' . $uname;
    }

    public static function payloadPlainForFile(string $payload): string
    {
        return html_entity_decode($payload, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
