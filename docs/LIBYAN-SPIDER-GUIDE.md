# دليل نشر موقع «أرض الحياة» على استضافة العنكبوت الليبي (Libyan Spider)

**الموقع:** شركة أرض الحياة للتنمية الزراعية والإنتاج الحيواني المساهمة. الموقع ثنائي اللغة ومعه لوحة التحكم.
**نسخة الكود:** 1.3.1، وهي النسخة نفسها المعروضة على معاينة Vercel. **تاريخ الدليل:** 22 نوفمبر 2026.
**وضع التشغيل:** الوضع العادي (PHP + SQLite على القرص). وضع Vercel/Blob السحابي **لا يُستخدم** هنا.

> الرموز المستخدمة: ✅ = تحقّقنا منه من مصدر رسمي (الرابط في قسم المصادر). ⚠️ = غير مؤكَّد، تحقّق منه في لوحتك أو اسأل الدعم الفني.
> في الأوامر: `USER` = اسم مستخدم cPanel، و`example.ly` = نطاق الشركة. استبدلهما بالقيم الحقيقية.

---

## 0. الخطوات باختصار

1. اشترِ باقة **Linux** مشتركة (Rodan أو Megalon) واربط النطاق بها.
2. من cPanel → **Select PHP Version**: اختر PHP **8.4** وفعّل الإضافات المطلوبة (القسم 4).
3. ارفع `ard-alhayat-cpanel-public_html.zip` إلى المجلد الرئيسي `/home/USER/` وفُكّه هناك (القسم 5).
4. انقل المحتوى من المعاينة بأمر واحد (`import-backup.php`)، أو ثبّت نسخة جديدة فارغة (`seed.php`) (القسم 7).
5. فعّل SSL (AutoSSL) ثم HTTPS، وأنشئ `app/config.php` (القسمان 8 و9).
6. اضبط SMTP من لوحة التحكم وأرسل رسالة تجريبية (القسم 10).
7. نفّذ قائمة التحقق (القسم 14). وعند موافقتك على الإطلاق ألغِ «إخفاء الموقع» (القسم 11).

---

## 1. ما وجدناه عن استضافة Libyan Spider

| البند | المعلومة | الحالة |
|---|---|---|
| باقات Linux المشتركة | Rodan بـ27 د.ل/شهر (4GB SSD، 48GB نقل بيانات، 15 بريدًا، نطاق إضافي واحد). Megalon بـ41.6 د.ل (7GB، 120GB، 125 بريدًا، نطاقان إضافيان). Godzilla بـ72.9 د.ل وGodzilla+10 بـ104.1 د.ل (مساحة «غير محدودة»). | ✅ [1] |
| لوحة التحكم | cPanel على CloudLinux. كل الباقات فيها: File Manager، وFTP، وCron Jobs مخصّصة، وGit، ونسخ cPanel جزئية وكاملة، وAdvanced DNS Zone Editor، وشهادة SSL مجانية. | ✅ [1] |
| PHP Selector | موجود في كل باقات Linux. تختار منه إصدار PHP وتفعّل الإضافات وتعدّل قيمًا مثل `upload_max_filesize` و`post_max_size` من الواجهة. | ✅ [1][2] |
| إصدارات PHP | صفحة الحالة تذكر تحديث الاستضافة المشتركة إلى PHP 8.2.35 و8.3.36 و8.4.27 و8.5.12 (أكتوبر 2026). | ✅ [3] |
| إضافات PHP بالاسم (pdo_sqlite، gd مع WebP، fileinfo، zip، …) | لم نجد قائمة رسمية بها. الواجهة تتيح تفعيل أي إضافة ظاهرة في القائمة، وعادةً تظهر كلها في CloudLinux. | ⚠️ تحقّق في الواجهة |
| SSL | AutoSSL (Let's Encrypt) يصدر الشهادة ويجدّدها تلقائيًا. التشغيل اليدوي من cPanel → Security → SSL/TLS Status → Run AutoSSL. يجب أن يشير النطاق و`www` إلى الخادم. | ✅ [4] |
| SSH / Terminal | يُفعَّل من منطقة العميل (Cloud Service → Manage → Enable Shell Access) أو من cPanel → Security → Toggle SSH Access. منفذ SSH هو **6934**. | ✅ [5] |
| Cron | cPanel → Advanced → Cron Jobs. | ✅ [6] |
| نطاقات ‎.ly وDNS | خوادم أسماء Premium DNS المجانية: `ns1.ns.ly` إلى `ns4.ns.ly`، وتدار السجلات من DNS Manager في منطقة العميل. تغيير خوادم الأسماء من: أسماء النطاق → تعديل اسم المخدم NS. | ✅ [7][8] |
| النسخ الاحتياطي من الشركة | «Daily, Weekly and Monthly Backup» ضمن مزايا كل الباقات. | ✅ [1] (لم نتحقق من مدة الاحتفاظ ⚠️) |
| خادم الويب | Apache أم LiteSpeed؟ غير مذكور. ملفات `.htaccess` في الموقع تعمل مع الاثنين. | ⚠️ |
| تغيير Document Root للنطاق الرئيسي | غير موثّق عندهم. في cPanel يمكن عادةً تحديده للنطاقات الإضافية (Addon) والفرعية فقط. | ⚠️ اسأل الدعم |
| حدود الموارد (RAM/CPU/inodes) | الصفحة تذكر فقط أن الحسابات التي تتجاوز 250,000 ملف (inodes) قد تؤثر على الخادم. الموقع أقل من ذلك بكثير. | ✅ [1] |

**لا تختر باقات Windows** (Lycosa وDelena وغيرها)، فهي تعمل بـPlesk/IIS ولا تقرأ ملفات `.htaccess`.

---

## 2. أي باقة نختار؟

الموقع نفسه صغير: الكود والخطوط والصور الأساسية نحو **8 ميغابايت**. يكبر بعدها مع الصور المرفوعة والسير الذاتية والنسخ الاحتياطية.

- **الحد الأدنى: Rodan** (27 د.ل/شهر). تكفي 4GB للموقع ونسخه الاحتياطية لسنوات، و48GB من نقل البيانات تكفي لموقع شركة. القيد الوحيد: **15 حساب بريد**.
- **الموصى به: Megalon** (41.6 د.ل/شهر) إذا كانت الشركة ستستخدم بريد الاستضافة لموظفيها (125 حسابًا)، أو تتوقع ألبومات صور كبيرة وسيرًا ذاتية كثيرة.
- المتطلبات الفنية للموقع: **Linux + cPanel + PHP 8.2 أو أحدث + SQLite**. لا يحتاج إلى قاعدة MySQL.

> ⚠️ الأسعار من صفحة الشركة بتاريخ 22/11/2026، وقد تتغير. سعر نطاق ‎.ly لم نتحقق منه.

---

## 3. ما تحتاجه قبل البدء

- حساب في منطقة العميل `my.libyanspider.com` فيه الباقة والنطاق. الأفضل أن يكون الحساب باسم الشركة.
- الملفات (كلها في مساحة عمل الوكالة):
  - `deploy/ard-alhayat-cpanel-public_html.zip`: **الأسهل**. مرتّب جاهزًا لـcPanel، وفيه `app/` و`public_html/`.
  - `ard-alhayat-website.zip`: الحزمة الكاملة (`ard-alhayat/app` و`ard-alhayat/public`)، للخيار (ب).
- لنقل المحتوى من المعاينة: كلمة مرور المدير على المعاينة (التي اخترتها أنت).
- اختياري لكنه يسهّل كثيرًا: تفعيل **SSH/Terminal**. إن لم يتوفر فسنستخدم Cron مرة واحدة بدلًا منه.

الحزم **لا تحتوي** أي كلمة مرور ولا قاعدة بيانات ولا مفاتيح Vercel.

---

## 4. ضبط PHP (Select PHP Version)

cPanel → **SOFTWARE → Select PHP Version** [2]:

1. **Current PHP version**: اختر **8.4** واضغط **Set as current**. يعمل أيضًا على 8.3 و8.2، ولا يعمل على 8.0 أو أقدم.
2. في **Extensions** تأكد أن هذه مفعّلة، ثم اضغط **Save**:

| الإضافة | لماذا | ضرورية؟ |
|---|---|---|
| `pdo` + `pdo_sqlite` | قاعدة البيانات (SQLite) | **نعم** |
| `gd` (مع دعم WebP) | تحويل الصور المرفوعة إلى WebP | **نعم** |
| `fileinfo` | فحص نوع الملفات المرفوعة (الصور، والسير الذاتية، والنسخ الاحتياطية) | **نعم** |
| `mbstring` | النصوص العربية | **نعم** |
| `dom` و`xml` | منظّف HTML في المحرر | **نعم** |
| `zip` | النسخ الاحتياطي والاستعادة، وتصدير Excel، وفحص ملفات DOCX | **نعم** |
| `openssl` | إرسال البريد عبر SMTP بتشفير TLS | **نعم** (مفعّلة عادةً) |
| `json` و`session` | مدمجة في PHP 8 | مدمجة |
| `exif` | تدوير صور الجوال تلقائيًا | اختيارية، يُنصح بها |
| `curl` | لأمر نقل الصور من المعاينة (`--images-from`) فقط | اختيارية |

غير مطلوب: `sqlite3` (الكود يستخدم PDO فقط)، و`intl`، و`imagick`، وMySQL.

3. اضغط **Switch to PHP Options** (أو تبويب Options) واضبط القيم التالية. تُحفظ كل قيمة فور تغييرها:

| الخيار | القيمة المقترحة | السبب |
|---|---|---|
| `upload_max_filesize` | `16M` | الموقع نفسه يحدّ الصورة بـ6MB والمستند بـ4MB |
| `post_max_size` | `32M` | رفع عدة صور معًا، واستعادة نسخة احتياطية |
| `memory_limit` | `256M` | معالجة الصور الكبيرة (حتى 30 ميغابكسل) |
| `max_execution_time` | `120` | النسخ الاحتياطي والاستعادة |
| `max_file_uploads` | `20` | رفع عدة صور في مكتبة الوسائط |

> للتحقق بعد التثبيت: لوحة التحكم ← **النسخ الاحتياطي** ← «إنشاء نسخة احتياطية الآن». نجاحها يعني أن `zip` و`pdo_sqlite` تعملان. ورفع صورة في **مكتبة الوسائط** يختبر `gd` و`fileinfo`.

---

## 5. رفع الملفات ومكان الـ Document Root

**القاعدة الذهبية:** المجلد الذي يُعرض على الإنترنت (الـ Document Root) يجب أن يحتوي محتوى `public/` فقط. أما `app/` (الكود وقاعدة البيانات والسير الذاتية والنسخ الاحتياطية) فيبقى **خارج** `public_html`.

### الخيار (أ): تخطيط `public_html`، وهو الموصى به ويعمل مع النطاق الرئيسي دائمًا

الشكل النهائي على الخادم:

```
/home/USER/
├── app/                 ← الكود + storage (قاعدة البيانات، الملفات، النسخ) — غير مكشوف
│   ├── storage/data/ardalhayat.sqlite
│   ├── storage/files/   (السير الذاتية والمرفقات)
│   ├── storage/backups/
│   ├── storage/cache/  storage/mail/
│   └── config.php       (تنشئه في القسم 8)
├── public_html/         ← Document Root
│   ├── index.php  .htaccess  favicon.ico
│   ├── assets/   uploads/   .well-known/
└── CREDENTIALS.txt      (يظهر فقط بعد التثبيت الجديد — احذفه بعد أول دخول)
```

الخطوات:

1. cPanel → **File Manager** → **Settings** (أعلى اليمين) → فعّل **Show Hidden Files (dotfiles)** → Save. بدونه لن ترى ملفات `.htaccess`.
2. افتح `public_html`. إن وجدت ملفات افتراضية مثل `index.html` أو `default.html` فاحذفها. **لا تحذف** `.well-known` ولا `cgi-bin`. وإن كان فيه `.htaccess` يحتوي سطور `# php -- BEGIN cPanel-generated handler` فاحفظ نسخة منه (انظر استكشاف الأخطاء).
3. ارجع إلى المجلد الرئيسي `/home/USER` (زر Home). اضغط **Upload** وارفع `ard-alhayat-cpanel-public_html.zip`.
4. حدّد الملف → **Extract** → وجهة الاستخراج `/home/USER` → **Extract Files**. سيظهر `app/` بجانب `public_html/` وتُدمج الملفات داخل `public_html/`.
5. تأكد من وجود `public_html/.htaccess` و`public_html/index.php` و`app/bootstrap.php`. ثم احذف ملف الـZIP.

> تفريغ الحزمة الكاملة يدويًا بدل حزمة cPanel: ضع **محتوى** `ard-alhayat/public/` (ومعه `.htaccess` و`.well-known`) داخل `public_html/`، وضع `ard-alhayat/app/` في `/home/USER/app/`. المجلد `api/` والملف `vercel.json` خاصّان بـVercel، فلا ترفعهما.

لا تحتاج إلى تعديل أي مسار: `public_html/index.php` يحمّل `../app/bootstrap.php`، والكود يتعرّف على `public_html` تلقائيًا كمجلد ويب، ويحفظ الصور المرفوعة في `public_html/uploads/`.

### الخيار (ب): توجيه Document Root إلى مجلد `public/`

يناسب هذا الخيار نطاقًا إضافيًا (Addon) أو فرعيًا (مثل `new.example.ly` للتجربة)، أو حالة يغيّر فيها الدعم الفني Document Root للنطاق الرئيسي ⚠️.

```
/home/USER/ard-alhayat/
├── app/        ← غير مكشوف
├── public/     ← Document Root للنطاق
├── api/  vercel.json  README.md   (غير مستخدمة هنا — يمكن حذف api/ و vercel.json)
└── CREDENTIALS.txt (بعد التثبيت الجديد فقط)
```

1. ارفع `ard-alhayat-website.zip` إلى `/home/USER/` وفُكّه، فينتج المجلد `/home/USER/ard-alhayat/`.
2. cPanel → **Domains** → **Create A New Domain** (أو **Manage** لنطاق موجود). ألغِ خيار «Share document root» إن وُجد، واكتب في **Document Root**: `ard-alhayat/public` → Submit/Update.
3. لا تضع شيئًا في `public_html` لهذا النطاق.

ملفات `.htaccess` واحدة في الخيارين، ولا تحتاج تعديلًا.

### ما الذي تحميه ملفات `.htaccess`؟ (اختبرناها على Apache 2.4)

- `public_html/.htaccess`:
  - يوجّه الروابط إلى `index.php`.
  - يقدّم `/assets` و`/uploads` مباشرة.
  - يرفض في `/uploads` كل ما ليس صورة WebP مولَّدة.
  - يمنع فتح ملفات ‎`.sqlite/.zip/.txt/.md/.json/.env/.log` (ما عدا `robots.txt` الذي يولّده الموقع).
  - يضيف رؤوس أمان وتخزين مؤقت.
- `public_html/uploads/.htaccess` يمنع تنفيذ أي سكربت داخل مجلد الصور.
- `public_html/.well-known/.htaccess` يسمح بملفات التحقق من SSL، فيعمل AutoSSL ويتجدد حتى بعد تفعيل إعادة التوجيه إلى HTTPS.
- `app/.htaccess` و`app/storage/*/.htaccess` طبقة حماية إضافية تمنع الوصول عبر الويب.

---

## 6. صلاحيات الملفات (Permissions)

في استضافة cPanel/CloudLinux يعمل PHP باسم مستخدم حسابك، فلا تحتاج أبدًا إلى `777`.

| المسار | الصلاحية |
|---|---|
| كل المجلدات | `755` (أو `750` لمجلدات `app/storage/*`) |
| كل الملفات | `644` |
| `app/storage/data/ardalhayat.sqlite` | `640` (ينشئها الموقع تلقائيًا) |
| `CREDENTIALS.txt` | `600` (ينشئه التثبيت تلقائيًا) |
| `app/config.php` | `640` أو `600` |

يجب أن يستطيع PHP **الكتابة** في هذه المجلدات:
- `app/storage/data/`: المجلد نفسه وليس الملف فقط، لأن SQLite تنشئ بجانب القاعدة ملفّي `-wal` و`-shm`.
- `app/storage/files/` و`app/storage/backups/` و`app/storage/cache/` و`app/storage/mail/`.
- `public_html/uploads/`، أو `ard-alhayat/public/uploads/` في الخيار (ب).

تعديل الصلاحيات من File Manager: حدّد العنصر ← **Permissions**. ومن Terminal:

```bash
cd ~ && find app public_html -type d -exec chmod 755 {} \; && find app public_html -type f -exec chmod 644 {} \;
```

---

## 7. التثبيت الأول

اختر **واحدًا** من المسارين:

- **(أ) نقل المحتوى من معاينة Vercel**: موصى به إن كنت أدخلت محتوى أو كلمة مرور على المعاينة.
- **(ب) تثبيت جديد فارغ** بمحتوى البداية.

كلا الأمرين يعمل من سطر الأوامر فقط (CLI)، ولا يمكن فتحه من المتصفح (يرجع 403). نفّذه بإحدى طريقتين:
- **Terminal/SSH** (أسهل): فعّل Shell Access [5]، ثم cPanel → **Advanced → Terminal**.
- **بدون SSH**: أنشئ Cron Job **مرة واحدة** (الطريقة في آخر القسم).

> ⚠️ مسار PHP في سطر الأوامر: في Terminal اكتب `php -v`، ويجب أن يظهر 8.2 أو أحدث. ثم اكتب `php -m | grep -i -E "pdo_sqlite|gd|zip"`.
> في Cron استخدم `/usr/local/bin/php`. إن أظهر ملف السجل إصدارًا قديمًا أو خطأ `could not find driver`، استخدم مسار إصدار CloudLinux مباشرة، مثل `/opt/alt/php84/usr/bin/php`.

### (أ) نقل المحتوى من المعاينة إلى Libyan Spider

**هل يدعم الموقع ذلك؟** نعم، لكن بأمر سطر أوامر خاص أضفناه لهذا الغرض: `app/import-backup.php`.

لماذا لا تكفي «الاستعادة» من لوحة التحكم؟ لأنها تقبل فقط النسخ التي أنشأها **الموقع نفسه**. النسخة موقّعة بمفتاح سري خاص بكل تثبيت، والتثبيت الجديد على Libyan Spider له مفتاح مختلف، فسيرفض نسخة المعاينة. هذا مقصود لأسباب أمنية.

ماذا يفعل الأمر:
- يتحقق من المانيفست، ومن بصمة قاعدة البيانات، ومن سلامة SQLite وإصدارها، ومن وجود مدير نظام فعّال.
- يجلب صور المعاينة. نسخة Vercel الاحتياطية تحتوي **قاعدة البيانات فقط**، فالصور تبقى على المعاينة ويُنزّلها الأمر بالخيار `--images-from`.
- يسجّل خروج الجميع، ويمسح كلمة مرور SMTP، ويفرّغ التخزين المؤقت.
- لا يُنشئ كلمة مرور جديدة: **تدخل بنفس اسم المستخدم وكلمة المرور التي على المعاينة**.

الخطوات:

1. على المعاينة: `https://ard-alhayat-preview.vercel.app/admin/` ← **النسخ الاحتياطي** ← **إنشاء نسخة احتياطية الآن**. يُنزَّل ملف `backup-YYYYMMDD-HHMMSS-manual-xxxxxx.zip` إلى جهازك فورًا.
2. ارفع هذا الملف عبر File Manager إلى `/home/USER/`، **وليس** داخل `public_html`.
3. شغّل الأمر من Terminal:

```bash
cd ~
php app/import-backup.php ~/backup-YYYYMMDD-HHMMSS-manual-xxxxxx.zip --images-from=https://ard-alhayat-preview.vercel.app
```

في الخيار (ب) غيّر المسار إلى `php ~/ard-alhayat/app/import-backup.php …`.

4. اقرأ النتيجة:
   - `Images: … downloaded`: عدد الصور المنقولة.
   - `Not found …`: صور لم توجد، فارفعها من جديد في مكتبة الوسائط.
   - `Documents … missing`: سير ذاتية أو مرفقات مناقصات رُفعت على المعاينة. هذه **لا تُنقل** لأنها مشفّرة في تخزين Vercel، فأعد إرفاقها من لوحة التحكم إن لزم.
5. ادخل إلى `https://example.ly/admin/` بنفس بيانات المعاينة. ثم أعد إدخال كلمة مرور SMTP (القسم 10).
6. احذف ملف النسخة من `/home/USER/` بعد النجاح، أو احتفظ به في مكان آمن خارج الخادم.

ملاحظات:
- إن كان على الخادم موقع مثبّت من قبل، يرفض الأمر الاستبدال إلا مع `--force`. ومع `--force` يحفظ القاعدة القديمة في `app/storage/backups/pre-import-*.sqlite`. لهذا تكرار الأمر بدون `--force` آمن ولا يغيّر شيئًا.
- **لا توقف المعاينة على Vercel** قبل التأكد من نقل الصور.
- بديل يدوي بلا أي أمر: فُكّ النسخة على جهازك، وأعد تسمية `db.sqlite` إلى `ardalhayat.sqlite`، وارفعها إلى `app/storage/data/`، ثم ارفع الصور يدويًا إلى `public_html/uploads/`. الطريقة الأولى أضمن لأنها تفحص الملف وتنقل الصور.

### (ب) تثبيت جديد فارغ

```bash
cd ~
php app/seed.php
```

- ينشئ قاعدة البيانات ومحتوى البداية، ومستخدم `admin` بكلمة مرور عشوائية تُكتب في `/home/USER/CREDENTIALS.txt`. في الخيار (ب) تُكتب في `~/ard-alhayat/CREDENTIALS.txt`. الملف خارج الويب بصلاحية 600.
- افتح الملف من File Manager ← View، وادخل إلى `/admin/`. **سيُطلب منك تغيير كلمة المرور فورًا** (إلزامي، 10 أحرف على الأقل).
- **احذف `CREDENTIALS.txt` مباشرة بعد ذلك.**
- إن شُغّل الأمر مرة ثانية يرفض («Already installed»)، فتكراره آمن. أما `--reset` فـ**يمسح كل شيء**، فلا تستخدمه على موقع حي.

### التنفيذ عبر Cron (عند عدم توفر SSH)

1. cPanel → **Advanced → Cron Jobs** [6] → Common Settings: **Once Per Minute (\* \* \* \* \*)**.
2. Command، مثال للنقل:

```bash
/usr/local/bin/php /home/USER/app/import-backup.php /home/USER/backup-YYYYMMDD-HHMMSS-manual-xxxxxx.zip --images-from=https://ard-alhayat-preview.vercel.app > /home/USER/install-log.txt 2>&1
```

   أو للتثبيت الجديد:

```bash
/usr/local/bin/php /home/USER/app/seed.php > /home/USER/install-log.txt 2>&1
```

3. **Add New Cron Job**. انتظر دقيقتين، ثم افتح `install-log.txt` من File Manager.
4. **احذف الـ Cron Job فورًا** (Current Cron Jobs ← Delete)، واحذف `install-log.txt`. التشغيلات المتكررة قبل الحذف لا تضر، لأن الأمرين يرفضان العمل مرة ثانية (لا تضع `--force` في Cron).

---

## 8. ملف الإعدادات `app/config.php`

بعد أن يعمل HTTPS (القسم 9)، انسخ `app/config.example.php` إلى `app/config.php` (File Manager ← Copy، ثم Rename) وعدّله:

```php
<?php
return [
    'base_url' => 'https://www.example.ly',   // بلا شرطة في النهاية — يُستخدم في sitemap و canonical والبريد
    'force_https_cookie' => true,             // جلسة لوحة التحكم عبر HTTPS فقط
];
```

- لا تفعّل `force_https_cookie` قبل أن يعمل HTTPS، وإلا فلن تستطيع تسجيل الدخول.
- خيارات أخرى موجودة لكن لا داعي لتغييرها: `db_path`، و`max_upload_mb` (الافتراضي 6)، و`max_doc_mb` (الافتراضي 4)، و`page_cache_ttl` (600 ثانية).

### ممنوع على هذه الاستضافة

**لا تضبط** المتغيرات `BLOB_READ_WRITE_TOKEN` و`AH_DATA_KEY`، لا في `.htaccess` (`SetEnv`) ولا في أي مكان آخر. وجودهما معًا يشغّل وضع Vercel السحابي، فيبحث الموقع عن تخزين Vercel ولن يعمل. الكود يعمل في الوضع العادي تلقائيًا عند غيابهما.

---

## 9. SSL وإجبار HTTPS

1. وجّه النطاق إلى الاستضافة:
   - إن كان النطاق مسجّلًا عند Libyan Spider: استخدم خوادم الأسماء المذكورة في رسالة تفعيل الاستضافة. ⚠️ تأكد منها في الرسالة.
   - أو استخدم Premium DNS (`ns1.ns.ly` إلى `ns4.ns.ly`) وأضف سجل **A** للنطاق و`www` إلى IP الخادم المذكور في cPanel [7][8].
   - انتشار تغيير DNS قد يستغرق حتى 24 ساعة.
2. cPanel → **Security → SSL/TLS Status** → **Run AutoSSL** [4]. تأكد أن `example.ly` و`www.example.ly` يظهران «AutoSSL Domain Validated». إن فشل، تحقق من DNS ومن عدم وجود سجل CAA يمنع `letsencrypt.org`.
3. افتح `https://example.ly/ar/`، ويجب أن يظهر القفل.
4. **فعّل التحويل إلى HTTPS**: في `public_html/.htaccess` (File Manager ← Edit) أزل علامة `#` من السطرين:

```apache
  RewriteCond %{HTTPS} !=on
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

   بديل: cPanel → **Domains** → مفتاح **Force HTTPS Redirect** للنطاق. استخدم طريقة واحدة فقط.
5. أنشئ `app/config.php` (القسم 8).
6. الموقع يرسل `Strict-Transport-Security` تلقائيًا عند العمل عبر HTTPS.

> تجديد الشهادة يعمل تلقائيًا حتى بعد التحويل إلى HTTPS، بفضل `public_html/.well-known/.htaccess`.

---

## 10. البريد (SMTP): نسيت كلمة المرور، وإشعارات النماذج

بدون SMTP لا يُرسل أي بريد فعليًا: الرسائل تُكتب في `app/storage/mail/outbox.log`. يشمل ذلك روابط «نسيت كلمة المرور» وإشعارات رسائل «اتصل بنا» وطلبات التوظيف. أما الرسائل والطلبات نفسها فتُحفظ دائمًا في لوحة التحكم.

1. cPanel → **Email Accounts** → أنشئ مثلًا `no-reply@example.ly` (ويمكن `info@` و`hr@`).
2. بجانب الحساب ← **Connect Devices**. ستجد «Outgoing Server» والمنفذ. المعتاد في cPanel: `mail.example.ly` بمنفذ **465** (SSL/TLS)، أو **587** (STARTTLS). ⚠️ خذ القيم كما تظهر لك.
3. في لوحة تحكم الموقع ← **الإعدادات العامة** ← قسم **«البريد الإلكتروني (SMTP)»** (يظهر لمدير النظام فقط):

| الحقل في اللوحة | القيمة |
|---|---|
| بريد المرسل (From address) | `no-reply@example.ly` |
| اسم المرسل (From name) | أرض الحياة |
| SMTP host | `mail.example.ly`، أو اسم الخادم الظاهر في Connect Devices |
| SMTP port | `465` |
| التشفير (Encryption) | **SSL/TLS**، أو **STARTTLS** مع المنفذ 587 |
| SMTP user | `no-reply@example.ly` (البريد كاملًا) |
| SMTP password | كلمة مرور حساب البريد. تُخفى بعد الحفظ، واترك الحقل فارغًا لاحقًا للإبقاء عليها |

4. في قسم **«معلومات الاتصال»** في الصفحة نفسها، عبّئ **بريد إشعارات الرسائل** و**بريد الموارد البشرية**، فإليهما تصل التنبيهات.
5. أضف بريدك في **الملف الشخصي** (اسمك أعلى اللوحة). زر الاختبار يرسل إليه، وهو أيضًا شرط لعمل «نسيت كلمة المرور».
6. اضغط **«إرسال رسالة تجريبية إلى بريدي»**.
   - إن فشل الاختبار، فالموقع يتحقق من شهادة خادم البريد بصرامة. جرّب اسم الخادم الظاهر في Connect Devices بدل `mail.example.ly`، أو بدّل بين 465/SSL و587/STARTTLS.
7. النسخ الاحتياطية **لا تحفظ كلمة مرور SMTP** أبدًا، فبعد أي نقل أو استعادة على خادم جديد أعد إدخالها.

---

## 11. إظهار الموقع لمحركات البحث (إلغاء noindex) عند الإطلاق

الموقع حاليًا **مخفي** عن محركات البحث، وهذا هو الإعداد الافتراضي حتى توافق على الإطلاق.

- مكان الإعداد: لوحة التحكم ← **الإعدادات العامة** ← قسم **«إعدادات SEO العامة»** ← مربع الاختيار **«إخفاء الموقع عن محركات البحث (حتى الإطلاق)»**. أزل العلامة ثم **حفظ**. اسم الإعداد في قاعدة البيانات `noindex`.
- النتيجة فورًا:
  - تختفي من الصفحات `<meta name="robots" content="noindex, nofollow">`.
  - يصبح `robots.txt` كالتالي: `Disallow: /admin/` و`Disallow: /files/` و`Sitemap: https://example.ly/sitemap.xml`.
  - يختفي تنبيه «الموقع مخفي…» من لوحة التحكم.
- لوحة التحكم `/admin/` تبقى `noindex` دائمًا، وهذا صحيح.
- رأس `X-Robots-Tag: noindex` الموجود على المعاينة مصدره إعدادات Vercel (`vercel.json`) فقط، ولا يوجد على Libyan Spider.
- بعد الإطلاق: أضف الموقع إلى Google Search Console وأرسل `https://example.ly/sitemap.xml`.

---

## 12. Cron: هل يحتاجه الموقع؟

**لا.** جدولة نشر الأخبار وإخفاء الإعلانات والمناقصات بعد تاريخ الانتهاء تُحسب لحظة فتح الصفحة، فلا يوجد عمل دوري. تحديث الصفحات المخزّنة مؤقتًا (10 دقائق) تلقائي أيضًا.

Cron يُستخدم مرة واحدة فقط عند التثبيت بدون SSH (القسم 7). اختياريًا، يمكن ضبط تذكير شهري لتنزيل نسخة احتياطية.

---

## 13. النسخ الاحتياطي والاستعادة

### من لوحة التحكم (الميزة المدمجة الموقّعة)

- **إنشاء**: لوحة التحكم ← **النسخ الاحتياطي** ← **إنشاء نسخة احتياطية الآن**.
  - النسخة ملف ZIP فيه: قاعدة البيانات، والصور المرفوعة، والسير الذاتية ومرفقات المناقصات، وملف `manifest.json` موقّع.
  - تُحفظ في `app/storage/backups/` خارج الويب، وتظهر في القائمة مع زر **تنزيل**.
  - لا تحتوي كلمة مرور SMTP.
- **نزّل نسخة واحفظها خارج الخادم** مرة شهريًا على الأقل، وقبل أي تعديل كبير. احذف القديمة من القائمة حتى لا تمتلئ المساحة.
- **استعادة** (لمدير النظام فقط): القسم نفسه ← **استعادة من نسخة احتياطية** ← اختر الملف ← أدخل **كلمة مرورك الحالية** ← استعادة.
  - يتحقق الموقع من التوقيع والسلامة، ويأخذ **نسخة تلقائية قبل الاستعادة** (`…-prerestore-…zip`).
  - بعد الاستعادة يُسجَّل خروج الجميع.
  - تُقبل فقط النسخ التي أنشأها **هذا التثبيت**. نسخ موقع آخر تُنقل بأمر `import-backup.php` (القسم 7أ).
  - إذا كان ملف النسخة أكبر من `post_max_size` فارفعه عبر File Manager، واستخدم `import-backup.php … --force`. الأمر يقبل النسخ المحلية كاملة مع الصور والمستندات.

### من الاستضافة

- cPanel → **Files → Backup**: نزّل **Home Directory** كاملًا. يشمل `app/storage` و`public_html`.
- خدمة النسخ اليومية والأسبوعية والشهرية من Libyan Spider [1]. ⚠️ اسأل الدعم عن مدة الاحتفاظ وطريقة الاستعادة.

---

## 14. قائمة التحقق بعد النشر

استبدل `example.ly` بنطاقك:

- [ ] `https://example.ly/` يحوّل إلى `/ar/`، والصفحات `/ar/` و`/en/` و`/ar/news` و`/ar/contact` تفتح بلا أخطاء.
- [ ] `http://example.ly/ar/` يحوّل إلى `https://` (بعد القسم 9).
- [ ] الخطوط والصور والشعار تظهر. افتح من الجوال أيضًا، وجرّب قائمة الجوال وزر «الرئيسية».
- [ ] `https://example.ly/admin/` يفتح، والدخول يعمل، وتغيير كلمة المرور الإلزامي تمّ (في التثبيت الجديد).
- [ ] رفع صورة في **مكتبة الوسائط** ينجح، والصورة تظهر على الموقع، ورابطها `/uploads/….webp` يعمل.
- [ ] نموذج «اتصل بنا» يصل إلى **الرسائل** في اللوحة، والإشعار يصل بالبريد.
- [ ] «إرسال رسالة تجريبية إلى بريدي» ينجح، و«نسيت كلمة المرور» يرسل رابطًا.
- [ ] «إنشاء نسخة احتياطية الآن» ينجح، والتنزيل يعمل.
- [ ] **اختبارات الحماية**، ويجب أن تُرجع كلها 403 أو 404:
  - `https://example.ly/app/storage/data/ardalhayat.sqlite`
  - `https://example.ly/CREDENTIALS.txt`
  - `https://example.ly/uploads/x.php`
  - `https://example.ly/.htaccess`
  - `https://example.ly/README.md`
- [ ] `CREDENTIALS.txt` وملفات ZIP و`install-log.txt` محذوفة من الخادم، وأي Cron مؤقت محذوف.
- [ ] `app/config.php` فيه `base_url` الصحيح، و`https://example.ly/sitemap.xml` يعرض روابط بالنطاق الصحيح.
- [ ] أدخلت بيانات الشركة الحقيقية: الهاتف، والعنوان، والخريطة، وروابط التواصل، والإحصاءات، والفريق، والمشاريع. ثم نشرت المسودات.
- [ ] قبل الإطلاق العام:
  - اشترِ رخصة الخط Expo Arabic للويب من 29LT، أو استبدله بخط مجاني.
  - احصل على موافقة العميل المكتوبة على تقنية PHP/SQLite وعلى حذف قسم المنتجات.
  - عند موافقتك ألغِ «إخفاء الموقع» (القسم 11).

---

## 15. استكشاف الأخطاء

| العَرَض | السبب المحتمل | الحل |
|---|---|---|
| **500 Internal Server Error** | (1) إصدار PHP أقدم من 8.1. (2) أمر في `.htaccess` غير مسموح على الخادم. (3) المجلد `app/` ليس بجانب `public_html`. | (1) اختر 8.4 من Select PHP Version. (2) راجع cPanel → **Metrics → Errors** وملف `error_log` في `public_html` و`app/`. (3) تحقق من الشكل في القسم 5: الخطأ `Failed opening required '…/app/bootstrap.php'` يعني أن المسار خاطئ. |
| الرئيسية تعمل لكن `/ar/news` يعطي 404 من الخادم | ملف `.htaccess` لم يُرفع (ملف مخفي)، أو `mod_rewrite` غير مفعّل | فعّل Show Hidden Files وتأكد من وجود `public_html/.htaccess`. `mod_rewrite` متوفر عادةً في cPanel ⚠️. |
| ضاعت إعدادات PHP بعد استبدال `.htaccess` | كان في `.htaccess` القديم بلوك `cPanel-generated handler` (MultiPHP) | أعد لصق ذلك البلوك أعلى الملف الجديد، أو أعد اختيار الإصدار من Select PHP Version. |
| `could not find driver` أو صفحة بيضاء | `pdo_sqlite` غير مفعّلة | Select PHP Version → Extensions → فعّل `pdo_sqlite` (و`pdo`) → Save. |
| `unable to open database file` أو `attempt to write a readonly database` | مجلد `app/storage/data` غير قابل للكتابة | صلاحية المجلد 755 والملف 640 أو 644، والمالك مستخدم cPanel. |
| رفع صورة يفشل: «الملف كبير» أو لا يحدث شيء | `upload_max_filesize` أو `post_max_size` صغيرة، أو `gd` بلا WebP | القيم في القسم 4. الحد داخل الموقع 6MB للصورة و4MB للمستند. |
| الصور لا تظهر بعد الرفع | صلاحيات `public_html/uploads/`، أو `.htaccess` قديم | المجلد 755، واستخدم ملف `.htaccess` الذي في الحزمة (1.3.1). |
| فشل «الاستعادة» برسالة أن النسخة غير صالحة | نسخة من تثبيت آخر (المعاينة مثلًا)، أو ملف معدّل، أو كبير | استخدم `import-backup.php` (القسم 7أ). |
| لا أستطيع البقاء مسجّلًا في لوحة التحكم | `force_https_cookie => true` والموقع يعمل على http | فعّل SSL أولًا، أو احذف السطر مؤقتًا. |
| كثرة محاولات الدخول الخاطئة تقفل الدخول | حماية Login throttling | انتظر 15 دقيقة تقريبًا وحاول مجددًا. |
| البريد لا يصل | SMTP غير مضبوط (الرسائل في `app/storage/mail/outbox.log`)، أو خطأ في الشهادة أو المنفذ | القسم 10، وراجع `error_log` بحثًا عن سطر يبدأ بـ`SMTP:`. |
| AutoSSL يفشل | DNS لا يشير إلى الخادم، أو سجل CAA، أو مجلد `.well-known` محذوف | القسم 9، وتأكد من وجود `public_html/.well-known/.htaccess`. |
| التصميم القديم ما زال يظهر | تخزين المتصفح المؤقت | تحديث قوي (Ctrl+F5). صفحات الموقع تتحدث خلال 10 دقائق، وأي حفظ في اللوحة يفرّغ التخزين المؤقت. |
| أحتاج رؤية الخطأ مؤقتًا | الأخطاء لا تُعرض للزوار (آمن) | الأفضل: cPanel → Errors أو `error_log`. للضرورة القصوى أضف مؤقتًا `SetEnv AH_DEBUG 1` أعلى `.htaccess` ⚠️، ثم **احذفه فورًا**. |

---

## 16. أسئلة مفتوحة (يجيب عنها مؤيد)

1. **النطاق:** ما اسمه بالضبط (`ardalhayat.ly`؟ `.com.ly`؟)؟ هل هو مسجّل باسم الشركة، وعند Libyan Spider أم جهة أخرى؟ هل يكون الموقع على `www` أم بدونه؟
2. **الباقة والحساب:** Rodan أم Megalon؟ وهل الحساب باسم الشركة أم الوكالة؟ ومن يدفع ويجدد؟
3. هل سيكون الموقع **النطاق الرئيسي** للحساب (فنستخدم الخيار أ)، أم نطاقًا إضافيًا (يمكن الخيار ب)؟
4. هل نفعّل **SSH/Terminal** على الحساب؟ وإلا سنستخدم طريقة Cron.
5. **البريد:** هل ستستخدم الشركة بريد الاستضافة (cPanel)، أم LS Suite أو Google/Microsoft؟ هذا يحدد إعدادات SMTP، وأحيانًا سجلات MX.
6. **المعاينة على Vercel:** متى نوقفها؟ نوصي بإيقافها فقط بعد نقل المحتوى والصور والتأكد منها.
7. **قبل الإطلاق:** رخصة خط Expo Arabic، وموافقة العميل على التقنية وعلى حذف المنتجات، ثم موافقتك على إلغاء «إخفاء الموقع».

---

## 17. المصادر (تمّت مراجعتها في 22/11/2026)

1. Libyan Spider: Cloud Shared Hosting (الأسعار وجدول المزايا): <https://libyanspider.com/shared-hosting/>
2. Libyan Spider KB: Changing your PHP Version in cPanel: <https://help.libyanspider.com/kb-article/changing-your-php-version-in-cpanel/>
3. Libyan Spider Status (تحديثات PHP 8.2–8.5): <https://status.libyanspider.com/>
4. Libyan Spider KB: How to Manage AutoSSL for cPanel Hosting: <https://help.libyanspider.com/kb-article/how-to-manage-autossl-for-cpanel-hosting-from-the-client-area/>
5. Libyan Spider KB: Enable/Disable Shell, SSH Access: <https://help.libyanspider.com/kb-article/enable-disable-shell-ssh-access-cpanel-account/>
6. Libyan Spider KB: How to set up cron jobs in cPanel: <https://help.libyanspider.com/kb-article/how-to-set-up-cron-jobs-in-cpanel/>
7. Libyan Spider KB: How to Activate Premium DNS Cloud Hosting: <https://help.libyanspider.com/kb-article/activating-premium-dns-cloud-hosting/>
8. العنكبوت الليبي، مركز المساعدة: كيفية تحديث خوادم أسماء النطاق: <https://help.libyanspider.com/ar/kb-article/update-nameserver/>
9. cPanel Docs: File Manager (الرفع والفك والملفات المخفية): <https://docs.cpanel.net/cpanel/files/file-manager/>
10. cPanel Docs: Managing SSL Certificates / AutoSSL: <https://docs.cpanel.net/cpanel/meridian/security/managing-ssl-certificates/>

**ملاحظة:** كل أسماء الإعدادات والمسارات والأوامر في هذا الدليل مأخوذة من كود الموقع نفسه. وجرّبنا الخيارين (أ) و(ب) على Apache 2.4 مع PHP 8.4: نجحت اختبارات الموقع كلها (98 من 98)، ونجح النقل بأمر `import-backup.php` وملفات التحقق من SSL.
