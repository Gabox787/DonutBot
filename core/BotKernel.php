<?php

declare(strict_types=1);

final class BotKernel
{
    /**
     * @param array<string, MessengerApi> $messengerApis At least one entry for {@see BotKernel::$platform}.
     */
    public function __construct(
        private array $messengerApis,
        private string $platform,
        private UserRepository $users,
        private StateRepository $states,
        private PlanRepository $plans,
        private TopupRepository $topups,
        private OrderRepository $orders,
        private PlanConfigRepository $configs,
        private PurchaseService $purchase,
        private ReferralRepository $referrals,
        /** @var array<string, mixed> */
        private array $config,
    ) {
    }

    private function mx(): MessengerApi
    {
        return $this->messengerApis[$this->platform]
            ?? throw new RuntimeException('messenger_missing_for_platform:' . $this->platform);
    }

    private function mxFor(string $platform): ?MessengerApi
    {
        $p = BotPlatform::normalize($platform);

        return $this->messengerApis[$p] ?? null;
    }

    public function handle(?array $update): void
    {
        if ($update === null) {
            return;
        }
        if (isset($update['callback_query'])) {
            $this->onCallback($update['callback_query']);

            return;
        }
        if (isset($update['message'])) {
            $this->onMessage($update['message']);
        }
    }

    private function isAdmin(int $telegramId): bool
    {
        $ids = BotPlatform::isBale($this->platform)
            ? ($this->config['admin_bale_ids'] ?? [])
            : ($this->config['admin_telegram_ids'] ?? []);
        foreach ($ids as $a) {
            if ((int) $a === $telegramId) {
                return true;
            }
        }

        return false;
    }

    private function requiredChannelUsername(): string
    {
        $key = BotPlatform::isBale($this->platform)
            ? 'required_channel_username_bale'
            : 'required_channel_username';
        $v = trim(ltrim((string) ($this->config[$key] ?? $this->config['required_channel_username'] ?? ''), '@'));

        return $v;
    }

    /** Join button URL: full override or default t.me */
    private function channelJoinUrl(string $channelUsername): string
    {
        $key = BotPlatform::isBale($this->platform) ? 'channel_join_url_bale' : 'channel_join_url_telegram';
        $override = trim((string) ($this->config[$key] ?? ''));
        if ($override !== '') {
            return $override;
        }

        return 'https://t.me/' . ltrim($channelUsername, '@');
    }

    /** @return list<array<string, mixed>> */
    private function plansActivePresented(): array
    {
        $list = $this->plans->listActive();
        if (BotPlatform::isBale($this->platform)) {
            return PlanRepository::applyBalePresentation($list);
        }

        return $list;
    }

    /** @return array<string, mixed>|null */
    private function planByIdPresented(int $id): ?array
    {
        $p = $this->plans->getById($id);
        if (BotPlatform::isBale($this->platform)) {
            return PlanRepository::applyBalePresentationRow($p);
        }

        return $p;
    }

    /**
     * On Bale, subscription line is sent as a .txt file (policy). Telegram: inline code in messages as before.
     *
     * @param array<string, mixed>|null $replyMarkup
     */
    private function deliverPayloadFileIfBale(int $chatId, string $payload, string $captionHtml, ?array $replyMarkup): void
    {
        if (!BotPlatform::isBale($this->platform)) {
            return;
        }
        $plain = Util::payloadPlainForFile($payload);
        $fn = 'order-' . substr(hash('sha256', $plain), 0, 10) . '.txt';
        $cap = trim(strip_tags(str_replace(['<b>', '</b>', '<code>', '</code>'], ['', '', '', ''], $captionHtml)));
        $this->mx()->sendDocument($chatId, $fn, $plain, $cap !== '' ? $cap : null, $replyMarkup, 'HTML');
    }

    /**
     * @param array<string, mixed> $orderRow from list/find with plan join
     */
    private function orderDisplayTitle(array $orderRow): string
    {
        if (BotPlatform::isBale($this->platform)) {
            $tb = trim((string) ($orderRow['title_bale'] ?? ''));

            return $tb !== '' ? $tb : (string) ($orderRow['title'] ?? '?');
        }

        return (string) ($orderRow['title'] ?? '?');
    }

    private function buildReferralLink(int $messengerUserId): string
    {
        if (BotPlatform::isBale($this->platform)) {
            $tpl = trim((string) ($this->config['referral_link_template_bale'] ?? ''));
            $bot = trim(ltrim((string) ($this->config['bale_bot_username'] ?? ''), '@'));
            if ($tpl !== '') {
                return str_replace(['{id}', '{bot}'], [(string) $messengerUserId, $bot], $tpl);
            }
            if ($bot !== '') {
                return 'https://ble.ir/' . rawurlencode($bot) . '?start=ref_' . $messengerUserId;
            }

            return '';
        }
        $tpl = trim((string) ($this->config['referral_link_template_telegram'] ?? ''));
        $bot = trim(ltrim((string) ($this->config['telegram_bot_username'] ?? ''), '@'));
        if ($tpl !== '') {
            return str_replace(['{id}', '{bot}'], [(string) $messengerUserId, $bot], $tpl);
        }
        if ($bot !== '') {
            return 'https://t.me/' . $bot . '?start=ref_' . $messengerUserId;
        }

        return '';
    }

    private function userMayUseBot(int $telegramId): bool
    {
        $ch = $this->requiredChannelUsername();
        if ($ch === '') {
            return true;
        }
        $r = $this->mx()->getChatMember($ch, $telegramId);
        if (!($r['ok'] ?? false)) {
            return false;
        }
        $s = (string) ($r['result']['status'] ?? '');

        return in_array($s, ['creator', 'administrator', 'member', 'restricted'], true);
    }

    private function sendChannelGate(int $chatId): void
    {
        $ch = $this->requiredChannelUsername();
        if ($ch === '') {
            return;
        }
        $link = $this->channelJoinUrl($ch);
        $this->mx()->sendMessage(
            $chatId,
            I18n::fmt('channel_gate_html', [
                'channel' => Util::e('@' . $ch),
                'link' => Util::e($link),
            ]),
            [
                'inline_keyboard' => [
                    [
                        ['text' => I18n::txt('btn_join_channel'), 'url' => $link],
                    ],
                ],
            ],
            'HTML'
        );
    }

    private function callbackBypassesChannelGate(string $data, int $telegramId): bool
    {
        return (str_starts_with($data, 'ap:') || str_starts_with($data, 'rj:')) && $this->isAdmin($telegramId);
    }

    private function rk(string $text, ?string $style = null): array
    {
        $b = ['text' => $text];
        if ($style !== null) {
            $b['style'] = $style;
        }

        return $b;
    }

    private function ibt(string $text, string $callbackData, ?string $style = null): array
    {
        $b = ['text' => $text, 'callback_data' => $callbackData];
        if ($style !== null) {
            $b['style'] = $style;
        }

        return $b;
    }

    /** @return array<string, mixed>|null */
    private function freshUser(int $telegramId): ?array
    {
        return $this->users->find($this->platform, $telegramId);
    }

    private function buildUserMainKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    $this->rk(I18n::txt('rk_buy'), 'primary'),
                    $this->rk(I18n::txt('rk_wallet'), 'success'),
                ],
                [
                    $this->rk(I18n::txt('rk_test')),
                    $this->rk(I18n::txt('rk_configs')),
                ],
                [
                    $this->rk(I18n::txt('rk_help')),
                    $this->rk(I18n::txt('rk_support')),
                ],
                [
                    $this->rk(I18n::txt('rk_income')),

                ],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    /** @param array<string, mixed>|null $inlineMarkup */
    private function sendPanel(int $telegramId, int $chatId, string $html, ?array $inlineMarkup, bool $attachReplyKeyboard): void
    {
        if ($this->freshUser($telegramId) === null) {
            return;
        }
        $replyMarkup = null;
        if ($inlineMarkup !== null && ($inlineMarkup['inline_keyboard'] ?? []) !== []) {
            $replyMarkup = $inlineMarkup;
        } elseif ($attachReplyKeyboard) {
            $replyMarkup = $this->buildUserMainKeyboard();
        }
        $sent = $this->mx()->sendMessage($chatId, $html, $replyMarkup, 'HTML');
        if (!($sent['ok'] ?? false) || !isset($sent['result']['message_id'])) {
            $this->log('sendPanel failed ' . json_encode($sent, JSON_UNESCAPED_UNICODE));

            return;
        }
        $this->users->setHub($this->platform, $telegramId, $chatId, (int) $sent['result']['message_id']);
    }

    private function walletInlineMarkup(): array
    {
        return [
            'inline_keyboard' => [
                [
                    $this->ibt(I18n::txt('rk_wallet_add'), 'wa', 'success'),
                ],
                [
                    $this->ibt(I18n::txt('btn_home'), 'h', 'primary'),
                ],
            ],
        ];
    }

    private function inlineCancelPayment(): array
    {
        return ['inline_keyboard' => [[$this->ibt(I18n::txt('btn_cancel_payment'), 'wx', 'danger')]]];
    }

    private function inlineHome(): array
    {
        return ['inline_keyboard' => [[$this->ibt(I18n::txt('btn_home'), 'h', 'primary')]]];
    }

    /** @param array<string, mixed> $msg */
    private function onMessage(array $msg): void
    {
        $from = $msg['from'] ?? [];
        $telegramId = (int) ($from['id'] ?? 0);
        if ($telegramId <= 0) {
            return;
        }
        $chatId = (int) ($msg['chat']['id'] ?? 0);
        $username = isset($from['username']) ? (string) $from['username'] : null;
        $first = (string) ($from['first_name'] ?? '');
        $this->users->touch($this->platform, $telegramId, $username, $first);

        $text = isset($msg['text']) ? trim((string) $msg['text']) : '';
        $cmd = $text !== '' ? $this->parseCommand($text) : null;

        if ($cmd !== null && $cmd['cmd'] === 'start') {
            $this->applyReferralStartArg($telegramId, $cmd['rest']);
            if (!$this->userMayUseBot($telegramId)) {
                $this->sendChannelGate($chatId);

                return;
            }
            $this->cmdStart($telegramId, $chatId);

            return;
        }

        if (!$this->userMayUseBot($telegramId)) {
            $this->sendChannelGate($chatId);

            return;
        }

        $user = $this->freshUser($telegramId);
        if ($user === null) {
            return;
        }

        if (($user['hub_message_id'] ?? null) === null || ($user['hub_chat_id'] ?? null) === null) {
            if ($cmd !== null) {
                $this->bootHub($telegramId, $chatId);
                if ($cmd['cmd'] !== 'start') {
                    $this->routeSlash($telegramId, $chatId, $cmd['cmd']);
                }

                return;
            }
            $this->mx()->sendMessage($chatId, I18n::txt('state_must_start'), null, 'HTML');

            return;
        }

        $state = (string) ($this->states->get($this->platform, $telegramId)['state'] ?? '');

        if (isset($msg['photo']) && is_array($msg['photo']) && $msg['photo'] !== []) {
            if ($state === 'awaiting_receipt') {
                $this->onReceiptPhoto($telegramId, $chatId, $msg['photo']);

                return;
            }
            $this->sendInvalid($chatId);

            return;
        }

        if ($cmd !== null) {
            $this->states->clear($this->platform, $telegramId);
            $this->routeSlash($telegramId, $chatId, $cmd['cmd']);

            return;
        }

        if ($text === '') {
            return;
        }

        if ($state === 'awaiting_gb_buy') {
            $this->onAwaitingGbBuy($telegramId, $chatId, $text);

            return;
        }

        if ($this->routeUserReplyKeyboard($telegramId, $chatId, $text)) {
            return;
        }

        if ($state === 'awaiting_amount') {
            $this->onWalletAmount($telegramId, $chatId, $text);

            return;
        }
        if ($state === 'awaiting_receipt') {
            $this->sendInvalid($chatId);

            return;
        }

        $this->sendInvalid($chatId);
    }

    private function routeUserReplyKeyboard(int $telegramId, int $chatId, string $text): bool
    {
        if ($text === I18n::txt('rk_buy')) {
            $this->states->clear($this->platform, $telegramId);
            $this->renderBuyList($telegramId, $chatId);

            return true;
        }
        if ($text === I18n::txt('rk_test')) {
            $this->states->clear($this->platform, $telegramId);
            $this->renderTestPlanPicker($telegramId, $chatId);

            return true;
        }
        if ($text === I18n::txt('rk_wallet')) {
            $this->renderWalletScreen($telegramId, $chatId);

            return true;
        }
        if ($text === I18n::txt('rk_configs')) {
            $this->states->clear($this->platform, $telegramId);
            $this->renderMyConfigs($telegramId, $chatId);

            return true;
        }
        if ($text === I18n::txt('rk_support')) {
            $this->states->clear($this->platform, $telegramId);
            $this->renderSupport($telegramId, $chatId);

            return true;
        }
        if ($text === I18n::txt('rk_help') || $text === I18n::txt('rk_faq')) {
            $this->states->clear($this->platform, $telegramId);
            $this->renderHelp($telegramId, $chatId);

            return true;
        }
        if ($text === I18n::txt('rk_income')) {
            $this->states->clear($this->platform, $telegramId);
            $this->renderIncomeScreen($telegramId, $chatId);

            return true;
        }

        return false;
    }

    /** @return array{cmd: string, rest: string}|null */
    private function parseCommand(string $text): ?array
    {
        if ($text === '' || $text[0] !== '/') {
            return null;
        }
        $parts = preg_split('/\s+/u', $text, 2) ?: [];
        $head = strtolower(ltrim($parts[0] ?? '', '/'));
        $head = explode('@', $head, 2)[0];
        if ($head === '') {
            return null;
        }

        return ['cmd' => $head, 'rest' => isset($parts[1]) ? (string) $parts[1] : ''];
    }

    private function applyReferralStartArg(int $telegramId, string $rest): void
    {
        $rest = trim($rest);
        if (!preg_match('/^ref_(\d{1,20})$/i', $rest, $m)) {
            return;
        }
        $ref = (int) $m[1];
        if ($this->users->setReferrerIfEmpty($this->platform, $telegramId, $ref) === 1) {
            $this->notifyReferrerNewJoin($ref, $telegramId);
        }
    }

    private function cmdStart(int $telegramId, int $chatId): void
    {
        $this->states->clear($this->platform, $telegramId);
        $this->bootHub($telegramId, $chatId);
    }

    private function bootHub(int $telegramId, int $chatId): void
    {
        $this->sendPanel($telegramId, $chatId, I18n::txt('hub_home'), null, true);
    }

    private function routeSlash(int $telegramId, int $chatId, string $cmd): void
    {
        match ($cmd) {
            'buy' => $this->renderBuyList($telegramId, $chatId),
            'test' => $this->renderTestPlanPicker($telegramId, $chatId),
            'wallet' => $this->renderWalletScreen($telegramId, $chatId),
            'configs' => $this->renderMyConfigs($telegramId, $chatId),
            'support' => $this->renderSupport($telegramId, $chatId),
            'faq', 'help' => $this->renderHelp($telegramId, $chatId),
            'income' => $this->renderIncomeScreen($telegramId, $chatId),
            default => $this->sendPanel($telegramId, $chatId, I18n::txt('hub_home'), null, true),
        };
    }

    /** @param array<string, mixed> $cb */
    private function onCallback(array $cb): void
    {
        $from = $cb['from'] ?? [];
        $telegramId = (int) ($from['id'] ?? 0);
        if ($telegramId <= 0) {
            return;
        }
        $data = (string) ($cb['data'] ?? '');
        $chatId = (int) ($cb['message']['chat']['id'] ?? 0);
        $this->mx()->answerCallbackQuery((string) ($cb['id'] ?? ''));

        $username = isset($from['username']) ? (string) $from['username'] : null;
        $first = (string) ($from['first_name'] ?? '');
        $this->users->touch($this->platform, $telegramId, $username, $first);

        $user = $this->freshUser($telegramId);
        if ($user === null) {
            return;
        }

        if (!$this->callbackBypassesChannelGate($data, $telegramId) && !$this->userMayUseBot($telegramId)) {
            $this->sendChannelGate($chatId);

            return;
        }

        if (str_starts_with($data, 'ap:')) {
            $this->adminApprove($telegramId, substr($data, 3), $cb);

            return;
        }
        if (str_starts_with($data, 'rj:')) {
            $this->adminReject($telegramId, substr($data, 3), $cb);

            return;
        }

        if ($data === 'wx') {
            $this->cancelWalletSession($telegramId, $chatId);

            return;
        }
        if ($data === 'wa') {
            $this->beginWalletCharge($telegramId, $chatId, null, null, null);

            return;
        }
        if (preg_match('/^wcx:(\d+)$/', $data, $m)) {
            $this->beginWalletCharge($telegramId, $chatId, (int) $m[1], null, 'test');

            return;
        }
        if (preg_match('/^wc:(\d+)(?::(\d+))?$/', $data, $m)) {
            $this->beginWalletCharge($telegramId, $chatId, (int) $m[1], isset($m[2]) ? (int) $m[2] : null, 'buy');

            return;
        }
        if (preg_match('/^tq:(\d+)$/', $data, $m)) {
            $this->finalizeTestPurchase($telegramId, $chatId, (int) $m[1]);

            return;
        }

        $this->states->clear($this->platform, $telegramId);

        if ($data === 'h') {
            $this->sendPanel($telegramId, $chatId, I18n::txt('hub_home'), null, true);

            return;
        }
        if ($data === 'b') {
            $this->renderBuyList($telegramId, $chatId);

            return;
        }
        if ($data === 'w') {
            $this->renderWalletScreen($telegramId, $chatId);

            return;
        }
        if ($data === 'm') {
            $this->renderMyConfigs($telegramId, $chatId);

            return;
        }
        if ($data === 't') {
            $this->renderTestPlanPicker($telegramId, $chatId);

            return;
        }
        if ($data === 's') {
            $this->renderSupport($telegramId, $chatId);

            return;
        }
        if ($data === 'q') {
            $this->renderHelp($telegramId, $chatId);

            return;
        }
        if ($data === 'z:h') {
            return;
        }
        if ($data === 'inc') {
            $this->renderIncomeScreen($telegramId, $chatId);

            return;
        }

        if (preg_match('/^c:([a-f0-9]{12})$/', $data, $m)) {
            $this->renderConfigDetail($telegramId, $chatId, $m[1]);

            return;
        }
        if (preg_match('/^tp:(\d+)$/', $data, $m)) {
            $this->renderTestPlanAction($telegramId, $chatId, (int) $m[1]);

            return;
        }

        if (preg_match('/^p:(\d+)$/', $data, $m)) {
            $this->renderPlanDetail($telegramId, $chatId, (int) $m[1]);

            return;
        }
        if (preg_match('/^g:(\d+)$/', $data, $m)) {
            $this->onProceedBuy($telegramId, $chatId, (int) $m[1]);

            return;
        }
        if (preg_match('/^v:(\d+)$/', $data, $m)) {
            $this->renderPlanDetail($telegramId, $chatId, (int) $m[1]);

            return;
        }
        if (preg_match('/^n:(\d+)(?::(\d+))?$/', $data, $m)) {
            $gb = isset($m[2]) ? (int) $m[2] : null;
            $this->finalizePurchase($telegramId, $chatId, (int) $m[1], $gb);

            return;
        }
    }

    private function onProceedBuy(int $telegramId, int $chatId, int $planId): void
    {
        $p = $this->planByIdPresented($planId);
        if ($p === null) {
            $this->renderBuyList($telegramId, $chatId);

            return;
        }
        if (!empty($p['allow_custom_gb'])) {
            $this->states->set($this->platform, $telegramId, 'awaiting_gb_buy', ['plan_id' => $planId]);
            $gmin = (string) Util::planGbMin($p);
            $gmax = (string) Util::planGbMax($p);
            $this->sendPanel(
                $telegramId,
                $chatId,
                I18n::fmt('buy_ask_gb', ['min' => $gmin, 'max' => $gmax]),
                [
                    'inline_keyboard' => [
                        [$this->ibt(I18n::txt('btn_back_plans'), 'b')],
                        [$this->ibt(I18n::txt('btn_home'), 'h', 'primary')],
                    ],
                ],
                false
            );

            return;
        }
        $this->renderCheckout($telegramId, $chatId, $planId, null);
    }

    private function onAwaitingGbBuy(int $telegramId, int $chatId, string $text): void
    {
        $st = $this->states->get($this->platform, $telegramId);
        $planId = (int) ($st['data']['plan_id'] ?? 0);
        $p = $planId > 0 ? $this->planByIdPresented($planId) : null;
        if ($p === null || empty($p['allow_custom_gb'])) {
            $this->states->clear($this->platform, $telegramId);
            $this->sendInvalid($chatId);

            return;
        }
        if (!preg_match('/^\d+$/', $text)) {
            $this->mx()->sendMessage($chatId, I18n::txt('buy_gb_invalid'), null, 'HTML');

            return;
        }
        $g = (int) $text;
        if ($g < Util::planGbMin($p) || $g > Util::planGbMax($p)) {
            $this->mx()->sendMessage($chatId, I18n::txt('buy_gb_invalid'), null, 'HTML');

            return;
        }
        $this->states->clear($this->platform, $telegramId);
        $this->renderCheckout($telegramId, $chatId, $planId, $g);
    }

    private function renderTestPlanPicker(int $telegramId, int $chatId): void
    {
        $list = $this->plansActivePresented();
        if ($list === []) {
            $this->sendPanel($telegramId, $chatId, I18n::txt('test_no_plans'), $this->inlineHome(), false);

            return;
        }
        $rows = [];
        foreach ($list as $p) {
            if (empty($p['test_enabled']) || trim((string) ($p['test_config_url'] ?? '')) === '') {
                continue;
            }
            $pid = (int) $p['id'];
            $label = I18n::fmt('plan_row', [
                'title' => (string) $p['title'],
                'gb' => (string) (int) $p['gb'],
                'price' => Util::formatNumber((int) $p['price_toman']),
            ]);
            $rows[] = [$this->ibt($label, 'tp:' . $pid)];
        }
        if ($rows === []) {
            $this->sendPanel($telegramId, $chatId, I18n::txt('test_no_plans_ready'), $this->inlineHome(), false);

            return;
        }
        $rows[] = [$this->ibt(I18n::txt('btn_home'), 'h', 'primary')];
        $this->sendPanel(
            $telegramId,
            $chatId,
            I18n::txt('test_pick_plan'),
            ['inline_keyboard' => $rows],
            false
        );
    }

    private function renderTestPlanAction(int $telegramId, int $chatId, int $planId): void
    {
        $p = $this->planByIdPresented($planId);
        if ($p === null) {
            $this->renderTestPlanPicker($telegramId, $chatId);

            return;
        }
        if (empty($p['test_enabled'])) {
            $this->sendPanel(
                $telegramId,
                $chatId,
                I18n::fmt('test_not_available', ['title' => Util::e((string) $p['title'])]),
                [
                    'inline_keyboard' => [
                        [$this->ibt(I18n::txt('btn_back_test'), 't')],
                        [$this->ibt(I18n::txt('btn_home'), 'h', 'primary')],
                    ],
                ],
                false
            );

            return;
        }
        if (trim((string) ($p['test_config_url'] ?? '')) === '') {
            $this->sendPanel(
                $telegramId,
                $chatId,
                I18n::fmt('test_no_url', ['title' => Util::e((string) $p['title'])]),
                [
                    'inline_keyboard' => [
                        [$this->ibt(I18n::txt('btn_back_test'), 't')],
                        [$this->ibt(I18n::txt('btn_home'), 'h', 'primary')],
                    ],
                ],
                false
            );

            return;
        }
        $price = (int) $p['test_price_toman'];
        $u = $this->freshUser($telegramId);
        $bal = (int) ($u['balance_toman'] ?? 0);
        $short = max(0, $price - $bal);
        $html = I18n::fmt('test_checkout', [
            'title' => Util::e((string) $p['title']),
            'price' => Util::formatNumber($price),
            'balance' => Util::formatNumber($bal),
        ]);
        if ($bal < $price) {
            $html .= I18n::fmt('buy_insufficient', ['shortage' => Util::formatNumber($short)]);
        }
        $kb = [];
        if ($bal >= $price) {
            $kb[] = [$this->ibt(I18n::txt('btn_pay_test'), 'tq:' . $planId, 'success')];
        } else {
            $kb[] = [$this->ibt(I18n::txt('btn_charge_wallet'), 'wcx:' . $planId, 'primary')];
        }
        $kb[] = [$this->ibt(I18n::txt('btn_back_test'), 't')];
        $this->sendPanel($telegramId, $chatId, $html, ['inline_keyboard' => $kb], false);
    }

    private function finalizeTestPurchase(int $telegramId, int $chatId, int $planId): void
    {
        $p = $this->planByIdPresented($planId);
        if ($p === null || empty($p['test_enabled'])) {
            $this->renderTestPlanPicker($telegramId, $chatId);

            return;
        }
        $testPrice = (int) $p['test_price_toman'];
        $res = $this->purchase->placeTestOrder($telegramId, $planId, $testPrice, $p);
        if ($res === null) {
            $this->renderTestPlanAction($telegramId, $chatId, $planId);

            return;
        }
        $kind = ($res['status'] === 'fulfilled') ? '✅ آنی' : '⏳ انتظار';
        $this->notifyAdminsOrder(
            $telegramId,
            (string) $p['title'],
            $testPrice,
            (string) $res['public_id'],
            $kind . ' · ' . I18n::txt('order_kind_test'),
        );
        if ($res['status'] === 'fulfilled' && isset($res['payload'])) {
            $exp = I18n::txt('test_valid_until_eod');
            $payloadStr = (string) $res['payload'];
            if (BotPlatform::isBale($this->platform)) {
                $this->sendPanel(
                    $telegramId,
                    $chatId,
                    I18n::fmt('test_success_bale', [
                        'amount' => Util::formatNumber($testPrice),
                        'valid' => $exp,
                    ]),
                    $this->inlineHome(),
                    false
                );
                $cap = I18n::txt('bale_payload_file_caption_test');
                $this->deliverPayloadFileIfBale($chatId, $payloadStr, $cap, null);

                return;
            }
            $this->sendPanel(
                $telegramId,
                $chatId,
                I18n::fmt('test_success', [
                    'payload' => Util::e($payloadStr),
                    'amount' => Util::formatNumber($testPrice),
                    'valid' => $exp,
                ]),
                $this->inlineHome(),
                false
            );

            return;
        }
        $pendKey = BotPlatform::isBale($this->platform) ? 'buy_pending_bale' : 'buy_pending';
        $this->sendPanel(
            $telegramId,
            $chatId,
            I18n::fmt($pendKey, ['order_id' => Util::e((string) $res['public_id'])]),
            $this->inlineHome(),
            false
        );
    }

    private function cancelWalletSession(int $telegramId, int $chatId): void
    {
        $st = $this->states->get($this->platform, $telegramId);
        $s = (string) ($st['state'] ?? '');
        $pub = (string) ($st['data']['public_id'] ?? '');
        if ($s === 'awaiting_receipt' && $pub !== '') {
            $this->topups->cancelPendingWithoutReceipt($pub, $this->platform, $telegramId);
        }
        $this->states->clear($this->platform, $telegramId);
        $html = I18n::txt('wallet_cancelled') . "\n\n" . $this->walletBodyHtml($telegramId);
        $this->sendPanel($telegramId, $chatId, $html, $this->walletInlineMarkup(), false);
    }

    private function walletBodyHtml(int $telegramId): string
    {
        $u = $this->freshUser($telegramId);
        $bal = (int) ($u['balance_toman'] ?? 0);

        return I18n::fmt('wallet_screen', ['balance' => Util::formatNumber($bal)]);
    }

    private function renderWalletScreen(int $telegramId, int $chatId): void
    {
        $this->states->clear($this->platform, $telegramId);
        $this->sendPanel(
            $telegramId,
            $chatId,
            $this->walletBodyHtml($telegramId),
            $this->walletInlineMarkup(),
            false
        );
    }

    private function beginWalletCharge(int $telegramId, int $chatId, ?int $resumePlan, ?int $resumeGb, ?string $resumeKind = null): void
    {
        $data = [];
        if ($resumePlan !== null && $resumePlan > 0) {
            $data['resume_plan_id'] = $resumePlan;
        }
        if ($resumeGb !== null && $resumeGb > 0) {
            $data['resume_gb'] = $resumeGb;
        }
        if ($resumeKind === 'test') {
            $data['resume_kind'] = 'test';
        }
        $this->states->set($this->platform, $telegramId, 'awaiting_amount', $data);
        $this->sendPanel(
            $telegramId,
            $chatId,
            I18n::txt('wallet_ask_amount') . "\n\n" . I18n::txt('wallet_cancel_hint'),
            $this->inlineCancelPayment(),
            false
        );
    }

    private function onWalletAmount(int $telegramId, int $chatId, string $text): void
    {
        if (!preg_match('/^\d+$/', $text)) {
            $this->mx()->sendMessage($chatId, I18n::txt('wallet_invalid_amount'), null, 'HTML');

            return;
        }
        $amount = (int) $text;
        if ($amount <= 0) {
            $this->mx()->sendMessage($chatId, I18n::txt('wallet_invalid_amount'), null, 'HTML');

            return;
        }

        $st = $this->states->get($this->platform, $telegramId);
        $resume = isset($st['data']['resume_plan_id']) ? (int) $st['data']['resume_plan_id'] : null;
        $resumeGb = isset($st['data']['resume_gb']) ? (int) $st['data']['resume_gb'] : null;
        $pub = Util::publicId12();
        $this->topups->createPending($this->platform, $telegramId, $amount, $pub);

        $pay = $this->config['payment'];
        $body = I18n::fmt('wallet_pay_instruction', [
            'amount' => Util::formatNumber($amount),
            'card' => Util::e((string) ($pay['card_number'] ?? '')),
            'holder' => Util::e((string) ($pay['card_holder'] ?? '')),
            'minutes' => (string) (int) ($pay['pay_window_minutes'] ?? 10),
        ]) . "\n\n" . I18n::txt('wallet_receipt_wait');

        $data = ['public_id' => $pub];
        if ($resume) {
            $data['resume_plan_id'] = $resume;
        }
        if ($resumeGb) {
            $data['resume_gb'] = $resumeGb;
        }
        $rk = (string) ($st['data']['resume_kind'] ?? '');
        if ($rk === 'test') {
            $data['resume_kind'] = 'test';
        }
        $this->states->set($this->platform, $telegramId, 'awaiting_receipt', $data);

        $user = $this->freshUser($telegramId);
        if (!$user) {
            return;
        }
        $this->sendPanel($telegramId, $chatId, $body, $this->inlineCancelPayment(), false);
    }

    /** @param list<array<string, mixed>> $photos */
    private function onReceiptPhoto(int $telegramId, int $chatId, array $photos): void
    {
        $st = $this->states->get($this->platform, $telegramId);
        $pub = (string) ($st['data']['public_id'] ?? '');
        if ($pub === '') {
            $this->sendInvalid($chatId);

            return;
        }
        $last = $photos[array_key_last($photos)];
        $fileId = (string) ($last['file_id'] ?? '');
        $uniq = (string) ($last['file_unique_id'] ?? '');
        if ($fileId === '') {
            return;
        }
        $row = $this->topups->findPendingByPublicIdForUser($pub, $this->platform, $telegramId);
        if ($row === null) {
            $this->states->clear($this->platform, $telegramId);

            return;
        }
        if (!empty($row['receipt_file_id'])) {
            $this->states->clear($this->platform, $telegramId);
            $this->sendPanel(
                $telegramId,
                $chatId,
                I18n::fmt('wallet_topup_created', ['trx_id' => Util::e($pub)]),
                null,
                true
            );

            return;
        }

        if (!$this->topups->attachReceipt($pub, $this->platform, $telegramId, $fileId, $uniq)) {
            return;
        }
        $resumePlan = isset($st['data']['resume_plan_id']) ? (int) $st['data']['resume_plan_id'] : null;
        $resumeGb = isset($st['data']['resume_gb']) ? (int) $st['data']['resume_gb'] : null;
        $resumeKind = (string) ($st['data']['resume_kind'] ?? '');
        $this->states->clear($this->platform, $telegramId);
        $this->sendPanel(
            $telegramId,
            $chatId,
            I18n::fmt('wallet_topup_created', ['trx_id' => Util::e($pub)]),
            null,
            true
        );
        $this->notifyAdminsTopup($telegramId, (int) $row['amount_toman'], $pub, $fileId);
        if ($resumePlan !== null && $resumePlan > 0) {
            $p = $this->planByIdPresented($resumePlan);
            if ($p !== null && $resumeKind === 'test' && !empty($p['test_enabled'])) {
                $this->finalizeTestPurchase($telegramId, $chatId, $resumePlan);
            } elseif ($p !== null) {
                $g = $resumeGb;
                if ($g !== null && $g <= 0) {
                    $g = null;
                }
                if (!empty($p['allow_custom_gb']) && $g === null) {
                    $this->onProceedBuy($telegramId, $chatId, $resumePlan);
                } else {
                    $this->renderCheckout($telegramId, $chatId, $resumePlan, $g);
                }
            }
        }
    }

    private function renderBuyList(int $telegramId, int $chatId): void
    {
        $stockSum = Util::formatNumber($this->configs->countAvailableTotal());
        $list = $this->plansActivePresented();
        $rows = [];
        foreach ($list as $p) {
            $pid = (int) $p['id'];
            $label = I18n::fmt('plan_row', [
                'title' => (string) $p['title'],
                'gb' => (string) (int) $p['gb'],
                'price' => (string) (int) $p['price_toman'],
            ]);
            $rows[] = [$this->ibt($label, 'p:' . $pid)];
        }
        $rows[] = [$this->ibt(I18n::txt('btn_home'), 'h', 'primary')];
        $this->sendPanel(
            $telegramId,
            $chatId,
            I18n::fmt('buy_intro', ['stock' => $stockSum]),
            ['inline_keyboard' => $rows],
            false
        );
    }

    private function renderPlanDetail(int $telegramId, int $chatId, int $planId): void
    {
        $p = $this->planByIdPresented($planId);
        if ($p === null) {
            $this->renderBuyList($telegramId, $chatId);

            return;
        }
        $desc = trim((string) ($p['description'] ?? ''));
        if (!empty($p['is_featured'])) {
            $desc = ($desc !== '' ? $desc . "\n\n" : '') . I18n::txt('plan_suggested');
        }
        $stock = Util::formatNumber($this->configs->countAvailable($planId));
        $gbLabel = !empty($p['allow_custom_gb'])
            ? I18n::fmt('plan_gb_range', [
                'min' => (string) Util::planGbMin($p),
                'max' => (string) Util::planGbMax($p),
            ])
            : (string) (int) $p['gb'];
        $uL = Util::planUserLimitDisplay($p);
        $dD = Util::planDurationDaysDisplay($p);
        $usersMeta = $uL === null
            ? I18n::txt('label_unlimited')
            : (string) $uL;
        $daysMeta = $dD === null
            ? I18n::txt('label_unlimited')
            : I18n::fmt('plan_days_term', ['n' => (string) $dD]);
        $html = I18n::fmt('plan_detail', [
            'title' => Util::e((string) $p['title']),
            'gb' => $gbLabel,
            'price' => Util::formatNumber((int) $p['price_toman']),
            'stock' => $stock,
            'description' => Util::e($desc),
            'users' => $usersMeta,
            'days' => $daysMeta,
        ]);
        $rows = [];
        if (!empty($p['test_enabled']) && trim((string) ($p['test_config_url'] ?? '')) !== '') {
            $rows[] = [$this->ibt(I18n::txt('btn_plan_get_test'), 'tp:' . $planId)];
        }
        $rows[] = [$this->ibt(I18n::txt('btn_proceed_buy'), 'g:' . $planId, 'primary')];
        $rows[] = [$this->ibt(I18n::txt('btn_back_plans'), 'b')];
        $this->sendPanel($telegramId, $chatId, $html, ['inline_keyboard' => $rows], false);
    }

    private function renderCheckout(int $telegramId, int $chatId, int $planId, ?int $chosenGb): void
    {
        $p = $this->planByIdPresented($planId);
        if ($p === null) {
            $this->renderBuyList($telegramId, $chatId);

            return;
        }
        $u = $this->freshUser($telegramId);
        $price = Util::planPriceToman($p, $chosenGb);
        $balance = (int) ($u['balance_toman'] ?? 0);
        $short = max(0, $price - $balance);
        $effGb = $chosenGb ?? (int) $p['gb'];
        $html = I18n::fmt('buy_checkout', [
            'price' => Util::formatNumber($price),
            'balance' => Util::formatNumber($balance),
        ]);
        $html .= "\n" . I18n::fmt('buy_checkout_gb', ['gb' => (string) $effGb]);
        if ($balance < $price) {
            $html .= I18n::fmt('buy_insufficient', ['shortage' => Util::formatNumber($short)]);
        }
        $gbSeg = ($chosenGb !== null) ? ':' . $chosenGb : '';
        $kb = [];
        if ($balance >= $price) {
            $kb[] = [$this->ibt(I18n::txt('btn_finalize_buy'), 'n:' . $planId . $gbSeg, 'success')];
        } else {
            $kb[] = [$this->ibt(I18n::txt('btn_charge_wallet'), 'wc:' . $planId . $gbSeg, 'primary')];
        }
        $kb[] = [$this->ibt(I18n::txt('btn_back'), 'v:' . $planId)];
        $this->sendPanel($telegramId, $chatId, $html, ['inline_keyboard' => $kb], false);
    }

    private function finalizePurchase(int $telegramId, int $chatId, int $planId, ?int $chosenGb): void
    {
        $p = $this->planByIdPresented($planId);
        if ($p === null) {
            $this->renderBuyList($telegramId, $chatId);

            return;
        }
        if (!empty($p['allow_custom_gb'])) {
            if ($chosenGb === null || $chosenGb < Util::planGbMin($p) || $chosenGb > Util::planGbMax($p)) {
                $this->onProceedBuy($telegramId, $chatId, $planId);

                return;
            }
        } else {
            $chosenGb = null;
        }
        $price = Util::planPriceToman($p, $chosenGb);
        $title = (string) $p['title'];
        $res = $this->purchase->placeOrder($telegramId, $planId, $price, $p, $chosenGb, 'standard');
        if ($res === null) {
            $this->renderCheckout($telegramId, $chatId, $planId, $chosenGb);

            return;
        }
        $statusLabel = ($res['status'] === 'fulfilled') ? '✅ آنی' : '⏳ انتظار';
        $this->notifyAdminsOrder($telegramId, $title, $price, (string) $res['public_id'], $statusLabel);

        if ($res['status'] === 'fulfilled') {
            $payloadStr = (string) $res['payload'];
            if (BotPlatform::isBale($this->platform)) {
                $this->sendPanel(
                    $telegramId,
                    $chatId,
                    I18n::txt('buy_success_bale'),
                    $this->inlineHome(),
                    false
                );
                $this->deliverPayloadFileIfBale(
                    $chatId,
                    $payloadStr,
                    I18n::txt('bale_payload_file_caption_buy'),
                    null
                );

                return;
            }
            $this->sendPanel(
                $telegramId,
                $chatId,
                I18n::fmt('buy_success', ['payload' => Util::e($payloadStr)]),
                $this->inlineHome(),
                false
            );

            return;
        }
        $pendKey = BotPlatform::isBale($this->platform) ? 'buy_pending_bale' : 'buy_pending';
        $this->sendPanel(
            $telegramId,
            $chatId,
            I18n::fmt($pendKey, ['order_id' => Util::e((string) $res['public_id'])]),
            $this->inlineHome(),
            false
        );
    }

    private function renderMyConfigs(int $telegramId, int $chatId): void
    {
        $rows = $this->orders->listForUser($this->platform, $telegramId);
        if ($rows === []) {
            $this->sendPanel($telegramId, $chatId, I18n::txt('my_configs_empty'), $this->inlineHome(), false);

            return;
        }
        $buf = I18n::txt('my_configs_title') . I18n::txt('my_configs_pick_hint');
        $kb = [];
        $rowBtns = [];
        foreach ($rows as $o) {
            $pub = (string) ($o['public_id'] ?? '');
            if ($pub === '' || strlen($pub) !== 12) {
                continue;
            }
            $label = I18n::fmt('my_config_btn', [
                'title' => mb_substr($this->orderDisplayTitle($o), 0, 16),
                'id4' => substr($pub, -4),
            ]);
            $rowBtns[] = $this->ibt($label, 'c:' . $pub);
            if (count($rowBtns) >= 2) {
                $kb[] = $rowBtns;
                $rowBtns = [];
            }
        }
        if ($rowBtns !== []) {
            $kb[] = $rowBtns;
        }
        $kb[] = [$this->ibt(I18n::txt('btn_home'), 'h', 'primary')];
        $this->sendPanel($telegramId, $chatId, $buf, ['inline_keyboard' => $kb], false);
    }

    private function renderConfigDetail(int $telegramId, int $chatId, string $publicId): void
    {
        $o = $this->orders->findForUserByPublicId($this->platform, $telegramId, $publicId);
        if ($o === null) {
            $this->renderMyConfigs($telegramId, $chatId);

            return;
        }
        $payload = (string) ($o['payload'] ?? '');
        $gbShow = $o['gb_ordered'] !== null && (int) $o['gb_ordered'] > 0
            ? (string) (int) $o['gb_ordered']
            : (string) (int) ($o['plan_gb'] ?? 0);
        $kindLine = ($o['order_kind'] ?? '') === 'test'
            ? I18n::txt('config_detail_test') . "\n"
            : '';
        $exp = '';
        if (!empty($o['test_expires_at'])) {
            $exp = I18n::fmt('config_detail_expires', ['at' => Util::e((string) $o['test_expires_at'])]) . "\n";
        }
        $limSnap = $o['user_limit_snapshot'];
        $userVal = ($limSnap !== null && (int) $limSnap > 0)
            ? (string) (int) $limSnap
            : I18n::txt('label_unlimited');
        $userLimLine = I18n::fmt('config_detail_users_line', ['val' => $userVal]);
        $metaLines = [];
        $eff = Util::orderEffectiveAccess($o);
        $accessLabel = $eff === 'active'
            ? I18n::txt('access_active')
            : I18n::txt('access_inactive');
        $metaLines[] = I18n::fmt('config_detail_access_line', ['access' => $accessLabel]);
        $metaLines[] = $userLimLine;
        if (!empty($o['service_started_at'])) {
            $metaLines[] = I18n::fmt('config_detail_started', ['at' => Util::e((string) $o['service_started_at'])]);
        }
        $endAt = (string) ($o['service_ends_at'] ?? '');
        if ($endAt === '' && ($o['order_kind'] ?? '') === 'test' && !empty($o['test_expires_at'])) {
            $endAt = (string) $o['test_expires_at'];
        }
        if ($endAt !== '') {
            $metaLines[] = I18n::fmt('config_detail_ends', ['at' => Util::e($endAt)]);
        }
        $meta = implode("\n", $metaLines);
        $dispTitle = Util::e($this->orderDisplayTitle($o));
        if (($o['status'] ?? '') === 'pending') {
            $html = I18n::fmt('config_detail_pending', [
                'title' => $dispTitle,
                'gb' => $gbShow,
                'order_id' => Util::e($publicId),
                'kind' => $kindLine,
            ]);
        } elseif (BotPlatform::isBale($this->platform) && $payload !== '') {
            $html = I18n::fmt('config_detail_ok_bale', [
                'title' => $dispTitle,
                'gb' => $gbShow,
                'kind' => $kindLine,
                'exp' => $exp,
                'meta' => $meta,
            ]);
        } else {
            $html = I18n::fmt('config_detail_ok', [
                'title' => $dispTitle,
                'gb' => $gbShow,
                'payload' => Util::e($payload),
                'kind' => $kindLine,
                'exp' => $exp,
                'meta' => $meta,
            ]);
        }
        $buttons = [];
        $buttons[] = [$this->ibt(I18n::txt('btn_back_configs'), 'm')];
        $buttons[] = [$this->ibt(I18n::txt('btn_home'), 'h', 'primary')];
        $kbd = ['inline_keyboard' => $buttons];
        $this->sendPanel($telegramId, $chatId, $html, $kbd, false);
        if (BotPlatform::isBale($this->platform) && ($o['status'] ?? '') !== 'pending' && $payload !== '') {
            $this->deliverPayloadFileIfBale(
                $chatId,
                $payload,
                I18n::txt('bale_payload_file_caption_detail'),
                null
            );
        }
    }

    private function renderIncomeScreen(int $telegramId, int $chatId): void
    {
        $link = $this->buildReferralLink($telegramId);
        $started = $this->users->countReferrals($this->platform, $telegramId);
        $buyers = $this->referrals->countPurchasingReferrals($this->platform, $telegramId);
        $earned = $this->referrals->totalEarnedByReferrer($this->platform, $telegramId);
        $pct = (string) (int) round((float) ($this->config['referral_percent_of_sale'] ?? 5));
        $html = I18n::fmt('income_screen', [
            'link' => Util::e($link !== '' ? $link : '—'),
            'started' => Util::formatNumber($started),
            'buyers' => Util::formatNumber($buyers),
            'earned' => Util::formatNumber($earned),
            'pct' => $pct,
        ]);
        if ($link === '') {
            $html .= "\n\n" . I18n::txt('income_missing_bot_username');
        }
        $this->sendPanel(
            $telegramId,
            $chatId,
            $html,
            [
                'inline_keyboard' => [
                    [$this->ibt(I18n::txt('btn_home'), 'h', 'primary')],
                ],
            ],
            false
        );
    }

    private function renderSupport(int $telegramId, int $chatId): void
    {
        $u = ltrim((string) ($this->config['support_username'] ?? ''), '@');
        $this->sendPanel(
            $telegramId,
            $chatId,
            I18n::fmt('support_text', ['username' => Util::e($u)]),
            $this->inlineHome(),
            false
        );
    }

    private function renderHelp(int $telegramId, int $chatId): void
    {
        $k = (string) ($this->config['help_text_key'] ?? $this->config['faq_text_key'] ?? 'help_body');
        $html = I18n::txt($k);
        $raw = trim((string) ($this->config['help_links_raw'] ?? ''));
        if ($raw !== '') {
            $html .= "\n\n📚 <b>" . I18n::txt('help_links_title') . "</b>\n";
            foreach (preg_split('/\R/u', $raw) ?: [] as $ln) {
                $ln = trim((string) $ln);
                if ($ln === '') {
                    continue;
                }
                $parts = explode('|', $ln, 2);
                if (count($parts) === 2) {
                    $t = trim($parts[0]);
                    $u = trim($parts[1]);
                    if ($t !== '' && $u !== '') {
                        $html .= '• <a href="' . Util::e($u) . '">' . Util::e($t) . "</a>\n";
                    }
                } else {
                    $html .= '• ' . Util::e($ln) . "\n";
                }
            }
        }
        $this->sendPanel($telegramId, $chatId, $html, $this->inlineHome(), false);
    }

    private function notifyReferrerNewJoin(int $referrerId, int $newUserId): void
    {
        if ($referrerId <= 0) {
            return;
        }
        $this->mx()->sendMessage(
            $referrerId,
            I18n::fmt('referrer_notify_join', ['user' => (string) $newUserId]),
            null,
            'HTML'
        );
    }

    private function notifyAdminsOrder(
        int $userId,
        string $planTitle,
        int $price,
        string $orderPublic,
        string $statusLabel,
    ): void {
        $body = I18n::fmt('admin_new_order', [
            'user' => (string) $userId,
            'plan' => Util::e($planTitle),
            'amount' => Util::formatNumber($price),
            'status' => Util::e($statusLabel),
            'order_id' => Util::e($orderPublic),
        ]);
        $tgApi = $this->mxFor(BotPlatform::TELEGRAM);
        if ($tgApi !== null) {
            foreach (($this->config['admin_telegram_ids'] ?? []) as $aid) {
                $aid = (int) $aid;
                if ($aid > 0) {
                    $tgApi->sendMessage($aid, $body, null, 'HTML');
                }
            }
        }
        $baleApi = $this->mxFor(BotPlatform::BALE);
        if ($baleApi !== null) {
            foreach (($this->config['admin_bale_ids'] ?? []) as $aid) {
                $aid = (int) $aid;
                if ($aid > 0) {
                    $baleApi->sendMessage($aid, $body, null, 'HTML');
                }
            }
        }
    }

    private function notifyAdminsTopup(int $telegramId, int $amount, string $publicId, string $photoId): void
    {
        $cap = I18n::fmt('admin_new_topup', [
            'user' => (string) $telegramId,
            'amount' => Util::formatNumber($amount),
            'trx_id' => Util::e($publicId),
        ]);
        $kb = [
            'inline_keyboard' => [
                [
                    $this->ibt('✅ تأیید', 'ap:' . $publicId, 'success'),
                    $this->ibt('❌ رد', 'rj:' . $publicId, 'danger'),
                ],
            ],
        ];
        $send = function (MessengerApi $api, string $platKey, array $adminIds) use ($cap, $kb, $photoId, $publicId): void {
            foreach ($adminIds as $aid) {
                $aid = (int) $aid;
                if ($aid <= 0) {
                    continue;
                }
                $r = $api->sendPhoto($aid, $photoId, $cap, $kb, 'HTML');
                if (($r['ok'] ?? false) && isset($r['result']['message_id'])) {
                    $this->topups->appendAdminNotifyHandle($publicId, $platKey, $aid, (int) $r['result']['message_id'], true);
                    continue;
                }
                $r2 = $api->sendMessage($aid, $cap, $kb, 'HTML');
                if (($r2['ok'] ?? false) && isset($r2['result']['message_id'])) {
                    $this->topups->appendAdminNotifyHandle($publicId, $platKey, $aid, (int) $r2['result']['message_id'], false);
                }
            }
        };
        $tgApi = $this->mxFor(BotPlatform::TELEGRAM);
        if ($tgApi !== null && ($this->config['admin_telegram_ids'] ?? []) !== []) {
            $send($tgApi, BotPlatform::TELEGRAM, $this->config['admin_telegram_ids']);
        }
        $baleApi = $this->mxFor(BotPlatform::BALE);
        if ($baleApi !== null && ($this->config['admin_bale_ids'] ?? []) !== []) {
            $send($baleApi, BotPlatform::BALE, $this->config['admin_bale_ids']);
        }
    }

    /** @param array<string, mixed> $cb */
    private function registerTopupAdminMessageFromCallback(string $publicId, array $cb): void
    {
        $msg = $cb['message'] ?? [];
        $c = (int) ($msg['chat']['id'] ?? 0);
        $mid = (int) ($msg['message_id'] ?? 0);
        if ($c <= 0 || $mid <= 0) {
            return;
        }
        $isPhoto = !empty($msg['photo']) && is_array($msg['photo']);
        $this->topups->appendAdminNotifyHandle($publicId, $this->platform, $c, $mid, $isPhoto);
    }

    /** @param array<string, mixed> $row wallet_topups row */
    private function refreshAdminTopupMessages(string $publicId, array $row): void
    {
        $status = (string) ($row['status'] ?? '');
        if ($status !== 'approved' && $status !== 'rejected') {
            return;
        }
        $base = I18n::fmt('admin_new_topup', [
            'user' => (string) ($row['user_id'] ?? ''),
            'amount' => Util::formatNumber((int) ($row['amount_toman'] ?? 0)),
            'trx_id' => Util::e($publicId),
        ]);
        $note = trim((string) ($row['admin_note'] ?? ''));
        if ($status === 'approved') {
            $suffix = "\n\n✅ <b>" . I18n::txt('admin_topup_final_approved') . '</b>';
        } else {
            $suffix = "\n\n❌ <b>" . I18n::txt('admin_topup_final_rejected') . '</b>';
            if ($note !== '') {
                $suffix .= "\n" . Util::e($note);
            }
        }
        $text = $base . $suffix;
        $emptyKb = ['inline_keyboard' => []];

        foreach ($this->topups->getAdminNotifyHandles($publicId) as $h) {
            $plat = BotPlatform::normalize((string) ($h['platform'] ?? BotPlatform::TELEGRAM));
            $api = $this->mxFor($plat);
            if ($api === null) {
                continue;
            }
            $cid = (int) ($h['chat_id'] ?? 0);
            $m = (int) ($h['message_id'] ?? 0);
            if ($cid <= 0 || $m <= 0) {
                continue;
            }
            if (!empty($h['is_photo'])) {
                $api->editMessageCaption($cid, $m, $text, $emptyKb, 'HTML');
            } else {
                $api->editMessage($cid, $m, $text, $emptyKb, 'HTML');
            }
        }
    }

    /** @param array<string, mixed> $cb */
    private function adminApprove(int $adminId, string $publicId, array $cb): void
    {
        if (!$this->isAdmin($adminId)) {
            return;
        }
        $this->registerTopupAdminMessageFromCallback($publicId, $cb);

        $info = $this->topups->approve($publicId);
        if ($info !== null) {
            $plat = BotPlatform::normalize((string) ($info['platform'] ?? BotPlatform::TELEGRAM));
            $uid = (int) ($info['messenger_id'] ?? $info['telegram_id'] ?? 0);
            $api = $this->mxFor($plat);
            if ($api !== null && $uid > 0) {
                $api->sendMessage(
                    $uid,
                    '✅ ' . I18n::txt('admin_approved') . "\n" . I18n::fmt('wallet_credited', ['amount' => Util::formatNumber((int) $info['amount_toman'])]),
                    null,
                    'HTML'
                );
            }
        }

        $row = $this->topups->findByPublicId($publicId);
        if ($row === null) {
            return;
        }
        $this->refreshAdminTopupMessages($publicId, $row);
    }

    /** @param array<string, mixed> $cb */
    private function adminReject(int $adminId, string $publicId, array $cb): void
    {
        if (!$this->isAdmin($adminId)) {
            return;
        }
        $this->registerTopupAdminMessageFromCallback($publicId, $cb);

        $row = $this->topups->findByPublicId($publicId);
        if ($row === null) {
            return;
        }
        if (($row['status'] ?? '') === 'pending') {
            $uid = (int) $row['user_id'];
            $plat = BotPlatform::normalize((string) ($row['platform'] ?? BotPlatform::TELEGRAM));
            if ($this->topups->reject($publicId, null)) {
                $api = $this->mxFor($plat);
                if ($api !== null) {
                    $api->sendMessage($uid, '❌ ' . I18n::txt('admin_rejected'), null, 'HTML');
                }
            }
        }

        $row = $this->topups->findByPublicId($publicId);
        if ($row === null) {
            return;
        }
        $this->refreshAdminTopupMessages($publicId, $row);
    }

    private function sendInvalid(int $chatId): void
    {
        $u = ltrim((string) ($this->config['support_username'] ?? ''), '@');
        $msg = I18n::txt('invalid_input') . "\n\n" . I18n::txt('invalid_use_commands');
        if ($u !== '') {
            $msg .= "\n\n" . I18n::fmt('invalid_chat_to_support', ['username' => Util::e($u)]);
        }
        $this->mx()->sendMessage($chatId, $msg, null, 'HTML');
    }

    private function log(string $line): void
    {
        $p = (string) ($this->config['log_file'] ?? '');
        if ($p !== '') {
            Log::write($p, $line);
        }
    }
}
