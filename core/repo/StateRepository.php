<?php

declare(strict_types=1);

final class StateRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /** @return array{state: string, data: array<string, mixed>} */
    public function get(string $platform, int $userId): array
    {
        $st = $this->pdo->prepare('SELECT state, data FROM states WHERE platform = ? AND user_id = ? LIMIT 1');
        $st->execute([$platform, $userId]);
        $row = $st->fetch();
        if (!$row) {
            return ['state' => '', 'data' => []];
        }
        $data = json_decode((string) ($row['data'] ?? '{}'), true);

        return [
            'state' => (string) ($row['state'] ?? ''),
            'data' => is_array($data) ? $data : [],
        ];
    }

    /** @param array<string, mixed> $data */
    public function set(string $platform, int $userId, string $state, array $data = []): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
        $st = $this->pdo->prepare(
            'INSERT INTO states (platform, user_id, state, data) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data)'
        );
        $st->execute([$platform, $userId, $state, $json]);
    }

    public function clear(string $platform, int $userId): void
    {
        $st = $this->pdo->prepare('DELETE FROM states WHERE platform = ? AND user_id = ?');
        $st->execute([$platform, $userId]);
    }
}
