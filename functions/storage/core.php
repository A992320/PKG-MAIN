<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  Multi-Storage — النواة: الحالة، الإحصاء، اختيار القرص، المسارات، الروابط
 * ───────────────────────────────────────────────────────────────────────────
 *  حالات التخزين:
 *    online      قابل للقراءة والكتابة
 *    readonly    مركّب للقراءة فقط (أو ضبطه المدير كذلك) — يُقرأ ولا يُكتب
 *    full        تحت الحدّ الأدنى للمساحة الحرة — يُقرأ ولا يُكتب إليه جديد
 *    maintenance وضعه المدير في الصيانة — لا يُقرأ ولا يُكتب
 *    offline     المجلد غير موجود أو القرص غير مركّب — لا يُقرأ ولا يُكتب
 *    error       قرص مختلف مركّب مكانه، أو فشل الفحص — لا يُقرأ ولا يُكتب
 *
 *  العزل: كل فحص يخصّ تخزيناً واحداً، وأي استثناء فيه يُحصر فيه. تعطّل
 *  قرص لا يُسقط الصفحة ولا يُعطّل الأقراص الأخرى.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('storageSettings')) {

// define لا const: التعريف داخل كتلة شرطية (const لا يُسمح به إلا في المستوى الأعلى)
if (!defined('STORAGE_READABLE')) define('STORAGE_READABLE', ['online', 'readonly', 'full']);
if (!defined('STORAGE_TYPES'))    define('STORAGE_TYPES',    ['movies', 'series', 'mixed']);
if (!defined('STORAGE_PURPOSES')) define('STORAGE_PURPOSES', ['movies', 'series', 'anime', 'uploads', 'other']);

/* ═════════════════════════════ الإعدادات ═════════════════════════════ */

function storageSettingDefaults(): array
{
    return [
        // التوزيع دائماً تلقائي. أبقينا المفتاحين للتوافق مع قواعد البيانات
        // القديمة، لكن لا يسمح أي مسار جديد باختيار قرص ثابت للملفات الجديدة.
        'storage_alloc_mode'     => 'auto',
        'storage_default_id'     => '0',
        'storage_min_free_gb'    => '20',
        'storage_min_free_pct'   => '5',
        'storage_warn_pct'       => '90',
        'storage_publish_target' => 'auto',
        'storage_smart_minutes'  => '30',
        'storage_web_base'       => '',
        'storage_state_rev'      => '0',
        'storage_monitor_at'     => '0',
        'storage_worker_at'      => '0',
        'storage_backfill_at'    => '0',
        'storage_legacy_fallback'=> '0',
        'storage_move_limit_mb'  => '0',      // حدّ سرعة النقل MB/s (0 = بلا حدّ)      // 1 = السماح بالتخزين الداخلي كاحتياط أخير
    ];
}

function storageSettings(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;
    $out = storageSettingDefaults();
    try {
        $keys = array_keys($out);
        $in   = implode(',', array_fill(0, count($keys), '?'));
        $st   = db()->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($in)");
        $st->execute($keys);
        foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            if ($v !== null) $out[$k] = (string) $v;
        }
    } catch (Throwable $e) { /* القيم الافتراضية */ }
    return $cache = $out;
}

function storageSettingSet(string $key, string $value): void
{
    db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
        ->execute([$key, $value]);
    storageSettings(true);
}

/** يُبطل كاش واجهة الموقع عند تغيّر حالة أي قرص (Online ⇄ Offline). */
function storageBumpRev(): void
{
    try {
        storageSettingSet('storage_state_rev', (string) (time() . mt_rand(100, 999)));
        if (function_exists('cacheDelete')) {
            cacheDelete('content_stamp');
            cacheDelete('storage_unreadable_ids');
        }
    } catch (Throwable $e) { error_log('storageBumpRev: ' . $e->getMessage()); }
}

/**
 * المسار الأساسي للموقع على الويب (مثل /iptv). يُحفظ من طلبات الويب حتى
 * يستطيع العامل (CLI، بلا SCRIPT_NAME) بناء روابط صحيحة.
 */
function storageWebBase(): string
{
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
        $b = rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'])), '/');
        // النداء قد يأتي من مجلد فرعي (ajax/ أو tools/) — نصعد لجذر المشروع
        $b = preg_replace('#/(ajax|tools|pages|handlers|functions)$#', '', $b);
        $s = storageSettings();
        if (($s['storage_web_base'] ?? '') !== $b) {
            try { storageSettingSet('storage_web_base', $b); } catch (Throwable $e) {}
        }
        return $b;
    }
    return rtrim((string) (storageSettings()['storage_web_base'] ?? ''), '/');
}

/* ═════════════════════════════ القراءة ═════════════════════════════ */

function storageAll(bool $includeRemoved = false): array
{
    $sql = "SELECT * FROM storages" . ($includeRemoved ? '' : " WHERE admin_state <> 'removed'")
         . " ORDER BY is_legacy ASC, priority DESC, id ASC";
    try { return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    catch (Throwable $e) { return []; }
}

function storageGet(int $id, bool $fresh = false): ?array
{
    static $cache = [];
    if (!$fresh && isset($cache[$id])) return $cache[$id];
    try {
        $st = db()->prepare("SELECT * FROM storages WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $row = false; }
    return $cache[$id] = ($row ?: null);
}

function storageLegacyId(): int
{
    try { return (int) db()->query("SELECT id FROM storages WHERE is_legacy = 1 ORDER BY id LIMIT 1")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

function storageIsReadable(?array $s): bool
{
    return $s && in_array((string) $s['status'], STORAGE_READABLE, true) && $s['admin_state'] !== 'removed';
}

function storageIsWritable(?array $s): bool
{
    return $s && $s['status'] === 'online' && $s['admin_state'] === 'active'
        && ($s['health'] ?? '') !== 'failing';
}

/**
 * مساحة محجوزة لمهام نقل لم تنته بعد، لكل وجهة. تدخل في الاختيار التلقائي
 * حتى لا تختار عدة عمليات متقاربة نفس القرص اعتماداً على مساحة قديمة.
 */
function storageReservedBytes(): array
{
    try {
        $sql = "SELECT j.dest_storage_id, COALESCE(SUM(i.bytes), 0) AS bytes
                  FROM storage_jobs j
                  JOIN storage_job_items i ON i.job_id = j.id
                 WHERE j.status IN ('queued','running','cancelling')
                   AND i.status IN ('pending','copying','verifying')
                 GROUP BY j.dest_storage_id";
        $out = [];
        foreach (db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['dest_storage_id']] = max(0, (int) $row['bytes']);
        }
        // الرفع المتجزئ يحجز الحجم المتبقي على القرص المختار منذ البداية.
        // من دونه قد تبدأ رفعتان على نفس القرص اعتماداً على رقم مساحة قديم.
        try {
            $sql = "SELECT storage_id, COALESCE(SUM(GREATEST(total_bytes - received_bytes, 0)), 0) AS bytes
                      FROM storage_upload_sessions
                     WHERE status = 'uploading'
                     GROUP BY storage_id";
            foreach (db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $id = (int) $row['storage_id'];
                $out[$id] = ($out[$id] ?? 0) + max(0, (int) $row['bytes']);
            }
        } catch (Throwable $e) { /* جدول الجلسات قد لا يكون موجوداً أثناء أول ترحيل */ }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * معرّفات التخزين غير المقروءة الآن — تستعملها الـAPI لتعطيل محتواها وحده.
 * كاش قصير: تُستدعى في كل طلب حلقات.
 */
function storageUnreadableIds(): array
{
    if (function_exists('cacheGet')) {
        $c = cacheGet('storage_unreadable_ids');
        if (is_array($c)) return $c;
    }
    $ids = [];
    try {
        $in = "'" . implode("','", STORAGE_READABLE) . "'";
        foreach (db()->query("SELECT id FROM storages WHERE status NOT IN ($in) OR admin_state = 'removed'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $ids[] = (int) $id;
        }
    } catch (Throwable $e) { $ids = []; }
    if (function_exists('cacheSet')) cacheSet('storage_unreadable_ids', $ids, 20);
    return $ids;
}

/* ═════════════════════════════ المسارات الآمنة ═════════════════════════════ */

/** مسار نسبي آمن: لا مطلق، لا ..، لا NUL، مقاطع معقولة. */
function storageRelSafe(string $rel): bool
{
    if ($rel === '' || strlen($rel) > 700) return false;
    if ($rel[0] === '/' || $rel[0] === '\\' || strpos($rel, "\0") !== false) return false;
    foreach (explode('/', str_replace('\\', '/', $rel)) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') return false;
    }
    return true;
}

/** المسار المطلق لملف داخل تخزين، أو null إن لم يكن آمناً. */
function storageAbs(array $s, string $rel): ?string
{
    $rel = str_replace('\\', '/', $rel);
    if (!storageRelSafe($rel)) return null;
    return rtrim((string) $s['mount_path'], '/') . '/' . $rel;
}

/** يتأكد أن ملفاً موجوداً يقع فعلاً داخل التخزين (يحلّ الروابط الرمزية). */
function storageAbsExistingInside(array $s, string $rel): ?string
{
    $abs = storageAbs($s, $rel);
    if ($abs === null) return null;
    $real = realpath($abs);
    $root = realpath((string) $s['mount_path']);
    if ($real === false || $root === false) return null;
    if (strpos($real, rtrim($root, '/') . '/') !== 0) return null;
    return $real;
}

/** اسم ملف/مجلد آمن للكتابة على القرص. */
function storageSafeName(string $name, string $fallback = 'item'): string
{
    $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '-', $name);
    $name = preg_replace('/\s+/u', ' ', (string) $name);
    $name = trim((string) $name, " .-");
    if ($name === '') $name = $fallback;
    if (function_exists('mb_substr')) $name = mb_substr($name, 0, 80);
    return $name;
}

/** مقطع مسار لاتيني قصير (للمجلدات) — آمن على كل أنظمة الملفات والروابط. */
function storageSlug(string $name, string $fallback): string
{
    $s = preg_replace('/[^A-Za-z0-9]+/', '-', $name);
    $s = strtolower(trim((string) $s, '-'));
    if (strlen($s) > 60) $s = substr($s, 0, 60);
    return $s !== '' ? $s : $fallback;
}

/** الرابط العام المستقر لملف: لا يحوي نقطة الربط، فيبقى صحيحاً إن تغيّرت. */
function storagePublicUrl(array $s, string $rel): string
{
    $base = storageWebBase();
    $enc  = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $rel))));
    if (!empty($s['is_legacy'])) {
        return $base . '/uploads/' . $enc;            // نفس رابط Apache المباشر القديم حرفياً
    }
    return $base . '/media.php/' . (int) $s['id'] . '/' . $enc;
}

/** العكس: من رابط محفوظ إلى (storage_id, rel) إن كان رابط تخزين. */
function storageParseUrl(string $url): ?array
{
    $p = (string) parse_url($url, PHP_URL_PATH);
    if ($p === '') return null;
    if (preg_match('#/media\.php/(\d+)/(.+)$#', $p, $m)) {
        $rel = rawurldecode($m[2]);
        return storageRelSafe($rel) ? ['storage_id' => (int) $m[1], 'rel' => $rel] : null;
    }
    $pos = strpos($p, '/uploads/');
    if ($pos !== false) {
        $rel = rawurldecode(substr($p, $pos + 9));
        $lid = storageLegacyId();
        return ($lid && storageRelSafe($rel)) ? ['storage_id' => $lid, 'rel' => $rel] : null;
    }
    return null;
}

/* ═════════════════════════════ الربط (mount) ═════════════════════════════ */

/** جدول الربط من /proc/mounts: target => [source, fstype, options[]] */
function storageMountTable(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;
    $out = [];
    $raw = @file_get_contents('/proc/mounts');
    if (is_string($raw)) {
        foreach (explode("\n", $raw) as $line) {
            $f = preg_split('/\s+/', trim($line));
            if (count($f) < 4) continue;
            $dec = static function ($v) {       // /proc/mounts يرمّز المسافات: \040
                return preg_replace_callback('/\\\\([0-7]{3})/', function ($m) { return chr(octdec($m[1])); }, $v);
            };
            $out[$dec($f[1])] = ['source' => $dec($f[0]), 'fstype' => $f[2], 'options' => explode(',', $f[3])];
        }
    }
    return $cache = $out;
}

/**
 * الربط الذي يحتوي المسار (أطول بادئة). null إن كان المسار على قرص النظام
 * نفسه (/) — وهذه هي الحالة الخطرة: قرص غير مركّب يجعل /mnt/storage3 مجلداً
 * فارغاً على قرص النظام، والكتابة إليه تملأ قرص النظام بصمت.
 */
function storageMountOf(string $path): ?array
{
    $path = rtrim($path, '/');
    $best = null; $bestLen = -1;
    foreach (storageMountTable() as $target => $info) {
        $t = rtrim($target, '/');
        if ($t === '') $t = '/';
        if ($t === '/' || $path === $t || strpos($path . '/', $t . '/') === 0) {
            $len = ($t === '/') ? 0 : strlen($t);
            if ($len > $bestLen) { $best = $info + ['target' => $target]; $bestLen = $len; }
        }
    }
    if (!$best || $bestLen === 0) return null;
    return $best;
}

/** UUID نظام الملفات لجهاز (بلا صلاحيات جذر). */
function storageDeviceUuid(string $dev): string
{
    static $cache = [];
    if (isset($cache[$dev])) return $cache[$dev];
    $uuid = '';
    if (is_dir('/dev/disk/by-uuid')) {
        $realDev = @realpath($dev) ?: $dev;
        foreach ((array) @scandir('/dev/disk/by-uuid') as $u) {
            if ($u === '.' || $u === '..') continue;
            if (@realpath('/dev/disk/by-uuid/' . $u) === $realDev) { $uuid = $u; break; }
        }
    }
    if ($uuid === '' && storageCanExec() && preg_match('#^/dev/[A-Za-z0-9/_.-]+$#', $dev)) {
        $o = []; @exec('lsblk -no UUID ' . escapeshellarg($dev) . ' 2>/dev/null', $o);
        $uuid = trim((string) ($o[0] ?? ''));
    }
    // بلا udev: lsblk لا يعرف الـUUID لمستخدم الويب — نسأل أداة الجذر (مع كاش)
    if ($uuid === '') { $uuid = (string) (storageDeviceProbe($dev)['uuid'] ?? ''); }
    return $cache[$dev] = $uuid;
}

/**
 * UUID ونوع نظام الملفات والتسمية لجهاز عبر أداة الجذر (blkid -p).
 * كاش 10 دقائق، ويُبطل عند أي تهيئة من اللوحة.
 */
function storageDeviceProbe(string $dev, bool $fresh = false): array
{
    if (!preg_match('#^/dev/[A-Za-z0-9]+$#', $dev) || !function_exists('storageHelperRun')) return [];
    $ck = 'storage_probe_' . md5($dev);
    if (!$fresh && function_exists('cacheGet')) {
        $c = cacheGet($ck);
        if (is_array($c)) return $c;
    }
    if (empty(storageHelperStatus()['installed'])) return [];
    $r = storageHelperRun(['probe', $dev], 20);
    $out = !empty($r['ok']) ? ['uuid' => (string) ($r['uuid'] ?? ''), 'fs' => (string) ($r['fs'] ?? ''), 'label' => (string) ($r['label'] ?? '')] : [];
    if (function_exists('cacheSet')) cacheSet($ck, $out, 600);
    return $out;
}

function storageCanExec(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    $dis = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return $ok = function_exists('exec') && !in_array('exec', $dis, true);
}

/* ═════════════════════════════ الفحص والحالة ═════════════════════════════ */

function storageMinFreeBytes(array $s, ?int $total = null): int
{
    $set = storageSettings();
    $gb  = $s['min_free_gb']  !== null && $s['min_free_gb']  !== '' ? (float) $s['min_free_gb']  : (float) $set['storage_min_free_gb'];
    $pct = $s['min_free_pct'] !== null && $s['min_free_pct'] !== '' ? (float) $s['min_free_pct'] : (float) $set['storage_min_free_pct'];
    $total = $total ?? (int) ($s['total_bytes'] ?? 0);
    return (int) max($gb * 1073741824, $total * $pct / 100);
}

/**
 * يفحص تخزيناً واحداً ويعيد [status, msg, total, free, used, mount].
 * لا يكتب في قاعدة البيانات. لا يرمي استثناءات.
 */
function storageProbe(array $s): array
{
    $r = ['status' => 'offline', 'msg' => '', 'total' => null, 'free' => null, 'used' => null, 'mount' => null];
    try {
        $path = rtrim((string) $s['mount_path'], '/');

        if (($s['admin_state'] ?? '') === 'removed') { $r['msg'] = 'أُزيل من النظام'; return $r; }

        if (!is_dir($path)) { $r['msg'] = 'مسار الربط غير موجود: ' . $path; return $r; }

        $ro = false;
        if (empty($s['is_legacy'])) {
            $m = storageMountOf($path);
            if (!$m) {
                $r['msg'] = 'القرص غير مركّب (Mount) — المسار موجود على قرص النظام نفسه';
                return $r;
            }
            $r['mount'] = $m;
            $uuid = trim((string) ($s['fs_uuid'] ?? ''));
            if ($uuid !== '' && strpos((string) $m['source'], '/dev/') === 0) {
                $cur = storageDeviceUuid((string) $m['source']);
                if ($cur !== '' && strcasecmp($cur, $uuid) !== 0) {
                    $r['status'] = 'error';
                    $r['msg'] = 'قرص مختلف مركّب في هذا المسار (UUID ' . $cur . ' بدل ' . $uuid . ')';
                    return $r;
                }
            }
            if (in_array('ro', $m['options'], true)) { $ro = true; }
        }

        $total = @disk_total_space($path);
        $free  = @disk_free_space($path);
        if ($total === false || $free === false) {
            $r['status'] = 'error'; $r['msg'] = 'تعذّر قراءة سعة القرص';
            return $r;
        }
        $r['total'] = (int) $total; $r['free'] = (int) $free; $r['used'] = (int) max(0, $total - $free);

        if (($s['admin_state'] ?? '') === 'maintenance') { $r['status'] = 'maintenance'; $r['msg'] = 'وضع الصيانة'; return $r; }

        if (!$ro && ($s['admin_state'] ?? '') !== 'readonly') {
            // اختبار كتابة حقيقي: خيار ro ليس الدليل الوحيد (أذونات، أعطال القرص)
            $probe = $path . '/.shs_probe_' . getmypid();
            if (@file_put_contents($probe, 'ok') === false) { $ro = true; $r['msg'] = 'الكتابة على القرص مرفوضة'; }
            else { @unlink($probe); }
        }
        if ($ro || ($s['admin_state'] ?? '') === 'readonly') {
            $r['status'] = 'readonly';
            if ($r['msg'] === '') $r['msg'] = ($s['admin_state'] ?? '') === 'readonly' ? 'للقراءة فقط (بقرار المدير)' : 'القرص مركّب للقراءة فقط';
            return $r;
        }

        if ($r['free'] < storageMinFreeBytes($s, $r['total'])) {
            $r['status'] = 'full';
            $r['msg'] = 'المساحة الحرة أقل من الحد الأدنى المضبوط (' . storageHumanBytes(storageMinFreeBytes($s, (int) $r['total'])) . ') — لا تُرسَل إليه ملفات جديدة، والتشغيل يعمل طبيعياً';
            return $r;
        }
        $r['status'] = 'online';
    } catch (Throwable $e) {
        $r['status'] = 'error'; $r['msg'] = 'فشل الفحص: ' . $e->getMessage();
    }
    return $r;
}

/** يفحص تخزيناً ويحفظ النتيجة ويرفع/يغلق التنبيهات. يعيد true إن تغيّرت الحالة. */
function storageRefresh(array $s): bool
{
    $p = storageProbe($s);
    $changed = ($p['status'] !== (string) $s['status']);
    try {
        /* قرص غائب لا تُقرأ سعته: نُبقي آخر أرقام معروفة (COALESCE) حتى تبقى
           البطاقة تعرض «8 TB — Offline» لا «0 B» — المدير يعرف أي قرص غاب. */
        db()->prepare("UPDATE storages SET status=?, status_msg=?, total_bytes=COALESCE(?, total_bytes), free_bytes=COALESCE(?, free_bytes),
                                           used_bytes=COALESCE(?, used_bytes), last_check_at=NOW(), updated_at=NOW() WHERE id=?")
            ->execute([$p['status'], mb_substr($p['msg'], 0, 490), $p['total'], $p['free'], $p['used'], (int) $s['id']]);
    } catch (Throwable $e) { error_log('storageRefresh: ' . $e->getMessage()); }

    $sid  = (int) $s['id'];
    $name = (string) $s['name'];
    if ($s['admin_state'] === 'removed') return $changed;

    // تنبيه عدم الإتاحة (ما عدا الصيانة: قرار المدير لا عطل)
    if (in_array($p['status'], ['offline', 'error'], true)) {
        storageAlertRaise("st:$sid:down", $sid, 'critical', 'storage_down', "التخزين «{$name}» غير متاح: " . $p['msg']);
    } else {
        storageAlertResolve("st:$sid:down");
    }
    if ($p['status'] === 'readonly' && ($s['admin_state'] ?? '') !== 'readonly') {
        storageAlertRaise("st:$sid:ro", $sid, 'warning', 'storage_readonly', "التخزين «{$name}» أصبح للقراءة فقط: " . $p['msg']);
    } else {
        storageAlertResolve("st:$sid:ro");
    }
    if ($p['status'] === 'full') {
        $minH = storageHumanBytes(storageMinFreeBytes($s, (int) $p['total']));
        storageAlertRaise("st:$sid:full", $sid, 'critical', 'storage_full',
            "التخزين «{$name}»: المساحة الحرة " . storageHumanBytes((int) $p['free']) . " أقل من الحد الأدنى المضبوط ({$minH}) — لن تُرسَل إليه ملفات جديدة. يمكنك تعديل الحد من زر التعديل ✎ أو من الإعدادات");
        storageAlertResolve("st:$sid:warn");
    } else {
        storageAlertResolve("st:$sid:full");
        $warn = (float) storageSettings()['storage_warn_pct'];
        if ($p['total'] && $warn > 0 && ($p['used'] / max(1, $p['total']) * 100) >= $warn) {
            $pct = round($p['used'] / $p['total'] * 100, 1);
            storageAlertRaise("st:$sid:warn", $sid, 'warning', 'storage_near_full', "التخزين «{$name}» يقترب من الامتلاء ({$pct}%)");
        } else {
            storageAlertResolve("st:$sid:warn");
        }
    }
    return $changed;
}

/** فحص كل الأقراص — كل واحد معزول عن الآخر. */
/** فكّ ربط مقصود من المدير ⇒ لا ربط تلقائي لهذا التخزين حتى يُربط يدوياً. */
function storageMountHold(int $sid, ?bool $set = null): bool
{
    $key = 'storage_mount_hold_' . $sid;
    try {
        if ($set !== null) {
            if ($set) storageSettingSet($key, '1');
            else db()->prepare("DELETE FROM settings WHERE setting_key = ?")->execute([$key]);
            return $set;
        }
        $st = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $st->execute([$key]);
        return $st->fetchColumn() === '1';
    } catch (Throwable $e) { return false; }
}

/**
 * يعيد ربط قرص تخزين معروف ببصمته (UUID) على نقطة ربطه المسجلة.
 * لا يهيّئ ولا يلمس المحتوى — mount فقط، عبر أداة الجذر (في فضاء ربط النظام).
 */
function storageRemount(array $s): array
{
    if (!empty($s['is_legacy'])) return ['ok' => false, 'error' => 'التخزين الداخلي لا يُربط'];
    $uuid = trim((string) ($s['fs_uuid'] ?? ''));
    $mp   = rtrim((string) $s['mount_path'], '/');
    if ($uuid === '') return ['ok' => false, 'error' => 'لا توجد بصمة UUID محفوظة لهذا التخزين — اربطه من «الأقراص» ثم احفظه من جديد'];
    if (!function_exists('storageHelperRun')) return ['ok' => false, 'error' => 'أداة الجذر غير متاحة'];
    $r = storageHelperRun(['mount-uuid', $uuid, $mp], 60);
    storageMountTable(true);
    $sid = (int) $s['id'];
    if (!empty($r['ok'])) {
        storageMountHold($sid, false);
        storageAlertResolve("st:$sid:mountfail");
        if (function_exists('logTo')) logTo('storage', "أعيد ربط التخزين «{$s['name']}» على $mp", ['uuid' => $uuid]);
    }
    return $r;
}

/**
 * ربط تلقائي لتخزين غائب (بعد إعادة تشغيل فشل فيها fstab، أو قرص أُعيد توصيله).
 * آمن: mount فقط بالبصمة نفسها، ولا يحاول أكثر من مرة كل 5 دقائق لكل قرص.
 */
function storageAutoRemount(): array
{
    $done = [];
    if (!function_exists('storageHelperStatus') || !storageHelperStatus()['installed']) return $done;
    storageMountTable(true);
    foreach (storageAll() as $s) {
        if (!empty($s['is_legacy']) || !in_array($s['admin_state'], ['active', 'readonly'], true)) continue;
        if (trim((string) ($s['fs_uuid'] ?? '')) === '') continue;
        if (storageMountHold((int) $s['id'])) continue;                       // فُكّ ربطه عمداً
        if (storageMountOf((string) $s['mount_path']) !== null) continue;   // مركّب
        $key = 'storage_remount_try_' . (int) $s['id'];
        if (function_exists('cacheGet') && cacheGet($key)) continue;
        if (function_exists('cacheSet')) cacheSet($key, 1, 300);
        $r = storageRemount($s);
        if (!empty($r['ok'])) { $done[] = (int) $s['id']; continue; }
        $sid = (int) $s['id'];
        // القرص غير موصول أصلاً: تنبيه «غير متاح» يكفي — لا نضاعف التنبيهات
        if (strpos((string) ($r['error'] ?? ''), 'غير موصول') !== false) continue;
        storageAlertRaise("st:$sid:mountfail", $sid, 'critical', 'mount_failed',
            "تعذّر ربط التخزين «{$s['name']}» تلقائياً على {$s['mount_path']}: " . ($r['error'] ?? 'خطأ غير معروف'));
    }
    return $done;
}

function storageRefreshAll(): array
{
    $changed = false; $out = [];
    storageMountTable(true);
    foreach (storageAll() as $s) {
        try { if (storageRefresh($s)) $changed = true; }
        catch (Throwable $e) { error_log('storage refresh #' . $s['id'] . ': ' . $e->getMessage()); }
        $out[] = (int) $s['id'];
    }
    if ($changed) storageBumpRev();
    return ['changed' => $changed, 'ids' => $out];
}

/* ═════════════════════════════ اختيار القرص ═════════════════════════════ */

/** هل يقبل التخزين هذا النوع من المحتوى؟ */
function storageAccepts(array $s, string $kind): bool
{
    $t = (string) $s['content_type'];
    return $t === 'mixed' || $kind === 'any' || $t === $kind;
}

/**
 * اختيار القرص لملف جديد.
 *   $kind    : movies | series | any
 *   $bytes   : حجم الملف (لضمان بقاء الحدّ الأدنى بعد الكتابة)
 *   $manual  : معرّف تخزين بعينه (الوضع اليدوي) أو null/0 للإعداد
 * يعيد ['ok'=>bool, 'storage'=>?array, 'reason'=>string, 'candidates'=>[]]
 */
function storagePick(string $kind, int $bytes = 0, ?int $manual = null): array
{
    $set  = storageSettings();
    // $manual جزء من التوقيع القديم فقط. قرار مكان أي ملف جديد لا يأتي من
    // المتصفح ولا من إعداد قديم؛ الاستثناء الوحيد هو مهمة نقل/إعادة توازن
    // صريحة، وهي لا تمر بهذه الدالة أصلاً.
    $manual = null;
    $reserved = storageReservedBytes();

    $check = static function (array $s) use ($kind, $bytes, $reserved): string {
        if (!storageIsWritable($s)) {
            return 'الحالة: ' . $s['status'] . ($s['status_msg'] ? ' — ' . $s['status_msg'] : '')
                 . (($s['health'] ?? '') === 'failing' ? ' — SMART يشير لعطل' : '');
        }
        if (!storageAccepts($s, $kind)) return 'نوعه ' . $s['content_type'] . ' لا يقبل ' . $kind;
        $free = max(0, (int) $s['free_bytes'] - (int) ($reserved[(int) $s['id']] ?? 0));
        if ($free - $bytes < storageMinFreeBytes($s)) return 'المساحة المتبقية بعد الملف ستكون تحت الحدّ الأدنى';
        return '';
    };

    $cands = []; $rejected = [];
    /* التخزين الداخلي (uploads على قرص النظام) لا يدخل الاختيار التلقائي ما
       دام في النظام قرص مستقل واحد على الأقل — وإلا امتلأت الأقراص فسقط
       المحتوى بصمت على قرص النظام، وهذا عكس غرض الحدّ الأدنى للمساحة.
       بلا أي قرص مستقل يبقى هو الخيار (السلوك القديم تماماً). */
    $all = storageAll();
    $hasIndependent = false;
    foreach ($all as $s) { if (empty($s['is_legacy'])) { $hasIndependent = true; break; } }
    $legacyOk = !$hasIndependent || (string) ($set['storage_legacy_fallback'] ?? '0') === '1';
    foreach ($all as $s) {
        if (!empty($s['is_legacy']) && !$legacyOk) { continue; }
        $why = $check($s);
        if ($why !== '') { $rejected[] = $s['name'] . ': ' . $why; continue; }
        $total = max(1, (int) $s['total_bytes']);
        $s['_reserved_bytes'] = (int) ($reserved[(int) $s['id']] ?? 0);
        $s['_used_pct'] = ((int) $s['used_bytes'] + $s['_reserved_bytes'] + $bytes) / $total * 100;
        $s['_available_after_reservations'] = max(0, (int) $s['free_bytes'] - $s['_reserved_bytes']);
        $cands[] = $s;
    }
    if (!$cands) {
        return ['ok' => false, 'storage' => null, 'reason' => 'لا يوجد قرص متاح للكتابة. ' . implode(' | ', $rejected), 'candidates' => []];
    }
    /* الأقل استخداماً أولاً (المثال: 95% ، 70% ، 30% ⇒ الثالث). التخزين الداخلي
       (uploads على قرص النظام) احتياطي أخير فقط: لا يُختار ما دام قرص آخر متاحاً. */
    usort($cands, static function ($a, $b) {
        if ((int) $a['is_legacy'] !== (int) $b['is_legacy']) return (int) $a['is_legacy'] - (int) $b['is_legacy'];
        if ((int) $a['priority'] !== (int) $b['priority']) return (int) $b['priority'] - (int) $a['priority'];
        if (abs($a['_used_pct'] - $b['_used_pct']) > 0.001) return $a['_used_pct'] < $b['_used_pct'] ? -1 : 1;
        return (int) $b['free_bytes'] - (int) $a['free_bytes'];
    });
    return ['ok' => true, 'storage' => $cands[0], 'reason' => 'اختيار تلقائي حسب المساحة الحرة', 'candidates' => $cands];
}

/* ═════════════════════════════ المسارات ═════════════════════════════ */

function storagePaths(int $sid): array
{
    try {
        $st = db()->prepare("SELECT * FROM storage_paths WHERE storage_id = ? ORDER BY is_default DESC, rel_path ASC");
        $st->execute([$sid]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** المسار الافتراضي لغرض ما داخل تخزين (movies / series ...). */
function storagePathFor(int $sid, string $purpose): string
{
    $paths = storagePaths($sid);
    foreach ($paths as $p) { if ($p['purpose'] === $purpose) return (string) $p['rel_path']; }
    foreach ($paths as $p) { if ((int) $p['is_default'] === 1) return (string) $p['rel_path']; }
    return $purpose;
}

function storagePathNormalize(string $rel): ?string
{
    $rel = trim(str_replace('\\', '/', $rel), '/');
    $rel = preg_replace('#/+#', '/', $rel);
    if (!storageRelSafe($rel)) return null;
    foreach (explode('/', $rel) as $seg) {
        if (!preg_match('/^[\p{L}\p{N}._ -]{1,80}$/u', $seg)) return null;
    }
    return $rel;
}

/** إنشاء المجلد فعلياً على القرص (فقط إن كان مركّباً وقابلاً للكتابة). */
function storageEnsureDir(array $s, string $rel): bool
{
    if (!storageIsWritable($s)) return false;
    $abs = storageAbs($s, $rel);
    if ($abs === null) return false;
    return is_dir($abs) || @mkdir($abs, 0775, true);
}

/* ═════════════════════════════ الإحصاء ═════════════════════════════ */

function storageContentStats(int $sid, bool $countFiles = false): array
{
    $pdo = db();
    $out = ['episodes' => 0, 'series' => 0, 'movies' => 0, 'bytes_db' => 0, 'files_on_disk' => null, 'files_truncated' => false];
    try {
        $st = $pdo->prepare("SELECT COUNT(*) AS e, COUNT(DISTINCT series_id) AS s, COALESCE(SUM(file_size),0) AS b FROM episodes WHERE storage_id = ?");
        $st->execute([$sid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        $out['episodes'] = (int) $r['e']; $out['bytes_db'] = (int) $r['b'];
        /* الفيلم في هذا المشروع = عمل بحلقة واحدة؛ المسلسل = أكثر من حلقة */
        $st = $pdo->prepare("SELECT
                SUM(CASE WHEN t.cnt = 1 THEN 1 ELSE 0 END) AS movies,
                SUM(CASE WHEN t.cnt > 1 THEN 1 ELSE 0 END) AS series
            FROM (SELECT e.series_id, (SELECT COUNT(*) FROM episodes e2 WHERE e2.series_id = e.series_id) AS cnt
                    FROM episodes e WHERE e.storage_id = ? GROUP BY e.series_id) t");
        $st->execute([$sid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        $out['movies'] = (int) ($r['movies'] ?? 0); $out['series'] = (int) ($r['series'] ?? 0);
    } catch (Throwable $e) { error_log('storageContentStats: ' . $e->getMessage()); }

    if ($countFiles) {
        $s = storageGet($sid, true);
        if ($s && is_dir((string) $s['mount_path']) && ($s['is_legacy'] || storageMountOf((string) $s['mount_path']))) {
            $n = 0; $t0 = microtime(true);
            try {
                // CATCH_GET_CHILD: مجلد بلا صلاحية قراءة (lost+found للجذر) يُتخطّى ولا يُوقف العدّ كله
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator((string) $s['mount_path'], FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
                foreach ($it as $f) {
                    if ($f->isFile()) $n++;
                    if ($n >= 200000 || microtime(true) - $t0 > 4) { $out['files_truncated'] = true; break; }
                }
            } catch (Throwable $e) { /* أذونات */ }
            $out['files_on_disk'] = $n;
        }
    }
    return $out;
}

/* ═════════════════════════════ التنبيهات ═════════════════════════════ */

function storageAlertRaise(string $key, ?int $sid, string $level, string $code, string $message): void
{
    $pdo = null; $locked = false;
    try {
        $pdo = db();
        /* الصفحة وcron قد يفحصان في اللحظة نفسها: «SELECT ثم INSERT» بلا قفل
           كان يُنتج التنبيه نفسه مرتين. قفل مسمّى قصير يجعل الفحص والإدراج ذرّيين. */
        $locked = (int) $pdo->query("SELECT GET_LOCK('shs_storage_alert', 5)")->fetchColumn() === 1;
        $st = $pdo->prepare("SELECT id, message FROM storage_alerts WHERE alert_key = ? AND is_active = 1 LIMIT 1");
        $st->execute([$key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ($row['message'] !== $message) {
                $pdo->prepare("UPDATE storage_alerts SET message=?, level=?, updated_at=NOW(), is_read=0 WHERE id=?")
                    ->execute([mb_substr($message, 0, 490), $level, (int) $row['id']]);
            }
            return;
        }
        $pdo->prepare("INSERT INTO storage_alerts (alert_key, storage_id, level, code, message, updated_at) VALUES (?,?,?,?,?,NOW())")
            ->execute([$key, $sid, $level, $code, mb_substr($message, 0, 490)]);
        if (function_exists('logTo')) logTo('storage', $message, ['code' => $code]);
    } catch (Throwable $e) { error_log('storageAlertRaise: ' . $e->getMessage()); }
    finally {
        if ($locked && $pdo) { try { $pdo->query("SELECT RELEASE_LOCK('shs_storage_alert')"); } catch (Throwable $e) {} }
    }
}

function storageAlertResolve(string $key): void
{
    try {
        db()->prepare("UPDATE storage_alerts SET is_active=0, resolved_at=NOW() WHERE alert_key = ? AND is_active = 1")->execute([$key]);
    } catch (Throwable $e) {}
}

function storageAlertsActive(int $limit = 50): array
{
    try {
        // تنبيه واحد لكل مفتاح (أحدثها) — يحمي من نسخ مكررة قديمة
        $st = db()->prepare("SELECT a.* FROM storage_alerts a
                              JOIN (SELECT MAX(id) mid FROM storage_alerts WHERE is_active = 1 GROUP BY alert_key) m ON m.mid = a.id
                              ORDER BY FIELD(a.level,'critical','warning','info'), a.id DESC LIMIT " . (int) $limit);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/* ═════════════════════════════ ربط الحلقات بالتخزين ═════════════════════════════ */

/**
 * يُستدعى بعد إنشاء/تعديل حلقة برابط محلي: يحفظ موقعها (قرص + مسار نسبي).
 * لا يغيّر stream_url.
 */
function storageTagEpisode(int $episodeId, string $url): void
{
    $loc = storageParseUrl($url);
    try {
        if ($loc) {
            $s = storageGet($loc['storage_id']);
            $size = null;
            if ($s) { $abs = storageAbs($s, $loc['rel']); if ($abs && is_file($abs)) $size = @filesize($abs) ?: null; }
            db()->prepare("UPDATE episodes SET storage_id=?, relative_path=?, file_size=? WHERE id=?")
                ->execute([$loc['storage_id'], $loc['rel'], $size, $episodeId]);
        } else {
            db()->prepare("UPDATE episodes SET storage_id=NULL, relative_path=NULL WHERE id=? AND storage_id IS NOT NULL")
                ->execute([$episodeId]);
        }
    } catch (Throwable $e) { /* الأعمدة غير موجودة بعد — لا نكسر النشر */ }
}

/** حذف ملف تخزين (لا يُستدعى إلا بعد حذف سجلّه أو نقله). */
function storageDeleteFile(int $sid, string $rel): bool
{
    $s = storageGet($sid, true);
    if (!$s || !storageIsReadable($s)) return false;
    if (empty($s['is_legacy']) && !storageMountOf((string) $s['mount_path'])) return false;
    $real = storageAbsExistingInside($s, $rel);
    if ($real === null || !is_file($real)) return false;
    $ok = @unlink($real);
    // تنظيف المجلدات الفارغة حتى جذر التخزين (بلا تجاوزه)
    if ($ok && empty($s['is_legacy'])) {
        $root = rtrim((string) realpath((string) $s['mount_path']), '/');
        $dir  = dirname($real);
        while ($dir !== $root && strpos($dir, $root . '/') === 0 && @rmdir($dir)) { $dir = dirname($dir); }
    }
    return $ok;
}

} // function_exists
