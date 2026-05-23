<div dir="rtl">

# متصفّح سجل النشاطات

[![أحدث إصدار على Packagist](https://img.shields.io/packagist/v/mahmoud-mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mahmoud-mhamed/spatie-activitylog-browse)
[![الرخصة](https://img.shields.io/packagist/l/mahmoud-mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mahmoud-mhamed/spatie-activitylog-browse)
[![إصدار PHP](https://img.shields.io/packagist/php-v/mahmoud-mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mahmoud-mhamed/spatie-activitylog-browse)

باكدج Laravel بيوسّع [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) v4 بإضافة **تسجيل تلقائي للموديلات**، **إثراء سياقي غني**، **واجهة تصفّح ويب**، **لوحة إحصائيات**، **تنظيف تلقائي** بـ retention policies، و **سجل تدقيقي للحذف** — بواجهة عربي/إنجليزي، dark mode، وإمكانية حماية بكلمة مرور.

> النسخة الإنجليزية: [README.md](README.md)

## المميزات

### التسجيل
- 🔁 **تسجيل تلقائي لكل الموديلات** بدون trait `LogsActivity` — مع إمكانية الاستثناء
- 📦 **إثراء غني** — بيانات الطلب، الجهاز، الأداء، التطبيق، الجلسة، وسياق التنفيذ متلصقة بكل سجل
- 🆔 **متوافق مع UUID** — أعمدة morph ID بتتعدّل تلقائياً لدعم UUID
- ⚡ **محسّن للأداء** — كاش لكل كلاس، collectors بنطاق الـ request، بدون reflection على كل event

### التصفّح والتحليلات
- 🌐 **واجهة تصفّح** — فلترة، بحث، popovers، diffs ملوّنة، تنقّل بين الموديلات المرتبطة
- 📊 **لوحة إحصائيات** — رسوم للنشاط بالساعة/اليوم/الشهر، أوقات الذروة، أعلى الموديلات/المسبّبين/الخصائص
- 🌍 **متعدد اللغات** — عربي وإنجليزي مع RTL تلقائي
- 🌙 **Dark mode** — يكتشف نظام التشغيل + toggle يدوي محفوظ في localStorage
- 📝 **ترجمة الخصائص** — يستخدم `validation.attributes` من Laravel

### التنظيف والتدقيق
- 🧹 **صفحة تنظيف يدوي** — معاينة قبل الحذف مع فلاتر للموديل والتاريخ
- ⏱ **Retention تلقائي** — حدود للعمر والحجم، استثناءات لكل موديل (تشمل `'forever'`)، تكامل مع scheduler
- 📜 **سجل الحذف** — ملف JSON تدقيقي بكل عملية تنظيف مع before/after للجدول

### الأمان
- 🛡 **Password gate اختياري** مع تسجيل دخول محدود (5 محاولات/دقيقة)
- 🚪 **دعم Laravel Gate** للتحكم بالصلاحيات
- 🏢 **Multi-tenancy جاهز** يشتغل مع [stancl/tenancy](https://tenancyforlaravel.com/)

## جدول المحتويات

- [المتطلبات](#المتطلبات)
- [التثبيت](#التثبيت)
- [بداية سريعة](#بداية-سريعة)
- [الإعدادات](#الإعدادات)
  - [التسجيل التلقائي](#التسجيل-التلقائي)
  - [الإثراء](#الإثراء)
  - [واجهة التصفّح](#إعدادات-واجهة-التصفّح)
  - [Password Gate](#password-gate)
  - [Retention / التنظيف التلقائي](#retention--التنظيف-التلقائي)
  - [سجل الحذف](#إعدادات-سجل-الحذف)
- [الاستخدام](#الاستخدام)
- [واجهة التصفّح](#واجهة-التصفّح)
- [لوحة الإحصائيات](#لوحة-الإحصائيات)
- [صفحة سجل الحذف](#صفحة-سجل-الحذف)
- [أوامر Artisan](#أوامر-artisan)
- [اللغات](#اللغات)
- [Multi-Tenancy](#multi-tenancy)
- [ملاحظات الأداء](#ملاحظات-الأداء)
- [البنية المعمارية](#البنية-المعمارية)
- [الرخصة](#الرخصة)

## المتطلبات

- PHP **8.1+**
- Laravel **10**, **11**, أو **12**
- spatie/laravel-activitylog **^4.0**

## التثبيت

<div dir="ltr">

```bash
composer require mahmoud-mhamed/spatie-activitylog-browse
```

</div>

لو الاكتشاف التلقائي مش شغّال، سجّل المزوّد يدوياً في `bootstrap/providers.php` (Laravel 11+) أو `config/app.php`:

<div dir="ltr">

```php
Mhamed\SpatieActivitylogBrowse\ActivitylogBrowseServiceProvider::class,
```

</div>

شغّل أمر التثبيت — بيـ publish الـ migration بتاع spatie، الـ config، يشغّل الـ migrations، يصلح أعمدة UUID، يضيف الفهارس، ويجهّز مجلد سجل الحذف:

<div dir="ltr">

```bash
php artisan activitylog-browse:install
```

</div>

> إعادة تشغيل `install` بعد ترقية الباكدج هيـ يعرض عليك تحديث الـ config عشان الإعدادات الجديدة (زي `retention` و `deletion_history`) تظهر.

### نشر الملفات بشكل منفصل

<div dir="ltr">

```bash
# تهجيرة Spatie
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate

# ملف الإعدادات
php artisan vendor:publish --tag=activitylog-browse-config

# القوالب (اختياري)
php artisan vendor:publish --tag=activitylog-browse-views

# ملفات اللغة (اختياري)
php artisan vendor:publish --tag=activitylog-browse-lang

# التهجيرات (اختياري — لـ multi-tenancy)
php artisan vendor:publish --tag=activitylog-browse-migrations
```

</div>

> **نصيحة:** استخدم `--force` لإعادة كتابة الملفات المنشورة قبل كده بعد ترقية الباكدج:
> <div dir="ltr">
>
> ```bash
> php artisan vendor:publish --tag=activitylog-browse-config --force
> ```
>
> </div>

### التطوير المحلي

أضف للـ `composer.json` بتاع تطبيقك:

<div dir="ltr">

```json
"repositories": [
    { "type": "path", "url": "../spatie-activitylog-browse" }
]
```

```bash
composer require mahmoud-mhamed/spatie-activitylog-browse:@dev
php artisan activitylog-browse:install
```

</div>

## بداية سريعة

بعد ما تشغّل `activitylog-browse:install`:

1. افتح `/activity-log` في المتصفح — محمي بـ `web` + `auth` افتراضياً.
2. خلّي أي تغيير في موديل — هيظهر مباشرة بكل الإثراءات.
3. (اختياري) فعّل الـ retention في `config/activitylog-browse.php` عشان السجلات القديمة تتنظف لوحدها.
4. (اختياري) حطّ `ACTIVITYLOG_BROWSE_PASSWORD` في `.env` لإضافة password gate.

## الإعدادات

ملف الإعدادات بعد الـ publish في `config/activitylog-browse.php`. الأقسام الأساسية:

### التسجيل التلقائي

<div dir="ltr">

```php
'auto_log' => [
    'enabled' => true,
    'events' => ['created', 'updated', 'deleted'],
    'models' => '*',                // '*' = كل الموديلات، أو array بكلاسات معيّنة
    'excluded_models' => [],
    'log_name' => 'default',
    'log_only_dirty' => true,
    'excluded_attributes' => [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ],
    'submit_empty_logs' => false,
    'exclude_null_on_create' => false,
],
```

</div>

الموديلات اللي بتستخدم trait `LogsActivity` بتتخطّى تلقائياً عشان نتفادى التسجيل المزدوج.

### الإثراء

كل قسم إثراء قابل للتفعيل/التعطيل وعنده toggles لكل حقل. تعطيل القسم بالكامل بيشيل أي overhead لكل event.

<details>
<summary><strong>عرض كل خيارات الإثراء</strong></summary>

<div dir="ltr">

```php
'request_data' => [
    'enabled' => true,
    'fields' => [
        'url' => true, 'previous_url' => true,
        'method' => true, 'route_name' => true,
    ],
],

'device_data' => [
    'enabled' => true,
    'fields' => ['ip' => true, 'user_agent' => true, 'referrer' => true],
],

'performance_data' => [
    'enabled' => true,
    'fields' => [
        'request_duration' => true,  // ms من LARAVEL_START
        'memory_peak' => true,       // bytes
        'db_query_count' => true,    // محتاج DB::enableQueryLog() عشان يكون مفيد
    ],
],

'app_data' => [
    'enabled' => true,
    'fields' => [
        'environment' => true,
        'php_version' => true,
        'server_hostname' => true,
    ],
],

'session_data' => [
    'enabled' => true,
    'fields' => ['auth_guard' => true],
],

'execution_context' => [
    'enabled' => true,
    'fields' => [
        'source' => true,        // "web" | "console" | "queue" | "schedule"
        'job_name' => true,      // اسم كلاس الـ queue job
        'command_name' => true,  // اسم أمر artisan
    ],
],
```

</div>

كل الـ collectors بترجّع بيانات فاضية بأمان لما تشتغل خارج سياقها (مثل request data في الـ console).

</details>

### إعدادات واجهة التصفّح

<div dir="ltr">

```php
'browse' => [
    'enabled' => true,
    'prefix' => 'activity-log',
    'middleware' => ['web', 'auth'],
    'per_page' => 25,
    'gate' => null,                 // مثلاً 'view-activity-log'
    'password' => env('ACTIVITYLOG_BROWSE_PASSWORD'),
    'available_locales' => ['en', 'ar'],
],
```

</div>

اضبط `gate` على اسم Laravel Gate لتقييد الوصول؛ الباكدج هيستدعي `Gate::authorize($name)` على كل request.

### Password Gate

لو محتاج كلمة مرور مشتركة تحمي واجهة التصفّح (بالإضافة لأي auth/middleware عندك):

<div dir="ltr">

```bash
# .env
ACTIVITYLOG_BROWSE_PASSWORD=your-secret-here
```

</div>

لما تكون مضبوطة، أي مستخدم بيدخل `/activity-log` بيتحوّل لصفحة login. النموذج محدود بـ **5 محاولات في الدقيقة لكل IP**. بعد الدخول الـ session بتفضل، وزرّ logout بيظهر في الـ navbar.

اضبط الـ env على قيمة فاضية (أو احذفها) لتعطيل الميزة كلياً.

### Retention / التنظيف التلقائي

حذف تلقائي للسجلات القديمة بناءً على العمر وحدود حجم الجدول، مع إمكانية استثناء موديلات معيّنة لتحتفظ بسجلاتها لفترة أطول (أو للأبد).

<div dir="ltr">

```php
'retention' => [
    'enabled' => true,

    'default_days' => 90,           // الحد العام للعمر
    'max_rows'     => 1_000_000,    // null لتعطيل
    'max_size_mb'  => 500,          // null لتعطيل

    'per_model' => [
        App\Models\AuditLog::class => 'forever',
        App\Models\User::class     => 365,
    ],

    'per_log_name' => [
        'security' => 365,
    ],

    'chunk_size'     => 1000,
    'optimize_after' => true,

    'schedule'      => 'daily',     // 'daily' | 'weekly' | 'monthly' | null
    'schedule_time' => '03:00',     // 24-ساعة HH:MM
],
```

</div>

#### ترتيب الأولوية (الأقوى ← الأضعف)

1. **`per_model` / `per_log_name`** — دايماً بتغلب.
   - `'forever'` محميّ بالكامل من الحذف بالعمر والحجم.
   - عدد أيام integer بيحمي السجلات اللي لسه أحدث من المدة من **الحذف بالعمر والحجم سوا**.
2. **`max_rows` / `max_size_mb`** — حدود صلبة بتغلب `default_days`: لما الجدول يعدّي الحد، السجلات الأقدم (اللي مش محميّة بـ per_model) بتتحذف **حتى لو لسه جوّة نافذة `default_days`**.
3. **`default_days`** — القاعدة الافتراضية. بتتطبق بس على السجلات اللي مش متغطية بقاعدة أعلى أولوية.

#### إيه اللي بيحصل عند حد الحجم؟

| القاعدة في `per_model` | الحذف بالعمر               | الحذف بالحجم (عند تعدّي `max_rows` / `max_size_mb`) |
|--------------------------|----------------------------|-------------------------------------------------------|
| غير مضبوطة              | يتحذف بعد `default_days`  | **ممكن يتحذف** (الأقدم فالأقدم)                     |
| `365` (أي عدد أيام)      | يتحذف بعد 365 يوم          | **محميّ** طول ما عمره أقل من 365 يوم؛ بعدها ممكن يتحذف |
| `'forever'`             | لا يُحذف أبداً             | **لا يُحذف أبداً** (محميّ بالكامل)                   |

> **خلاصة:** قاعدة `per_model` هي المرجع الأقوى. موديل عنده `365` يوم سجلاته هتفضل محفوظة طول الـ 365 يوم حتى لو الجدول عدّى حد الحجم — السجلات بتدخل الحذف بالحجم بس بعد ما تخلّص نافذة الـ retention بتاعتها. ده معناه إن الحد الأقصى للحجم **best-effort**: لو كل السجلات لسه جوّة مدتها، مفيش حاجة هتتحذف وهيفضل الجدول فوق الحد لحد ما الحماية تخلص. حطّ قيم منطقية في `per_model` عشان الحد الأقصى يفضل فعّال.

#### طرق التشغيل

| المصدر       | متى |
|---------------|------|
| **Schedule** | تلقائي عند `schedule_time` (التكرار = `daily`/`weekly`/`monthly`). يحتاج `schedule:work` أو cron يشغّل `schedule:run`. |
| **CLI**      | `php artisan activitylog-browse:prune` — راجع [أوامر Artisan](#أوامر-artisan) |
| **UI**       | زرّ **تشغيل التنظيف الآن** في صفحة Cleanup. |

### إعدادات سجل الحذف

كل عملية تنظيف (يدوية، مجدولة، CLI، dry-run) بتتسجّل في ملف JSON تراكمي:

<div dir="ltr">

```php
'deletion_history' => [
    'enabled' => true,
    'path' => storage_path('activitylog-browse/deletion-history.json'),
    'max_entries' => 500,    // الأقدم بتتشطف الأول
    'max_size_mb' => 3,      // الملف بيتـ reset لو عدّى الحد
],
```

</div>

كل entry بتسجّل: timestamp، المصدر (`schedule`/`cli`/`ui`/`manual`)، نوع العملية، عدد المحذوف + التفاصيل، المدة، حالة الجدول قبل/بعد (الصفوف + الحجم MB)، snapshot من الإعدادات، وسياق المستخدم/IP. العمليات الفاضية (0 سجلات) بتتخطّى.

الباكدج بيعمل الـ directory + `.gitignore` تلقائياً عشان تتفادى commit للـ JSON file.

## الاستخدام

### التسجيل التلقائي

بعد التثبيت، كل أحداث الـ Eloquent بتتسجّل أوتوماتيكياً:

<div dir="ltr">

```php
$user = User::create(['name' => 'John']);   // مسجّل
$user->update(['name' => 'Jane']);          // مسجّل
$user->delete();                            // مسجّل
```

</div>

لاستثناء موديلات معيّنة:

<div dir="ltr">

```php
'excluded_models' => [
    App\Models\TemporaryFile::class,
],
```

</div>

### محتوى الـ Enrichment

كل سجل نشاط — سواء من الـ auto-logging، أو trait `LogsActivity`، أو استدعاء `activity()` يدوي — بيتم إثراؤه بسياق إضافي:

<details>
<summary><strong>مثال على <code>properties</code> بعد الإثراء</strong></summary>

<div dir="ltr">

```json
{
    "attributes": { "name": "Jane" },
    "old": { "name": "John" },
    "request_data": {
        "url": "https://example.com/users/1",
        "method": "PUT",
        "route_name": "users.update"
    },
    "device_data": {
        "ip": "192.168.1.1",
        "user_agent": "Mozilla/5.0 ..."
    },
    "performance_data": {
        "request_duration": 142,
        "memory_peak": 12582912,
        "db_query_count": 8
    },
    "app_data": {
        "environment": "production",
        "php_version": "8.3.0",
        "server_hostname": "web-01"
    },
    "session_data": { "auth_guard": "web" },
    "execution_context": {
        "source": "web",
        "job_name": null,
        "command_name": null
    }
}
```

</div>

</details>

## واجهة التصفّح

افتح `/activity-log` (أو الـ prefix اللي ظبطته). الـ navigation فيه: **Activity Log**، **Statistics**، **Cleanup**، **Deletion History**، **About** — بالإضافة لـ language switcher، theme toggle، و(لما الـ password gate مفعّل) زرّ logout.

صفحة القائمة فيها:

- **فلترة** — log name، نوع الحدث، نوع الموديل، Model ID، المسبّب، نطاق التاريخ، بحث في الوصف
- **فلتر الخاصية المتغيّرة** — اختار نوع موديل، وفلتر بخاصية معيّنة (مثلاً اعرض السجلات اللي اتغيّر فيها `name` بس)
- **Quick preview popover** — وقّف الماوس على أيقونة المعلومات لرؤية فروقات old/new inline
- **Current attributes popover** — شوف بيانات الموديل الحيّة بدون ما تخرج من القائمة
- **شريط جانبي للموديل** — لما تختار نوع موديل، بيظهر شريط فيه إجمالي السجلات، السجلات الفريدة، اسم الجدول، حجم الجدول، شارات الأحداث، وخصائص قابلة للنقر للفلترة
- **التنقل بين الموديلات المرتبطة** — انتقل لكل سجلات أي موديل مرتبط
- **عرض التفاصيل** — diff ملوّن، metadata الطلب/الجهاز/الأداء/التطبيق/الجلسة/التنفيذ، JSON خام

### ترجمة الخصائص

أسماء الخصائص (مثل `first_name`، `email_verified_at`) بتتترجم تلقائياً من `lang/{locale}/validation.php`:

- لو `validation.attributes.{key}` موجودة → **"الاسم الأول" (first_name)**
- غير كده → **"Email Verified At"** (auto-headlined) مع المفتاح الأصلي بحجم صغير

اعمل الترجمة مرة واحدة وهي بتنعكس في كل الواجهة:

<div dir="ltr">

```php
'attributes' => [
    'first_name' => 'الاسم الأول',
    'email' => 'البريد الإلكتروني',
    'created_at' => 'تاريخ الإنشاء',
],
```

</div>

## لوحة الإحصائيات

افتح `/activity-log/statistics`. كل قسم بيتحمّل مستقل عبر AJAX مع skeleton states للـ rendering السريع.

فلتر تاريخ في الأعلى بيُطبَّق على كل الأقسام (الكاش 60 ثانية مع الفلتر، 120 ثانية للـ all-time).

**الأقسام:** كروت Overview · رسم Peak Hour · النشاط اليومي (30 يوم) · النشاط بأيام الأسبوع · أوقات الذروة · النشاط الشهري · System vs User · تفصيل الأحداث · أسماء السجلات · أعلى الموديلات · أعلى المسبّبين · أكثر الخصائص تغييراً (آخر 1000 تحديث).

## صفحة سجل الحذف

`/activity-log/deletion-history` — سجل تدقيقي لكل عمليات التنظيف:

- **كروت إحصائية** — إجمالي السجلات، حجم الملف، المسار الحالي
- **لكل صف** — متى، شارة المصدر (ملوّنة: schedule/cli/ui/manual + dry-run)، نوع العملية، عدد المحذوف + التفاصيل (age vs size)، حجم الجدول قبل ← بعد مع الفرق، المدة بالـ ms، المستخدم/IP/الأمر
- **JSON قابل للتوسيع** — اضغط على أي صف لرؤية الـ payload كامل (config snapshot، table state، context)
- **Pagination** — 25 لكل صفحة
- **زرّ Clear** — يمسح ملف الـ JSON (بـ confirmation)

## أوامر Artisan

<div dir="ltr">

```bash
# تثبيت / ترقية
php artisan activitylog-browse:install

# Retention / تنظيف
php artisan activitylog-browse:prune                # تنظيف كامل (عمر + حجم)
php artisan activitylog-browse:prune --dry-run      # تقرير بما سيتم حذفه فقط
php artisan activitylog-browse:prune --age          # حسب العمر فقط
php artisan activitylog-browse:prune --size         # حسب الحجم فقط
```

</div>

أمر `prune` بيتسجّل تلقائياً مع Laravel scheduler لما `retention.schedule` يكون مضبوط (وعندك `schedule:work` أو cron شغّال).

## اللغات

الباكدج بيجي مع ترجمات عربي وإنجليزي. الواجهة بتتحوّل لـ RTL تلقائياً لما اللغة `ar`.

<div dir="ltr">

```php
// config/app.php
'locale' => 'ar',
```

</div>

أو وقت الـ runtime:

<div dir="ltr">

```php
App::setLocale('ar');
```

</div>

في الواجهة كمان زرّ تبديل لغة بيحفظ التفضيل في الـ session.

لتخصيص الترجمات:

<div dir="ltr">

```bash
php artisan vendor:publish --tag=activitylog-browse-lang
# استخدم --force لإعادة كتابة الملفات المنشورة
php artisan vendor:publish --tag=activitylog-browse-lang --force
```

</div>

ده بينقل الملفات لـ `lang/vendor/activitylog-browse/` تقدر تعدّل فيها أو تضيف لغات جديدة — بعد كده حدّث `available_locales` في الـ config.

## Multi-Tenancy

شغّال جاهز مع [stancl/tenancy](https://tenancyforlaravel.com/) (multi-database tenancy):

- **عزل الكاش** — مفاتيح الكاش بتاخد prefix الـ tenant ID (مثلاً `activitylog-browse:t:1:stats:overview`).
- **اتصال الـ DB** — الاستعلامات بتستخدم الـ connection اللي على Activity model.
- **بدون dependency** — اكتشاف الـ tenant بيستخدم `function_exists('tenant')`.

### إعداد لـ multi-database tenancy

1. عطّل الـ migrations التلقائية عشان متشتغلش على الـ central DB:
   <div dir="ltr">

   ```php
   'load_migrations' => false,
   ```

   </div>
2. انشر الـ migrations لمسار الـ tenant:
   <div dir="ltr">

   ```bash
   php artisan vendor:publish --tag=activitylog-browse-migrations
   ```

   </div>
   نقّلهم لـ `database/migrations/tenant/` (أو حيث ما الـ migrations بتاعتك).
3. ضيف middleware الـ tenancy لـ routes التصفّح:
   <div dir="ltr">

   ```php
   'browse' => [
       'middleware' => ['web', 'auth', \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class],
   ],
   ```

   </div>

من غير tenancy، مفيش إعداد إضافي محتاج — كله شغّال على طول.

## ملاحظات الأداء

الباكدج مصمّم لأقل overhead حتى على تطبيقات عالية الـ traffic مع auto-logging مفعّل:

- **كاش لكل كلاس** في `GlobalModelLogger` — اكتشاف trait `LogsActivity` بيشتغل **مرة واحدة لكل كلاس موديل**، مش كل event
- **Collectors بنطاق الـ request** — `debug_backtrace`، تعداد الـ auth guards، اكتشاف المصدر بيشتغلوا **مرة واحدة في الـ request**، النتائج محفوظة كـ static properties
- **Enrichment ذكي بالـ disabled** — الـ observer بيستدعي بس الـ collectors المفعّلة في الـ config؛ الأقسام المعطّلة فيها **صفر overhead**
- **Schedule console-only** — HTTP requests بتـ skip تسجيل الـ scheduler كلياً
- **Chunks للحذف** — الـ retention بيشتغل بـ chunks بحجم 1000 (configurable) مع `set_time_limit(30)` لتفادي قفل الجدول

لأفضل أداء على تطبيقات عالية الـ throughput:

- ضيف الموديلات عالية التردّد لـ `excluded_models`
- عطّل `execution_context.fields.job_name` لو مش محتاج tracking للـ queue (بيوفّر `debug_backtrace` لكل request)
- استخدم `Model::withoutEvents(...)` حوالين عمليات الـ bulk imports

## البنية المعمارية

| المكوّن | الدور |
|---|---|
| `ActivitylogBrowseServiceProvider` | بيسجّل كل حاجة: listener، observer، routes، scheduler |
| `GlobalModelLogger` | بيستمع لأحداث Eloquent العامة؛ ويسجّل النشاط للموديلات اللي مش بتستخدم `LogsActivity` |
| `ActivityEnrichmentObserver` | بيرصد حدث `creating` للـ Activity model؛ ويدمج الـ enrichment في الـ properties |
| `RequestDataCollector` / `DeviceDataCollector` / ... | الـ collectors المنفصلة اللي بيناديها الـ observer |
| `RelationDiscovery` | اكتشاف تلقائي بالـ reflection لعلاقات Eloquent للتنقّل بين الموديلات المرتبطة |
| `RetentionPruner` | تطبيق ترتيب الأولوية — حذف بالعمر، الحجم، per-model، per-log-name |
| `DeletionLogger` | بيكتب entries الحذف في ملف JSON بحدود للحجم والعدد |
| `ActivityLogHelpers` | helpers مشتركة — اسم الـ connection، حجم الجدول، prefix كاش، تنظيف stats cache |
| `ActivityLogController` | بيدير واجهة التصفّح: filtering، AJAX endpoints، statistics API، attribute inspection، cleanup، deletion history |
| `RequirePassword` middleware | بيطبّق الـ password gate الاختياري (تسجيل دخول محدود) |
| `SetLocale` middleware | بيطبّق تفضيل اللغة من الـ session |
| `InstallCommand` | `activitylog-browse:install` — بيـ publish الأصول، يصلح أعمدة UUID، يضيف فهارس |
| `PruneCommand` | `activitylog-browse:prune` — تشغيل retention يدوي / مجدول |

## الرخصة

MIT — راجع [LICENSE](LICENSE).

</div>
