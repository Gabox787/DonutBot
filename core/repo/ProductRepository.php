<?php

declare(strict_types=1);

final class ProductRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    private const SELECT_PUBLIC = 'id, slug, title, title_bale, description, description_bale, base_qty, qty_unit, price_toman, sort_order, is_featured, delivery_template,
        allow_custom_qty, qty_min, qty_max, test_enabled, test_price_toman, test_sample_payload, user_limit, duration_days';

    /**
     * Map DB row to keys expected by BotKernel / Util (legacy gb_* names).
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $r): array
    {
        $r['gb'] = (int) ($r['base_qty'] ?? $r['gb'] ?? 1);
        $r['allow_custom_gb'] = !empty($r['allow_custom_qty']) || !empty($r['allow_custom_gb']);
        $r['gb_min'] = (int) ($r['qty_min'] ?? $r['gb_min'] ?? 1);
        $r['gb_max'] = (int) ($r['qty_max'] ?? $r['gb_max'] ?? 0);
        $r['test_config_url'] = trim((string) ($r['test_sample_payload'] ?? $r['test_config_url'] ?? ''));
        $r['config_template'] = (string) ($r['delivery_template'] ?? $r['config_template'] ?? '');
        $r['qty_unit'] = trim((string) ($r['qty_unit'] ?? 'kg'));
        if ($r['qty_unit'] === '') {
            $r['qty_unit'] = 'kg';
        }

        return $r;
    }

    /** @return list<array<string, mixed>> */
    public function listActive(): array
    {
        $st = $this->pdo->query(
            'SELECT ' . self::SELECT_PUBLIC . '
             FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        );
        $rows = $st->fetchAll() ?: [];

        return array_map(fn (array $r) => self::normalizeRow($r), $rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function applyBalePresentation(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = self::applyBalePresentationRow($r);
        }

        return $out;
    }

    /** @param array<string, mixed>|null $r */
    public static function applyBalePresentationRow(?array $r): ?array
    {
        if ($r === null) {
            return null;
        }
        $tb = trim((string) ($r['title_bale'] ?? ''));
        if ($tb !== '') {
            $r['title'] = $tb;
        }
        $db = trim((string) ($r['description_bale'] ?? ''));
        if ($db !== '') {
            $r['description'] = $db;
        }

        return $r;
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT ' . self::SELECT_PUBLIC . '
             FROM products WHERE id = ? AND is_active = 1 LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ? self::normalizeRow($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listAllAdmin(): array
    {
        $st = $this->pdo->query(
            'SELECT id, slug, title, base_qty, qty_unit, price_toman, is_active, is_featured, sort_order,
                allow_custom_qty, qty_min, qty_max, test_enabled, test_price_toman, test_sample_payload, user_limit, duration_days, title_bale
             FROM products ORDER BY sort_order ASC, id ASC'
        );
        $rows = $st->fetchAll() ?: [];

        return array_map(fn (array $r) => self::normalizeRow($r), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listAllForWeb(int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->query(
            'SELECT * FROM products ORDER BY sort_order ASC, id ASC LIMIT ' . $lim
        );
        $rows = $st->fetchAll() ?: [];

        return array_map(fn (array $r) => self::normalizeRow($r), $rows);
    }

    /** @return array<string, mixed>|null */
    public function getByIdAny(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ? self::normalizeRow($row) : null;
    }

    public function nextSortOrder(): int
    {
        $v = $this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM products')->fetch();

        return (int) ($v['n'] ?? 1);
    }

    public function createProduct(string $slug, string $title, string $description, int $baseQty, int $priceToman, int $sortOrder, int $featured): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO products (slug, title, title_bale, description, description_bale, base_qty, qty_unit, price_toman, sort_order, is_featured, is_active, delivery_template,
                allow_custom_qty, qty_min, qty_max, test_enabled, test_price_toman, test_sample_payload, user_limit, duration_days)
             VALUES (?, ?, NULL, ?, NULL, ?, \'kg\', ?, ?, ?, 1, \'\', 0, 1, 0, 0, 0, NULL, NULL, NULL)'
        );
        $st->execute([$slug, $title, $description, $baseQty, $priceToman, $sortOrder, $featured]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setActive(int $id, bool $active): bool
    {
        $st = $this->pdo->prepare('UPDATE products SET is_active = ? WHERE id = ?');
        $st->execute([$active ? 1 : 0, $id]);

        return $st->rowCount() === 1;
    }

    public function updateProductCore(
        int $id,
        string $title,
        string $description,
        int $baseQty,
        int $priceToman,
        int $sortOrder,
        int $featured,
    ): bool {
        $st = $this->pdo->prepare(
            'UPDATE products SET title = ?, description = ?, base_qty = ?, price_toman = ?, sort_order = ?, is_featured = ?
             WHERE id = ?'
        );
        $st->execute([$title, $description, $baseQty, $priceToman, $sortOrder, $featured, $id]);

        return $st->rowCount() === 1;
    }

    /** @param array<string, mixed> $row */
    public function saveFullFromWeb(int $id, array $row): bool
    {
        $testPayload = trim((string) ($row['test_sample_payload'] ?? $row['test_config_url'] ?? ''));
        $testPayload = $testPayload !== '' ? $testPayload : null;
        $uLim = ($row['user_limit'] ?? '') === '' || (string) ($row['user_limit'] ?? '') === '0' ? null : max(1, (int) $row['user_limit']);
        $dur = ($row['duration_days'] ?? '') === '' || (string) ($row['duration_days'] ?? '') === '0' ? null : max(1, (int) $row['duration_days']);
        $qtyUnit = trim((string) ($row['qty_unit'] ?? 'kg'));
        if ($qtyUnit === '') {
            $qtyUnit = 'kg';
        }
        $allowCustom = !empty($row['allow_custom_qty']) || !empty($row['allow_custom_gb']);
        $baseQty = (int) ($row['base_qty'] ?? $row['gb'] ?? 1);
        $st = $this->pdo->prepare(
            'UPDATE products SET
                slug = ?, title = ?, title_bale = ?, description = ?, description_bale = ?, base_qty = ?, qty_unit = ?, price_toman = ?, sort_order = ?, is_featured = ?, is_active = ?,
                allow_custom_qty = ?, qty_min = ?, qty_max = ?, test_enabled = ?, test_price_toman = ?, delivery_template = ?,
                test_sample_payload = ?, user_limit = ?, duration_days = ?
             WHERE id = ?'
        );
        $tb = trim((string) ($row['title_bale'] ?? ''));
        $tb = $tb !== '' ? $tb : null;
        $db = trim((string) ($row['description_bale'] ?? ''));
        $db = $db !== '' ? $db : null;
        $st->execute([
            (string) $row['slug'],
            (string) $row['title'],
            $tb,
            (string) ($row['description'] ?? ''),
            $db,
            $baseQty,
            $qtyUnit,
            (int) $row['price_toman'],
            (int) $row['sort_order'],
            (int) !empty($row['is_featured']),
            (int) !empty($row['is_active']),
            (int) $allowCustom,
            max(1, (int) ($row['qty_min'] ?? $row['gb_min'] ?? 1)),
            max(0, (int) ($row['qty_max'] ?? $row['gb_max'] ?? 0)),
            (int) !empty($row['test_enabled']),
            max(0, (int) ($row['test_price_toman'] ?? 0)),
            (string) ($row['delivery_template'] ?? $row['config_template'] ?? ''),
            $testPayload,
            $uLim,
            $dur,
            $id,
        ]);

        return $st->rowCount() === 1;
    }

    /** @param array<string, mixed> $row */
    public function insertFullFromWeb(array $row): int
    {
        $testPayload = trim((string) ($row['test_sample_payload'] ?? $row['test_config_url'] ?? ''));
        $testPayload = $testPayload !== '' ? $testPayload : null;
        $uLim = ($row['user_limit'] ?? '') === '' || (string) ($row['user_limit'] ?? '') === '0' ? null : max(1, (int) $row['user_limit']);
        $dur = ($row['duration_days'] ?? '') === '' || (string) ($row['duration_days'] ?? '') === '0' ? null : max(1, (int) $row['duration_days']);
        $tb = trim((string) ($row['title_bale'] ?? ''));
        $tb = $tb !== '' ? $tb : null;
        $db = trim((string) ($row['description_bale'] ?? ''));
        $db = $db !== '' ? $db : null;
        $qtyUnit = trim((string) ($row['qty_unit'] ?? 'kg'));
        if ($qtyUnit === '') {
            $qtyUnit = 'kg';
        }
        $allowCustom = !empty($row['allow_custom_qty']) || !empty($row['allow_custom_gb']);
        $baseQty = (int) ($row['base_qty'] ?? $row['gb'] ?? 1);
        $st = $this->pdo->prepare(
            'INSERT INTO products (slug, title, title_bale, description, description_bale, base_qty, qty_unit, price_toman, sort_order, is_featured, is_active,
                allow_custom_qty, qty_min, qty_max, test_enabled, test_price_toman, delivery_template,
                test_sample_payload, user_limit, duration_days)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            (string) $row['slug'],
            (string) $row['title'],
            $tb,
            (string) ($row['description'] ?? ''),
            $db,
            $baseQty,
            $qtyUnit,
            (int) $row['price_toman'],
            (int) $row['sort_order'],
            (int) !empty($row['is_featured']),
            (int) !empty($row['is_active']),
            (int) $allowCustom,
            max(1, (int) ($row['qty_min'] ?? $row['gb_min'] ?? 1)),
            max(0, (int) ($row['qty_max'] ?? $row['gb_max'] ?? 0)),
            (int) !empty($row['test_enabled']),
            max(0, (int) ($row['test_price_toman'] ?? 0)),
            (string) ($row['delivery_template'] ?? $row['config_template'] ?? ''),
            $testPayload,
            $uLim,
            $dur,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteProduct(int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM products WHERE id = ?');
        $st->execute([$id]);

        return $st->rowCount() === 1;
    }

    /** @return list<string> */
    public function existingSlugs(): array
    {
        $st = $this->pdo->query('SELECT slug FROM products');
        $rows = $st->fetchAll(\PDO::FETCH_COLUMN);

        return is_array($rows) ? array_map('strval', $rows) : [];
    }

    public static function unitLabelFa(string $code): string
    {
        return match (strtolower(trim($code))) {
            'kg', 'kilo' => 'کیلو',
            'g', 'gr' => 'گرم',
            'piece', 'pc', 'عدد' => 'عدد',
            'box' => 'باکس',
            default => $code,
        };
    }
}
