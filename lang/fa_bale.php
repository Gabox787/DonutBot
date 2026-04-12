<?php

declare(strict_types=1);

return [
    'kb_anchor_hint' => '⌨️',

    'rk_buy' => '🛒 خرید دونات',
    'rk_wallet' => '💰 کیف پول',
    'rk_configs' => '📦 سفارش‌های من',
    'rk_support' => '💬 پشتیبانی',
    'rk_faq' => '❓ سوالات',
    'rk_help' => '📚 راهنما',
    'rk_income' => '💎 کسب درآمد',
    'rk_test' => '🧪 تست طعم',
    'rk_wallet_add' => '➕ افزایش موجودی',
    'rk_back' => '◀️ بازگشت',
    'rk_admin_panel' => '⚙️ پنل ادمین',
    'rk_admin_stats' => '📊 آمار فروش',
    'rk_admin_plans' => '📋 محصولات',
    'rk_admin_stock' => '📥 انبار تحویل',
    'rk_admin_newplan' => '🆕 محصول جدید',
    'rk_admin_disable' => '🔴 غیرفعال محصول',
    'rk_admin_pending' => '⏳ سفارش باز',
    'rk_admin_user_menu' => '🏠 منوی کاربری',
    'rk_admin_edit' => '✏️ ویرایش محصول',

    'admin_panel_home' => "🛰️ <b>پنل مدیریت</b>\nاز دکمه‌های زیر استفاده کنید.",

    'btn_proceed_buy' => 'ادامهٔ خرید',
    'btn_finalize_buy' => '✅ نهایی خرید',
    'btn_back_plans' => '⤴️ محصولات',
    'btn_back' => '◀️ بازگشت',
    'btn_charge_wallet' => '💰 شارژ کیف',
    'btn_home' => '🏠 خانه',
    'btn_cancel_payment' => '⛔ لغو پرداخت',

    'hub_home' => "🏠 <b>خانه</b>\n\nفروشگاه دونات آنلاین — از دکمه‌های پایین یا <code>/buy</code> ، <code>/test</code> ، <code>/wallet</code> ، <code>/income</code> ، <code>/configs</code> و <code>/help</code> استفاده کنید.\nپیام‌های قبلی حفظ می‌شوند؛ آخرین پنل همان‌جاست.",

    'channel_gate_html' => "📢 برای استفاده از ربات، ابتدا در کانال <b>:channel</b> عضو شوید.\n\n<a href=\":link\">ورود به کانال</a>",

    'btn_join_channel' => '📢 عضویت در کانال',

    'income_screen' => "💎 <b>کسب درآمد از معرفی</b>\n\nلینک اختصاصی (بفرستید تا با آن وارد شوند):\n<code>:link</code>\n\n📥 با این لینک وارد شده‌اند: <b>:started</b>\n✅ خرید عادی (غیر تست) انجام داده‌اند: <b>:buyers</b>\n💰 جمع پاداش واریزشده: <b>:earned</b> تومان\n\nبا هر خرید عادی زیرمجموعه، <b>:pct</b> درصد مبلغ (گرد پایین) به کیف شما می‌نشیند.",

    'income_missing_bot_username' => '⚠️ برای لینک معرفی، نام کاربری ربات بله و قالب لینک را در پنل یا config تنظیم کنید.',

    'label_unlimited' => 'نامحدود',

    'access_active' => 'فعال',
    'access_inactive' => 'غیرفعال',

    'btn_plan_get_test' => '🧪 تست طعم / خرید نمونه',

    'wallet_screen' => "💰 <b>کیف پول</b>\n\nموجودی: <b>:balance</b> تومان\n\nبرای شارژ، «افزایش موجودی» را بزنید.",

    'buy_intro' => "🛒 <b>انتخاب محصول</b>\n\n📦 واحد آماده در انبار (همه): <b>:stock</b>\nیکی را بزنید:",

    'plan_row' => ':title — :qty :unit — :price تومان',
    'plan_qty_range' => ':min تا :max :unit (انتخاب شما)',
    'plan_detail' => "📋 <b>:title</b>\n\n📊 مقدار: :qty_line\n💰 قیمت (مرجع): :price تومان\n👥 سقف همزمان: <b>:users</b>\n📅 مدت اعتبار: <b>:days</b>\n📦 موجودی انبار: <b>:stock</b>\n\n:description",

    'plan_days_term' => ':n روز',

    'plan_suggested' => '⭐ پیشنهادی',

    'buy_ask_qty' => "📊 <b>مقدار را بفرستید</b>\n\nمحدوده: <b>:min</b> تا <b>:max</b> :unit (فقط عدد).",
    'buy_qty_invalid' => '⛔ مقدار معتبر نیست یا خارج از محدوده است.',
    'buy_checkout' => "💳 <b>تأیید پرداخت</b>\n\nمبلغ: <b>:price</b> تومان\nموجودی: <b>:balance</b> تومان",
    'buy_checkout_qty' => "📊 مقدار انتخابی: <b>:qty</b> :unit",

    'buy_insufficient' => "\n\n⚠️ موجودی کافی نیست؛ <b>:shortage</b> تومان کم است.",

    'buy_success' => "✅ <b>خرید انجام شد</b>\nاطلاعات تحویل:\n<code>:payload</code>",
    'buy_success_bale' => "✅ <b>سفارش دونات ثبت شد</b>\nرسپی و جزئیات فنی داخل <b>فایل txt</b> همین گفتگو برایتان ارسال می‌شود.",

    'buy_pending' => "⏳ <b>سفارش ثبت شد</b>\n\nشناسه سفارش: <code>:order_id</code>\n\nموجودی این محصول در انبار تمام است. به‌محض افزودن توسط ادمین، همین‌جا اطلاع داده می‌شود.",
    'buy_pending_bale' => "⏳ <b>سفارش ثبت شد</b>\n\nکد پیگیری: <code>:order_id</code>\n\nالان موجودی این طعم تمام است؛ با رسیدن دونات‌های تازه از طرف ادمین خبرتان می‌کنیم.",

    'order_fulfilled_notify' => "✅ سفارش <code>:order_id</code> آماده است:\n<code>:payload</code>",
    'order_fulfilled_notify_bale' => "✅ سفارش <code>:order_id</code> آماده است.\nفایل رسپی همین گفتگوست.",

    'wallet_ask_amount' => "💰 مبلغ شارژ را <b>فقط عدد تومان</b> بفرستید.\nبرای لغو از «لغو پرداخت» زیر همین پیام استفاده کنید.",
    'wallet_invalid_amount' => 'عدد نامعتبر است.',
    'wallet_pay_instruction' => "🏦 پرداخت کارت‌به‌کارت\n\nمبلغ: <b>:amount</b> تومان\n<code>:card</code>\n<b>:holder</b>\n\n⏱ تا <b>:minutes</b> دقیقه — سپس <b>عکس رسید</b>.",
    'wallet_receipt_wait' => '📎 فقط تصویر رسید را بفرستید.',
    'wallet_topup_created' => "✅ درخواست ثبت شد.\n<code>:trx_id</code>\nبعد از تأیید ادمین شارژ می‌شود.",
    'wallet_cancel_hint' => 'می‌توانید از «بازگشت» در منوی پایین به کیف پول برگردید.',

    'wallet_cancelled' => 'پرداخت لغو شد.',

    'test_no_plans' => '🧪 در حال حاضر محصول فعالی نیست.',
    'test_pick_plan' => "🧪 <b>نمونهٔ آزمایشی</b>\n\nمحصولی را بزنید که «نمونه» برایش فعال باشد.",
    'test_no_plans_ready' => '🧪 محصولی با «نمونه فعال + محتوای نمونه» نیست؛ بعداً سر بزنید یا خرید عادی بزنید.',
    'test_not_available' => "🚫 محصول <b>:title</b> نمونهٔ آزمایشی ندارد.",
    'test_no_url' => "⚠️ نمونه برای <b>:title</b> فعال است ولی محتوا در پنل نیست؛ با پشتیبانی تماس بگیرید.",
    'test_checkout' => "🧪 <b>خرید نمونه</b>\n\nمحصول: :title\nهزینه: <b>:price</b> تومان\nموجودی: <b>:balance</b> تومان",
    'test_success' => "🧪 <b>نمونه فعال شد</b>\n\n<code>:payload</code>\n\n💰 پرداخت‌شده: :amount تومان\n⏳ :valid",
    'test_success_bale' => "🧪 <b>نمونه آماده است</b>\n\nجزئیات فنی و «رسپی» داخل فایل txt همین گفتگوست.\n\n💰 پرداخت‌شده: :amount تومان\n⏳ :valid",
    'test_valid_until_eod' => 'تا <b>پایان همین روز</b> (به وقت سرور) مجاز به استفاده هستید؛ بعد از آن غیرفعال می‌شود.',
    'order_kind_test' => 'تست',
    'btn_pay_test' => '💳 پرداخت و دریافت تست',
    'btn_back_test' => '⤴️ تست',

    'my_configs_title' => "📦 <b>سفارش‌های شما</b>\n\n",
    'my_configs_pick_hint' => 'یک سفارش را بزنید:',
    'my_config_btn' => ':title (:id4)',
    'config_detail_test' => '🧪 نوع: <b>تست</b>',
    'config_detail_expires' => '⏳ اعتبار تا: :at',
    'config_detail_pending' => "⏳ <b>در انتظار</b>\n\n:kind📋 :title — :gb کیلو\nکد: <code>:order_id</code>",
    'config_detail_ok' => "📦 <b>:title</b> — :gb کیلو\n:kind:exp<b>جزئیات سفارش</b>\n:meta\n\n<code>:payload</code>",
    'config_detail_ok_bale' => "📦 <b>:title</b> — :gb کیلو\n:kind:exp<b>جزئیات سفارش</b>\n:meta\n\nمحتوای رسپی در <b>فایل txt</b> بعد از این پیام ارسال می‌شود.",
    'config_detail_users_line' => '👥 سقف کاربر (این سفارش): :val',
    'config_detail_access_line' => '🔐 وضعیت دسترسی: :access',
    'config_detail_started' => '▶️ زمان فعال‌سازی: :at',
    'config_detail_ends' => '⏹ پایان دسترسی: :at',
    'btn_back_configs' => '⤴️ لیست سفارش‌ها',
    'my_config_item' => "──\n<b>:title</b> — :gb کیلو — :state\n<code>:payload</code>\n",
    'my_config_pending_line' => "──\n<b>:title</b> — ⏳ در صف پخت\nکد: <code>:order_id</code>\n",
    'my_configs_empty' => '📦 سفارشی نیست. از «خرید دونات» شروع کنید.',
    'bale_payload_file_caption_test' => 'نمونهٔ تست — فایل رسپی',
    'bale_payload_file_caption_buy' => 'سفارش شما — فایل رسپی',
    'bale_payload_file_caption_detail' => 'جزئیات سفارش — فایل رسپی',

    'support_text' => "💬 پشتیبانی: @:username",

    'faq_body' => "❓ <b>سوالات</b>\n\n• <code>/buy</code> خرید • <code>/test</code> نمونه • <code>/wallet</code> کیف • <code>/configs</code> سفارش‌ها • <code>/income</code> معرفی\n• محصول با مقدار دلخواه: بعد از انتخاب، عدد را بفرستید.\n• بدون موجودی انبار، سفارش معلق می‌ماند.\n• عضویت کانال در صورت تنظیم اجباری است.\n• پنل وب برای مدیریت فروشگاه.",

    'help_body' => "📚 <b>راهنمای سریع</b>\n\n• <code>/buy</code> • <code>/test</code> • <code>/wallet</code> • <code>/configs</code> • <code>/income</code> • <code>/help</code>\n• هر محصول یک دکمه دارد.\n• مقدار شناور: بعد از محصول، عدد را بفرستید.\n• انبار خالی = سفارش معلق تا ادمین خط تحویل بگذارد.\n• متن‌ها از «متن‌های ربات» در پنل قابل ویرایش است.",

    'help_links_title' => 'لینک‌های آموزشی',

    'plan_col_name' => 'نام',
    'plan_col_gb' => 'مقدار',
    'plan_col_price' => 'قیمت',

    'referrer_notify_join' => '🤝 با لینک شما یک نفر وارد فروشگاه شد.\nکد: <code>:user</code>',

    'referrer_notify_purchase' => '💰 زیرمجموعه (<code>:buyer</code>) خرید عادی انجام داد.\nپاداش: <b>:amount</b> تومان — سفارش: <code>:order</code>',

    'user_access_revoked' => "⛔ <b>این سفارش غیرفعال شد</b>\n\nکد: <code>:order</code>\nدلیل: :reason",

    'admin_topup_final_approved' => 'این درخواست تأیید شد.',
    'admin_topup_final_rejected' => 'این درخواست رد شد.',

    'state_must_start' => '/start را بزنید.',

    'invalid_input' => '⛔ ورودی مجاز نیست.',
    'invalid_use_commands' => 'از منوی پایین یا دستورات استفاده کنید.',
    'invalid_chat_to_support' => 'گفتگو: <a href="https://t.me/:username">پشتیبانی</a>',

    'admin_new_topup' => "🔔 شارژ\nکاربر: <code>:user</code>\nمبلغ: :amount\nTRX: <code>:trx_id</code>",
    'admin_new_order' => "🛒 <b>خرید</b>\nکاربر: <code>:user</code>\nمحصول: :plan\nمبلغ: :amount\nوضعیت: :status\nسفارش: <code>:order_id</code>",

    'admin_approved' => 'تأیید شد.',
    'wallet_credited' => '+:amount تومان',
    'admin_rejected' => 'رد شد.',

    'admin_stats' => "📊 <b>آمار</b>\nفروش نهایی: <b>:sold</b>\nدرآمد: <b>:rev</b> تومان\nسفارش باز: <b>:pend</b>\nردیف آماده در انبار: <b>:stock</b>",

    'admin_plans_list' => "📋 <b>محصولات</b>\n\n:lines",
    'admin_plan_line' => "— <code>:id</code> :title (:gb) :price ::active\n",

    'admin_pending_list' => "⏳ <b>سفارش‌های باز</b>\n\n:lines",
    'admin_pending_line' => "• <code>:oid</code> کاربر <code>:uid</code> — :plan (:price)\n",

    'admin_stock_ask_plan' => '📥 ابتدا <b>شناسه محصول</b> را عدد بفرستید.',
    'admin_stock_ask_lines' => '📥 هر خط یک «تحویل». محصول: <b>:plan_id</b>',
    'admin_stock_done' => "✅ <b>:n</b> ردیف انبار اضافه شد.\n<b>:m</b> سفارش معلق تکمیل شد.",

    'admin_newplan_title' => '🆕 عنوان محصول را بفرستید.',
    'admin_newplan_gb' => '🆕 مقدار مرجع (عدد) را بفرستید.',
    'admin_newplan_price' => '🆕 قیمت تومان را عدد بفرستید.',
    'admin_newplan_desc' => '🆕 توضیح کوتاه بفرستید (یا `-` برای خالی).',
    'admin_newplan_done' => '✅ محصول ساخته شد. id=<code>:id</code>',

    'admin_disable_ask' => '🔴 شناسه محصول را برای غیرفعال‌سازی بفرستید.',
    'admin_disable_done' => '✅ محصول <code>:id</code> غیرفعال شد.',

    'admin_edit_ask' => '✏️ شناسه محصول را بفرستید سپس:\n<code>عنوان|مقدار|قیمت|توضیح|پیشنهادی0/1</code>',

    'admin_edit_blob' => '✏️ خط ویرایش را بفرستید. محصول: <code>:id</code>',
    'admin_edit_done' => '✅ محصول به‌روز شد.',

    'err_plan_not_found' => 'محصول پیدا نشد.',
    'err_invalid' => 'نامعتبر.',

    'noop' => '…',
];
