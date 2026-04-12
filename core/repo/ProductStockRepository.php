<?php

declare(strict_types=1);

final class ProductStockRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function countAvailableTotal(): int
    {
        $st = $this->pdo->query('SELECT COUNT(*) AS c FROM product_stock WHERE status = \'available\'');
        $row = $st->fetch();

        return (int) ($row['c'] ?? 0);
    }

    public function countAvailable(int $productId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) AS c FROM product_stock WHERE product_id = ? AND status = \'available\''
        );
        $st->execute([$productId]);
        $row = $st->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** Add non-empty lines as available inventory. */
    public function bulkInsertLines(int $productId, array $lines): int
    {
        $n = 0;
        $ins = $this->pdo->prepare(
            'INSERT INTO product_stock (product_id, payload, status) VALUES (?, ?, \'available\')'
        );
        foreach ($lines as $line) {
            $p = trim((string) $line);
            if ($p === '') {
                continue;
            }
            $ins->execute([$productId, $p]);
            ++$n;
        }

        return $n;
    }

    /**
     * Call only inside an open transaction on $pdo (same connection as repo).
     *
     * @return array{id: int, payload: string}|null
     */
    public function claimWithinOpenTransaction(\PDO $pdo, int $productId, int $orderId): ?array
    {
        $sel = $pdo->prepare(
            'SELECT id, payload FROM product_stock
             WHERE product_id = ? AND status = \'available\'
             ORDER BY id ASC LIMIT 1 FOR UPDATE'
        );
        $sel->execute([$productId]);
        $row = $sel->fetch();
        if (!$row) {
            return null;
        }
        $id = (int) $row['id'];
        $up = $pdo->prepare(
            'UPDATE product_stock SET status = \'assigned\', assigned_order_id = ?
             WHERE id = ? AND status = \'available\''
        );
        $up->execute([$orderId, $id]);
        if ($up->rowCount() !== 1) {
            return null;
        }

        return ['id' => $id, 'payload' => (string) $row['payload']];
    }

    /** @return list<array<string, mixed>> */
    public function listForProduct(int $productId, int $limit = 200): array
    {
        $lim = max(1, min(1000, $limit));
        $st = $this->pdo->prepare(
            'SELECT * FROM product_stock WHERE product_id = ? ORDER BY id DESC LIMIT ' . $lim
        );
        $st->execute([$productId]);

        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listRecentAll(int $limit = 150): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->query(
            'SELECT ps.*, p.title AS product_title
             FROM product_stock ps
             JOIN products p ON p.id = ps.product_id
             ORDER BY ps.id DESC LIMIT ' . $lim
        );

        return $st->fetchAll() ?: [];
    }

    public function deleteById(int $id): bool
    {
        $st = $this->pdo->prepare(
            "DELETE FROM product_stock WHERE id = ? AND status = 'available'"
        );
        $st->execute([$id]);

        return $st->rowCount() === 1;
    }
}
