<div dir="rtl">

# متصفح سجل النشاطات

[![أحدث إصدار على Packagist](https://img.shields.io/packagist/v/mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mhamed/spatie-activitylog-browse)
[![الرخصة](https://img.shields.io/packagist/l/mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mhamed/spatie-activitylog-browse)
[![إصدار PHP](https://img.shields.io/packagist/php-v/mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mhamed/spatie-activitylog-browse)

حزمة Laravel تُوسّع [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) v4 بتسجيل تلقائي للنماذج، وإثراء سياقي غني، ومتصفح ويب للسجلات، ولوحة إحصائيات شاملة.

## المميزات

- **تسجيل تلقائي لجميع النماذج** — تسجيل أحداث الإنشاء/التحديث/الحذف تلقائياً لجميع نماذج Eloquent بدون إضافة trait `LogsActivity`
- **إثراء غني** — إرفاق بيانات الطلب والجهاز والأداء والتطبيق والجلسة وسياق التنفيذ تلقائياً مع كل سجل
- **واجهة تصفح** — واجهة ويب لعرض وتصفية والبحث وفحص سجلات النشاط مع معاينات سريعة وعرض فروقات ملوّن
- **لوحة إحصائيات** — صفحة تحليلات شاملة مع رسوم بيانية وتفاصيل وتحليل أوقات الذروة
- **تصفح النماذج المرتبطة** — التنقل بين سجلات النماذج المرتبطة عبر اكتشاف تلقائي لعلاقات Eloquent
- **شريط جانبي لمعلومات النموذج** — عرض إحصائيات النموذج وحجم الجدول وأزرار الخصائص القابلة للنقر للتصفية
- **ترجمة الخصائص** — ترجمة أسماء الخصائص تلقائياً باستخدام ملف `validation.attributes` في كامل الواجهة
- **دعم اللغات** — دعم مدمج للعربية والإنجليزية مع تخطيط RTL

## جدول المحتويات

- [المتطلبات](#المتطلبات)
- [التثبيت](#التثبيت)
- [الإعدادات](#الإعدادات)
- [الاستخدام](#الاستخدام)
- [واجهة التصفح](#واجهة-التصفح)
- [لوحة الإحصائيات](#لوحة-الإحصائيات)
- [دعم اللغات](#دعم-اللغات)
- [البنية المعمارية](#البنية-المعمارية)
- [الرخصة](#الرخصة)

## المتطلبات

- PHP 8.1+
- Laravel 10 أو 11 أو 12
- spatie/laravel-activitylog ^4.0

## التثبيت

<div dir="ltr">

```bash
composer require mhamed/spatie-activitylog-browse
```

</div>

إذا لم يعمل الاكتشاف التلقائي، سجّل مزود الخدمة يدوياً في `bootstrap/providers.php` (Laravel 11+) أو `config/app.php`:

<div dir="ltr">

```php
Mhamed\SpatieActivitylogBrowse\ActivitylogBrowseServiceProvider::class,
```

</div>

ثم شغّل أمر التثبيت. هذا ينشر migration الخاصة بـ spatie وملف الإعدادات ويشغّل التهجيرات:

<div dir="ltr">

```bash
php artisan activitylog-browse:install
```

</div>

أو انشر كل ملف على حدة:

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
```

</div>

> **ملاحظة:** استخدم `--force` لإعادة نشر الملفات المنشورة سابقاً (مثلاً بعد تحديث الحزمة):
> <div dir="ltr">
>
> ```bash
> php artisan vendor:publish --tag=activitylog-browse-config --force
> php artisan vendor:publish --tag=activitylog-browse-views --force
> php artisan vendor:publish --tag=activitylog-browse-lang --force
> ```
>
> </div>

### التطوير المحلي

لتثبيت كمستودع محلي، أضف التالي إلى `composer.json` في تطبيق Laravel:

<div dir="ltr">

```json
"repositories": [
    {
        "type": "path",
        "url": "../spatie-activitylog-browse"
    }
]
```

</div>

ثم:

<div dir="ltr">

```bash
composer require mhamed/spatie-activitylog-browse:@dev
```

</div>

شغّل أمر التثبيت:

<div dir="ltr">

```bash
php artisan activitylog-browse:install
```

</div>

## الإعدادات

بعد النشر، ملف الإعدادات موجود في `config/activitylog-browse.php`. يحتوي على الأقسام التالية:

### التسجيل التلقائي

<div dir="ltr">

```php
'auto_log' => [
    'enabled' => true,
    'events' => ['created', 'updated', 'deleted'],
    'models' => '*',              // '*' = جميع النماذج، أو مصفوفة من كلاسات محددة
    'excluded_models' => [],
    'log_name' => 'default',
    'log_only_dirty' => true,
    'excluded_attributes' => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
    'submit_empty_logs' => false,
],
```

</div>

اضبط `models` على `'*'` لتسجيل جميع النماذج تلقائياً، أو مرر مصفوفة لتسجيل نماذج محددة:

<div dir="ltr">

```php
'models' => [
    App\Models\User::class,
    App\Models\Order::class,
],
```

</div>

النماذج التي تستخدم trait `LogsActivity` يتم تجاوزها تلقائياً لمنع التكرار.

### إثراء بيانات الطلب

<div dir="ltr">

```php
'request_data' => [
    'enabled' => true,
    'fields' => [
        'url' => true,
        'previous_url' => true,
        'method' => true,
        'route_name' => true,
    ],
],
```

</div>

### إثراء بيانات الجهاز

<div dir="ltr">

```php
'device_data' => [
    'enabled' => true,
    'fields' => [
        'ip' => true,
        'user_agent' => true,
        'referrer' => true,
    ],
],
```

</div>

### إثراء بيانات الأداء

<div dir="ltr">

```php
'performance_data' => [
    'enabled' => true,
    'fields' => [
        'request_duration' => true,  // ميلي ثانية منذ LARAVEL_START
        'memory_peak' => true,       // ذروة استخدام الذاكرة بالبايت
        'db_query_count' => true,    // عدد استعلامات قاعدة البيانات
    ],
],
```

</div>

### إثراء بيانات التطبيق

<div dir="ltr">

```php
'app_data' => [
    'enabled' => true,
    'fields' => [
        'environment' => true,       // مثل "local"، "production"
        'php_version' => true,
        'server_hostname' => true,
    ],
],
```

</div>

### إثراء بيانات الجلسة

<div dir="ltr">

```php
'session_data' => [
    'enabled' => true,
    'fields' => [
        'auth_guard' => true,        // حارس المصادقة المستخدم
    ],
],
```

</div>

### إثراء سياق التنفيذ

<div dir="ltr">

```php
'execution_context' => [
    'enabled' => true,
    'fields' => [
        'source' => true,            // "web"، "console"، "queue"، أو "schedule"
        'job_name' => true,          // اسم كلاس المهمة في الطابور
        'command_name' => true,      // اسم أمر artisan
    ],
],
```

</div>

جميع جامعي الإثراء تُرجع مصفوفات فارغة بأمان عند التشغيل في سياق console/queue حيث بيانات الطلب غير متوفرة.

### واجهة التصفح

<div dir="ltr">

```php
'browse' => [
    'enabled' => true,
    'prefix' => 'activity-log',
    'middleware' => ['web', 'auth'],
    'per_page' => 25,
    'gate' => null,
    'available_locales' => ['en', 'ar'],
],
```

</div>

اضبط `gate` على اسم بوابة لتقييد الوصول (مثل `'gate' => 'view-activity-log'`).

## الاستخدام

### التسجيل التلقائي

بمجرد التثبيت، يتم تسجيل جميع أحداث نماذج Eloquent تلقائياً. لا حاجة لأي trait:

<div dir="ltr">

```php
$user = User::create(['name' => 'John']); // تم التسجيل
$user->update(['name' => 'Jane']);         // تم التسجيل
$user->delete();                           // تم التسجيل
```

</div>

لاستبعاد نماذج محددة:

<div dir="ltr">

```php
'excluded_models' => [
    App\Models\TemporaryFile::class,
],
```

</div>

### الإثراء

كل سجل نشاط (بما في ذلك تلك من trait `LogsActivity` أو استدعاءات `activity()` اليدوية) يتم إثراؤه تلقائياً ببيانات سياقية:

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
    "session_data": {
        "auth_guard": "web"
    },
    "execution_context": {
        "source": "web",
        "job_name": null,
        "command_name": null
    }
}
```

</div>

## واجهة التصفح

قم بزيارة `/activity-log` (أو البادئة المُعدّة) لتصفح السجلات. توفر الواجهة:

- **التصفية** — تصفية حسب اسم السجل ونوع الحدث ونوع النموذج ومعرف النموذج والمسبب والفترة الزمنية والبحث في الوصف
- **تصفية الخاصية المتغيرة** — اختر نوع نموذج، ثم صفّي حسب خاصية محددة (مثل عرض السجلات التي تغيرت فيها خاصية `name` فقط)
- **معاينة سريعة** — مرر الماوس على أيقونة المعلومات لرؤية فروقات القيم القديمة/الجديدة بدون مغادرة القائمة
- **معاينة الخصائص الحالية** — عرض بيانات النموذج الحالية من القائمة
- **شريط جانبي لمعلومات النموذج** — عند اختيار نوع نموذج، يظهر شريط جانبي بإحصائيات النموذج (إجمالي السجلات، السجلات الفريدة، اسم الجدول، حجم الجدول)، وشارات تفصيل الأحداث، وأزرار خصائص قابلة للنقر للتصفية السريعة
- **تصفح النماذج المرتبطة** — انقر للتنقل لعرض جميع سجلات نموذج مرتبط
- **صفحة التفاصيل** — عرض فروقات القيم ملوّنة، بيانات الطلب، بيانات الجهاز، مقاييس الأداء (مع شارات سريع/عادي/بطيء)، معلومات التطبيق، معلومات الجلسة، سياق التنفيذ، وعرض JSON الخام
- **تبديل اللغة** — التبديل بين اللغات المتوفرة مباشرة من الواجهة

### ترجمة الخصائص

في كامل الواجهة، يتم ترجمة أسماء الخصائص (أسماء أعمدة قاعدة البيانات مثل `first_name`، `email_verified_at`) تلقائياً باستخدام ملف لغة `validation.attributes` في Laravel:

- إذا وُجدت ترجمة في `validation.attributes.{key}` — يعرض الاسم المترجم مع المفتاح الأصلي بين قوسين، مثل **"الاسم الأول" (first_name)**
- إذا لم توجد ترجمة — يعرض نسخة "عنوان" من المفتاح، مثل **"Email Verified At"** مع المفتاح الأصلي بخط صغير

ينطبق هذا على جدول التغييرات في صفحة التفاصيل، وأزرار الخصائص في الشريط الجانبي، وقسم "أكثر الخصائص تغييراً" في صفحة الإحصائيات.

لإضافة ترجمات، عرّفها في `lang/{locale}/validation.php`:

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

قم بزيارة `/activity-log/statistics` للوصول إلى لوحة الإحصائيات. تُحمّل الصفحة كل قسم بشكل مستقل عبر AJAX لعرض أولي سريع مع حالات تحميل هيكلية.

### تصفية الفترة

فلتر فترة زمنية في الأعلى يُطبّق على جميع الأقسام. اختر تاريخ "من" و"إلى" وانقر "تطبيق" للتصفية. انقر "إعادة تعيين" للعودة لبيانات كل الوقت.

### الأقسام

تتضمن اللوحة الأقسام التالية:

#### بطاقات النظرة العامة
خمس بطاقات ملخصة تعرض:
- **إجمالي السجلات** — العدد الإجمالي لسجلات النشاط
- **حجم الجدول** — حجم جدول قاعدة البيانات (البيانات + الفهارس)
- **المعدل / يوم** — متوسط عدد السجلات يومياً
- **أقدم سجل** — تاريخ أول نشاط مسجل
- **أحدث سجل** — تاريخ آخر نشاط مسجل

#### رسم بياني لساعة الذروة
رسم بياني شريطي لـ 24 ساعة يوضح توزيع النشاط حسب ساعة اليوم. الساعة الأكثر نشاطاً مُميّزة باللون البرتقالي. مرر الماوس على أي شريط لرؤية العدد الدقيق.

#### النشاط اليومي
رسم بياني شريطي يوضح النشاط خلال آخر 30 يوم (أو الفترة المحددة). مرر الماوس لرؤية التاريخ والعدد الدقيق.

#### النشاط حسب اليوم
رسم بياني شريطي يوضح توزيع النشاط على أيام الأسبوع (الأحد حتى السبت). اليوم الأكثر نشاطاً مُميّز. يستخدم أسماء أيام مترجمة.

#### ملخص أوقات الذروة
ثلاث بطاقات تعرض:
- **أكثر ساعة نشاطاً** — الساعة ذات أعلى نشاط (مثل "2 PM")
- **أكثر يوم نشاطاً** — التاريخ الذي سجّل أعلى عدد نشاطات
- **أكثر شهر نشاطاً** — الشهر (YYYY-MM) ذو أعلى نشاط

#### النشاط الشهري
رسم بياني شريطي يوضح النشاط لكل شهر عبر جميع البيانات المتوفرة. الشهر الأعلى مُميّز باللون البرتقالي.

#### إجراءات النظام مقابل المستخدم
شريط تقدم يقارن النشاطات المنفذة من مستخدمين مصادقين مقابل إجراءات النظام/التلقائية (السجلات بدون `causer_id`). يعرض الأعداد والنسب المئوية.

#### تفصيل الأحداث
جدول مرتّب يعرض كل نوع حدث (`created`، `updated`، `deleted`، إلخ) مع العدد وشريط تناسبي. الأحداث ملوّنة بشارات.

#### أسماء السجلات
جدول مرتّب يعرض عدد النشاطات لكل اسم سجل (مثل `default`، `auth`، `system`).

#### أكثر النماذج
جدول مرتّب لأكثر 10 أنواع نماذج تسجيلاً (يُعرض كاسم كلاس مختصر).

#### أكثر المسببين
جدول مرتّب لأكثر 10 مسببين نشاطاً. يحل أسماء المسببين من قاعدة البيانات عند الإمكان (يستخدم خصائص `name` أو `email` أو `title`).

#### أكثر الخصائص تغييراً
جدول مرتّب لأكثر 30 خاصية تغييراً (من أحداث `updated`). يفحص آخر 1000 سجل تحديث. العنوان يعرض فترة البحث النشطة أو "كل الأوقات". أسماء الخصائص مترجمة باستخدام `validation.attributes` — يعرض الاسم المقروء مع اسم العمود الأصلي.

### التخزين المؤقت

استجابات الإحصائيات مخزّنة مؤقتاً:
- **استعلامات كل الوقت**: مخزّنة لمدة 120 ثانية
- **استعلامات مصفّاة بتاريخ**: مخزّنة لمدة 60 ثانية

مفاتيح التخزين المؤقت مُنظّمة حسب القسم والفترة الزمنية.

## دعم اللغات

الحزمة تأتي مع ترجمات للإنجليزية والعربية. الواجهة تتكيف تلقائياً مع تخطيط RTL عند تعيين اللغة إلى `ar`.

اضبط اللغة في `config/app.php`:

<div dir="ltr">

```php
'locale' => 'ar',
```

</div>

أو بدّل أثناء التشغيل:

<div dir="ltr">

```php
App::setLocale('ar');
```

</div>

واجهة التصفح تتضمن زر تبديل اللغة الذي يحفظ التفضيل في الجلسة.

لتخصيص الترجمات، انشر ملفات اللغة:

<div dir="ltr">

```bash
php artisan vendor:publish --tag=activitylog-browse-lang
# استخدم --force لإعادة نشر الملفات المنشورة سابقاً
php artisan vendor:publish --tag=activitylog-browse-lang --force
```

</div>

هذا ينسخ الملفات إلى `lang/vendor/activitylog-browse/` حيث يمكنك تعديلها أو إضافة لغات جديدة.

## البنية المعمارية

| المكوّن | الدور |
|---|---|
| `GlobalModelLogger` | يستمع لأحداث Eloquent العامة ويسجل النشاط للنماذج بدون trait `LogsActivity` |
| `ActivityEnrichmentObserver` | يراقب حدث `creating` لنموذج Activity لدمج بيانات الإثراء في الخصائص قبل الحفظ |
| `RequestDataCollector` | يجمع URL والطريقة واسم المسار والURL السابق من الطلب الحالي |
| `DeviceDataCollector` | يجمع IP ومتصفح المستخدم والمُحيل من الطلب الحالي |
| `PerformanceDataCollector` | يلتقط مدة الطلب وذروة استخدام الذاكرة وعدد استعلامات قاعدة البيانات |
| `AppDataCollector` | يسجل البيئة وإصدار PHP واسم الخادم |
| `SessionDataCollector` | يحدد حارس المصادقة المستخدم |
| `ExecutionContextCollector` | يحدد مصدر التنفيذ (web/console/queue/schedule) ويلتقط أسماء المهام/الأوامر |
| `RelationDiscovery` | يستخدم الانعكاس لاكتشاف علاقات Eloquent تلقائياً لتصفح النماذج المرتبطة |
| `ActivityLogController` | يدير واجهة التصفح مع التصفية والتصفح وواجهة AJAX ولوحة الإحصائيات وفحص الخصائص |
| `SetLocale` | وسيط يطبق تفضيل لغة المستخدم من الجلسة |

## الرخصة

MIT

</div>
