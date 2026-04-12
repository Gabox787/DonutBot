# TG Donut Bot

**Telegram (and optional Bale) storefront bot** — wallet, catalog, per-unit pricing (default **kilograms**), stock-backed delivery lines, sample/trial SKUs, referrals, and a **web admin** with charts. Ships with **Persian** UI; strings live in `lang/` and can be overridden from the admin panel.

Think: *your products* (the sample data is donut-themed), *your prices*, *your delivery payloads* (tracking links, pickup codes, file URLs — anything you paste per stock line).

---

## Features

| Area | What you get |
|------|----------------|
| **Catalog** | `products` with `base_qty` + `qty_unit` (default `kg`), optional **custom quantity** (min/max), featured flag, rich descriptions (Telegram + optional Bale text). |
| **Stock** | `product_stock` — one **payload string** per line; first paid order claims the next free line (FIFO). |
| **Orders** | Wallet debit → fulfilled if stock exists, else **pending** until you add lines; admin drain completes pending automatically. |
| **Samples** | `test_sample_payload` + test price; no inventory row required (expires end-of-day by timezone). |
| **Wallet** | Card-to-card flow, receipt photo, admin approve/reject on Telegram/Bale. |
| **Referrals** | Percent of standard orders credited to referrer wallet. |
| **Admin** | Install wizard, dashboard **stats + Chart.js** (revenue / orders / new users), products CRUD, delivery stock, orders, settings, i18n JSON overrides. |

---

## Migrating from an old `plans` / `plan_configs` database

This release uses **`products`**, **`product_stock`**, and **`orders_config.product_id`**. Older SQL dumps are **not** compatible. For production data you already have, either:

- **Re-deploy fresh**: export what you need (e.g. user balances), import `database.sql`, and migrate rows manually, or  
- **Write a one-off SQL migration** from your backup (rename tables/columns and fix foreign keys).

The repository no longer ships incremental migration files for the legacy schema; treat `database.sql` as the canonical **new install** only.

---

## Requirements

- **PHP** 8.1+ (8.2+ recommended) with extensions: `pdo_mysql`, `json`, `mbstring`.
- **MySQL** 5.7+ or **MariaDB** 10.3+.
- **HTTPS** webhook URL for production.

---

## Quick start (production-shaped)

### 1. Clone and permissions

```bash
git clone <your-fork-or-repo-url> tg-donut-bot
cd tg-donut-bot
chmod -R u+rw storage/logs
```

### 2. Database

Create an empty database, then import the schema (creates tables + sample products):

```bash
mysql -u USER -p YOUR_DB < database.sql
```

### 3. Configuration

```bash
cp config.local.example.php config.local.php
# Edit: bot_token, db_*, admin_telegram_ids, payment card, telegram_bot_username, etc.
```

`config.local.php` is **gitignored**. Optional: use only **`admin/install.php`** to write DB credentials into `config.local.php` and set the panel password (after `database.sql` is applied).

### 4. Web server

Point the document root at this folder (or map a path). Ensure PHP runs for:

| URL path | Role |
|----------|------|
| `/index.php` | Telegram webhook |
| `/hook_bale.php` | Bale webhook (optional) |
| `/admin/` | Web admin + installer |

### 5. Telegram webhook

```text
https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://YOUR_DOMAIN/index.php
```

### 6. Bot commands (optional)

Set `commands_setup_key` in config or admin → then open:

```text
https://YOUR_DOMAIN/tools/set_commands.php?key=YOUR_KEY
```

### 7. First login

Open `https://YOUR_DOMAIN/admin/` — complete install if prompted, then **Settings** for tokens, card, channel gate, brand name, etc.

---

## Project layout

```text
index.php, hook_bale.php     # Webhook entrypoints
core/bootstrap.php           # Wires DB, i18n, kernel
core/BotKernel.php           # Telegram/Bale update handling
core/PurchaseService.php     # Orders + stock claim + referral credit
core/repo/                   # ProductRepository, ProductStockRepository, OrderRepository, …
admin/                       # Panel + install + Chart.js dashboard
lang/fa.php, lang/fa_bale.php
database.sql                 # Single source of truth for a new install
storage/logs/                # bot.log (gitignored)
```

---

## Data model (high level)

- **`products`** — sellable SKU: pricing step (`base_qty` + `qty_unit`), optional custom quantity range, test sample fields, optional “subscription-style” caps (`user_limit`, `duration_days`) for digital/slot use cases.
- **`product_stock`** — `payload` text per unit of inventory (delivery instruction).
- **`orders_config`** — links to `product_id`, optional `stock_item_id`, `qty_ordered`, payment and access metadata.

---

## Customization

- **Copy & strings**: `lang/fa.php` (Telegram) and `lang/fa_bale.php` (Bale). Admin → **متن‌های ربات** stores JSON overrides in `app_settings`.
- **Brand fragment** on URLs: `bot_brand_name` (Settings).
- **Sample products**: edit/delete rows in `products` after install or use the admin **محصولات** screen.

---

## Security notes

- Treat **`admin/`** and **`tools/set_commands.php`** like production apps: strong panel password, HTTPS, firewall if possible.
- The **installer** is reachable until the app is fully configured; finish setup quickly on public hosts.
- Never commit **`config.local.php`** or bot tokens.

---

## License

MIT — see [LICENSE](LICENSE).

---

## Credits

Built as a **portfolio-grade** open stack: plain PHP, no framework lock-in, clear separation between repositories and bot logic. PRs welcome for docs, extra locales, and hardening.
