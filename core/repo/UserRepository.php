<?php

declare(strict_types=1);

final class UserRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(string $platform, int $messengerUserId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM users WHERE platform = ? AND telegram_id = ? LIMIT 1');
        $st->execute([$platform, $messengerUserId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function touch(string $platform, int $messengerUserId, ?string $username, string $firstName): array
    {
        $st = $this->pdo->prepare(
            'INSERT INTO users (platform, telegram_id, username, first_name)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                first_name = VALUES(first_name)'
        );
        $st->execute([$platform, $messengerUserId, $username, $firstName]);
        $u = $this->find($platform, $messengerUserId);
        if ($u === null) {
            throw new RuntimeException('user_touch_failed');
        }

        return $u;
    }

    public function setHub(string $platform, int $messengerUserId, int $chatId, int $messageId): void
    {
        $st = $this->pdo->prepare('UPDATE users SET hub_chat_id = ?, hub_message_id = ? WHERE platform = ? AND telegram_id = ?');
        $st->execute([$chatId, $messageId, $platform, $messengerUserId]);
    }

    public function clearHub(string $platform, int $messengerUserId): void
    {
        $st = $this->pdo->prepare('UPDATE users SET hub_chat_id = NULL, hub_message_id = NULL WHERE platform = ? AND telegram_id = ?');
        $st->execute([$platform, $messengerUserId]);
    }

    public function setKbAnchor(string $platform, int $messengerUserId, ?int $messageId): void
    {
        if ($messageId === null || $messageId <= 0) {
            $this->pdo->prepare('UPDATE users SET kb_anchor_message_id = NULL WHERE platform = ? AND telegram_id = ?')
                ->execute([$platform, $messengerUserId]);

            return;
        }
        $this->pdo->prepare('UPDATE users SET kb_anchor_message_id = ? WHERE platform = ? AND telegram_id = ?')
            ->execute([$messageId, $platform, $messengerUserId]);
    }

    public function addBalance(string $platform, int $messengerUserId, int $deltaToman): void
    {
        $st = $this->pdo->prepare('UPDATE users SET balance_toman = balance_toman + ? WHERE platform = ? AND telegram_id = ?');
        $st->execute([$deltaToman, $platform, $messengerUserId]);
    }

    public function deductBalance(string $platform, int $messengerUserId, int $amountToman): bool
    {
        $st = $this->pdo->prepare(
            'UPDATE users SET balance_toman = balance_toman - ? WHERE platform = ? AND telegram_id = ? AND balance_toman >= ?'
        );
        $st->execute([$amountToman, $platform, $messengerUserId, $amountToman]);

        return $st->rowCount() === 1;
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 100, int $offset = 0): array
    {
        $lim = max(1, min(500, $limit));
        $off = max(0, $offset);
        $st = $this->pdo->query(
            'SELECT * FROM users ORDER BY updated_at DESC LIMIT ' . $lim . ' OFFSET ' . $off
        );

        return $st->fetchAll() ?: [];
    }

    public function countAll(): int
    {
        $st = $this->pdo->query('SELECT COUNT(*) AS c FROM users');
        $row = $st->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return int users who used this referrer's link (may include non-buyers), same platform */
    public function countReferrals(string $platform, int $referrerId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) AS c FROM users WHERE platform = ? AND referred_by = ?');
        $st->execute([$platform, $referrerId]);
        $row = $st->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return int rows affected (1 if referrer was newly set) */
    public function setReferrerIfEmpty(string $platform, int $userId, int $referrerId): int
    {
        if ($userId <= 0 || $referrerId <= 0 || $userId === $referrerId) {
            return 0;
        }
        $st = $this->pdo->prepare(
            'UPDATE users SET referred_by = ? WHERE platform = ? AND telegram_id = ? AND (referred_by IS NULL OR referred_by = 0)'
        );
        $st->execute([$referrerId, $platform, $userId]);

        return $st->rowCount();
    }
}
