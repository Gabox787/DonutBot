DonutNetBot — نمونهٔ JSON برای اوورراید متن‌ها
============================================

فایل‌ها:
  i18n_telegram.overrides.sample.json  → برای ربات تلگرام
  i18n_bale.overrides.sample.json      → برای ربات بله (دونات / fa_bale)

چطور استفاده کنم؟
1. ترجیحاً پنل ادمین → «متن‌های ربات» (رابط کامل، بدون JSON).
2. یا فایل‌های .sample.json را ویرایش کرده به‌صورت دستی در app_settings / دیتابیس وارد کنید (پیشرفته).
3. بعد از ذخیره، ربات متن سفارشی را از دیتابیس می‌خواند.

قوانین JSON:
  - انکی UTF-8، بدون کامای اضافهٔ آخر.
  - هر زوج «کلید رشته‌ای» → «مقدار رشته‌ای» مثل lang/fa.php.
  - در مقادیر می‌توانید از HTML تلگرام/بله مثل <b> و <code> استفاده کنید.
  - جایگزین‌ها مثل :stock ، :price در fmt همان‌طور که در fa.php تعریف شده کار می‌کنند.

کلیدها را از کجا بیاورم؟
  - فایل‌های lang/fa.php و lang/fa_bale.php — نام سمت چپ آرایه (مثلاً hub_home، buy_intro).

بله — اگر بعد از /start هیچ اتفاقی نمی‌افتد:
  1) در config.local.php حتماً هر دو را جدا بگذارید:
     'bot_token'       → فقط تلگرام
     'bale_bot_token'  → فقط توکن ربات بله (از BotFather بله)
     فقط پر کردن bot_token برای بله کافی نیست.
  2) آدرس وب‌هوک باید دقیقاً به hook_bale.php بخورد، نه index.php:
     مثال: https://your-domain.com/DonutNetBot/hook_bale.php
  3) دیتابیس باید migration v5 (ستون platform روی users و ...) اجرا شده باشد؛ وگرنه insert کاربر خطا می‌دهد.
  4) اگر required_channel_username_bale پر است، باید عضو کانال بله باشید و getChatMember روی بله پشتیبانی شود؛ وگرنه فقط پیام «عضویت در کانال» می‌بینید.
  5) پاسخ HTTP وب‌هوک را با curl یا لاگ سرور چک کنید: اگر 500 و متن «bot token missing for platform bale» بود، همان نکتهٔ 1 است.

امنیت: فایل config.local.php را در گیت/چت عمومی قرار ندهید؛ در صورت لو رفتن توکن، در BotFather تلگرام/بله توکن را revoke کنید.
