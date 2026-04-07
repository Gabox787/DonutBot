<?php

declare(strict_types=1);

final class TopupRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function createPending(string $platform, int $userId, int $amountToman, string $publicId): array
    {
        $st = $this->pdo->prepare(
            'INSERT INTO wallet_topups (platform, public_id, user_id, amount_toman, status) VALUES (?, ?, ?, ?, \'pending\')'
        );
        $st->execute([$platform, $publicId, $userId, $amountToman]);
        $id = (int) $this->pdo->lastInsertId();

        return ['id' => $id, 'public_id' => $publicId];
    }

    public function attachReceipt(string $publicId, string $platform, int $userId, string $fileId, string $uniqueId): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE wallet_topups
             SET receipt_file_id = ?, receipt_file_unique = ?
             WHERE public_id = ? AND platform = ? AND user_id = ? AND status = \'pending\''
        );
        $st->execute([$fileId, $uniqueId, $publicId, $platform, $userId]);

        return $st->rowCount() === 1;
    }

    /** @return array<string, mixed>|null */
    public function findPendingByPublicIdForUser(string $publicId, string $platform, int $userId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM wallet_topups WHERE public_id = ? AND platform = ? AND user_id = ? AND status = \'pending\' LIMIT 1'
        );
        $st->execute([$publicId, $platform, $userId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByPublicId(string $publicId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM wallet_topups WHERE public_id = ? LIMIT 1');
        $st->execute([$publicId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return array{platform: string, messenger_id: int, amount_toman: int}|null */
    public function approve(string $publicId): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare(
                "SELECT * FROM wallet_topups WHERE public_id = ? AND status = 'pending' FOR UPDATE"
            );
            $st->execute([$publicId]);
            $row = $st->fetch();
            if (!$row) {
                $this->pdo->rollBack();

                return null;
            }
            $plat = (string) ($row['platform'] ?? BotPlatform::TELEGRAM);
            $uid = (int) $row['user_id'];
            $amt = (int) $row['amount_toman'];
            $u = $this->pdo->prepare('UPDATE users SET balance_toman = balance_toman + ? WHERE platform = ? AND telegram_id = ?');
            $u->execute([$amt, $plat, $uid]);
            $t = $this->pdo->prepare(
                "UPDATE wallet_topups SET status = 'approved', resolved_at = NOW() WHERE public_id = ?"
            );
            $t->execute([$publicId]);
            $this->pdo->commit();

            return ['platform' => $plat, 'messenger_id' => $uid, 'amount_toman' => $amt, 'telegram_id' => $uid];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function reject(string $publicId, ?string $note = null): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE wallet_topups SET status = 'rejected', admin_note = ?, resolved_at = NOW()
             WHERE public_id = ? AND status = 'pending'"
        );
        $st->execute([$note, $publicId]);

        return $st->rowCount() === 1;
    }

    /** Cancel before receipt uploaded. */
    public function cancelPendingWithoutReceipt(string $publicId, string $platform, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE wallet_topups SET status = 'cancelled', resolved_at = NOW()
             WHERE public_id = ? AND platform = ? AND user_id = ? AND status = 'pending'
               AND (receipt_file_id IS NULL OR receipt_file_id = '')"
        );
        $st->execute([$publicId, $platform, $userId]);

        return $st->rowCount() === 1;
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 80): array
    {
        $lim = max(1, min(300, $limit));
        $st = $this->pdo->query(
            'SELECT * FROM wallet_topups ORDER BY id DESC LIMIT ' . $lim
        );

        return $st->fetchAll() ?: [];
    }

    /** @return list<array{platform: string, chat_id: int, message_id: int, is_photo: bool}> */
    public function getAdminNotifyHandles(string $publicId): array
    {
        $st = $this->pdo->prepare('SELECT admin_notify_handles FROM wallet_topups WHERE public_id = ? LIMIT 1');
        $st->execute([$publicId]);
        $row = $st->fetch();
        if (!$row) {
            return [];
        }
        $raw = $row['admin_notify_handles'] ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $h) {
            if (!is_array($h)) {
                continue;
            }
            $plat = BotPlatform::normalize((string) ($h['platform'] ?? BotPlatform::TELEGRAM));
            $cid = (int) ($h['chat_id'] ?? 0);
            $mid = (int) ($h['message_id'] ?? 0);
            if ($cid <= 0 || $mid <= 0) {
                continue;
            }
            $out[] = [
                'platform' => $plat,
                'chat_id' => $cid,
                'message_id' => $mid,
                'is_photo' => !empty($h['is_photo']),
            ];
        }

        return $out;
    }

    public function appendAdminNotifyHandle(string $publicId, string $platform, int $chatId, int $messageId, bool $isPhoto): void
    {
        if ($chatId <= 0 || $messageId <= 0) {
            return;
        }
        $plat = BotPlatform::normalize($platform);
        $handles = $this->getAdminNotifyHandles($publicId);
        foreach ($handles as $h) {
            if ($h['platform'] === $plat && $h['chat_id'] === $chatId && $h['message_id'] === $messageId) {
                return;
            }
        }
        $handles[] = [
            'platform' => $plat,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'is_photo' => $isPhoto,
        ];
        $st = $this->pdo->prepare(
            'UPDATE wallet_topups SET admin_notify_handles = ? WHERE public_id = ?'
        );
        $st->execute([json_encode($handles, JSON_UNESCAPED_UNICODE), $publicId]);
    }
}
