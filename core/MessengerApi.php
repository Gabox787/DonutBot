<?php

declare(strict_types=1);

require_once __DIR__ . '/BaleMarkup.php';

/**
 * Telegram-compatible HTTP bot API (works with Bale: https://tapi.bale.ai/botTOKEN/METHOD).
 *
 * When constructed with {@see self::__construct} $isBaleClient = true, outgoing text is converted from Telegram-HTML to
 * Bale Markdown and {@see parse_mode} is omitted (Bale formats messages per client Markdown rules).
 */
final class MessengerApi
{
    public const BASE_TELEGRAM = 'https://api.telegram.org';

    public const BASE_BALE = 'https://tapi.bale.ai';

    public function __construct(
        private string $token,
        private string $apiBaseHost = self::BASE_TELEGRAM,
        private bool $isBaleClient = false,
    ) {
    }

    public function token(): string
    {
        return $this->token;
    }

    private function methodUrl(string $method): string
    {
        $base = rtrim($this->apiBaseHost, '/');

        return $base . '/bot' . $this->token . '/' . $method;
    }

    /** @param array<string, mixed|string| CURLFile> $params */
    public function api(string $method, array $params = []): array
    {
        $url = $this->methodUrl($method);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($params !== []) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            return ['ok' => false, 'description' => curl_error($ch)];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['ok' => false, 'description' => 'invalid_json'];
    }

    /** @param string $chatId Channel username (without or with @) or numeric chat id */
    public function getChatMember(string $chatId, int $userId): array
    {
        $cid = trim($chatId);
        if ($cid !== '' && $cid[0] !== '-' && !str_starts_with($cid, '@')) {
            $cid = '@' . $cid;
        }

        return $this->api('getChatMember', ['chat_id' => $cid, 'user_id' => (string) $userId]);
    }

    public function answerCallbackQuery(string $id, ?string $text = null): void
    {
        $p = ['callback_query_id' => $id];
        if ($text !== null && $text !== '') {
            $p['text'] = $text;
            $p['show_alert'] = false;
        }
        $this->api('answerCallbackQuery', $p);
    }

    /** @param array<string, mixed> $replyMarkup */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): array
    {
        $out = $this->isBaleClient && ($parseMode === 'HTML' || $parseMode === '')
            ? BaleMarkup::fromTelegramHtml($text)
            : $text;
        $p = [
            'chat_id' => (string) $chatId,
            'text' => $out,
            'disable_web_page_preview' => true,
        ];
        if (!$this->isBaleClient) {
            $p['parse_mode'] = $parseMode;
        }
        if ($replyMarkup !== null) {
            $p['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        return $this->api('sendMessage', $p);
    }

    /**
     * Upload a plain-text file (used on Bale to avoid sending subscription links in chat).
     *
     * @param array<string, mixed>|null $replyMarkup
     */
    public function sendDocument(
        int $chatId,
        string $fileName,
        string $contents,
        ?string $caption = null,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML',
    ): array {
        $tmp = tempnam(sys_get_temp_dir(), 'dn_doc_');
        if ($tmp === false) {
            return ['ok' => false, 'description' => 'tempnam_failed'];
        }
        file_put_contents($tmp, $contents);
        $cf = new \CURLFile($tmp, 'text/plain', $fileName);
        $p = [
            'chat_id' => (string) $chatId,
            'document' => $cf,
        ];
        if ($caption !== null && $caption !== '') {
            $p['caption'] = $this->isBaleClient && ($parseMode === 'HTML' || $parseMode === '')
                ? BaleMarkup::fromTelegramHtml($caption)
                : $caption;
            if (!$this->isBaleClient) {
                $p['parse_mode'] = $parseMode;
            }
        }
        if ($replyMarkup !== null) {
            $p['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }
        $r = $this->api('sendDocument', $p);
        @unlink($tmp);

        return $r;
    }

    public function deleteMessage(int $chatId, int $messageId): void
    {
        $this->api('deleteMessage', ['chat_id' => (string) $chatId, 'message_id' => (string) $messageId]);
    }

    /**
     * Remove reply keyboard without leaving a visible message (send + delete).
     */
    public function stripReplyKeyboardSilently(int $chatId): void
    {
        $r = $this->sendMessage($chatId, "\u{2060}", ['remove_keyboard' => true], $this->isBaleClient ? '' : 'HTML');
        if (($r['ok'] ?? false) && isset($r['result']['message_id'])) {
            $this->deleteMessage($chatId, (int) $r['result']['message_id']);
        }
    }

    /** @param array<string, mixed>|null $replyMarkup */
    public function sendPhoto(
        int $chatId,
        string $fileId,
        ?string $caption = null,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML',
    ): array {
        $p = [
            'chat_id' => (string) $chatId,
            'photo' => $fileId,
        ];
        if (!$this->isBaleClient) {
            $p['parse_mode'] = $parseMode;
        }
        if ($caption !== null && $caption !== '') {
            $p['caption'] = $this->isBaleClient && ($parseMode === 'HTML' || $parseMode === '')
                ? BaleMarkup::fromTelegramHtml($caption)
                : $caption;
        }
        if ($replyMarkup !== null) {
            $p['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        return $this->api('sendPhoto', $p);
    }

    /** @param array<string, mixed> $replyMarkup */
    public function editMessage(int $chatId, int $messageId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): array
    {
        $out = $this->isBaleClient && ($parseMode === 'HTML' || $parseMode === '')
            ? BaleMarkup::fromTelegramHtml($text)
            : $text;
        $p = [
            'chat_id' => (string) $chatId,
            'message_id' => (string) $messageId,
            'text' => $out,
            'disable_web_page_preview' => true,
        ];
        if (!$this->isBaleClient) {
            $p['parse_mode'] = $parseMode;
        }
        if ($replyMarkup !== null) {
            $p['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        return $this->api('editMessageText', $p);
    }

    /** @param array<string, mixed>|null $replyMarkup */
    public function editMessageCaption(
        int $chatId,
        int $messageId,
        string $caption,
        ?array $replyMarkup = null,
        string $parseMode = 'HTML',
    ): array {
        $out = $this->isBaleClient && ($parseMode === 'HTML' || $parseMode === '')
            ? BaleMarkup::fromTelegramHtml($caption)
            : $caption;
        $p = [
            'chat_id' => (string) $chatId,
            'message_id' => (string) $messageId,
            'caption' => $out,
        ];
        if (!$this->isBaleClient && $parseMode !== '') {
            $p['parse_mode'] = $parseMode;
        }
        if ($replyMarkup !== null) {
            $p['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        return $this->api('editMessageCaption', $p);
    }
}
