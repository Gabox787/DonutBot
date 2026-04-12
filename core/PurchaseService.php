<?php

declare(strict_types=1);

require_once __DIR__ . '/BotPlatform.php';
require_once __DIR__ . '/I18n.php';
require_once __DIR__ . '/MessengerApi.php';
require_once __DIR__ . '/Util.php';

final class PurchaseService
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, MessengerApi>|null $messengerApis
     */
    public function __construct(
        private \PDO $pdo,
        private OrderRepository $orders,
        private ProductStockRepository $stock,
        private UserRepository $users,
        private ReferralRepository $referrals,
        private string $platform,
        private array $config,
        private ?array $messengerApis = null,
    ) {
    }

    private function brandName(): string
    {
        return trim((string) ($this->config['bot_brand_name'] ?? 'TG Donut Bot'));
    }

    private function testExpiryEndOfDay(): string
    {
        $tz = new \DateTimeZone((string) ($this->config['timezone'] ?? 'Asia/Tehran'));
        $d = new \DateTime('today', $tz);
        $d->setTime(23, 59, 59);

        return $d->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $plan
     * @return array{0: ?string, 1: ?string, 2: ?int}
     */
    private function subscriptionFieldsForStandard(array $plan): array
    {
        $tz = new \DateTimeZone((string) ($this->config['timezone'] ?? 'Asia/Tehran'));
        $start = new \DateTime('now', $tz);
        $started = $start->format('Y-m-d H:i:s');
        $days = isset($plan['duration_days']) ? (int) $plan['duration_days'] : 0;
        $ends = null;
        if ($days > 0) {
            $ends = (clone $start)->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
        }
        $lim = isset($plan['user_limit']) ? (int) $plan['user_limit'] : 0;
        $snap = $lim > 0 ? $lim : null;

        return [$started, $ends, $snap];
    }

    private function maybeCreditReferrer(int $buyerId, string $publicId, int $priceToman, string $orderKind): void
    {
        if ($orderKind !== 'standard' || $priceToman <= 0) {
            return;
        }
        $buyer = $this->users->find($this->platform, $buyerId);
        if ($buyer === null) {
            return;
        }
        $ref = (int) ($buyer['referred_by'] ?? 0);
        if ($ref <= 0 || $ref === $buyerId) {
            return;
        }
        $pct = (float) ($this->config['referral_percent_of_sale'] ?? 5) / 100.0;
        $amt = (int) floor($priceToman * $pct);
        if ($amt < 1) {
            return;
        }
        $this->users->addBalance($this->platform, $ref, $amt);
        $this->referrals->recordPayout($this->platform, $ref, $buyerId, $publicId, $amt);
        $this->notifyReferrerPurchase($this->platform, $ref, $buyerId, $amt, $publicId);
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function finalizePayloadForOrder(
        string $rawPayload,
        int $messengerUserId,
        array $plan,
        string $orderKind,
    ): array {
        $user = $this->users->find($this->platform, $messengerUserId) ?? [];
        $ascii = BotPlatform::isBale($this->platform);
        $remark = Util::defaultConfigRemark($user, $plan, $this->brandName(), $ascii);
        $base = Util::stripUrlFragment($rawPayload);
        $display = Util::withUrlFragment($base, $remark);
        $testAt = $orderKind === 'test' ? $this->testExpiryEndOfDay() : null;

        return ['payload' => $display, 'remark' => $remark, 'test_expires_at' => $testAt];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array{status: string, public_id: string, order_id: int, payload?: string}|null
     */
    public function placeOrder(
        int $messengerUserId,
        int $planId,
        int $priceToman,
        array $plan,
        ?int $gbOrdered = null,
        string $orderKind = 'standard',
    ): ?array {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare(
                'UPDATE users SET balance_toman = balance_toman - ? WHERE platform = ? AND telegram_id = ? AND balance_toman >= ?'
            );
            $st->execute([$priceToman, $this->platform, $messengerUserId, $priceToman]);
            if ($st->rowCount() !== 1) {
                $this->pdo->rollBack();

                return null;
            }

            $publicId = Util::publicId12();
            $orderId = $this->orders->insertPendingRow(
                $this->pdo,
                $this->platform,
                $messengerUserId,
                $planId,
                $priceToman,
                $publicId,
                $orderKind,
                $gbOrdered,
            );

            $claimed = $this->stock->claimWithinOpenTransaction($this->pdo, $planId, $orderId);
            if ($claimed !== null) {
                $fin = $this->finalizePayloadForOrder($claimed['payload'], $messengerUserId, $plan, $orderKind);
                [$svcStart, $svcEnd, $userLim] = $orderKind === 'standard'
                    ? $this->subscriptionFieldsForStandard($plan)
                    : [null, $fin['test_expires_at'], null];
                $this->orders->markFulfilledPdo(
                    $this->pdo,
                    $orderId,
                    $fin['payload'],
                    $claimed['id'],
                    $fin['test_expires_at'],
                    $fin['remark'],
                    $svcStart,
                    $orderKind === 'test' ? $fin['test_expires_at'] : $svcEnd,
                    $userLim,
                );
                $this->pdo->commit();
                $this->maybeCreditReferrer($messengerUserId, $publicId, $priceToman, $orderKind);

                return [
                    'status' => 'fulfilled',
                    'public_id' => $publicId,
                    'order_id' => $orderId,
                    'payload' => $fin['payload'],
                ];
            }

            $this->pdo->commit();

            return [
                'status' => 'pending',
                'public_id' => $publicId,
                'order_id' => $orderId,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $plan
     * @return array{status: string, public_id: string, order_id: int, payload?: string}|null
     */
    public function placeTestOrder(int $messengerUserId, int $planId, int $testPriceToman, array $plan): ?array
    {
        $url = trim((string) ($plan['test_config_url'] ?? ''));
        if ($url === '' || empty($plan['test_enabled'])) {
            return null;
        }
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare(
                'UPDATE users SET balance_toman = balance_toman - ? WHERE platform = ? AND telegram_id = ? AND balance_toman >= ?'
            );
            $st->execute([$testPriceToman, $this->platform, $messengerUserId, $testPriceToman]);
            if ($st->rowCount() !== 1) {
                $this->pdo->rollBack();

                return null;
            }

            $publicId = Util::publicId12();
            $fin = $this->finalizePayloadForOrder($url, $messengerUserId, $plan, 'test');
            $orderId = $this->orders->insertFulfilledStaticTest(
                $this->pdo,
                $this->platform,
                $messengerUserId,
                $planId,
                $testPriceToman,
                $publicId,
                $fin['payload'],
                (string) $fin['test_expires_at'],
                $fin['remark'],
            );
            $this->pdo->commit();

            return [
                'status' => 'fulfilled',
                'public_id' => $publicId,
                'order_id' => $orderId,
                'payload' => $fin['payload'],
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return list<array{platform: string, user_id: int, public_id: string, payload: string, order_kind: string, test_expires_at: ?string}>
     */
    public function drainPendingForProduct(int $productId, array $productRow): array
    {
        $out = [];
        while (true) {
            $this->pdo->beginTransaction();
            try {
                $order = $this->orders->lockNextPendingForProductPdo($this->pdo, $productId);
                if ($order === null) {
                    $this->pdo->rollBack();
                    break;
                }
                $orderKind = (string) ($order['order_kind'] ?? 'standard');
                if ($orderKind !== 'standard') {
                    $this->pdo->rollBack();
                    break;
                }
                $plat = BotPlatform::normalize((string) ($order['platform'] ?? BotPlatform::TELEGRAM));
                $orderId = (int) $order['id'];
                $uid = (int) $order['user_id'];
                $publicId = (string) $order['public_id'];
                $paid = (int) ($order['price_paid_toman'] ?? 0);
                $claimed = $this->stock->claimWithinOpenTransaction($this->pdo, $productId, $orderId);
                if ($claimed === null) {
                    $this->pdo->rollBack();
                    break;
                }
                $fin = $this->finalizePayloadForOrderCrossPlatform($claimed['payload'], $plat, $uid, $productRow, $orderKind);
                [$svcStart, $svcEnd, $userLim] = $this->subscriptionFieldsForStandard($productRow);
                $this->orders->markFulfilledPdo(
                    $this->pdo,
                    $orderId,
                    $fin['payload'],
                    $claimed['id'],
                    $fin['test_expires_at'],
                    $fin['remark'],
                    $svcStart,
                    $svcEnd,
                    $userLim,
                );
                $this->pdo->commit();
                $this->maybeCreditReferrerCrossPlatform($plat, $uid, $publicId, $paid, $orderKind);
                $out[] = [
                    'platform' => $plat,
                    'user_id' => $uid,
                    'public_id' => $publicId,
                    'payload' => $fin['payload'],
                    'order_kind' => $orderKind,
                    'test_expires_at' => $fin['test_expires_at'],
                ];
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function finalizePayloadForOrderCrossPlatform(
        string $rawPayload,
        string $platform,
        int $messengerUserId,
        array $plan,
        string $orderKind,
    ): array {
        $user = $this->users->find($platform, $messengerUserId) ?? [];
        $ascii = BotPlatform::isBale($platform);
        $remark = Util::defaultConfigRemark($user, $plan, $this->brandName(), $ascii);
        $base = Util::stripUrlFragment($rawPayload);
        $display = Util::withUrlFragment($base, $remark);
        $testAt = $orderKind === 'test' ? $this->testExpiryEndOfDay() : null;

        return ['payload' => $display, 'remark' => $remark, 'test_expires_at' => $testAt];
    }

    private function maybeCreditReferrerCrossPlatform(
        string $platform,
        int $buyerId,
        string $publicId,
        int $priceToman,
        string $orderKind,
    ): void {
        if ($orderKind !== 'standard' || $priceToman <= 0) {
            return;
        }
        $buyer = $this->users->find($platform, $buyerId);
        if ($buyer === null) {
            return;
        }
        $ref = (int) ($buyer['referred_by'] ?? 0);
        if ($ref <= 0 || $ref === $buyerId) {
            return;
        }
        $pct = (float) ($this->config['referral_percent_of_sale'] ?? 5) / 100.0;
        $amt = (int) floor($priceToman * $pct);
        if ($amt < 1) {
            return;
        }
        $this->users->addBalance($platform, $ref, $amt);
        $this->referrals->recordPayout($platform, $ref, $buyerId, $publicId, $amt);
        $this->notifyReferrerPurchase($platform, $ref, $buyerId, $amt, $publicId);
    }

    private function notifyReferrerPurchase(
        string $platform,
        int $referrerId,
        int $buyerId,
        int $amountToman,
        string $orderPublicId,
    ): void {
        if ($this->messengerApis === null || $referrerId <= 0 || $amountToman < 1) {
            return;
        }
        $plat = BotPlatform::normalize($platform);
        $api = $this->messengerApis[$plat] ?? null;
        if ($api === null) {
            return;
        }
        $msg = I18n::fmt('referrer_notify_purchase', [
            'buyer' => (string) $buyerId,
            'amount' => Util::formatNumber($amountToman),
            'order' => Util::e($orderPublicId),
        ]);
        $api->sendMessage($referrerId, $msg, null, 'HTML');
    }
}
