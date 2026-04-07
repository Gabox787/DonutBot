<?php

declare(strict_types=1);

/**
 * Converts a safe subset of Telegram-HTML (used in I18n) to Bale client Markdown.
 *
 * @see https://docs.bale.ai/ — bold * with surrounding spaces, italic _, links [text](url)
 */
final class BaleMarkup
{
    /** @param string $html From I18n / BotKernel (Telegram HTML subset) */
    public static function fromTelegramHtml(string $html): string
    {
        $s = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $s = preg_replace('/<br\s*\/?>/iu', "\n", $s) ?? $s;
        $s = preg_replace('/<\/(p|div|li|tr)>/iu', "\n", $s) ?? $s;

        $max = 30;
        while ($max-- > 0 && preg_match('/<a\s/i', $s)) {
            $s = preg_replace_callback(
                '/<a\s+[^>]*href=(["\'])([^"\']*)\1[^>]*>(.*?)<\/a>/is',
                static function (array $m): string {
                    $url = trim($m[2]);
                    $inner = strip_tags($m[3]);
                    $inner = preg_replace('/\s+/u', ' ', trim($inner));
                    if ($url === '' || $inner === '') {
                        return $inner;
                    }

                    return '[' . $inner . '](' . $url . ')';
                },
                $s,
                1
            ) ?? $s;
        }

        $max = 40;
        while ($max-- > 0 && preg_match('/<code/i', $s)) {
            $s = preg_replace_callback(
                '/<code>(.*?)<\/code>/is',
                static function (array $m): string {
                    $t = trim($m[1]);

                    return '`' . str_replace('`', '\'', $t) . '`';
                },
                $s,
                1
            ) ?? $s;
        }

        $max = 20;
        while ($max-- > 0 && preg_match('/<(b|strong)\b/i', $s)) {
            $s = preg_replace_callback(
                '/<(b|strong)>(.*?)<\/\1>/is',
                static function (array $m): string {
                    $inner = trim(strip_tags($m[2]));
                    if ($inner === '') {
                        return '';
                    }
                    /* Bale: space before opening * and after closing * */
                    return ' *' . $inner . '* ';
                },
                $s,
                1
            ) ?? $s;
        }

        $max = 20;
        while ($max-- > 0 && preg_match('/<(i|em)\b/i', $s)) {
            $s = preg_replace_callback(
                '/<(i|em)>(.*?)<\/\1>/is',
                static function (array $m): string {
                    $inner = trim(strip_tags($m[2]));
                    if ($inner === '') {
                        return '';
                    }

                    return ' _' . $inner . '_ ';
                },
                $s,
                1
            ) ?? $s;
        }

        $s = strip_tags($s);
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace("/\n{3,}/u", "\n\n", $s) ?? $s;

        return trim($s);
    }
}
