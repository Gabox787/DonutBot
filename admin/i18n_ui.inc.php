<?php

declare(strict_types=1);

/**
 * Admin UI for i18n overrides (stored as JSON in app_settings).
 * Included from index.php when logged in.
 */

/**
 * @param array<string, mixed> $config
 * @param array<string, string> $post
 * @return string user-visible success message
 */
function dn_admin_i18n_save(SettingsRepository $settingsRepo, array $config, array $post): string
{
    $tab = (($post['i18n_tab'] ?? '') === 'bale') ? 'bale' : 'telegram';
    $settingKey = $tab === 'bale' ? 'i18n_json_bale' : 'i18n_json_telegram';
    $path = dn_admin_i18n_lang_path($config, $tab);
    /** @var array<string, string> $base */
    $base = require $path;
    $incoming = $post['v'] ?? [];
    if (!is_array($incoming)) {
        $incoming = [];
    }
    $out = [];
    foreach ($base as $key => $defaultVal) {
        if (!is_string($key) || !is_string($defaultVal)) {
            continue;
        }
        if (!array_key_exists($key, $incoming)) {
            continue;
        }
        $sub = str_replace("\r\n", "\n", trim((string) $incoming[$key]));
        if ($sub === '' || $sub === $defaultVal) {
            continue;
        }
        $out[$key] = $sub;
    }
    foreach (dn_admin_i18n_overrides($settingsRepo, $settingKey) as $orphanKey => $orphanVal) {
        if (!isset($base[$orphanKey]) && is_string($orphanKey) && is_string($orphanVal)) {
            $out[$orphanKey] = $orphanVal;
        }
    }
    $json = $out === [] ? '' : json_encode($out, JSON_UNESCAPED_UNICODE);
    $settingsRepo->set($settingKey, $json);

    return 'متن‌های ' . ($tab === 'bale' ? 'بله' : 'تلگرام') . ' ذخیره شد — ' . count($out) . ' کلید سفارشی فعال.';
}

/** @param array<string, mixed> $config */
function dn_admin_i18n_lang_path(array $config, string $tab): string
{
    $langDir = dirname(__DIR__) . '/lang';
    $slug = $tab === 'bale'
        ? (string) ($config['locale_bale'] ?? 'fa_bale')
        : (string) ($config['locale'] ?? 'fa');
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug) ?: ($tab === 'bale' ? 'fa_bale' : 'fa');
    $path = $langDir . '/' . $slug . '.php';
    if (!is_file($path)) {
        $path = $langDir . '/' . ($tab === 'bale' ? 'fa_bale.php' : 'fa.php');
    }

    return $path;
}

function dn_admin_i18n_overrides(SettingsRepository $repo, string $settingKey): array
{
    $raw = trim($repo->get($settingKey));
    if ($raw === '') {
        return [];
    }
    $dec = json_decode($raw, true);

    /** @var array<string, string> $out */
    $out = [];
    if (is_array($dec)) {
        foreach ($dec as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $out[$k] = $v;
            }
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $config
 */
function dn_admin_i18n_export_json(SettingsRepository $repo, string $tab): void
{
    $sk = $tab === 'bale' ? 'i18n_json_bale' : 'i18n_json_telegram';
    $body = $repo->get($sk);
    $fn = $tab === 'bale' ? 'i18n_bale_overrides.json' : 'i18n_telegram_overrides.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fn . '"');
    echo $body !== '' ? $body : '{}';
    exit;
}

/**
 * @param array<string, mixed> $config
 */
function dn_admin_i18n_render(SettingsRepository $settingsRepo, array $config, string $tab, string $selfUrl = 'index.php'): void
{
    $tab = $tab === 'bale' ? 'bale' : 'telegram';
    $settingKey = $tab === 'bale' ? 'i18n_json_bale' : 'i18n_json_telegram';
    $path = dn_admin_i18n_lang_path($config, $tab);
    $langSlug = basename($path, '.php');
    /** @var array<string, string> $base */
    $base = require $path;
    $overrides = dn_admin_i18n_overrides($settingsRepo, $settingKey);

    $groups = [];
    foreach (array_keys($base) as $k) {
        $prefix = str_contains($k, '_') ? strstr($k, '_', true) : 'general';
        if (!isset($groups[$prefix])) {
            $groups[$prefix] = [];
        }
        $groups[$prefix][] = $k;
    }
    ksort($groups);
    foreach ($groups as $gk => $keys) {
        sort($keys, SORT_STRING);
        $groups[$gk] = $keys;
    }

    $changed = 0;
    foreach ($base as $k => $def) {
        if (isset($overrides[$k]) && $overrides[$k] !== $def) {
            ++$changed;
        }
    }

    echo '<div class="i18n-page">';
    echo '<h1 class="i18n-title">متن‌های ربات</h1>';
    echo '<p class="hint i18n-hint">فایل پایه: <code dir="ltr">' . Util::e($langSlug . '.php') . '</code> — فقط خطوطی که اینجا پر کنید در دیتابیس ذخیره می‌شوند؛ خالی = همان متن فایل زبان.</p>';

    echo '<nav class="i18n-tabs" aria-label="پلتفرم">';
    echo '<a href="' . Util::e($selfUrl) . '?p=i18n&amp;tab=telegram" class="' . ($tab === 'telegram' ? 'active' : '') . '">تلگرام</a>';
    echo '<a href="' . Util::e($selfUrl) . '?p=i18n&amp;tab=bale" class="' . ($tab === 'bale' ? 'active' : '') . '">بله</a>';
    echo '</nav>';

    echo '<div class="i18n-toolbar">';
    echo '<input type="search" class="i18n-search" id="i18n-filter" placeholder="جستجو در نام کلید یا متن…" autocomplete="off">';
    echo '<label class="i18n-chk"><input type="checkbox" id="i18n-only-changed"> فقط تغییر یافته</label>';
    echo '<button type="button" class="btn btn-ghost" id="i18n-open-groups">باز کردن همه</button>';
    echo '<button type="button" class="btn btn-ghost" id="i18n-close-groups">جمع کردن همه</button>';
    echo '<a class="btn btn-ghost" href="' . Util::e($selfUrl) . '?p=i18n&amp;tab=' . Util::e($tab) . '&amp;export=1">دانلود JSON ذخیره‌شده</a>';
    echo '</div>';

    echo '<form method="post" class="i18n-form" id="i18n-form">';
    echo '<input type="hidden" name="save_i18n" value="1">';
    echo '<input type="hidden" name="i18n_tab" value="' . Util::e($tab) . '">';

    $defaultsJson = json_encode($base, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo '<script>window.DN_I18N_DEFAULTS=' . ($defaultsJson !== false ? $defaultsJson : '{}') . ';</script>';

    foreach ($groups as $prefix => $keys) {
        $open = count($keys) <= 8;
        echo '<details class="i18n-group"' . ($open ? ' open' : '') . ' data-prefix="' . Util::e($prefix) . '">';
        echo '<summary class="i18n-group-sum"><span class="i18n-group-name">' . Util::e($prefix) . '</span>';
        echo '<span class="i18n-group-count">' . count($keys) . ' کلید</span></summary>';
        echo '<div class="i18n-group-body">';

        foreach ($keys as $key) {
            if (!isset($base[$key]) || !is_string($base[$key])) {
                continue;
            }
            $def = $base[$key];
            $ov = $overrides[$key] ?? '';
            $isChanged = $ov !== '' && $ov !== $def;
            $rowClass = 'i18n-row' . ($isChanged ? ' i18n-row--changed' : '');
            echo '<div class="' . $rowClass . '" data-key="' . Util::e($key) . '" data-changed="' . ($isChanged ? '1' : '0') . '" data-search="' . Util::e(mb_strtolower($key . ' ' . $def . ' ' . $ov, 'UTF-8')) . '">';
            echo '<div class="i18n-row-head">';
            echo '<code class="i18n-key" title="کلید در کد">' . Util::e($key) . '</code>';
            if ($isChanged) {
                echo '<span class="i18n-badge">سفارشی</span>';
            }
            echo '<button type="button" class="i18n-mini" data-fill-key="' . Util::e($key) . '">پر از پیش‌فرض</button>';
            echo '<button type="button" class="i18n-mini i18n-mini-danger" data-clear-key="' . Util::e($key) . '">خالی</button>';
            echo '</div>';
            echo '<div class="i18n-ref" aria-label="پیش‌فرض فایل زبان"><span class="i18n-ref-label">پیش‌فرض</span><pre class="i18n-ref-pre">' . Util::e($def) . '</pre></div>';
            $rows = max(2, min(12, substr_count($ov !== '' ? $ov : $def, "\n") + 2));
            echo '<label class="i18n-ta-wrap"><span class="sr-only">مقدار سفارشی</span>';
            echo '<textarea class="i18n-ta" name="v[' . Util::e($key) . ']" id="ta-' . Util::e($key) . '" rows="' . (int) $rows . '" placeholder="خالی = استفاده از پیش‌فرض بالا">' . Util::e($ov) . '</textarea></label>';
            echo '</div>';
        }

        echo '</div></details>';
    }

    echo '<div class="i18n-sticky">';
    echo '<div class="i18n-sticky-inner">';
    echo '<span class="i18n-sticky-meta"><strong id="i18n-count-nonempty">0</strong> فیلد پر شده · ' . (int) count($base) . ' کلید · <strong>' . (int) $changed . '</strong> متفاوت از پیش‌فرض</span>';
    echo '<button type="submit" class="btn i18n-save-btn">ذخیره در دیتابیس</button>';
    echo '</div></div>';
    echo '</form>';

    echo <<<'JS'
<script>
(function () {
  var filter = document.getElementById('i18n-filter');
  var onlyCh = document.getElementById('i18n-only-changed');
  var rows = [].slice.call(document.querySelectorAll('.i18n-row'));
  var details = [].slice.call(document.querySelectorAll('.i18n-group'));

  function norm(s) { return (s || '').toLowerCase(); }

  function applyFilter() {
    var q = norm(filter && filter.value);
    var oc = onlyCh && onlyCh.checked;
    rows.forEach(function (row) {
      var hay = row.getAttribute('data-search') || '';
      var okQ = !q || hay.indexOf(q) !== -1;
      var okC = !oc || row.getAttribute('data-changed') === '1';
      row.style.display = (okQ && okC) ? '' : 'none';
    });
    details.forEach(function (d) {
      var vis = [].some.call(d.querySelectorAll('.i18n-row'), function (r) { return r.style.display !== 'none'; });
      d.style.display = vis ? '' : 'none';
    });
    updateCount();
  }

  function updateCount() {
    var n = 0;
    rows.forEach(function (r) {
      if (r.style.display === 'none') return;
      var ta = r.querySelector('.i18n-ta');
      if (ta && ta.value.trim() !== '') n++;
    });
    var el = document.getElementById('i18n-count-nonempty');
    if (el) el.textContent = String(n);
  }

  if (filter) filter.addEventListener('input', applyFilter);
  if (onlyCh) onlyCh.addEventListener('change', applyFilter);
  document.querySelectorAll('.i18n-ta').forEach(function (ta) {
    ta.addEventListener('input', function () {
      var row = ta.closest('.i18n-row');
      if (!row || !window.DN_I18N_DEFAULTS) return;
      var k = row.getAttribute('data-key');
      var def = window.DN_I18N_DEFAULTS[k] || '';
      var v = ta.value.replace(/\r\n/g, '\n');
      var ch = v.trim() !== '' && v !== def;
      row.setAttribute('data-changed', ch ? '1' : '0');
      row.classList.toggle('i18n-row--changed', ch);
      var badge = row.querySelector('.i18n-badge');
      if (ch && !badge) {
        var sp = document.createElement('span');
        sp.className = 'i18n-badge';
        sp.textContent = 'سفارشی';
        row.querySelector('.i18n-row-head').insertBefore(sp, row.querySelector('.i18n-mini'));
      }
      if (!ch && badge) badge.remove();
      applyFilter();
    });
  });

  document.querySelectorAll('[data-fill-key]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var k = btn.getAttribute('data-fill-key');
      if (!k || !window.DN_I18N_DEFAULTS) return;
      var ta = document.getElementById('ta-' + k);
      if (ta) {
        ta.value = window.DN_I18N_DEFAULTS[k] || '';
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  });
  document.querySelectorAll('[data-clear-key]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var k = btn.getAttribute('data-clear-key');
      var ta = k ? document.getElementById('ta-' + k) : null;
      if (ta) {
        ta.value = '';
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  });

  document.getElementById('i18n-open-groups') && document.getElementById('i18n-open-groups').addEventListener('click', function () {
    details.forEach(function (d) { d.open = true; });
  });
  document.getElementById('i18n-close-groups') && document.getElementById('i18n-close-groups').addEventListener('click', function () {
    details.forEach(function (d) { d.open = false; });
  });

  applyFilter();
})();
</script>
JS;

    echo '</div>';
}
