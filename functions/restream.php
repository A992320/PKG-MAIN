<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  إعادة بثّ Xtream مع تحويل الصوت — المكتبة الأساسية
 * ───────────────────────────────────────────────────────────────────────────
 *  المشكلة: بثّ Xtream يحمل صوت AC3 ولا متصفح يفكّه. الفيديو h264 مدعوم.
 *  فالمطلوب تحويل الصوت وحده — قياسه على خادم حقيقي: 5% من نواة و55MB
 *  للقناة 720p، لأن الفيديو يُنسخ بلا إعادة ترميز.
 *
 *  ═══ التصميم: عملية لكل قناة لا لكل مشاهد ═══
 *
 *  الخطأ الشائع هنا هو تشغيل ffmpeg لكل مشاهد. عند 500 مشاهد يعني ذلك
 *  500 عملية و500 نسخة مسحوبة من المزوّد — مستحيل. الصحيح أن تكتب عملية
 *  واحدة مقاطع HLS إلى القرص، ويقرأها كل مشاهدي القناة عبر Apache
 *  كملفات ساكنة. 500 مشاهد على 20 قناة = 20 عملية = نواة واحدة.
 *
 *  المقاطع تُكتب في /dev/shm (ذاكرة) لا على القرص: بثّ حيّ يكتب ويحذف
 *  آلاف الملفات في الساعة، وقرص SSD يتآكل بذلك بلا داعٍ.
 *
 *  ⚠ القيد الحقيقي هو النطاق الترددي لا المعالج. الوسيط ينقل نطاق كل
 *    المشاهدين إلى خادمك بينما هو الآن على مزوّد Xtream.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (defined('RESTREAM_LIB')) return;
define('RESTREAM_LIB', '1.0.0');

/* ── الإعدادات (تُقرأ من .env) ── */
function rsCfg(string $k, $d = null) {
    $v = function_exists('env') ? env($k, $d) : ($_SERVER[$k] ?? $d);
    return ($v === null || $v === '') ? $d : $v;
}

/**
 * سجلّ تشخيص إعادة البث. لا نكتب رابط المصدر كاملاً لأن بعض مزوّدي IPTV
 * يضعون بيانات الدخول داخله؛ نحتفظ باسم المضيف وسبب الفشل فقط.
 */
function rsLog(string $message, array $context = []): void
{
    if (function_exists('logTo')) {
        logTo('restream', $message, $context);
    }
}

function rsSourceHost(string $url): string
{
    $host = (string)(parse_url($url, PHP_URL_HOST) ?: 'unknown');
    return preg_replace('/[^a-zA-Z0-9.:-]/', '', $host) ?: 'unknown';
}

function rsSafeDetail(string $text, int $limit = 500): string
{
    /* ffmpeg يطبع رابط الإدخال أحياناً، وقد يتضمن اسم مستخدم أو كلمة مرور. */
    $text = preg_replace_callback('~https?://[^\s\'\"]+~i', static function (array $m): string {
        return '[source:' . rsSourceHost($m[0]) . ']';
    }, $text) ?? $text;
    return mb_substr(trim($text), 0, $limit);
}

/** جذر مقاطع HLS — ذاكرة إن توفّرت، وإلا storage. */
function rsRoot(): string {
    static $r = null;
    if ($r !== null) return $r;
    $pref = (string)rsCfg('RESTREAM_DIR', '');
    if ($pref !== '') return $r = rtrim($pref, '/');
    if (is_dir('/dev/shm') && is_writable('/dev/shm')) return $r = '/dev/shm/shs_hls';
    return $r = dirname(__DIR__) . '/storage/hls';
}

/* حدّ القنوات المتزامنة.
   يُقرأ من قاعدة البيانات أوّلاً (يضبطه المدير من اللوحة بلا طرفية)،
   ثم من ملف الإعدادات (RESTREAM_MAX_CHANNELS الذي يضعه setup_restream.sh)،
   وإلا 25. نفس نمط restream_enabled — فيتغيّر فوراً من الويب.

   القيد الحقيقي على هذا الرقم هو نطاق المدير الترددي، لا معالجه — كما
   يقول تعليق أعلى الملف وتنبيه الواجهة نفسه. لذلك لا نفرض هنا أي سقف
   مبني على وجود NVENC من عدمه: بعد إصلاح استهلاك المعالج (نسخ الفيديو
   الافتراضي بلا إعادة ترميز)، غالبية القنوات على مسار CPU لا تستهلك
   معالجاً يُذكر أصلاً، وسقف "قناتان فقط بلا NVENC" كان يتجاهل ذلك
   ويُصادِر قيمة المدير المحفوظة بصمت — فيحفظ 50 مثلاً ويُطبَّق 2،
   وهذا هو عطل "لا يمكنني تحديد الكمية" المُبلَّغ عنه. يبقى سقف عقلاني
   واحد يمنع رقماً كارثياً بالخطأ (RS_MAX_CHANNELS_CEILING)، مطبَّق هنا
   وفي rsSetMaxChannels() معاً كي لا يتناقض ما يُحفظ مع ما يُقرأ. */
function rsMaxChannelsCeiling(): int { return 100000; }

/** يستخدم NVENC إن كان متاحاً؛ وإلا libx264 على المعالج. لا علاقة له بحدّ القنوات. */
function rsVideoEngine(): string {
    static $engine = null;
    if ($engine !== null) return $engine;
    if (!function_exists('shell_exec')) return $engine = 'cpu';

    $encoders = (string)@shell_exec('ffmpeg -hide_banner -encoders 2>/dev/null');
    $gpu = (string)@shell_exec('nvidia-smi -L 2>/dev/null');
    return $engine = ($gpu !== '' && stripos($encoders, 'h264_nvenc') !== false) ? 'nvenc' : 'cpu';
}

function rsMaxChannels(): int {
    try {
        if (function_exists('db') && db()) {
            $st = db()->prepare("SELECT setting_value FROM settings WHERE setting_key='restream_max_channels' LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v !== false && (int)$v > 0) return max(1, min(rsMaxChannelsCeiling(), (int)$v));
        }
    } catch (Throwable $e) { /* نسقط للاحتياطي */ }
    return max(1, min(rsMaxChannelsCeiling(), (int)rsCfg('RESTREAM_MAX_CHANNELS', 25)));
}

/** ضبط حدّ القنوات من اللوحة. يكتب في settings فيُقرأ فوراً. */
function rsSetMaxChannels(int $n): bool {
    $n = max(1, min(rsMaxChannelsCeiling(), $n));   // نفس سقف rsMaxChannels() تماماً — لا فرق بين ما يُحفظ وما يُطبَّق
    try {
        $pdo = db();
        /* لا نفترض وجود عمود id: بعض النسخ القديمة من جدول settings
           تحتوي setting_key و setting_value فقط. التحديث أولاً يعمل مع
           جميع هذه المخططات، ثم نضيف المفتاح عندما لا يكون موجوداً. */
        $u = $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
        $u->execute([(string)$n, 'restream_max_channels']);
        if ($u->rowCount() > 0) return true;

        return $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?)")
                   ->execute(['restream_max_channels', (string)$n]);
    } catch (Throwable $e) {
        error_log('rsSetMaxChannels: ' . $e->getMessage());
        return false;
    }
}

/* ═══ سقف جودة إعادة الترميز (480/720/1080/4K) ═══
   "auto" (الافتراضي) يبقي السلوك القديم حرفياً: نسخ الفيديو حين يكون
   المصدر متوافقاً بلا إعادة ترميز، وإن لزمت إعادة الترميز لسبب آخر
   (توافق/فحص/علم دائم) فسقف 1280px القديم كما هو. اختيار جودة محدّدة
   يعني أن المدير يريد ضمان ألا تتجاوز القناة هذه الدقة/المعدّل مهما كان
   المصدر — وهذا يتطلب إعادة ترميز فعلية (لا يمكن تصغير الدقة بالنسخ)،
   فتُفرض عندها حتى لو كان المصدر متوافقاً أصلاً. الكلفة معالج إضافية
   لكل قناة كهذه، وهي اختيار المدير الواعي لا افتراضاً صامتاً. */
function rsRestreamQuality(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $allowed = ['auto', '480', '720', '1080', '4k'];
    $q = '';
    try {
        if (function_exists('db') && db()) {
            $st = db()->prepare("SELECT setting_value FROM settings WHERE setting_key='restream_quality' LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v !== false) $q = (string)$v;
        }
    } catch (Throwable $e) { /* نسقط للاحتياطي */ }
    if ($q === '') $q = (string)rsCfg('RESTREAM_QUALITY', 'auto');
    return $cached = in_array($q, $allowed, true) ? $q : 'auto';
}

/** ضبط سقف الجودة من اللوحة. يكتب في settings فيُقرأ فوراً. */
function rsSetRestreamQuality(string $q): bool {
    $allowed = ['auto', '480', '720', '1080', '4k'];
    if (!in_array($q, $allowed, true)) return false;
    try {
        $pdo = db();
        $u = $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
        $u->execute([$q, 'restream_quality']);
        if ($u->rowCount() > 0) return true;

        return $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?)")
                   ->execute(['restream_quality', $q]);
    } catch (Throwable $e) {
        error_log('rsSetRestreamQuality: ' . $e->getMessage());
        return false;
    }
}

/** الارتفاع الذي يجب ألا يتجاوزه المصدر بلا إعادة ترميز، أو null لعدم فرض سقف (auto). */
function rsQualityTargetHeight(string $quality): ?int {
    switch ($quality) {
        case '480':  return 480;
        case '720':  return 720;
        case '1080': return 1080;
        case '4k':   return 2160;
        default:     return null; // auto: لا سقف جودة إضافي — القرار القديم وحده يحكم
    }
}

/**
 * ملف الترميز (عرض الفيديو الأقصى + المعدّل الأقصى) حسب الجودة والمحرّك.
 * قيم "auto" هنا تُطابق حرفياً القيم الثابتة القديمة في rsStart() قبل
 * إضافة هذا الإعداد — من لم يغيّر شيئاً في اللوحة لا يتغيّر عنده شيء.
 */
function rsQualityProfile(string $quality, string $engine): array {
    if ($engine === 'nvenc') {
        switch ($quality) {
            case '480':  return ['w' => 854,  'br' => '1500k',  'buf' => '750k'];
            case '720':  return ['w' => 1280, 'br' => '5000k',  'buf' => '2500k'];
            case '1080': return ['w' => 1920, 'br' => '8000k',  'buf' => '4000k'];
            case '4k':   return ['w' => 3840, 'br' => '20000k', 'buf' => '10000k'];
            default:     return ['w' => null, 'br' => '5000k',  'buf' => '2500k']; // auto = السلوك القديم بلا أي تغيير
        }
    }
    switch ($quality) {
        case '480':  return ['w' => 854,  'maxrate' => '1200k',  'buf' => '2400k'];
        case '720':  return ['w' => 1280, 'maxrate' => '3200k',  'buf' => '1600k'];
        case '1080': return ['w' => 1920, 'maxrate' => '5000k',  'buf' => '2500k'];
        case '4k':   return ['w' => 3840, 'maxrate' => '14000k', 'buf' => '7000k'];
        default:     return ['w' => 1280, 'maxrate' => '3200k',  'buf' => '1600k']; // auto = السلوك القديم بلا أي تغيير
    }
}

/**
 * فحص خفيف لارتفاع الفيديو فقط (بلا مقاطع مفاتيح) — يخدم قرار سقف
 * الجودة حصراً. مستقل تماماً عن rsVideoNeedsTranscode() المُختبَرة
 * لقرار النسخ/الترميز الأصلي كي لا نغيّر تلك الدالة المُثبَتة.
 */
function rsProbeVideoHeight(string $url): int {
    static $memo = [];
    if (isset($memo[$url])) return $memo[$url];
    $timeout = is_executable('/usr/bin/timeout') ? '/usr/bin/timeout 8 ' :
               (is_executable('/bin/timeout') ? '/bin/timeout 8 ' : '');
    $cmd = $timeout . 'ffprobe -v error -select_streams v:0'
         . ' -analyzeduration 1000000 -probesize 1000000 -rw_timeout 6000000'
         . ' -user_agent ' . escapeshellarg('VLC/3.0.20 LibVLC/3.0.20')
         . ' -show_entries stream=height'
         . ' -of json ' . escapeshellarg($url) . ' 2>/dev/null';
    $raw = @shell_exec($cmd);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $h = (is_array($data) && !empty($data['streams'][0]['height'])) ? (int)$data['streams'][0]['height'] : 0;
    return $memo[$url] = $h;
}

/** القناة غير المشاهدة لا تبقى تسحب المصدر أو المعالج طويلاً. */
function rsIdleTimeout(): int {
    return max(20, min(1800, (int)rsCfg('RESTREAM_IDLE', 60)));
}
/* لا ينبغي لمصدر حي صالح أن يبقى أكثر من 20 ثانية بلا قائمة HLS أو مقطع.
   هذا حد بدء، وليس حد خمول المشاهدين. */
function rsStartupTimeout(): int { return max(12, (int)rsCfg('RESTREAM_STARTUP_TIMEOUT', 20)); }

/**
 * هل الوسيط مفعّل؟
 *
 * الأولوية لجدول settings لا لملف .env: المفتاح في لوحة الإدارة يجب أن
 * يعمل فوراً وبلا صلاحية كتابة على .env. و.env يبقى المرجع عند غياب
 * الإعداد من قاعدة البيانات، فلا ينكسر تركيب قائم لم يمرّ باللوحة.
 *
 * ملاحظة: RESTREAM_ENABLED=0 في .env يعطّل الوسيط نهائياً مهما قال
 * الجدول — مفتاح أمان يعمل حتى لو تعطّلت قاعدة البيانات.
 */
function rsEnabled(): bool
{
    static $cached = null;
    if ($cached !== null) return $cached;

    /* القفل الصلب: يتجاوز قاعدة البيانات ولوحة الإدارة معاً.
       يضعه setup_restream.sh --off، وهو مخرج الطوارئ حين لا تعمل اللوحة
       أو حين تريد إيقافاً مؤكّداً من الطرفية لا يستطيع أحد نقضه من الويب. */
    if ((string)rsCfg('RESTREAM_HARD_OFF', '0') === '1') {
        return $cached = false;
    }

    try {
        if (function_exists('db') && db()) {
            $st = db()->prepare("SELECT setting_value FROM settings WHERE setting_key='restream_enabled' LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null) return $cached = ((string)$v === '1');
        }
    } catch (Throwable $e) { /* نرتدّ إلى .env */ }

    return $cached = ((string)rsCfg('RESTREAM_ENABLED', '0') === '1');
}

/** يكتب حالة التفعيل في قاعدة البيانات. */
function rsSetEnabled(bool $on): bool
{
    try {
        $pdo = db();
        /* توافق مع جداول settings القديمة التي لا تحتوي عمود id. */
        $u = $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
        $u->execute([$on ? '1' : '0', 'restream_enabled']);
        if ($u->rowCount() > 0) {
            $ok = true;
        } else {
            $i = $pdo->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?)");
            $ok = $i->execute(['restream_enabled', $on ? '1' : '0']);
        }
        // عند الإطفاء نُنهي كل ما يعمل فوراً بدل انتظار المنظّف
        if ($ok && !$on) rsStopAll();
        
        // إفراغ الكاش لتحديث حالة المشغل في الواجهة فوراً
        if (function_exists('cacheDelete')) {
            cacheDelete('site_settings');
        } elseif (function_exists('settingsCacheBust')) {
            settingsCacheBust();
        }
        
        return $ok;
    } catch (Throwable $e) {
        if (function_exists('logTo')) logTo('error', 'rsSetEnabled: ' . $e->getMessage());
        return false;
    }
}

/** يُنهي كل عمليات إعادة البثّ. */
function rsStopAll(): int
{
    $n = 0;
    $dirs = [];
    foreach (rsAllRoots() as $root) {
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $d) $dirs[] = $d;
    }
    foreach ($dirs as $dir) {
        $pidF = $dir . '/.pid';
        $pid = is_file($pidF) ? (int)@file_get_contents($pidF) : 0;
        if ($pid > 1 && rsAlive($pid)) {
            if (function_exists('posix_kill')) @posix_kill($pid, 15); else @exec('kill ' . $pid . ' 2>/dev/null');
            for ($i = 0; $i < 10 && rsAlive($pid); $i++) usleep(100000);
            if (rsAlive($pid)) {
                if (function_exists('posix_kill')) @posix_kill($pid, 9); else @exec('kill -9 ' . $pid . ' 2>/dev/null');
            }
            $n++;
        }
        foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
        foreach (glob($dir . '/.*') ?: [] as $f) if (!is_dir($f)) @unlink($f);
        @rmdir($dir);
    }
    return $n;
}
function rsSecret(): string {
    $s = (string)rsCfg('RESTREAM_SECRET', '');
    if ($s !== '') return $s;
    // نشتقّه من مفتاح التطبيق بدل توليد مفتاح جديد كل طلب
    $f = dirname(__DIR__) . '/storage/.appkey';
    return is_file($f) ? hash('sha256', 'restream|' . (string)@file_get_contents($f)) : 'shs-restream-fallback';
}

/**
 * مفتاح البثّ — يميّز النوع لا الرقم وحده.
 *
 * محتوى Xtream موزّع على جدولين: القنوات المباشرة في `channels`،
 * والأفلام والمسلسلات في `episodes`. والرقم 5 قد يوجد في الاثنين
 * لصفّين مختلفين تماماً. لولا البادئة لتصادما على نفس المجلد فأعطى
 * أحدهما بثّ الآخر — عطل يصعب تفسيره لأن كل شيء "يعمل".
 *
 * @param string $kind 'c' لقناة أو 'e' لحلقة/فيلم
 */
function rsKey(string $kind, int $id): string {
    return ($kind === 'e' ? 'e' : 'c') . $id;
}

/**
 * اسم مجلد البثّ — غير قابل للتخمين.
 * لو استُخدم الرقم مباشرةً لأمكن تصفّح /hls/1/ و/hls/2/ والوصول إلى
 * كل شيء بلا اشتراك. الاشتقاق بـ HMAC يمنع ذلك.
 */
function rsSlug(string $key): string {
    return substr(hash_hmac('sha256', 'stream:' . $key, rsSecret()), 0, 24);
}

/**
 * هل هذا محتوى مسجَّل (فيلم/حلقة) لا بثّاً حيّاً؟
 * المفتاح يبدأ بـ e للحلقات، وهي دائماً VOD.
 */
function rsIsVod(string $key): bool { return isset($key[0]) && $key[0] === 'e'; }

/**
 * جذر تخزين المقاطع.
 *
 * البثّ الحيّ يذهب إلى الذاكرة: ست مقاطع متدحرجة لا تتجاوز 2MB، وتُحذف
 * خلف المشاهد باستمرار. أما الفيلم فيحتفظ بكل مقاطعه ليستطيع المشاهد
 * الإرجاع — ساعتان بمعدّل 2 ميغابت تساوي 1.8 غيغابايت، وذلك يملأ
 * /dev/shm بفيلم واحد ويُسقط الخادم. الأفلام على القرص إذن.
 */
function rsRootFor(string $key): string
{
    if (!rsIsVod($key)) return rsRoot();
    $d = (string)rsCfg('RESTREAM_VOD_DIR', '');
    return $d !== '' ? rtrim($d, '/') : dirname(__DIR__) . '/storage/vod';
}

/** حصّة القرص القصوى لكل الأفلام مجتمعةً (بالميغابايت). */
function rsVodQuotaMb(): int { return max(512, (int)rsCfg('RESTREAM_VOD_QUOTA_MB', 20480)); }

/** مهلة خمول الأفلام — أقصر من البثّ الحيّ لأنها تشغل قرصاً لا ذاكرة. */
function rsVodIdle(): int { return max(30, (int)rsCfg('RESTREAM_VOD_IDLE', 180)); }

/** الحجم الكلي لمجلد الأفلام بالبايت. */
function rsVodUsedBytes(): int
{
    $root = rsRootFor('e0');
    if (!is_dir($root)) return 0;
    $n = 0;
    foreach (glob($root . '/*/*.{ts,m4s}', GLOB_BRACE) ?: [] as $f) $n += (int)@filesize($f);
    return $n;
}

/**
 * المسار العام الذي يخدم منه Apache.
 * لكل جذر تخزين اسمُه المستعار: /hls للذاكرة و/vodhls للقرص. لو أعدنا
 * المسار نفسه للاثنين لبحث Apache عن مقاطع الفيلم في مجلد الذاكرة
 * فأعطى 404 — والملفات موجودة لكن في المكان الآخر.
 */
function rsPublicBase(string $key): string {
    $k = rsIsVod($key) ? 'RESTREAM_VOD_PUBLIC' : 'RESTREAM_PUBLIC';
    $d = rsIsVod($key) ? '/vodhls' : '/hls';
    return rtrim((string)rsCfg($k, $d), '/');
}

/** الرابط العام الكامل لقائمة التشغيل. */
function rsPublicUrl(string $key): string {
    return rsPublicBase($key) . '/' . rsSlug($key) . '/index.m3u8';
}

function rsDir(string $key): string     { return rsRootFor($key) . '/' . rsSlug($key); }
function rsIndex(string $key): string   { return rsDir($key) . '/index.m3u8'; }
function rsPidFile(string $key): string { return rsDir($key) . '/.pid'; }

/* تغيير مسار إخراج FFmpeg يجب أن يعيد تشغيل العمليات القديمة مرة واحدة،
   وإلا يبقى المشاهد على عملية تعمل بالإعداد السابق حتى انتهاء الخمول. */
function rsPipelineVersion(): string { return 'hls-browser-fmp4-v8-balanced-fast-start'; }
function rsPipelineFile(string $key): string { return rsDir($key) . '/.pipeline'; }
function rsPipelineCurrent(string $key): bool {
    return trim((string)@file_get_contents(rsPipelineFile($key))) === rsPipelineVersion();
}

/* لا نعلن جاهزية HLS من وجود القائمة وحده: قد تُكتب القائمة قبل أول
   مقطع fMP4 بلحظة، فيحصل المتصفح على 404 ويبدأ دورة إعادة تشغيل وهمية. */
function rsReady(string $key): bool {
    $idx = rsIndex($key);
    if (!is_file($idx) || (int)@filesize($idx) <= 40) return false;
    foreach (glob(rsDir($key) . '/s*.{m4s,ts}', GLOB_BRACE) ?: [] as $seg) {
        if ((int)@filesize($seg) > 0) return true;
    }
    return false;
}
function rsHitFile(string $key): string { return rsDir($key) . '/.hit'; }

/** هل يُسمح لـ PHP بتشغيل أوامر النظام؟ */
function rsCanExec(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    if (!function_exists('shell_exec')) return $ok = false;
    $dis = (string)@ini_get('disable_functions');
    foreach (preg_split('/\s*,\s*/', $dis) ?: [] as $f) {
        if (strcasecmp(trim($f), 'shell_exec') === 0) return $ok = false;
    }
    return $ok = true;
}

/* فحص خفيف للفيديو قبل تشغيل القناة. لا نعيد ترميز H.264/8-bit السليم
   بمفاتيح منتظمة (نسخ الفيديو فقط — القياس الأصلي: 5% من نواة)،
   ونحوّل ما عدا ذلك: HEVC وAV1 وMPEG-2 و10-bit و4K، وأيضاً H.264
   السليم إن كانت مفاتيحه متباعدة أكثر من اللازم — بعض مزوّدي IPTV
   يرسلون مفتاحاً كل 10-15 ثانية فقط، فيتأخر أول مقطع HLS أو يغيب
   تماماً حين نكتفي بالنسخ. تعذّر الفحص نفسه يُحسب "يحتاج تحويلاً":
   الخطأ الآمن هنا هو معالج إضافي لا بثّ معطوب. */
function rsVideoNeedsTranscode(string $url): bool {
    static $memo = [];
    if (isset($memo[$url])) return $memo[$url];

    $timeout = is_executable('/usr/bin/timeout') ? '/usr/bin/timeout 12 ' :
               (is_executable('/bin/timeout') ? '/bin/timeout 12 ' : '');
    $cmd = $timeout . 'ffprobe -v error -select_streams v:0'
         . ' -analyzeduration 3000000 -probesize 2000000 -rw_timeout 8000000'
         . ' -user_agent ' . escapeshellarg('VLC/3.0.20 LibVLC/3.0.20')
         . ' -show_entries stream=codec_name,pix_fmt,width,height:packet=pts_time,dts_time,flags'
         . ' -read_intervals %+12'
         . ' -of json ' . escapeshellarg($url) . ' 2>/dev/null';
    $raw = @shell_exec($cmd);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $stream = is_array($data) && !empty($data['streams'][0]) ? $data['streams'][0] : null;
    if (!is_array($stream) || empty($stream['codec_name'])) {
        return $memo[$url] = true;   // تعذّر الفحص ⇒ الأصل الآمن هو إعادة الترميز
    }

    $codec = strtolower((string)($stream['codec_name'] ?? ''));
    $pix   = strtolower((string)($stream['pix_fmt'] ?? ''));
    $w     = (int)($stream['width'] ?? 0);
    $h     = (int)($stream['height'] ?? 0);

    if ($codec !== 'h264' || ($pix !== '' && $pix !== 'yuv420p') || $w > 1920 || $h > 1080) {
        return $memo[$url] = true;
    }

    /* الحاوية والترميز سليمان — يبقى انتظام المفاتيح: نسخ الفيديو يعني
       أن مقطع HLS لا يُقطع إلا عند مفتاح. فجوة أكبر من 4 ثوانٍ (ضعف
       طول مقطعنا) تجعل أول مقطع أو مقاطع أثناء المشاهدة بطيئة أو غائبة. */
    $packets = is_array($data['packets'] ?? null) ? $data['packets'] : [];
    $keyTimes = [];
    foreach ($packets as $p) {
        if (!is_array($p) || strpos((string)($p['flags'] ?? ''), 'K') === false) continue;
        $t = null;
        if (isset($p['pts_time']) && is_numeric($p['pts_time'])) $t = (float) $p['pts_time'];
        elseif (isset($p['dts_time']) && is_numeric($p['dts_time'])) $t = (float) $p['dts_time'];
        if ($t !== null) $keyTimes[] = $t;
    }
    sort($keyTimes);
    if (count($keyTimes) < 2) {
        // مفتاح واحد أو لا شيء خلال مدة الفحص كلها: لا يكفي لإثبات الانتظام
        // (فجوة 12 ثانية تترك مفتاحاً واحداً فقط في نافذة فحص 12 ثانية) —
        // الأصل الآمن هو إعادة الترميز لا افتراض أن النسخ سيعمل.
        return $memo[$url] = true;
    }
    if ($keyTimes[0] > 4.0) {
        return $memo[$url] = true;   // أول مفتاح متأخر جداً
    }
    for ($i = 1, $c = count($keyTimes); $i < $c; $i++) {
        if (($keyTimes[$i] - $keyTimes[$i - 1]) > 4.0) {
            return $memo[$url] = true;   // فجوة كبيرة بين مفتاحين
        }
    }
    return $memo[$url] = false;
}

/**
 * علم "أعد ترميز هذا المصدر دائماً" — مستقل عن مفتاح القناة (الذي يتغيّر
 * مع كل تعديل صوت)، مربوط برابط المصدر نفسه لأنه الهوية الثابتة الحقيقية.
 * يُكتب فقط بعد أن يثبت نسخ الفيديو فشله فعلياً مع هذا المصدر بالذات —
 * لا بتخمين مسبق. صلاحية محدودة كي يُعاد اختبار مصدر أُصلح لاحقاً من
 * طرف مزوّده تلقائياً بلا تدخّل يدوي.
 */
function rsForceTranscodeDir(): string { return dirname(__DIR__) . '/storage/cache/restream_force'; }
function rsForceTranscodeFile(string $url): string { return rsForceTranscodeDir() . '/' . hash('sha256', $url) . '.flag'; }
function rsForceTranscodeTtlSec(): int { return max(1800, (int)rsCfg('RESTREAM_FORCE_TRANSCODE_TTL', 21600)); }
function rsForceTranscode(string $url): bool {
    $f = rsForceTranscodeFile($url);
    if (!is_file($f)) return false;
    if ((time() - (int)@filemtime($f)) > rsForceTranscodeTtlSec()) { @unlink($f); return false; }
    return true;
}
function rsMarkForceTranscode(string $url): void {
    $d = rsForceTranscodeDir();
    if (!is_dir($d)) @mkdir($d, 0755, true);
    @file_put_contents(rsForceTranscodeFile($url), (string)time(), LOCK_EX);
}
/** هل العملية حيّة؟ */
function rsAlive(int $pid): bool {
    if ($pid < 2) return false;
    // /proc أدقّ من posix_kill لأنه لا يحتاج امتيازات ولا يخطئ مع PID مُعاد استخدامه حديثاً
    if (is_dir('/proc')) return is_dir('/proc/' . $pid);
    if (function_exists('posix_kill')) return @posix_kill($pid, 0);
    @exec('ps -p ' . (int)$pid . ' -o pid= 2>/dev/null', $o);
    return !empty($o);
}

function rsPid(string $key): int {
    $f = rsPidFile($key);
    return is_file($f) ? (int)@file_get_contents($f) : 0;
}

function rsRunning(string $key): bool { return rsAlive(rsPid($key)); }

/** يسجّل أن أحداً طلب هذه القناة الآن (يمنع القاتل الدوري من إنهائها). */
function rsTouch(string $key): void { @touch(rsHitFile($key)); }

/** كل جذور التخزين (حيّ + أفلام) — تُستخدم في العدّ والتنظيف. */
function rsAllRoots(): array {
    $r = [rsRoot()];
    $v = rsRootFor('e0');
    if ($v !== $r[0]) $r[] = $v;
    return $r;
}

/** عدد العمليات العاملة حالياً في الجذرين معاً. */
function rsActiveCount(): int {
    $n = 0;
    foreach (rsAllRoots() as $root) {
        foreach (glob($root . '/*/.pid') ?: [] as $f) {
            if (rsAlive((int)@file_get_contents($f))) $n++;
        }
    }
    return $n;
}

/**
 * يوقف بثّ قناة وينظّف ملفاتها.
 */
function rsStop(string $key): void {
    $pid = rsPid($key);
    if ($pid > 1 && rsAlive($pid)) {
        // SIGTERM أولاً ليُغلق ffmpeg ملفاته بنظافة، ثم SIGKILL عند العناد
        if (function_exists('posix_kill')) @posix_kill($pid, 15); else @exec('kill ' . $pid . ' 2>/dev/null');
        for ($i = 0; $i < 12 && rsAlive($pid); $i++) usleep(120000);
        if (rsAlive($pid)) {
            if (function_exists('posix_kill')) @posix_kill($pid, 9); else @exec('kill -9 ' . $pid . ' 2>/dev/null');
        }
    }
    $d = rsDir($key);
    foreach (glob($d . '/*') ?: [] as $f) @unlink($f);
    foreach (glob($d . '/.*') ?: [] as $f) { if (!is_dir($f)) @unlink($f); }
    @rmdir($d);
}

/**
 * عندما تمتلئ سعة القنوات، نحرر أقدم قناة دافئة لا ترسل نبضة مشاهدة منذ
 * دقيقة ونصف. القنوات التي يشاهدها أحد تستمر نبضتها كل 25 ثانية، لذلك لا
 * نقطع بثاً حياً كي نبدأ قناة جديدة.
 */
function rsReclaimWarmSlot(string $exceptKey = ''): bool {
    $candidate = null;
    $candidateLast = PHP_INT_MAX;
    $now = time();

    foreach (glob(rsRoot() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $key = basename($dir);
        if ($key === '' || $key === $exceptKey) continue;

        $pid = is_file($dir . '/.pid') ? (int)@file_get_contents($dir . '/.pid') : 0;
        if ($pid < 2 || !rsAlive($pid)) continue;

        $last = is_file($dir . '/.hit') ? (int)@filemtime($dir . '/.hit') : 0;
        if ($last <= 0 || ($now - $last) < 90) continue;
        if ($last < $candidateLast) {
            $candidate = $key;
            $candidateLast = $last;
        }
    }

    if ($candidate === null) return false;
    rsStop($candidate);
    rsLog('تحرير قناة دافئة للسعة', ['key' => $candidate]);
    return true;
}

/**
 * يشغّل ffmpeg لقناة إن لم يكن يعمل.
 *
 * @return array{ok:bool,error?:string,started?:bool}
 */
function rsStart(string $key, string $srcUrl, bool $compatVideo = false, string $audioUrl = '', float $audioDelay = 0.0): array {
    if (!rsEnabled()) {
        rsLog('رفض بدء الوسيط: معطّل', ['key' => $key]);
        return ['ok' => false, 'error' => 'disabled'];
    }
    if (!preg_match('~^https?://~i', $srcUrl)) {
        rsLog('رفض بدء الوسيط: رابط مصدر غير مدعوم', ['key' => $key]);
        return ['ok' => false, 'error' => 'bad_url'];
    }
    if ($audioUrl !== '' && !preg_match('~^https?://~i', $audioUrl)) $audioUrl = '';
    $audioDelay = max(-30.0, min(30.0, $audioDelay));
    /* كثير من الاستضافات تعطّل shell_exec في php.ini عبر disable_functions.
       بدون هذا الفحص يُرجع @shell_exec القيمة null فيبدو الأمر كأن ffmpeg
       فشل — فيُبحث عن العلة في الترميز أو المصدر بينما الدالة نفسها ممنوعة.
       خطأ صريح هنا يوفّر ساعات في المكان الخطأ. */
    if (!rsCanExec()) {
        rsLog('فشل بدء الوسيط: shell_exec معطّلة', ['key' => $key]);
        return ['ok' => false, 'error' => 'shell_disabled'];
    }

    /* العملية الموجودة قد تكون نشأت قبل إصلاح مسار TS/HLS. نبدلها
       تلقائياً عند أول مشاهدة، ثم تبقى النسخة الجديدة مشتركة للجميع. */
    if (rsRunning($key) && !rsPipelineCurrent($key)) rsStop($key);
    if (rsRunning($key)) {
        /* بعض المصادر تتأخر في تسليم أول keyframe رغم أنها صالحة. نسجل
           البداية البطيئة مرة واحدة، لكن لا نقتل العملية التي قد تصبح جاهزة
           بعد لحظات؛ قتلها كان سيحوّل التأخير إلى فشل مؤكد. */
        $pidFile = rsPidFile($key);
        $age = is_file($pidFile) ? (time() - (int)@filemtime($pidFile)) : 0;
        $stuckCopy = false;
        if (!rsReady($key) && $age >= rsStartupTimeout()) {
            $detail = is_file(rsDir($key) . '/.log')
                ? rsSafeDetail((string)@file_get_contents(rsDir($key) . '/.log'))
                : 'لم يُنتج المصدر أي مقطع HLS';
            $slowMark = rsDir($key) . '/.slow_start_logged';
            if (!is_file($slowMark)) {
                @file_put_contents($slowMark, (string)time(), LOCK_EX);
                rsLog('تأخر بدء المصدر بلا مقاطع', [
                    'key' => $key,
                    'wait_s' => $age,
                    'detail' => $detail,
                ]);
            }
            /* نسخ الفيديو بلا إعادة ترميز يفترض مفاتيح منتظمة كما تحقّقنا
               قبل البدء؛ مصدر حيّ قد يغيّر ذلك لاحقاً (GOP متغيّر) فتبقى
               العملية تعمل بلا أي مقطع إلى الأبد. مهلة أطول ثلاثة أضعاف،
               ثم إعادة تشغيل بإعادة ترميز حقيقية تُصلح ذلك تلقائياً — ولا
               تتكرر لاحقاً لأن المصدر يُسجَّل ليعاد ترميزه دائماً. */
            if (is_file(rsDir($key) . '/.copyv') && $age >= rsStartupTimeout() * 3) {
                rsLog('نسخ الفيديو عالق بلا مقاطع — التحويل إلى إعادة الترميز', [
                    'key' => $key, 'source_host' => rsSourceHost($srcUrl),
                ]);
                rsMarkForceTranscode($srcUrl);
                rsStop($key);
                $stuckCopy = true;
            }
        }
        if (!$stuckCopy) {
            rsTouch($key);
            return ['ok' => true, 'started' => false, 'pending' => !rsReady($key)];
        }
        // $stuckCopy: نتابع التنفيذ أدناه لبدء عملية جديدة بإعادة الترميز
    }

    // تنظيف بقايا عملية ميتة قبل البدء
    if (is_dir(rsDir($key))) {
        foreach (glob(rsDir($key) . '/*') ?: [] as $f) @unlink($f);
    }

    if (rsActiveCount() >= rsMaxChannels()) {
        // لا نسمح للقنوات الدافئة أن تحجب قناة جديدة عند امتلاء الحد.
        rsReclaimWarmSlot($key);
    }
    if (rsActiveCount() >= rsMaxChannels()) {
        rsLog('فشل بدء الوسيط: بلغ حد القنوات', ['key' => $key, 'limit' => rsMaxChannels()]);
        return ['ok' => false, 'error' => 'capacity'];
    }

    /* حصّة قرص الأفلام.
       البثّ الحيّ يحدّ نفسه بست مقاطع متدحرجة، أما الفيلم فيتراكم حتى
       نهايته. بلا سقف يكفي عشرون مشاهداً لأفلام مختلفة ليمتلئ القرص —
       وامتلاء القرص لا يُعطّل الأفلام وحدها بل يُسقط قاعدة البيانات
       وسجلّات Apache معها. */
    if (rsIsVod($key)) {
        $usedMb = (int)round(rsVodUsedBytes() / 1048576);
        if ($usedMb >= rsVodQuotaMb()) {
            // ننظّف المهجور أولاً ثم نُعيد القياس قبل الرفض
            rsReapIdle();
            $usedMb = (int)round(rsVodUsedBytes() / 1048576);
            if ($usedMb >= rsVodQuotaMb()) {
                return ['ok' => false, 'error' => 'vod_quota',
                        'detail' => $usedMb . 'MB / ' . rsVodQuotaMb() . 'MB'];
            }
        }
    }

    $dir = rsDir($key);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        rsLog('فشل بدء الوسيط: تعذر إنشاء مجلد HLS', ['key' => $key, 'root' => rsRootFor($key)]);
        return ['ok' => false, 'error' => 'mkdir_failed'];
    }

    // قفل يمنع طلبين متزامنين من تشغيل عمليتين للقناة نفسها
    $lock = @fopen($dir . '/.lock', 'c');
    if ($lock === false) {
        rsLog('فشل بدء الوسيط: تعذر قفل مجلد HLS', ['key' => $key]);
        return ['ok' => false, 'error' => 'lock_failed'];
    }
    if (!@flock($lock, LOCK_EX | LOCK_NB)) {
        // طلب آخر يشغّلها الآن — ننتظر ظهور القائمة بدل تكرار العملية
        fclose($lock);
        for ($i = 0; $i < 60; $i++) {
            if (rsReady($key)) return ['ok' => true, 'started' => false];
            usleep(200000);
        }
        rsLog('فشل بدء الوسيط: انتهت مهلة انتظار القفل', ['key' => $key]);
        return ['ok' => false, 'error' => 'start_timeout'];
    }
    if (rsRunning($key) && !rsPipelineCurrent($key)) rsStop($key);
    if (rsRunning($key)) {
        flock($lock, LOCK_UN); fclose($lock); rsTouch($key);
        return ['ok'=>true,'started'=>false,'pending'=>!rsReady($key)];
    }

    $idx = rsIndex($key);
    $log = $dir . '/.log';

    /* ── سقف عمر صلب للعملية ──
       كل آليات الإنهاء عندنا تمرّ بـ PHP: نبضة المشاهد، ثم cron، ثم
       التنظيف الانتهازي. وكلها تفشل معاً في حالة واحدة: تعطّل cron مع
       عدم ورود أي طلب جديد — فتبقى العملية تسحب من المزوّد إلى الأبد
       لأجل لا أحد.

       timeout يجعل العملية تنهي نفسها بلا وسيط. إن كان أحد يشاهد فعلاً
       عند انتهاء المهلة فنبضته تتلقّى 410 ويُعيد المشغّل التشغيل تلقائياً
       — انقطاع ثوانٍ مرة كل 12 ساعة مقابل ضمان ألا تتراكم عمليات يتيمة. */
    $isVod   = rsIsVod($key);
    /* قرار الترميز يُتخذ على الخادم من خصائص المصدر، لا من تخمينات
       JavaScript. يظل H.264/1080p السليم سريعاً بنسخ الفيديو. */
    /* القرار: نسخ الفيديو افتراضياً بلا إعادة ترميز (القياس الأصلي: 5% من
       نواة و55MB لقناة 720p)، ولا نعيد الترميز إلا حين يلزم فعلاً:
       طلب توافق صريح من المشغّل (4K/HEVC)، أو إثبات الفحص أن نسخ هذا
       المصدر سيُنتج بثاً بطيء البداية أو معطوباً (ترميز/دقة غير مدعومة،
       أو مفاتيح متباعدة أكثر من اللازم)، أو إثبات محاولة سابقة فعلية أن
       النسخ لا يعمل مع هذا المصدر تحديداً. حد القنوات في اللوحة يقي
       المعالج من تشغيل عدد كبير في آن واحد على القنوات التي تحتاج فعلاً
       إعادة ترميز. */
    /* سقف جودة اختياري من اللوحة (480/720/1080/4K). "auto" لا يفرض شيئاً
       هنا؛ جودة محدّدة تعني ضمان ألا تتجاوز القناة هذه الدقة، وهذا لا
       يتحقق بالنسخ — فنفرض إعادة الترميز حتى لو كان المصدر متوافقاً،
       وفقط حين يتجاوز المصدر فعلاً السقف المطلوب (فحص الارتفاع أدناه
       لا يُستدعى إطلاقاً في وضع "auto" فلا كلفة إضافية على من لم يغيّر
       الإعداد). */
    $quality      = rsRestreamQuality();
    $qTargetH     = rsQualityTargetHeight($quality);
    $qOverCap     = $qTargetH !== null && rsProbeVideoHeight($srcUrl) > $qTargetH;
    $transcodeVideo = $compatVideo || rsForceTranscode($srcUrl) || rsVideoNeedsTranscode($srcUrl) || $qOverCap;
    $qp = rsQualityProfile($quality, rsVideoEngine());
    $qScale = $qp['w'] !== null ? (' -vf ' . escapeshellarg("scale=w='min({$qp['w']},iw)':h=-2:flags=lanczos")) : '';
    $videoArgs = rsVideoEngine() === 'nvenc'
        ? ' -c:v h264_nvenc -preset p1 -tune ll -rc cbr -b:v ' . $qp['br'] . ' -maxrate ' . $qp['br'] . ' -bufsize ' . $qp['buf'] . $qScale . ' -g 25 -forced-idr 1 -force_key_frames ' . escapeshellarg('expr:gte(t,n_forced*1)')
        : ' -c:v libx264 -threads 1 -preset ultrafast -tune zerolatency -crf 26 -maxrate ' . $qp['maxrate'] . ' -bufsize ' . $qp['buf'] . ' -pix_fmt yuv420p' . $qScale . ' -g 25 -keyint_min 25 -sc_threshold 0 -force_key_frames ' . escapeshellarg('expr:gte(t,n_forced*1)');
    $maxLife = max(600, (int)rsCfg('RESTREAM_MAX_LIFE', 43200));
    $timeout = '';
    // -k يرسل SIGKILL بعد 10 ثوانٍ إن تجاهل ffmpeg إشارة الإنهاء اللطيفة
    if (is_executable('/usr/bin/timeout')) {
        $timeout = '/usr/bin/timeout -k 10 ' . (int)$maxLife . ' ';
    } elseif (is_executable('/bin/timeout')) {
        $timeout = '/bin/timeout -k 10 ' . (int)$maxLife . ' ';
    }

    $cmd = $timeout . 'ffmpeg -hide_banner -loglevel error -nostdin'
         /* HLS/TS القادم من بعض المزودين يحمل PTS ناقصاً أو متقطعاً.
            VLC يتسامح معه، بينما MSE في المتصفح يرفض المقطع بلا صورة. */
         /* نخفض مرحلة تحليل المصدر ونمنع FFmpeg من تجميع عدة ثوانٍ قبل
            تسليم أول حزمة. هذا آمن لبث IPTV الحي ويزيل انتظار التحليل
            الافتراضي الذي كان ظاهرًا للمشاهد كـ«جارٍ تجهيز البث». */
         . ' -analyzeduration 0 -probesize 32768 -fflags +nobuffer+genpts+discardcorrupt'
         // مهلات تمنع تعليق العملية إلى الأبد عند سقوط المصدر
         . ' -rw_timeout 15000000 -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5'
         . ' -user_agent ' . escapeshellarg('VLC/3.0.20 LibVLC/3.0.20')
         . ' -i ' . escapeshellarg($srcUrl)
         /* عند وجود رابط صوت منفصل نجمعه هنا في HLS واحد. هذا أدق من
            تشغيل عنصري video/audio مستقلين في المتصفح، إذ يستخدم FFmpeg
            الطوابع الزمنية ويعيد أخذ عينات الصوت في خط واحد. */
         . ($audioUrl !== ''
             ? ' -itsoffset ' . escapeshellarg(number_format($audioDelay, 3, '.', '')) . ' -rw_timeout 15000000 -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 -i ' . escapeshellarg($audioUrl) . ' -map 0:v:0 -map 1:a:0?'
             : ' -map 0:v:0 -map 0:a:0?')
         /* نسخ H.264/1080p السليم سريع جداً. أما أي فيديو لا يفهمه
            المتصفح فنحوّله هنا مرة واحدة لكل قناة، لا مرة لكل مستخدم. */
         . ($transcodeVideo
             ? $videoArgs
             : ' -c:v copy')
         /* تحويل صوت ثابت للمتصفح: بعض مزوّدي IPTV يرسلون 44.1kHz أو
            طوابع زمنية متقطّعة داخل AC3/EAC3، فتسمع تقطيعاً أو تشويهاً.
            نعيد مزامنة الصوت ونوحّد العيّنة إلى 48kHz قبل AAC stereo. */
         . ' -c:a aac -b:a 192k -ac 2 -ar 48000 -af ' . escapeshellarg('aresample=async=1:min_hard_comp=0.100:first_pts=0')
         /* fMP4 يحوي معلومات البداية في init.mp4 ويعمل مباشرةً مع MSE
            في Chrome/Edge/Safari. المقاطع الأطول تمنح البث الحي هامشاً
            مستقراً عند تأخر المصدر أو كتابة أي مقطع. */
         /* مقطع أول قصير لتقليل زمن فتح القناة؛ بعده تعود المقاطع إلى
            أربع ثوانٍ لاستقرار أفضل واستهلاك أقل تحت الحمل. */
         /* مقاطع قصيرة للبث الحي: أول مقطع يُنشر أسرع، ثم يستمر المشغّل
            ببناء مخزنه أثناء المشاهدة. لا نرّمز الفيديو هنا، لذلك يبقى
            الحمل منخفضاً حتى مع قنوات ومشاهدين متعددين. */
         . ' -f hls -hls_segment_type fmp4 -hls_time 2 -hls_init_time 0.5 -flush_packets 1'
         . ' -hls_fmp4_init_filename init.mp4'
         /* ══ الفارق الجوهري بين الفيلم والبثّ الحيّ ══
            الحيّ: قائمة متدحرجة من ست مقاطع تُحذف خلف المشاهد. لا معنى
                  للإرجاع في بثّ مباشر، والحذف يُبقي الذاكرة ثابتة.
            الفيلم: hls_list_size=0 يبقي كل المقاطع في القائمة، وبلا
                  delete_segments لا يُحذف شيء — فيستطيع المشاهد الإرجاع
                  والتقديم داخل ما أُنتج. ولولا ذلك لكان شريط التقدّم
                  زينةً لا تعمل. playlist_type=event يخبر المشغّل أن
                  المدة تنمو، فلا يعرض Infinity:NaN. */
         . ($isVod
             ? ' -hls_list_size 0 -hls_playlist_type event -hls_flags independent_segments+append_list'
             : ' -hls_list_size 8 -hls_flags independent_segments+delete_segments+append_list+omit_endlist+temp_file')
         . ' -hls_allow_cache 0'
         . ' -hls_segment_filename ' . escapeshellarg($dir . '/s%05d.m4s')
         . ' ' . escapeshellarg($idx)
         . ' > ' . escapeshellarg($log) . ' 2>&1 & echo $!';

    rsLog('بدء تجهيز القناة', ['key' => $key, 'source_host' => rsSourceHost($srcUrl), 'compat_video' => $compatVideo]);
    $pid = (int)@shell_exec($cmd);
    if ($pid < 2) {
        flock($lock, LOCK_UN); fclose($lock);
        rsLog('فشل بدء الوسيط: لم يُنشأ ffmpeg', ['key' => $key, 'source_host' => rsSourceHost($srcUrl)]);
        return ['ok' => false, 'error' => 'spawn_failed'];
    }
    @file_put_contents(rsPidFile($key), (string)$pid);
    @file_put_contents(rsPipelineFile($key), rsPipelineVersion());
    // علامة "هذه العملية تنسخ الفيديو" — تُستخدم في كشف العلوق أعلاه وفي فشل البدء أدناه
    if ($transcodeVideo) @unlink($dir . '/.copyv'); else @touch($dir . '/.copyv');
    rsTouch($key);

    /* ── انتظار قصير ثم تسليم ──
       ننتظر أول قائمة تشغيل لأن إعادتها قبل وجودها تعني 404 عند المشغّل.
       لكن الانتظار محدود بثوانٍ قليلة عمداً: كل طلب منتظر يحجز عاملاً من
       عمّال Apache، وعند 500 مشترك يكفي عشرون بدايةً باردة متزامنة لتجميد
       الموقع كله. إن لم تجهز في المهلة نُعيد "قيد التحضير" ويعيد العميل
       السؤال — العملية تكمل في الخلفية بلا أن يحجزها أحد.
       القياس: أول قائمة تظهر خلال ~2 ثانية مع hls_init_time=1. */
    $waitMs   = 900;
    $stepMs   = 100;
    $ready    = false;
    for ($i = 0, $n = (int)($waitMs / $stepMs); $i < $n; $i++) {
        if (rsReady($key)) { $ready = true; break; }
        if (!rsAlive($pid)) break;
        usleep($stepMs * 1000);
    }
    flock($lock, LOCK_UN); fclose($lock);

    if ($ready) {
        rsLog('القناة جاهزة', ['key' => $key, 'pid' => $pid, 'source_host' => rsSourceHost($srcUrl)]);
        if (function_exists('logTo')) logTo('info', "restream $key بدأ (pid=$pid)");
        return ['ok' => true, 'started' => true];
    }

    if (rsAlive($pid)) {
        // ما زالت تعمل — تحتاج وقتاً أطول فقط (مصدر بطيء أو GOP طويل)
        return ['ok' => true, 'started' => true, 'pending' => true];
    }

    // ماتت العملية: عطل حقيقي في المصدر
    $err = is_file($log) ? rsSafeDetail((string)@file_get_contents($log)) : '';
    if (!$transcodeVideo) {
        // نسخ الفيديو فشل فعلياً مع هذا المصدر تحديداً — إعادة ترميزه من المحاولة القادمة
        rsMarkForceTranscode($srcUrl);
        rsLog('فشل نسخ الفيديو — سيُعاد ترميز هذا المصدر تلقائياً من الآن', ['key' => $key, 'source_host' => rsSourceHost($srcUrl)]);
    }
    rsStop($key);
    rsLog('فشل ffmpeg', ['key' => $key, 'source_host' => rsSourceHost($srcUrl), 'detail' => $err]);
    if (function_exists('logTo')) logTo('error', "restream $key فشل: " . mb_substr($err, 0, 300));
    return ['ok' => false, 'error' => 'ffmpeg_failed', 'detail' => mb_substr($err, 0, 300)];
}

/**
 * ينهي القنوات التي لم يطلبها أحد.
 * يُستدعى من cron وأيضاً بعد كل تشغيل جديد — بدونه تتراكم العمليات
 * إلى أن تلتهم الخادم، لأن المشاهد يغلق التبويب ولا يخبر أحداً.
 *
 * @return int عدد ما أُنهي
 */
function rsReapIdle(): int {
    $killed = 0;
    $liveTimeout = rsIdleTimeout();
    $vodTimeout  = rsVodIdle();
    $dirs = [];
    foreach (rsAllRoots() as $root) {
        $isVod = ($root !== rsRoot());
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $d) $dirs[] = [$d, $isVod];
    }
    foreach ($dirs as [$dir, $__isVod]) {
        $timeout = $__isVod ? $vodTimeout : $liveTimeout;
        $pidF = $dir . '/.pid';
        $hitF = $dir . '/.hit';
        $pid = is_file($pidF) ? (int)@file_get_contents($pidF) : 0;

        if ($pid > 1 && !rsAlive($pid)) {
            // عملية ماتت وتركت مجلدها
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            foreach (glob($dir . '/.*') ?: [] as $f) if (!is_dir($f)) @unlink($f);
            @rmdir($dir);
            continue;
        }
        if ($pid < 2) continue;

        $last = is_file($hitF) ? (int)@filemtime($hitF) : 0;
        if ($last > 0 && (time() - $last) > $timeout) {
            if (function_exists('posix_kill')) @posix_kill($pid, 15); else @exec('kill ' . $pid . ' 2>/dev/null');
            usleep(300000);
            if (rsAlive($pid)) {
                if (function_exists('posix_kill')) @posix_kill($pid, 9); else @exec('kill -9 ' . $pid . ' 2>/dev/null');
            }
            foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
            foreach (glob($dir . '/.*') ?: [] as $f) if (!is_dir($f)) @unlink($f);
            @rmdir($dir);
            $killed++;
        }
    }
    return $killed;
}

/** لوحة حالة مختصرة. */
function rsStatus(): array {
    // القفل الصلب من الطرفية (setup_restream.sh --off) يتجاوز اللوحة.
    // نكشفه للواجهة كي تشرح للمدير لماذا لا يعمل الزرّ بدل صمت محيّر.
    $hardOff = ((string)rsCfg('RESTREAM_HARD_OFF', '0') === '1');
    $out = ['enabled' => rsEnabled(), 'hard_off' => $hardOff,
            'root' => rsRoot(), 'max' => rsMaxChannels(),
            'quality' => rsRestreamQuality(),
            'idle' => rsIdleTimeout(), 'channels' => []];
    $dirs = [];
    foreach (rsAllRoots() as $root) {
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $d) $dirs[] = $d;
    }
    foreach ($dirs as $dir) {
        $pid = is_file($dir . '/.pid') ? (int)@file_get_contents($dir . '/.pid') : 0;
        /* البث الحي الحالي يستخدم fMP4 (.m4s)، بينما النسخ القديمة قد
           تستخدم TS. عدّهما معاً كي لا تعرض لوحة المراقبة «0 مقطع»
           لقناة تعمل فعلياً. */
        $segs = glob($dir . '/*.{ts,m4s}', GLOB_BRACE) ?: [];
        $size = 0;
        foreach ($segs as $s) $size += (int)@filesize($s);
        $out['channels'][] = [
            'slug'     => basename($dir),
            'pid'      => $pid,
            'alive'    => rsAlive($pid),
            'segments' => count($segs),
            'bytes'    => $size,
            'idle_s'   => is_file($dir . '/.hit') ? (time() - (int)@filemtime($dir . '/.hit')) : -1,
        ];
    }
    $out['active'] = count(array_filter($out['channels'], function ($c) { return !empty($c['alive']); }));
    return $out;
}
