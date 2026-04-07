<?php

declare(strict_types=1);

/**
 * Webhook entry for Bale (set in Bale bot dashboard → same Update JSON shape as Telegram).
 * API base default: https://tapi.bale.ai/bot{TOKEN}/METHOD
 */
define('DN_PLATFORM', 'bale');

require_once __DIR__ . '/core/bootstrap.php';

$raw = file_get_contents('php://input');
$payload = $raw !== false && $raw !== '' ? $raw : 'null';
$update = json_decode($payload, true);
if (!is_array($update) && $raw !== false && $raw !== '') {
    error_log('hook_bale: JSON decode failed or not object, input_len=' . strlen($raw));
}
handleUpdate(is_array($update) ? $update : null);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
