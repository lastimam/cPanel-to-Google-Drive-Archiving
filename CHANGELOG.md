# Changelog

كل التغييرات الجديرة بالذكر في هذا المشروع تُوثَّق هنا.
يتبع التنسيق [Keep a Changelog](https://keepachangelog.com/ar/1.1.0/)
ويلتزم المشروع بـ [Semantic Versioning](https://semver.org/lang/ar/).

## [1.0.0] — 2026-04-17

### إضافة (Added)

**البنية التحتية**
- `Config.gs` — 22 مفتاحاً في ScriptProperties مع تشفير تلقائي للأسرار.
- `Utils.gs` — SHA-256 + HMAC-SHA256-CTR AE + Retry with Backoff +
  TimeBudget + مولّد رموز عشوائي آمن.

**طبقة الاتصال بـ cPanel**
- `CpanelConnector.gs` — `PhpBridgeConnector` مع ping/list/checksum/
  download/downloadRange/delete.
- `bridge/bridge.php` — PHP bridge محمي بـ Bearer token + حماية Path
  Traversal + دعم Range headers + JSON envelope موحد.
- `bridge/.htaccess` — إجبار HTTPS + حجب كل الملفات غير bridge.php +
  ترويسات أمان.

**طبقة Drive**
- `DriveArchiver.gs` — Drive API v3 مع Multipart upload (≤ 5 MB) و
  Resumable upload (> 5 MB) بـ chunks 10 MB. مجلدات شجرية عبر cache.
- `Deduplicator.gs` — فهرس Google Sheet بـ SHA-256 + `buildVersionedName`
  مع دعم امتدادات ودعم dotfiles.

**التنسيق والجدولة**
- `Scheduler.gs` — 4 handlers (scheduled/resume/daily/weekly) + LockService
  + Checkpoint في Drive JSON + إدارة triggers ديناميكية.
- `Logger.gs` — Google Sheet بـ 12 عمود + `logBatch` + `getStatsBetween`
  + `getPendingManual` + singleton pattern.
- `Notifier.gs` — تقارير HTML RTL عربية (Daily/Weekly/Alert) + fallback
  GmailApp → MailApp.
- `ArchiveOrchestrator.gs` — حلقة معالجة كاملة مع TimeBudget + Circuit
  Breaker (20 فشل متتالي) + pending retry paths.

**الواجهة**
- `Index.html` — SPA shell بـ 3 تبويبات RTL.
- `Stylesheet.html` — Material Design 3 tokens + مكوّنات قابلة لإعادة
  الاستخدام (card/btn/input/switch/kpi/data-table/snackbar).
- `Scripts.html` — `gsRun` Promise wrapper + Snackbar + Tabs + formatters.
- `Settings.html` — نموذج 15 حقلاً + password toggle + trigger management.
- `Dashboard.html` — 6 KPI + status chip + روابط للأوراق + manual run.
- `ManualQueue.html` — جدول الطابور مع multi-select + retry all/selected.

**الأمان**
- Web App محدود بـ `access=MYSELF` + `executeAs=USER_DEPLOYING`.
- `assertAuthorized_()` في كل `ui*` كـ defense-in-depth.
- `hash_equals` في bridge لمقارنة Token بوقت ثابت.
- `constantTimeEquals_` لمقارنة MAC.

**النقاط النهائية UI (Main.gs)**
- 12 دالة `ui*`: getConfig, saveConfig, testConnection, testEmail,
  installSchedule, removeAllTriggers, listTriggers, getDashboardStats,
  getPendingManual, retryPending, runNow.
- `include()` helper لـ HTML templating.

**الاختبارات**
- `Tests.gs` — 24 اختبار وحدة AAA تغطي: hashing, AE round-trip, MAC
  tampering detection, constant-time compare, retry/backoff, TimeBudget,
  formatters, versioning, filtering.

**التوثيق**
- README.md شامل (بنية + معمارية + نشر + استكشاف أخطاء + FAQ).
- CHANGELOG.md (هذا الملف).
- LICENSE (MIT).
- `.gitignore` للمشروع.
