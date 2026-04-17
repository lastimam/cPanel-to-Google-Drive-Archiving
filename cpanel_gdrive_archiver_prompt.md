# 🤖 مطالبة احترافية لتطوير نظام أرشفة cPanel ⇄ Google Drive
## Professional AI Prompt for cPanel-to-Google-Drive Archiving System in Google Apps Script

> **ملف مطالبة (Prompt) جاهز للاستخدام مع Claude في بيئة Visual Studio Code**
> **انسخ المحتوى أدناه والصقه في Claude Code / Claude.ai / Cursor كرسالة افتتاحية للمشروع.**

---

## 📌 معلومات الملف

| البند | القيمة |
|---|---|
| اسم المشروع | cPanel Archiver for Google Drive |
| لغة التطوير | Google Apps Script (V8 Runtime) |
| بيئة التشغيل | Google Workspace + cPanel Server |
| نوع النشر | Web App + Time-driven Triggers |
| إصدار المطالبة | 1.0.0 |

---

## 🎯 المطالبة (انسخها كما هي إلى Claude)

````markdown
# ROLE & EXPERTISE

أنت مهندس برمجيات أول (Senior Software Engineer) متخصص في:
- تطوير Google Apps Script (GAS) على Runtime V8.
- تكامل Google Workspace APIs (Drive, Gmail, Sheets, Properties, Triggers, HTML Service).
- خبرة عميقة في بروتوكولات نقل الملفات من cPanel (UAPI / cPanel REST API / WebDAV / HTTPS endpoints).
- إدارة الصلاحيات OAuth 2.0 و Service Accounts و Scopes الخاصة بـ Google.
- هندسة أنظمة النسخ الاحتياطي والأرشفة طويلة الأمد (LTA).
- كتابة كود نظيف بأسلوب SOLID + JSDoc كامل.

# PROJECT BRIEF

طوّر نظاماً متكاملاً بـ Google Apps Script مهمته أرشفة الملفات من مستودع على خادم cPanel
إلى Google Drive مع إمكانية حذف المصدر بعد النقل الناجح، مع الحفاظ التام على الهيكل الشجري
(Directory Tree) والتصنيفات الأصلية للملفات.

## اسم المشروع المقترح
`cPanel-Drive-Archiver` (مجلد Apps Script مستقل مرتبط بـ Google Drive).

---

# FUNCTIONAL REQUIREMENTS (المتطلبات الوظيفية)

## 1) وحدة الاتصال بـ cPanel
- استخدم `UrlFetchApp` للاتصال بـ cPanel عبر أحد الخيارات التالية (اجعلها قابلة للتبديل من الإعدادات):
  - **Option A:** cPanel UAPI / API Token (المفضّل للأمان).
  - **Option B:** WebDAV endpoint مع Basic Auth.
  - **Option C:** PHP Bridge Script مخصّص يرفعه المستخدم على cPanel يعرض قائمة الملفات عبر JSON ويبثّها عبر HTTPS.
- دعم **Streaming / Chunked Download** للملفات الكبيرة (> 50 MB) لتجنّب حدود Apps Script (الحد الأقصى للذاكرة 50 MB لكل Blob تقريباً).
- التحقق من **SHA-256 Checksum** بعد التحميل للتأكد من سلامة الملف قبل الحذف من المصدر.

## 2) وحدة الأرشفة في Google Drive
- إنشاء المجلد الرئيسي (Root Archive Folder) في Drive إن لم يكن موجوداً.
- إعادة بناء الهيكل الشجري الكامل كما هو في cPanel (نفس أسماء المجلدات والتسلسل الهرمي).
- كشف **الملفات المكررة** باستخدام:
  - مطابقة SHA-256 Hash (أولوية أولى).
  - مطابقة (اسم الملف + الحجم + تاريخ التعديل) كاحتياط.
- إدارة الإصدارات (Versioning):
  - إذا وُجد ملف بنفس الاسم وبمحتوى مختلف → إضافة لاحقة زمنية `_v{timestamp}` أو استخدام Drive File Revisions API.
  - الاحتفاظ بسجل إصدارات في ورقة Google Sheet مرتبطة.

## 3) واجهة الإعدادات (Settings UI)
ابنِ واجهة ويب عبر `HtmlService` (Web App) تحتوي على:

| الحقل | النوع | الوصف |
|---|---|---|
| `SOURCE_DELETE_MODE` | Toggle (احتفاظ / حذف) | حذف الملفات من المصدر بعد النقل الناجح أم الإبقاء عليها |
| `ROOT_DRIVE_FOLDER_ID` | Text + Folder Picker | معرّف المجلد الرئيسي في Drive |
| `CPANEL_HOST` | Text | عنوان خادم cPanel (مثل: example.com:2083) |
| `CPANEL_USERNAME` | Text | اسم المستخدم |
| `CPANEL_API_TOKEN` | Password (مشفّر) | رمز API الخاص بـ cPanel |
| `CPANEL_SOURCE_PATH` | Text | المسار المصدر في cPanel (مثل: /home/user/public_html/uploads) |
| `GCP_API_KEY` | Password (اختياري) | مفتاح Google Cloud Console إن لزم (عند استخدام Advanced Services) |
| `SCHEDULE_FREQUENCY` | Dropdown | (كل ساعة / يومي / أسبوعي / مخصّص Cron-like) |
| `SCHEDULE_TIME` | Time Picker | الوقت المحدد للتشغيل |
| `NOTIFICATION_EMAIL` | Email | البريد المستقبل للإشعارات |
| `DAILY_REPORT_ENABLED` | Toggle | تفعيل التقارير اليومية |
| `ALERT_ON_FAILURE` | Toggle | إرسال تنبيه فوري عند فشل أي عملية |
| `MAX_RETRIES` | Number (1–10) | عدد محاولات إعادة التنفيذ قبل الفشل |
| `BANDWIDTH_LIMIT_MB` | Number | حدّ الاستهلاك لكل جلسة لمنع Quota Exhaustion |
| `FILE_TYPE_FILTER` | Multi-select | فلترة بامتدادات معينة (اختياري) |
| `ARCHIVE_STATUS_DISPLAY` | Read-only | يعرض (نشط / متوقف / قيد التنفيذ / خطأ) |

- استخدم `PropertiesService.getScriptProperties()` لتخزين الإعدادات، و **لا تخزّن** كلمات المرور كنص صريح — استخدم Google KMS أو على الأقل تشفير AES قبل التخزين.
- صمّم الواجهة بـ Material Design 3 (Google Material Components) مع دعم RTL كامل للعربية.

## 4) الجدولة والتشغيل
- استخدم `ScriptApp.newTrigger()` لإنشاء Time-driven Triggers ديناميكياً حسب إعدادات المستخدم.
- احذف الـ Triggers القديمة عند تحديث الجدولة لمنع التكرار.
- أضف **Lock Service** (`LockService.getScriptLock()`) لمنع تشغيل أكثر من عملية أرشفة متوازية.
- طبّق **Execution Time Limit Awareness**: Apps Script محدود بـ 6 دقائق للاستدعاء العادي و 30 دقيقة لـ Workspace Accounts — يجب تقسيم العمل على دفعات (Batching) مع حفظ Checkpoint في Properties Service للاستئناف.

## 5) نظام السجلات والإبلاغ
- أنشئ ورقة Google Sheet مخصصة تلقائياً عند أول تشغيل، تحوي الأعمدة التالية:
  `Timestamp | FileName | SourcePath | DrivePath | SizeBytes | SHA256 | Status | DurationMs | ErrorMessage | RetryCount | ActionTaken`
- حالات (Status): `SUCCESS | SKIPPED_DUPLICATE | VERSIONED | FAILED | PENDING_MANUAL | CHECKSUM_MISMATCH`.
- اكتب Logger.log بالتوازي مع Stackdriver (Cloud Logging) للمراقبة المتقدمة.

## 6) الإشعارات عبر البريد
استخدم `MailApp.sendEmail()` أو `GmailApp` لإرسال:
- **تقرير يومي (Daily Digest):** ملخص HTML أنيق يحوي: إجمالي الملفات، الناجحة، الفاشلة، المتكررة، الحجم الإجمالي المؤرشف، الوقت المستغرق، قائمة الملفات التي تتطلب تدخلاً يدوياً مع رابط مباشر لإعادة الأرشفة.
- **تنبيه فوري (Real-time Alert):** عند فشل > X ملف أو انقطاع الاتصال بـ cPanel.
- **تقرير أسبوعي:** اتجاهات، أعلى المجلدات حجماً، توقعات الاستهلاك.

## 7) الموثوقية وتجاوز الأخطاء
- طبّق نمط **Retry with Exponential Backoff** (ابدأ بـ 1 ثانية، ضاعف حتى 60 ثانية، حدّ أقصى MAX_RETRIES).
- **Circuit Breaker**: إذا فشل أكثر من 20 ملف متتالي → أوقف الجلسة وأرسل تنبيه.
- عزل الأخطاء: فشل ملف واحد **يجب ألا** يوقف باقي المهمة.
- سجّل كل ملف فاشل في طابور خاص `pending_manual_queue` قابل لإعادة التشغيل من الواجهة بضغطة زر.
- أضف دالة `retryFailedArchives()` قابلة للاستدعاء يدوياً أو عبر Trigger منفصل.

## 8) الأمان والصلاحيات
- حدّد Scopes في `appsscript.json` بدقة (مبدأ أقل الامتيازات):
  ```
  "https://www.googleapis.com/auth/drive",
  "https://www.googleapis.com/auth/script.external_request",
  "https://www.googleapis.com/auth/script.scriptapp",
  "https://www.googleapis.com/auth/spreadsheets",
  "https://www.googleapis.com/auth/gmail.send",
  "https://www.googleapis.com/auth/script.container.ui"
  ```
- لا تُدرج كلمات السر في الكود أبداً — استخدم `PropertiesService` فقط.
- نفّذ تحقق CSRF للـ Web App عبر State Token.
- لا تقبل استدعاءات `doGet`/`doPost` دون مصادقة المستخدم.

---

# NON-FUNCTIONAL REQUIREMENTS (متطلبات غير وظيفية)

- **الأداء:** معالجة ≥ 500 ملف / جلسة بدون تجاوز الحدود.
- **القابلية للصيانة:** كود معياري (Modular) مقسّم في ملفات منفصلة:
  - `Config.gs` — ثوابت وإعدادات افتراضية
  - `CpanelConnector.gs` — الاتصال بـ cPanel
  - `DriveArchiver.gs` — كتابة وإدارة Drive
  - `Scheduler.gs` — إدارة الـ Triggers
  - `Notifier.gs` — الإيميلات
  - `Logger.gs` — السجلات والتقارير
  - `Deduplicator.gs` — كشف التكرار والإصدارات
  - `UI.html` / `Settings.html` / `Dashboard.html` — الواجهات
  - `Utils.gs` — دوال مساعدة (Hashing, Retry, Crypto)
  - `Main.gs` — نقطة الدخول + `doGet`/`doPost`
- **JSDoc كامل** لكل دالة مع `@param` و `@return` و `@throws`.
- **اختبارات وحدة** في ملف `Tests.gs` تستخدم نمط Arrange-Act-Assert.

---

# DELIVERABLES (المخرجات المطلوبة)

أنجز المهمة على خطوات، وأرسل في النهاية:

1. **هيكل المشروع الكامل** (Tree View لكل ملفات .gs و .html).
2. **كود كل ملف منفصلاً** مع JSDoc.
3. **ملف `appsscript.json`** مع Scopes وإعدادات Runtime.
4. **ملف `README.md`** يحوي:
   - وصف المشروع.
   - خطوات النشر خطوة بخطوة (Deployment Guide).
   - متطلبات cPanel (مثلاً: تفعيل API Tokens، إنشاء PHP Bridge إن لزم).
   - استكشاف الأخطاء (Troubleshooting).
   - FAQ.
5. **ملف `CHANGELOG.md`** للتتبع المستقبلي.
6. **Diagram معماري** (ASCII أو Mermaid) يوضح تدفق البيانات: cPanel → GAS → Drive → Sheet → Email.
7. **سيناريوهات اختبار** (Test Cases) تغطي: نجاح، فشل شبكة، ملف مكرر، ملف ضخم، انقطاع منتصف العملية.

---

# CODING STANDARDS

- استخدم `const` و `let` فقط (لا `var`).
- استخدم `async/await` حيث يدعمها V8.
- أسماء متغيرات واضحة بـ camelCase، وثوابت بـ UPPER_SNAKE_CASE.
- لا تتجاوز 80 حرفاً لكل سطر.
- علّق بالعربية على المنطق الحرج، وبالإنجليزية على JSDoc.
- تعامل دائماً مع `try/catch` — لا تترك Promises معلقة.

---

# EXECUTION PLAN (خطة التنفيذ المتوقعة منك)

**المرحلة 1:** تحليل المتطلبات واقتراح المعمارية + Diagram.
**المرحلة 2:** كتابة `Config.gs` و `appsscript.json` و `Utils.gs`.
**المرحلة 3:** كتابة `CpanelConnector.gs` مع اختبار اتصال.
**المرحلة 4:** كتابة `DriveArchiver.gs` + `Deduplicator.gs`.
**المرحلة 5:** كتابة `Scheduler.gs` + `Logger.gs` + `Notifier.gs`.
**المرحلة 6:** بناء الواجهات (HTML + CSS + JS) بـ RTL.
**المرحلة 7:** كتابة `Main.gs` ودوال `doGet/doPost`.
**المرحلة 8:** كتابة الاختبارات والـ README.

قبل البدء بأي مرحلة، **اسألني عن**:
- نوع الاتصال المفضّل بـ cPanel (UAPI / WebDAV / PHP Bridge).
- أقصى حجم ملف متوقع في المصدر.
- هل حساب Google هو Workspace أم شخصي؟ (لتحديد حد التنفيذ 6 أو 30 دقيقة).
- هل سيُستخدم Service Account أم OAuth المستخدم الحالي؟

ابدأ الآن بالمرحلة 1 فقط، ولا تنتقل إلى التالية حتى أوافق.
````

---

## 🧭 ملاحظات الاستخدام للمطوّر

### كيفية استخدام هذه المطالبة في VS Code
1. ثبّت إضافة **Claude Code** أو **Cline** أو **Continue** في VS Code.
2. أنشئ مجلداً جديداً `cpanel-drive-archiver/` وافتحه.
3. أنشئ ملف `.claude/instructions.md` والصق بداخله كامل محتوى الكتلة البرمجية أعلاه (بين علامات ` ```markdown ` ).
4. ابدأ المحادثة بـ: `ابدأ المرحلة 1 من الخطة`.

### بديل للـ Apps Script المحلي
يمكنك استخدام أداة **clasp** (Command Line Apps Script) لربط المشروع المحلي بـ VS Code:
```bash
npm install -g @google/clasp
clasp login
clasp create --type webapp --title "cPanel-Drive-Archiver"
clasp pull
# بعد أن يولّد Claude الكود:
clasp push
```

### نقاط انتباه حرجة
- ⚠️ **حدود Apps Script:** انتبه لحدّ 6 دقائق (حسابات Gmail العادية) أو 30 دقيقة (Workspace) — المطالبة تُلزم Claude بالتعامل معها عبر Checkpointing.
- ⚠️ **cPanel FTP غير مدعوم مباشرةً** في Apps Script، لذا المطالبة تقترح 3 بدائل (UAPI/WebDAV/PHP Bridge).
- ⚠️ **حجم Blob الأقصى ≈ 50 MB** — للملفات الأكبر يجب استخدام Resumable Upload عبر Drive API v3 مباشرةً بـ `UrlFetchApp`.
- 🔐 **كلمات المرور:** لا تُرسلها إلى Claude كنص صريح — اطلب منه كتابة placeholder وأدخلها يدوياً بعد النشر.

---

## 📚 مراجع مفيدة
- [Google Apps Script Documentation](https://developers.google.com/apps-script)
- [cPanel UAPI Reference](https://api.docs.cpanel.net/)
- [Drive API v3](https://developers.google.com/drive/api/v3/reference)
- [clasp — Command Line Apps Script](https://github.com/google/clasp)

---

**المؤلف:** مطالبة مُولَّدة لـ Claude في VS Code
**الإصدار:** 1.0.0
**الترخيص:** استخدم وعدّل بحرية (MIT-style)
