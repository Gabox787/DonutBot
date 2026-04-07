<?php

declare(strict_types=1);

final class PlanRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    private const SELECT_PUBLIC = 'id, slug, title, title_bale, description, description_bale, gb, price_toman, sort_order, is_featured, config_template,
        allow_custom_gb, gb_min, gb_max, test_enabled, test_price_toman, test_config_url, user_limit, duration_days';

    /** @return list<array<string, mixed>> */
    public function listActive(): array
    {
        $st = $this->pdo->query(
            'SELECT ' . self::SELECT_PUBLIC . '
             FROM plans WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        );

        return $st->fetchAll() ?: [];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function applyBalePresentation(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $tb = trim((string) ($r['title_bale'] ?? ''));
            if ($tb !== '') {
                $r['title'] = $tb;
            }
            $db = trim((string) ($r['description_bale'] ?? ''));
            if ($db !== '') {
                $r['description'] = $db;
            }
            $out[] = $r;
        }

        return $out;
    }

    /** @param array<string, mixed>|null $r */
    public static function applyBalePresentationRow(?array $r): ?array
    {
        if ($r === null) {
            return null;
        }
        $x = self::applyBalePresentation([$r]);

        return $x[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function getById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT ' . self::SELECT_PUBLIC . '
             FROM plans WHERE id = ? AND is_active = 1 LIMIT 1'
        );
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listAllAdmin(): array
    {
        $st = $this->pdo->query(
            'SELECT id, slug, title, gb, price_toman, is_active, is_featured, sort_order,
                allow_custom_gb, gb_min, gb_max, test_enabled, test_price_toman, test_config_url, user_limit, duration_days, title_bale
             FROM plans ORDER BY sort_order ASC, id ASC'
        );

        return $st->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAllForWeb(int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->query(
            'SELECT * FROM plans ORDER BY sort_order ASC, id ASC LIMIT ' . $lim
        );

        return $st->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function getByIdAny(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM plans WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function nextSortOrder(): int
    {
        $v = $this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM plans')->fetch();

        return (int) ($v['n'] ?? 1);
    }

    public function createPlan(string $slug, string $title, string $description, int $gb, int $priceToman, int $sortOrder, int $featured): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO plans (slug, title, title_bale, description, description_bale, gb, price_toman, sort_order, is_featured, is_active, config_template,
                allow_custom_gb, gb_min, gb_max, test_enabled, test_price_toman, test_config_url, user_limit, duration_days)
             VALUES (?, ?, NULL, ?, NULL, ?, ?, ?, ?, 1, \'\', 0, 1, 0, 0, 0, NULL, NULL, NULL)'
        );
        $st->execute([$slug, $title, $description, $gb, $priceToman, $sortOrder, $featured]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setActive(int $id, bool $active): bool
    {
        $st = $this->pdo->prepare('UPDATE plans SET is_active = ? WHERE id = ?');
        $st->execute([$active ? 1 : 0, $id]);

        return $st->rowCount() === 1;
    }

    public function updatePlanCore(
        int $id,
        string $title,
        string $description,
        int $gb,
        int $priceToman,
        int $sortOrder,
        int $featured,
    ): bool {
        $st = $this->pdo->prepare(
            'UPDATE plans SET title = ?, description = ?, gb = ?, price_toman = ?, sort_order = ?, is_featured = ?
             WHERE id = ?'
        );
        $st->execute([$title, $description, $gb, $priceToman, $sortOrder, $featured, $id]);

        return $st->rowCount() === 1;
    }

    /** @param array<string, mixed> $row */
    public function saveFullFromWeb(int $id, array $row): bool
    {
        $testUrl = trim((string) ($row['test_config_url'] ?? ''));
        $testUrl = $testUrl !== '' ? $testUrl : null;
        $uLim = ($row['user_limit'] ?? '') === '' || (string) ($row['user_limit'] ?? '') === '0' ? null : max(1, (int) $row['user_limit']);
        $dur = ($row['duration_days'] ?? '') === '' || (string) ($row['duration_days'] ?? '') === '0' ? null : max(1, (int) $row['duration_days']);
        $st = $this->pdo->prepare(
            'UPDATE plans SET
                slug = ?, title = ?, title_bale = ?, description = ?, description_bale = ?, gb = ?, price_toman = ?, sort_order = ?, is_featured = ?, is_active = ?,
                allow_custom_gb = ?, gb_min = ?, gb_max = ?, test_enabled = ?, test_price_toman = ?, config_template = ?,
                test_config_url = ?, user_limit = ?, duration_days = ?
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
            (int) $row['gb'],
            (int) $row['price_toman'],
            (int) $row['sort_order'],
            (int) !empty($row['is_featured']),
            (int) !empty($row['is_active']),
            (int) !empty($row['allow_custom_gb']),
            max(1, (int) ($row['gb_min'] ?? 1)),
            max(0, (int) ($row['gb_max'] ?? 0)),
            (int) !empty($row['test_enabled']),
            max(0, (int) ($row['test_price_toman'] ?? 0)),
            (string) ($row['config_template'] ?? ''),
            $testUrl,
            $uLim,
            $dur,
            $id,
        ]);

        return $st->rowCount() === 1;
    }

    /** @param array<string, mixed> $row */
    public function insertFullFromWeb(array $row): int
    {
        $testUrl = trim((string) ($row['test_config_url'] ?? ''));
        $testUrl = $testUrl !== '' ? $testUrl : null;
        $uLim = ($row['user_limit'] ?? '') === '' || (string) ($row['user_limit'] ?? '') === '0' ? null : max(1, (int) $row['user_limit']);
        $dur = ($row['duration_days'] ?? '') === '' || (string) ($row['duration_days'] ?? '') === '0' ? null : max(1, (int) $row['duration_days']);
        $tb = trim((string) ($row['title_bale'] ?? ''));
        $tb = $tb !== '' ? $tb : null;
        $db = trim((string) ($row['description_bale'] ?? ''));
        $db = $db !== '' ? $db : null;
        $st = $this->pdo->prepare(
            'INSERT INTO plans (slug, title, title_bale, description, description_bale, gb, price_toman, sort_order, is_featured, is_active,
                allow_custom_gb, gb_min, gb_max, test_enabled, test_price_toman, config_template,
                test_config_url, user_limit, duration_days)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            (string) $row['slug'],
            (string) $row['title'],
            $tb,
            (string) ($row['description'] ?? ''),
            $db,
            (int) $row['gb'],
            (int) $row['price_toman'],
            (int) $row['sort_order'],
            (int) !empty($row['is_featured']),
            (int) !empty($row['is_active']),
            (int) !empty($row['allow_custom_gb']),
            max(1, (int) ($row['gb_min'] ?? 1)),
            max(0, (int) ($row['gb_max'] ?? 0)),
            (int) !empty($row['test_enabled']),
            max(0, (int) ($row['test_price_toman'] ?? 0)),
            (string) ($row['config_template'] ?? ''),
            $testUrl,
            $uLim,
            $dur,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function deletePlan(int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM plans WHERE id = ?');
        $st->execute([$id]);

        return $st->rowCount() === 1;
    }

    /** @return list<string> */
    public function existingSlugs(): array
    {
        $st = $this->pdo->query('SELECT slug FROM plans');
        $rows = $st->fetchAll(\PDO::FETCH_COLUMN);

        return is_array($rows) ? array_map('strval', $rows) : [];
    }
}
