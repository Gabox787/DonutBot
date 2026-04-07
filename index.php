<?php

declare(strict_types=1);

if (!defined('DN_PLATFORM')) {
    define('DN_PLATFORM', 'telegram');
}

require_once __DIR__ . '/core/bootstrap.php';

$raw = file_get_contents('php://input');
$payload = $raw !== false && $raw !== '' ? $raw : 'null';
$update = json_decode($payload, true);
handleUpdate(is_array($update) ? $update : null);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
