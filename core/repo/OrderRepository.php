<?php

declare(strict_types=1);

final class OrderRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function insertPendingRow(
        \PDO $pdo,
        string $platform,
        int $userId,
        int $productId,
        int $pricePaidToman,
        string $publicId,
        string $orderKind = 'standard',
        ?int $qtyOrdered = null,
    ): int {
        $st = $pdo->prepare(
            'INSERT INTO orders_config (platform, public_id, user_id, product_id, price_paid_toman, payload, status, order_kind, qty_ordered)
             VALUES (?, ?, ?, ?, ?, NULL, \'pending\', ?, ?)'
        );
        $st->execute([$platform, $publicId, $userId, $productId, $pricePaidToman, $orderKind, $qtyOrdered]);

        return (int) $pdo->lastInsertId();
    }

    /** Static test: no inventory row */
    public function insertFulfilledStaticTest(
        \PDO $pdo,
        string $platform,
        int $userId,
        int $productId,
        int $pricePaidToman,
        string $publicId,
        string $payload,
        string $testExpiresAt,
        ?string $userRemark,
    ): int {
        $st = $pdo->prepare(
            "INSERT INTO orders_config (
                platform, public_id, user_id, product_id, price_paid_toman, payload, status,
                order_kind, stock_item_id, test_expires_at, user_remark,
                service_started_at, service_ends_at, access_status
            ) VALUES (?, ?, ?, ?, ?, ?, 'fulfilled', 'test', NULL, ?, ?, NULL, ?, 'active')"
        );
        $st->execute([$platform, $publicId, $userId, $productId, $pricePaidToman, $payload, $testExpiresAt, $userRemark, $testExpiresAt]);

        return (int) $pdo->lastInsertId();
    }

    public function markFulfilledPdo(
        \PDO $pdo,
        int $orderId,
        string $payload,
        ?int $stockItemId,
        ?string $testExpiresAt,
        ?string $userRemark,
        ?string $serviceStartedAt,
        ?string $serviceEndsAt,
        ?int $userLimitSnapshot,
        string $accessStatus = 'active',
    ): void {
        $st = $pdo->prepare(
            'UPDATE orders_config SET
                payload = ?,
                status = \'fulfilled\',
                stock_item_id = ?,
                test_expires_at = ?,
                user_remark = ?,
                service_started_at = ?,
                service_ends_at = ?,
                user_limit_snapshot = ?,
                access_status = ?
             WHERE id = ?'
        );
        $st->execute([
            $payload,
            $stockItemId,
            $testExpiresAt,
            $userRemark,
            $serviceStartedAt,
            $serviceEndsAt,
            $userLimitSnapshot,
            $accessStatus,
            $orderId,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function lockNextPendingForProductPdo(\PDO $pdo, int $productId): ?array
    {
        $st = $pdo->prepare(
            'SELECT id, platform, user_id, public_id, order_kind, price_paid_toman FROM orders_config
             WHERE product_id = ? AND status = \'pending\' AND order_kind = \'standard\'
             ORDER BY id ASC LIMIT 1 FOR UPDATE'
        );
        $st->execute([$productId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(string $platform, int $userId, int $limit = 20): array
    {
        $lim = max(1, min(50, $limit));
        $st = $this->pdo->prepare(
            'SELECT o.id, o.public_id, o.payload, o.status, o.created_at, o.order_kind, o.qty_ordered AS gb_ordered,
                    o.test_expires_at, o.user_remark, o.access_status, o.service_started_at, o.service_ends_at,
                    o.user_limit_snapshot,
                    p.title, p.title_bale, p.description_bale, p.base_qty AS plan_gb, p.user_limit AS plan_user_limit, p.duration_days AS plan_duration_days
             FROM orders_config o
             JOIN products p ON p.id = o.product_id
             WHERE o.platform = ? AND o.user_id = ?
             ORDER BY o.id DESC
             LIMIT ' . $lim
        );
        $st->execute([$platform, $userId]);

        return $st->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findForUserByPublicId(string $platform, int $userId, string $publicId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT o.*, p.title, p.title_bale, p.description_bale, p.base_qty AS plan_gb, p.user_limit AS plan_user_limit, p.duration_days AS plan_duration_days
             FROM orders_config o
             JOIN products p ON p.id = o.product_id
             WHERE o.platform = ? AND o.user_id = ? AND o.public_id = ? LIMIT 1'
        );
        $st->execute([$platform, $userId, $publicId]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function setAccessStatus(int $orderId, string $status): bool
    {
        if ($status !== 'active' && $status !== 'inactive') {
            return false;
        }
        $st = $this->pdo->prepare('UPDATE orders_config SET access_status = ? WHERE id = ?');
        $st->execute([$status, $orderId]);

        return $st->rowCount() === 1;
    }

    /** @return list<array<string, mixed>> */
    public function listActiveStandardAccess(int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->query(
            'SELECT o.*, p.title AS plan_title, p.title_bale AS plan_title_bale, p.base_qty AS plan_gb_ref
             FROM orders_config o
             JOIN products p ON p.id = o.product_id
             WHERE o.status = \'fulfilled\' AND o.order_kind = \'standard\' AND o.access_status = \'active\'
             ORDER BY o.id DESC
             LIMIT ' . $lim
        );

        return $st->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null Full row with plan_title join after update */
    public function deactivateAccessWithReason(int $orderId, string $reason): ?array
    {
        $reason = mb_substr(trim($reason), 0, 500);
        $st = $this->pdo->prepare(
            'UPDATE orders_config SET
                access_status = \'inactive\',
                access_revoke_reason = ?,
                access_revoked_at = NOW()
             WHERE id = ? AND status = \'fulfilled\' AND access_status = \'active\''
        );
        $st->execute([$reason !== '' ? $reason : null, $orderId]);
        if ($st->rowCount() !== 1) {
            return null;
        }
        $st2 = $this->pdo->prepare(
            'SELECT o.*, p.title AS plan_title, p.title_bale AS plan_title_bale
             FROM orders_config o
             JOIN products p ON p.id = o.product_id
             WHERE o.id = ? LIMIT 1'
        );
        $st2->execute([$orderId]);
        $row = $st2->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listAllPending(int $limit = 40): array
    {
        $lim = max(1, min(100, $limit));
        $st = $this->pdo->query(
            'SELECT o.id, o.public_id, o.user_id, o.product_id, o.price_paid_toman, o.created_at, o.platform, p.title
             FROM orders_config o
             JOIN products p ON p.id = o.product_id
             WHERE o.status = \'pending\'
             ORDER BY o.id ASC
             LIMIT ' . $lim
        );

        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAllRecent(int $limit = 100, int $offset = 0): array
    {
        $lim = max(1, min(500, $limit));
        $off = max(0, $offset);
        $st = $this->pdo->query(
            'SELECT o.*, p.title AS plan_title
             FROM orders_config o
             JOIN products p ON p.id = o.product_id
             ORDER BY o.id DESC
             LIMIT ' . $lim . ' OFFSET ' . $off
        );

        return $st->fetchAll() ?: [];
    }

    public function countAll(): int
    {
        $st = $this->pdo->query('SELECT COUNT(*) AS c FROM orders_config');
        $row = $st->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return array{orders_fulfilled: int, orders_pending: int, revenue_toman: int, stock_available: int} */
    public function adminStats(): array
    {
        $a = $this->pdo->query(
            'SELECT
                (SELECT COUNT(*) FROM orders_config WHERE status = \'fulfilled\') AS fulfilled,
                (SELECT COUNT(*) FROM orders_config WHERE status = \'pending\') AS pending,
                (SELECT COALESCE(SUM(price_paid_toman), 0) FROM orders_config WHERE status = \'fulfilled\') AS revenue,
                (SELECT COUNT(*) FROM product_stock WHERE status = \'available\') AS stock'
        )->fetch();

        return [
            'orders_fulfilled' => (int) ($a['fulfilled'] ?? 0),
            'orders_pending' => (int) ($a['pending'] ?? 0),
            'revenue_toman' => (int) ($a['revenue'] ?? 0),
            'stock_available' => (int) ($a['stock'] ?? 0),
        ];
    }

    /**
     * Fulfilled revenue grouped by calendar day (server TZ) for charts.
     *
     * @return list<array{day: string, revenue: int, orders: int}>
     */
    public function fulfilledStatsByDay(int $days = 14): array
    {
        $days = max(1, min(90, $days));
        $st = $this->pdo->prepare(
            'SELECT DATE(created_at) AS d, COALESCE(SUM(price_paid_toman), 0) AS revenue, COUNT(*) AS orders
             FROM orders_config
             WHERE status = \'fulfilled\' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY d ASC'
        );
        $st->execute([$days]);

        return $st->fetchAll() ?: [];
    }

    /** @return list<array{day: string, c: int}> */
    public function newUsersByDay(int $days = 14): array
    {
        $days = max(1, min(90, $days));
        $st = $this->pdo->prepare(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c
             FROM users
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY d ASC'
        );
        $st->execute([$days]);

        return $st->fetchAll() ?: [];
    }

    /** Top products by fulfilled revenue (all time, capped). */
    /** @return list<array{title: string, revenue: int, orders: int}> */
    public function topProductsByRevenue(int $limit = 8): array
    {
        $lim = max(1, min(30, $limit));
        $st = $this->pdo->query(
            'SELECT p.title AS title,
                    COALESCE(SUM(o.price_paid_toman), 0) AS revenue,
                    COUNT(*) AS orders
             FROM orders_config o
             JOIN products p ON p.id = o.product_id
             WHERE o.status = \'fulfilled\'
             GROUP BY o.product_id, p.title
             ORDER BY revenue DESC
             LIMIT ' . $lim
        );

        return $st->fetchAll() ?: [];
    }
}
