# X-Plore File Manager

A secure, single-file PHP file manager with a dark, mobile-first UI and PWA support.
Two security layers (URL token + session password), confined to a single managed directory.

**Languages:** [English](#english) · [فارسی](#فارسی)

![PHP](https://img.shields.io/badge/PHP-7.4%2B-7c6af7) ![Single File](https://img.shields.io/badge/single--file-yes-3ecf8e) ![PWA](https://img.shields.io/badge/PWA-installable-f5a623) ![License](https://img.shields.io/badge/license-MIT-888899)

---

## English

### Features

- **Single file** — drop `index.php` on any PHP host, no database, no dependencies.
- **Two-layer security** — a secret URL token (`?t=...`) plus a session-based password.
- **Confined root** — all operations are locked inside one `dir/` folder; the app's own files are never exposed or deletable.
- **Core operations** — browse, upload, download, view, rename, create folder, recursive delete, and recursive search.
- **In-browser viewer** — preview text-based files (under 512 KB) without leaving the page.
- **Dark, mobile-first UI** — sticky header, breadcrumb navigation, slide-up modals, and a floating action button.
- **PWA** — installable to the home screen, app icons, web manifest, and a service worker that caches static assets while always serving fresh listings from the network.
- **LTR, fully English interface.**

### Directory layout

```
x/
├─ index.php            the app          → https://example.com/x/index.php
├─ sw.js                service worker
├─ asset/               icons + manifest + browserconfig
│   ├─ manifest.json
│   ├─ browserconfig.xml
│   └─ *.png / favicon.ico
└─ dir/                 managed root     → https://example.com/x/dir
```

Everything the manager touches lives under `dir/`. The folder is created automatically on first run if it is missing.

### Installation

1. Copy the `x/` folder to your web root (or any subpath).
2. Make sure `dir/` is writable by the web server (e.g. `chmod 755 dir`).
3. Open the configuration block at the top of `index.php` and change the defaults:

```php
define('ACCESS_TOKEN',   'change_me');        // Layer 1: URL token  -> ?t=change_me
define('ADMIN_PASSWORD', 'change_me_too');    // Layer 2: login password
define('ROOT_PATH',      __DIR__ . '/dir');   // Managed root
define('ASSET_URL',      'asset');            // Icon/manifest folder (relative)
define('SESSION_TTL',    3600);               // Session lifetime (seconds)
define('MAX_UPLOAD_MB',  100);                // Max upload size (MB)
define('BLOCKED_EXT',    ['php','phtml','phar','php3','php4','php5','php7','phps']);
```

4. Visit `https://example.com/x/index.php?t=YOUR_TOKEN`, then sign in with your password.

> If you set a custom token, update `start_url` in `asset/manifest.json` so the installed PWA launches with the correct token.

### Configuration reference

| Constant | Purpose |
| --- | --- |
| `ACCESS_TOKEN` | Secret string required in the URL as `?t=`. Wrong/missing token → `403`. |
| `ADMIN_PASSWORD` | Password checked after the token, stored in the session once valid. |
| `ROOT_PATH` | The only directory the manager can read or write. |
| `ASSET_URL` | Folder that holds icons and the web manifest (relative to `index.php`). |
| `SESSION_TTL` | Idle timeout in seconds before the session expires. |
| `MAX_UPLOAD_MB` | Upload size cap in megabytes. |
| `BLOCKED_EXT` | Extensions rejected on upload (executable PHP variants by default). |

### Security notes

- Always serve over **HTTPS** — the token and password travel in the request otherwise.
- The token alone is a soft gate; the password is the real lock. Use a long, random value for both.
- PHP and other executable extensions are blocked from upload, and every path is validated with `realpath()` against `ROOT_PATH` to prevent directory traversal.
- This tool grants full file access to whoever authenticates. Treat the credentials accordingly.

### Requirements

- PHP 7.4 or newer (uses `match`, arrow functions, and null-coalescing).
- A writable managed directory.

### License

MIT.

---

<div dir="rtl" lang="fa">

## فارسی

### امکانات

- **تک‌فایلی** — کافی است `index.php` را روی هر هاست PHP قرار دهید؛ بدون دیتابیس و بدون وابستگی.
- **امنیت دو لایه** — یک توکن مخفی در URL (`?t=...`) به‌علاوه‌ی رمز عبور مبتنی بر session.
- **روت محصور** — همه‌ی عملیات فقط داخل پوشه‌ی `dir/` محدود می‌شود؛ فایل‌های خود اپ هرگز نمایش داده یا حذف نمی‌شوند.
- **عملیات اصلی** — مرور، آپلود، دانلود، مشاهده، تغییر نام، ساخت پوشه، حذف بازگشتی و جست‌وجوی بازگشتی.
- **نمایشگر داخل مرورگر** — پیش‌نمایش فایل‌های متنی (کمتر از ۵۱۲ کیلوبایت) بدون خروج از صفحه.
- **رابط دارک و موبایل‌فرست** — هدر چسبان، ناوبری breadcrumb، مودال‌های کشویی و دکمه‌ی شناور (FAB).
- **PWA** — قابل نصب روی صفحه‌ی اصلی، آیکون‌های اپ، web manifest و یک service worker که asset‌های ثابت را cache می‌کند ولی لیست فایل‌ها همیشه تازه از شبکه می‌آید.
- **رابط کاملاً انگلیسی و LTR.**

### ساختار پوشه‌ها

```
x/
├─ index.php            خودِ اپ          → https://example.com/x/index.php
├─ sw.js                سرویس‌ورکر
├─ asset/               آیکون‌ها + manifest + browserconfig
│   ├─ manifest.json
│   ├─ browserconfig.xml
│   └─ *.png / favicon.ico
└─ dir/                 روت مدیریت فایل   → https://example.com/x/dir
```

هر چیزی که فایل‌منیجر با آن کار می‌کند، زیر `dir/` قرار دارد. اگر این پوشه وجود نداشته باشد، در اولین اجرا به‌صورت خودکار ساخته می‌شود.

### نصب

۱. پوشه‌ی `x/` را در ریشه‌ی وب (یا هر زیرمسیری) کپی کنید.
۲. مطمئن شوید پوشه‌ی `dir/` برای وب‌سرور قابل نوشتن است (مثلاً `chmod 755 dir`).
۳. بلوک تنظیمات در ابتدای `index.php` را باز کنید و مقادیر پیش‌فرض را تغییر دهید:

```php
define('ACCESS_TOKEN',   'change_me');        // لایه ۱: توکن URL  -> ?t=change_me
define('ADMIN_PASSWORD', 'change_me_too');    // لایه ۲: رمز ورود
define('ROOT_PATH',      __DIR__ . '/dir');   // روت مدیریت فایل
define('ASSET_URL',      'asset');            // پوشه‌ی آیکون/manifest (نسبی)
define('SESSION_TTL',    3600);               // مدت session (ثانیه)
define('MAX_UPLOAD_MB',  100);                // حداکثر حجم آپلود (مگابایت)
define('BLOCKED_EXT',    ['php','phtml','phar','php3','php4','php5','php7','phps']);
```

۴. آدرس `https://example.com/x/index.php?t=YOUR_TOKEN` را باز کنید و با رمز خود وارد شوید.

> اگر توکن دلخواهی تنظیم کردید، مقدار `start_url` را در `asset/manifest.json` هم به‌روزرسانی کنید تا PWA نصب‌شده با توکن درست اجرا شود.

### مرجع تنظیمات

| ثابت | کاربرد |
| --- | --- |
| `ACCESS_TOKEN` | رشته‌ی مخفی که باید در URL به‌صورت `?t=` بیاید. توکن اشتباه/خالی → `403`. |
| `ADMIN_PASSWORD` | رمزی که بعد از توکن بررسی می‌شود و پس از تأیید در session ذخیره می‌گردد. |
| `ROOT_PATH` | تنها پوشه‌ای که فایل‌منیجر اجازه‌ی خواندن یا نوشتن در آن را دارد. |
| `ASSET_URL` | پوشه‌ی نگه‌دارنده‌ی آیکون‌ها و manifest (نسبت به `index.php`). |
| `SESSION_TTL` | مدت بی‌کاری (ثانیه) تا انقضای session. |
| `MAX_UPLOAD_MB` | سقف حجم آپلود برحسب مگابایت. |
| `BLOCKED_EXT` | پسوندهایی که هنگام آپلود رد می‌شوند (به‌صورت پیش‌فرض نسخه‌های اجرایی PHP). |

### نکات امنیتی

- همیشه روی **HTTPS** سرو کنید؛ در غیر این صورت توکن و رمز در درخواست لو می‌روند.
- توکن به‌تنهایی یک سدِ سبک است؛ قفل اصلی همان رمز است. برای هر دو از مقدار طولانی و تصادفی استفاده کنید.
- پسوندهای PHP و سایر فایل‌های اجرایی هنگام آپلود مسدودند و هر مسیر با `realpath()` نسبت به `ROOT_PATH` اعتبارسنجی می‌شود تا از directory traversal جلوگیری شود.
- این ابزار به هر کسی که احراز هویت شود دسترسی کامل به فایل‌ها می‌دهد. اطلاعات ورود را متناسب با این موضوع محافظت کنید.

### پیش‌نیازها

- PHP نسخه‌ی ۷.۴ یا جدیدتر (از `match`، arrow function و null-coalescing استفاده می‌کند).
- یک پوشه‌ی مدیریت فایل قابل‌نوشتن.

### مجوز

MIT.

</div>
