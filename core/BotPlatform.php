<?php

declare(strict_types=1);

final class BotPlatform
{
    public const TELEGRAM = 'telegram';
    public const BALE = 'bale';

    public static function normalize(string $p): string
    {
        $p = strtolower(trim($p));

        return $p === self::BALE ? self::BALE : self::TELEGRAM;
    }

    public static function isBale(string $p): bool
    {
        return self::normalize($p) === self::BALE;
    }
}
