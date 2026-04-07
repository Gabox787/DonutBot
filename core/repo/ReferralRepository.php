<?php

declare(strict_types=1);

final class ReferralRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function recordPayout(string $platform, int $referrerId, int $buyerId, string $orderPublicId, int $amountToman): void
    {
        if ($amountToman <= 0 || $referrerId <= 0 || $buyerId <= 0) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO referral_payouts (platform, referrer_id, buyer_id, order_public_id, amount_toman) VALUES (?,?,?,?,?)'
        );
        $st->execute([$platform, $referrerId, $buyerId, $orderPublicId, $amountToman]);
    }

    public function totalEarnedByReferrer(string $platform, int $referrerId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount_toman),0) AS s FROM referral_payouts WHERE platform = ? AND referrer_id = ?'
        );
        $st->execute([$platform, $referrerId]);
        $row = $st->fetch();

        return (int) ($row['s'] ?? 0);
    }

    public function countPurchasingReferrals(string $platform, int $referrerId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT o.user_id) AS c
             FROM orders_config o
             INNER JOIN users u ON u.platform = o.platform AND u.telegram_id = o.user_id
             WHERE u.platform = ? AND u.referred_by = ?
               AND o.status = \'fulfilled\'
               AND o.order_kind = \'standard\''
        );
        $st->execute([$platform, $referrerId]);
        $row = $st->fetch();

        return (int) ($row['c'] ?? 0);
    }
}
