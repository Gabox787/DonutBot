<?php

declare(strict_types=1);

final class PlanConfigRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function countAvailableTotal(): int
    {
        $st = $this->pdo->query('SELECT COUNT(*) AS c FROM plan_configs WHERE status = \'available\'');
        $row = $st->fetch();
        return (int) ($row['c'] ?? 0);
    }

    public function countAvailable(int $planId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) AS c FROM plan_configs WHERE plan_id = ? AND status = \'available\''
        );
        $st->execute([$planId]);
        $row = $st->fetch();
        return (int) ($row['c'] ?? 0);
    }

    /** Add non-empty lines as available inventory. */
    public function bulkInsertLines(int $planId, array $lines): int
    {
        $n = 0;
        $ins = $this->pdo->prepare(
            'INSERT INTO plan_configs (plan_id, payload, status) VALUES (?, ?, \'available\')'
        );
        foreach ($lines as $line) {
            $p = trim((string) $line);
            if ($p === '') {
                continue;
            }
            $ins->execute([$planId, $p]);
            $n++;
        }
        return $n;
    }

    /**
     * Call only inside an open transaction on $pdo (same connection as repo).
     *
     * @return array{id: int, payload: string}|null
     */
    public function claimWithinOpenTransaction(\PDO $pdo, int $planId, int $orderId): ?array
    {
        $sel = $pdo->prepare(
            'SELECT id, payload FROM plan_configs
             WHERE plan_id = ? AND status = \'available\'
             ORDER BY id ASC LIMIT 1 FOR UPDATE'
        );
        $sel->execute([$planId]);
        $row = $sel->fetch();
        if (!$row) {
            return null;
        }
        $id = (int) $row['id'];
        $up = $pdo->prepare(
            'UPDATE plan_configs SET status = \'assigned\', assigned_order_id = ?
             WHERE id = ? AND status = \'available\''
        );
        $up->execute([$orderId, $id]);
        if ($up->rowCount() !== 1) {
            return null;
        }
        return ['id' => $id, 'payload' => (string) $row['payload']];
    }

    /** @return list<array<string, mixed>> */
    public function listForPlan(int $planId, int $limit = 200): array
    {
        $lim = max(1, min(1000, $limit));
        $st = $this->pdo->prepare(
            'SELECT * FROM plan_configs WHERE plan_id = ? ORDER BY id DESC LIMIT ' . $lim
        );
        $st->execute([$planId]);

        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listRecentAll(int $limit = 150): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->query(
            'SELECT pc.*, p.title AS plan_title
             FROM plan_configs pc
             JOIN plans p ON p.id = pc.plan_id
             ORDER BY pc.id DESC LIMIT ' . $lim
        );

        return $st->fetchAll() ?: [];
    }

    public function deleteById(int $id): bool
    {
        $st = $this->pdo->prepare(
            "DELETE FROM plan_configs WHERE id = ? AND status = 'available'"
        );
        $st->execute([$id]);

        return $st->rowCount() === 1;
    }
}
