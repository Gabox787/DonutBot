<?php

declare(strict_types=1);

require_once __DIR__ . '/MessengerApi.php';

/** @deprecated Prefer MessengerApi; kept so new Telegram($token) targets api.telegram.org */
final class Telegram extends MessengerApi
{
    public function __construct(string $token)
    {
        parent::__construct($token, self::BASE_TELEGRAM, false);
    }
}
