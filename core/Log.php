<?php

declare(strict_types=1);

final class Log
{
    public static function write(string $path, string $line): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($path, date('c') . ' ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
