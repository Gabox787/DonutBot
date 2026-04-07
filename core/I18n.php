<?php

declare(strict_types=1);

final class I18n
{
    /** @var array<string, string> */
    private static array $strings = [];

    /**
     * @param array<string, string>|null $mergeFromDb JSON-decoded or flat overrides (after base file)
     */
    public static function boot(string $locale, string $langDir, ?array $mergeFromDb = null): void
    {
        $file = $langDir . '/' . $locale . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Locale file missing: ' . $file);
        }
        /** @var array<string, string> $data */
        $data = require $file;
        self::$strings = $data;
        if ($mergeFromDb !== null && $mergeFromDb !== []) {
            foreach ($mergeFromDb as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    self::$strings[$k] = $v;
                }
            }
        }
    }

    /** @param array<string, string> $more */
    public static function merge(array $more): void
    {
        foreach ($more as $k => $v) {
            if (is_string($k) && is_string($v)) {
                self::$strings[$k] = $v;
            }
        }
    }

    public static function txt(string $key): string
    {
        return self::$strings[$key] ?? $key;
    }

    /** @param array<string, string|int|float> $repl */
    public static function fmt(string $key, array $repl = []): string
    {
        $s = self::txt($key);
        foreach ($repl as $k => $v) {
            $s = str_replace(':' . $k, (string) $v, $s);
        }

        return $s;
    }
}
