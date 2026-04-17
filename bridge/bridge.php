<?php
/**
 * cPanel ⇄ Google Drive Archiver — PHP Bridge
 *
 * مسار الرفع المقترح في cPanel:
 *   /home/USER/public_html/.archiver-bridge/bridge.php
 * ثم احمِ المجلد بـ .htaccess (HTTPS فقط + deny except index).
 *
 * خطوات التثبيت:
 *   1) عدّل ثابت BRIDGE_SECRET أدناه إلى سلسلة عشوائية طويلة (≥ 32 حرف).
 *   2) عدّل ALLOWED_ROOT إلى المسار الفعلي الذي تريد أرشفته.
 *   3) ارفع الملف عبر File Manager أو FTP.
 *   4) تأكد من أن نسخة PHP ≥ 7.4 (Software → Select PHP Version).
 *   5) نفّذ في المتصفح: https://DOMAIN/PATH/bridge.php?action=ping
 *      مع Header: Authorization: Bearer YOUR_SECRET
 *
 * ⚠️ أمان: لا تُودِع هذا الملف في Git وهو يحوي السر الفعلي.
 *    استخدم bridge.php.example أو ضع BRIDGE_SECRET في ملف خارجي مُحمّى.
 *
 * المسارات المدعومة:
 *   GET  ?action=ping                            → فحص اتصال
 *   GET  ?action=list&path=REL&recursive=0|1     → قائمة ملفات
 *   GET  ?action=checksum&path=REL               → SHA-256 + حجم + mtime
 *   GET  ?action=download&path=REL               → تحميل (يدعم Range)
 *   POST ?action=delete  body={"path":"REL"}    → حذف ملف
 */

declare(strict_types=1);

// =============================================================
// 1) الإعدادات (عدّل قبل الرفع)
// =============================================================

/** السر المشترك مع إعدادات Apps Script — غيّره. */
const BRIDGE_SECRET = 'REPLACE_WITH_A_LONG_RANDOM_SECRET_AT_LEAST_32_CHARS';

/** الجذر المسموح للأرشفة — كل عملية تُقيَّد داخله. */
const ALLOWED_ROOT = '/home/REPLACE_USER/public_html/uploads';

/** حجم البفر لقراءة/إرسال الملفات (1 MB). */
const READ_BUFFER = 1048576;

/** حد أقصى لعدد المدخلات في استجابة list (حماية ضد DoS). */
const MAX_LIST_ENTRIES = 10000;

// =============================================================
// 2) أدوات مساعدة (Helpers)
// =============================================================

/**
 * إرجاع استجابة JSON موحدة ثم الخروج.
 * @param array<string,mixed> $payload
 */
function jsonOut(array $payload, int $httpCode = 200): void {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * استخراج Bearer token من الترويسة، مع دعم عدة بيئات تشغيل.
 */
function getBearerToken(): ?string {
    $auth = '';
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
    }
    if ($auth === '') {
        $auth = $_SERVER['HTTP_AUTHORIZATION']
             ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
             ?? '';
    }
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return null;
}

/**
 * فرض المصادقة — مقارنة السر بوقت ثابت.
 */
function requireAuth(): void {
    $token = getBearerToken();
    if ($token === null || !hash_equals(BRIDGE_SECRET, $token)) {
        jsonOut(['ok' => false, 'error' => 'unauthorized'], 401);
    }
    if (BRIDGE_SECRET === 'REPLACE_WITH_A_LONG_RANDOM_SECRET_AT_LEAST_32_CHARS'
        || BRIDGE_SECRET === '') {
        jsonOut(['ok' => false, 'error' => 'bridge_not_configured'], 500);
    }
}

/**
 * تحقق من أن المسار داخل الجذر المسموح — حماية ضد Path Traversal.
 * يقبل مسار نسبي فارغ (= الجذر).
 */
function resolveSafePath(string $relPath): string {
    $rootReal = realpath(ALLOWED_ROOT);
    if ($rootReal === false) {
        jsonOut(['ok' => false, 'error' => 'allowed_root_missing'], 500);
    }
    $candidate = $relPath === ''
        ? $rootReal
        : $rootReal . '/' . ltrim($relPath, '/');

    // للعمليات التي قد تُستدعى على عنصر غير موجود، نعالج realpath(false)
    $real = realpath($candidate);
    if ($real === false) {
        // نحاول التحقق من أن المجلد الأب داخل الجذر (لحالات مثل delete
        // على ملف مفقود لنرجع not_found بدل forbidden)
        $parent = realpath(dirname($candidate));
        if ($parent === false || strpos($parent, $rootReal) !== 0) {
            jsonOut(['ok' => false, 'error' => 'path_forbidden'], 403);
        }
        return $candidate;
    }

    // المقارنة الحاسمة: المسار الحقيقي يجب أن يبدأ بالجذر الحقيقي
    if (strpos($real . DIRECTORY_SEPARATOR,
               $rootReal . DIRECTORY_SEPARATOR) !== 0
        && $real !== $rootReal) {
        jsonOut(['ok' => false, 'error' => 'path_forbidden'], 403);
    }
    return $real;
}

/**
 * تحويل المسار المطلق إلى مسار نسبي بالنسبة للجذر.
 */
function toRelPath(string $absPath): string {
    $rootReal = realpath(ALLOWED_ROOT);
    if ($rootReal === false) return $absPath;
    if (strpos($absPath, $rootReal) === 0) {
        return ltrim(substr($absPath, strlen($rootReal)), '/');
    }
    return $absPath;
}

// =============================================================
// 3) الإجراءات (Actions)
// =============================================================

function actionPing(): void {
    jsonOut(['ok' => true, 'data' => [
        'pong'    => true,
        'root'    => ALLOWED_ROOT,
        'time'    => time(),
        'php'     => PHP_VERSION,
        'version' => '1.0.0',
    ]]);
}

function actionList(): void {
    $rel       = $_GET['path'] ?? '';
    $recursive = !empty($_GET['recursive']);
    $abs = resolveSafePath($rel);

    if (!is_dir($abs)) {
        jsonOut(['ok' => false, 'error' => 'not_a_directory'], 400);
    }

    try {
        $iter = $recursive
            ? new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $abs, FilesystemIterator::SKIP_DOTS))
            : new FilesystemIterator($abs, FilesystemIterator::SKIP_DOTS);
    } catch (Throwable $e) {
        jsonOut(['ok' => false, 'error' => 'iterator_failed: ' .
                 $e->getMessage()], 500);
    }

    $out = [];
    $truncated = false;
    foreach ($iter as $f) {
        if (count($out) >= MAX_LIST_ENTRIES) {
            $truncated = true;
            break;
        }
        /** @var SplFileInfo $f */
        $absPath = $f->getPathname();
        $out[] = [
            'path'    => $absPath,
            'relPath' => toRelPath($absPath),
            'name'    => $f->getFilename(),
            'size'    => $f->isFile() ? $f->getSize() : 0,
            'mtime'   => $f->getMTime(),
            'type'    => $f->isDir() ? 'dir' : 'file',
        ];
    }

    jsonOut([
        'ok'        => true,
        'data'      => $out,
        'truncated' => $truncated,
        'count'     => count($out),
    ]);
}

function actionChecksum(): void {
    $rel = $_GET['path'] ?? '';
    $abs = resolveSafePath($rel);
    if (!is_file($abs)) {
        jsonOut(['ok' => false, 'error' => 'not_a_file'], 404);
    }
    $hash = hash_file('sha256', $abs);
    if ($hash === false) {
        jsonOut(['ok' => false, 'error' => 'hash_failed'], 500);
    }
    jsonOut(['ok' => true, 'data' => [
        'sha256' => $hash,
        'size'   => filesize($abs),
        'mtime'  => filemtime($abs),
    ]]);
}

function actionDownload(): void {
    $rel = $_GET['path'] ?? '';
    $abs = resolveSafePath($rel);
    if (!is_file($abs)) {
        jsonOut(['ok' => false, 'error' => 'not_a_file'], 404);
    }
    $size  = filesize($abs);
    $start = 0;
    $end   = $size - 1;
    $partial = false;

    // دعم Range header للتحميل المجزأ
    if (isset($_SERVER['HTTP_RANGE']) &&
        preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = (int)$m[1];
        if ($m[2] !== '') $end = (int)$m[2];
        if ($end >= $size) $end = $size - 1;
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $partial = true;
    }

    $len = $end - $start + 1;
    http_response_code($partial ? 206 : 200);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $len);
    header('Accept-Ranges: bytes');
    header('Content-Disposition: attachment; filename="' .
           basename($abs) . '"');
    if ($partial) {
        header("Content-Range: bytes $start-$end/$size");
    }

    // منع أي مخزن مؤقت يبتلع البيانات قبل الإرسال
    if (ob_get_level() > 0) @ob_end_clean();
    set_time_limit(0);

    $fp = fopen($abs, 'rb');
    if ($fp === false) exit;
    if ($start > 0) fseek($fp, $start);
    $remaining = $len;
    while ($remaining > 0 && !feof($fp)) {
        $read = (int)min(READ_BUFFER, $remaining);
        $chunk = fread($fp, $read);
        if ($chunk === false) break;
        echo $chunk;
        $remaining -= strlen($chunk);
        @flush();
    }
    fclose($fp);
    exit;
}

function actionDelete(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '{}', true);
    if (!is_array($body)) {
        jsonOut(['ok' => false, 'error' => 'bad_json_body'], 400);
    }
    $rel = $body['path'] ?? '';
    if ($rel === '') {
        jsonOut(['ok' => false, 'error' => 'missing_path'], 400);
    }
    $abs = resolveSafePath($rel);
    if (!is_file($abs)) {
        jsonOut(['ok' => false, 'error' => 'not_a_file'], 404);
    }
    if (!@unlink($abs)) {
        jsonOut(['ok' => false, 'error' => 'delete_failed'], 500);
    }
    jsonOut(['ok' => true, 'data' => ['deleted' => toRelPath($abs)]]);
}

// =============================================================
// 4) الموجّه (Router)
// =============================================================

try {
    requireAuth();
    $action = $_GET['action'] ?? 'ping';
    switch ($action) {
        case 'ping':     actionPing();     break;
        case 'list':     actionList();     break;
        case 'checksum': actionChecksum(); break;
        case 'download': actionDownload(); break;
        case 'delete':   actionDelete();   break;
        default:
            jsonOut(['ok' => false, 'error' => 'unknown_action'], 400);
    }
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok'    => false,
        'error' => 'server_error',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
