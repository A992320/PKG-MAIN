<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  Multi-Storage — محرك نقل المحتوى بين الأقراص
 * ───────────────────────────────────────────────────────────────────────────
 *  ترتيب كل ملف — ولا يُحذف الأصل إلا في آخر خطوة:
 *    1) نسخ إلى ‎<dest>.shs-part‎ مع حساب SHA-1 للمصدر أثناء القراءة
 *    2) fsync ثم إغلاق، ومقارنة الحجم
 *    3) إعادة قراءة الملف من القرص الجديد وحساب SHA-1 له ومطابقته
 *    4) إعادة تسمية ذرية ‎.shs-part → الاسم النهائي‎ (نفس نظام الملفات)
 *    5) تحديث قاعدة البيانات داخل معاملة (storage_id + relative_path + stream_url)
 *    6) حذف الأصل — فقط الآن
 *  انقطاع في أي خطوة قبل (5) يترك الأصل سليماً والسجلّ يشير إليه. انقطاع بعد
 *  (5) وقبل (6) يترك نسخة زائدة في المصدر فقط — لا فقدان بيانات في أي حال.
 *
 *  المهمة قابلة للاستئناف: كل ملف عنصر مستقل بحالته. العامل الذي يتوقف
 *  يُستأنف من أول عنصر غير مكتمل، والملف الجزئي يُعاد من البداية.
 *  عامل واحد فقط في كل لحظة (GET_LOCK) — لا تتصارع نسختان على نفس الملف.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('storageJobCreate')) {

if (!defined('STORAGE_CHUNK')) define('STORAGE_CHUNK', 8 * 1048576);
if (!defined('STORAGE_VIDEO_EXT')) define('STORAGE_VIDEO_EXT', ['mp4', 'mkv', 'avi', 'mov', 'webm', 'ts', 'flv', 'm4v', 'mpg', 'mpeg', 'wmv', 'm2ts']);

/** معلومات العمل لحلقة: نوعه (فيلم/مسلسل) ومقاطع مساره. */
function storageEpisodeMeta(int $episodeId): ?array
{
    $st = db()->prepare("SELECT e.id, e.series_id, e.storage_id, e.relative_path, e.stream_url, e.episode_number,
                                s.name AS series_name, s.created_at AS series_created,
                                (SELECT COUNT(*) FROM episodes e2 WHERE e2.series_id = e.series_id) AS ep_count
                           FROM episodes e LEFT JOIN series s ON s.id = e.series_id WHERE e.id = ?");
    $st->execute([$episodeId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/**
 * المسار الهدف لحلقة قادمة من التخزين الداخلي (ملفات مسطّحة):
 *   أفلام:    <movies>/<السنة>/<id>-<slug>/<الملف>
 *   مسلسلات:  <series>/<id>-<slug>/<الملف>
 * ومن تخزين إلى تخزين يُحفظ المسار النسبي كما هو (بنية واحدة على كل الأقراص).
 */
function storagePlanDestRel(array $meta, array $src, array $dst, ?int $destPathId): string
{
    $file = basename((string) $meta['relative_path']);
    if (empty($src['is_legacy'])) return (string) $meta['relative_path'];

    $isMovie = (int) $meta['ep_count'] <= 1;
    $root = null;
    if ($destPathId) {
        foreach (storagePaths((int) $dst['id']) as $p) { if ((int) $p['id'] === $destPathId) { $root = (string) $p['rel_path']; break; } }
    }
    if ($root === null) $root = storagePathFor((int) $dst['id'], $isMovie ? 'movies' : 'series');
    $sid  = (int) $meta['series_id'];
    $slug = $sid . '-' . storageSlug((string) $meta['series_name'], 'item');
    if ($isMovie) {
        $year = $meta['series_created'] ? date('Y', strtotime((string) $meta['series_created'])) : date('Y');
        return trim($root, '/') . '/' . $year . '/' . $slug . '/' . $file;
    }
    return trim($root, '/') . '/' . $slug . '/' . $file;
}

/**
 * مسار وجهة لا يتصادم: إن وُجد ملف بنفس المسار على الوجهة (أو خُطّط له في
 * نفس المهمة) نضيف لاحقة ‎-2، -3…‎ قبل الامتداد. لا نكتب فوق ملف قائم أبداً.
 */
function storageUniqueDestRel(array $dst, string $rel, array &$taken): string
{
    $try = $rel; $n = 2;
    $dot = strrpos($rel, '.'); $slash = strrpos($rel, '/');
    $hasExt = $dot !== false && ($slash === false || $dot > $slash);
    $base = $hasExt ? substr($rel, 0, $dot) : $rel;
    $ext  = $hasExt ? substr($rel, $dot) : '';
    while (isset($taken[$try]) || (($abs = storageAbs($dst, $try)) !== null && file_exists($abs))) {
        $try = $base . '-' . $n . $ext; $n++;
        if ($n > 999) break;
    }
    $taken[$try] = true;
    return $try;
}

/**
 * إنشاء مهمة نقل.
 * $o: source_storage_id, dest_storage_id, dest_path_id?, scope(all|series|movie|episode|folder),
 *     scope_ref?, remove_source_after?, created_by?, type?
 * يعيد ['ok'=>bool, 'job_id'=>int, 'error'=>string, 'summary'=>[]]
 */
function storageJobCreate(array $o): array
{
    $pdo = db();
    $srcId = (int) ($o['source_storage_id'] ?? 0);
    $dstId = (int) ($o['dest_storage_id'] ?? 0);
    $scope = (string) ($o['scope'] ?? 'all');
    $ref   = trim((string) ($o['scope_ref'] ?? ''));
    $destPathId = (int) ($o['dest_path_id'] ?? 0) ?: null;

    if (!in_array($scope, ['all', 'series', 'movie', 'episode', 'folder'], true)) return ['ok' => false, 'error' => 'نطاق غير معروف'];
    if ($srcId <= 0 || $dstId <= 0) return ['ok' => false, 'error' => 'حدّد المصدر والوجهة'];
    if ($srcId === $dstId) return ['ok' => false, 'error' => 'المصدر والوجهة نفس التخزين'];
    $src = storageGet($srcId, true); $dst = storageGet($dstId, true);
    if (!$src || !$dst) return ['ok' => false, 'error' => 'تخزين غير موجود'];
    storageRefresh($dst); $dst = storageGet($dstId, true);
    storageRefresh($src); $src = storageGet($srcId, true);
    if (!storageIsReadable($src)) return ['ok' => false, 'error' => 'المصدر غير قابل للقراءة الآن: ' . $src['status'] . ' — ' . $src['status_msg']];
    if (!storageIsWritable($dst)) return ['ok' => false, 'error' => 'الوجهة غير قابلة للكتابة الآن: ' . $dst['status'] . ' — ' . $dst['status_msg']];

    // ── العناصر المرتبطة بقاعدة البيانات ──
    $where = 'e.storage_id = ?'; $args = [$srcId];
    if ($scope === 'series' || $scope === 'movie') { $where .= ' AND e.series_id = ?'; $args[] = (int) $ref; }
    if ($scope === 'episode') { $where .= ' AND e.id = ?'; $args[] = (int) $ref; }
    if ($scope === 'folder') {
        $ref = storagePathNormalize($ref);
        if ($ref === null) return ['ok' => false, 'error' => 'مسار المجلد غير صالح'];
        $where .= ' AND e.relative_path LIKE ?'; $args[] = str_replace(['%', '_'], ['\\%', '\\_'], $ref) . '/%';
    }
    $st = $pdo->prepare("SELECT e.id FROM episodes e WHERE $where AND e.relative_path IS NOT NULL ORDER BY e.series_id, e.episode_number, e.id");
    $st->execute($args);
    $epIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    $items = []; $bytes = 0; $missing = 0; $rejectedType = 0; $planned = []; $takenDst = [];
    foreach ($epIds as $eid) {
        $m = storageEpisodeMeta($eid);
        if (!$m) continue;
        $rel = (string) $m['relative_path'];
        if (isset($planned[$rel])) continue;                 // حلقتان تشيران لنفس الملف
        $kind = ((int) $m['ep_count'] <= 1) ? 'movies' : 'series';
        if (!storageAccepts($dst, $kind)) { $rejectedType++; continue; }
        $abs = storageAbsExistingInside($src, $rel);
        $size = ($abs && is_file($abs)) ? (int) @filesize($abs) : -1;
        if ($size < 0) { $missing++; }
        $dstRel = storageUniqueDestRel($dst, storagePlanDestRel($m, $src, $dst, $destPathId), $takenDst);
        $planned[$rel] = true;
        $items[] = ['episode_id' => $eid, 'src_rel' => $rel, 'dst_rel' => $dstRel, 'bytes' => max(0, $size), 'missing' => $size < 0];
        if ($size > 0) $bytes += $size;
    }

    // ── ملفات على قرص مستقل غير مسجّلة في القاعدة (للإفراغ الكامل قبل الإزالة) ──
    if (empty($src['is_legacy']) && in_array($scope, ['all', 'folder'], true)) {
        $root = rtrim((string) realpath((string) $src['mount_path']), '/');
        $start = $scope === 'folder' ? $root . '/' . $ref : $root;
        if ($root !== '' && is_dir($start)) {
            try {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($start, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
                foreach ($it as $f) {
                    if (!$f->isFile()) continue;
                    $rel = ltrim(substr($f->getPathname(), strlen($root)), '/');
                    if (isset($planned[$rel]) || substr($rel, -9) === '.shs-part' || strpos(basename($rel), '.shs_probe') === 0) continue;
                    if ($rel === 'lost+found' || strpos($rel, 'lost+found/') === 0) continue;
                    $planned[$rel] = true;
                    $size = (int) $f->getSize();
                    $items[] = ['episode_id' => null, 'src_rel' => $rel, 'dst_rel' => storageUniqueDestRel($dst, $rel, $takenDst), 'bytes' => $size, 'missing' => false];
                    $bytes += $size;
                }
            } catch (Throwable $e) { /* أذونات */ }
        }
    }

    if (!$items) {
        $msg = 'لا توجد ملفات في هذا النطاق على التخزين المصدر';
        if ($rejectedType) $msg .= ' (استُبعد ' . $rejectedType . ' لأن نوع الوجهة لا يقبلها)';
        return ['ok' => false, 'error' => $msg];
    }
    if ($missing > 0 && $missing === count($items)) {
        return ['ok' => false, 'error' => 'ملفات هذا النطاق غير موجودة على قرص المصدر (' . $missing . ') — السجلات تشير إلى ملفات حُذفت أو نُقلت يدوياً'];
    }
    if ($rejectedType) {
        return ['ok' => false, 'error' => 'نوع الوجهة «' . $dst['content_type'] . '» لا يقبل ' . $rejectedType . ' عنصراً من هذا النطاق — اختر وجهة Mixed أو من النوع المطابق'];
    }
    // احسب حجوزات المهام الأخرى أيضاً. لا يحق لمهمة إعادة توازن صريحة أن
    // تتجاوز الحماية نفسها التي يستخدمها التوزيع التلقائي للملفات الجديدة.
    $reserved = (int) (storageReservedBytes()[$dstId] ?? 0);
    $available = max(0, (int) $dst['free_bytes'] - $reserved);
    $need = $bytes + storageMinFreeBytes($dst);
    if ($available < $need) {
        return ['ok' => false, 'error' => 'المساحة غير كافية على الوجهة: المطلوب ' . storageHumanBytes($bytes)
            . ' + الحدّ الأدنى ' . storageHumanBytes(storageMinFreeBytes($dst)) . '، والمتاح بعد حجوزات المهام الأخرى ' . storageHumanBytes($available)];
    }

    $label = (string) ($o['scope_label'] ?? '');
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO storage_jobs (type, status, source_storage_id, dest_storage_id, dest_path_id, scope, scope_ref, scope_label,
                                                 remove_source_after, total_files, total_bytes, created_by)
                       VALUES (?, 'queued', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([(string) ($o['type'] ?? 'move'), $srcId, $dstId, $destPathId, $scope, $ref, mb_substr($label, 0, 250),
                       !empty($o['remove_source_after']) ? 1 : 0, count($items), $bytes, (string) ($o['created_by'] ?? '')]);
        $jobId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO storage_job_items (job_id, episode_id, src_storage_id, src_rel, dst_rel, bytes, status, error) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($items as $it) {
            $ins->execute([$jobId, $it['episode_id'], $srcId, $it['src_rel'], $it['dst_rel'], $it['bytes'],
                           $it['missing'] ? 'failed' : 'pending', $it['missing'] ? 'الملف الأصلي غير موجود على القرص' : null]);
        }
        if ($missing) $pdo->prepare("UPDATE storage_jobs SET failed_files = ? WHERE id = ?")->execute([$missing, $jobId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'تعذّر إنشاء المهمة: ' . $e->getMessage()];
    }
    return ['ok' => true, 'job_id' => $jobId, 'summary' => ['files' => count($items), 'bytes' => $bytes, 'missing' => $missing]];
}

function storageJobGet(int $id): ?array
{
    $st = db()->prepare("SELECT j.*, s1.name AS source_name, s2.name AS dest_name
                           FROM storage_jobs j
                           LEFT JOIN storages s1 ON s1.id = j.source_storage_id
                           LEFT JOIN storages s2 ON s2.id = j.dest_storage_id WHERE j.id = ?");
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function storageJobsRecent(int $limit = 20): array
{
    try {
        return db()->query("SELECT j.*, s1.name AS source_name, s2.name AS dest_name
                              FROM storage_jobs j
                              LEFT JOIN storages s1 ON s1.id = j.source_storage_id
                              LEFT JOIN storages s2 ON s2.id = j.dest_storage_id
                             ORDER BY FIELD(j.status,'running','cancelling','queued') DESC, j.id DESC LIMIT " . (int) $limit)
                   ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

function storageJobCancel(int $id): bool
{
    $st = db()->prepare("UPDATE storage_jobs SET status = CASE WHEN status = 'queued' THEN 'cancelled' ELSE 'cancelling' END,
                                finished_at = CASE WHEN status = 'queued' THEN NOW() ELSE finished_at END
                          WHERE id = ? AND status IN ('queued','running')");
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

/* ═════════════════════════════ العامل ═════════════════════════════ */

function storagePhpCli(): ?string
{
    $c = [];
    if (defined('PHP_BINDIR')) { $c[] = PHP_BINDIR . '/php'; $c[] = PHP_BINDIR . '/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; }
    $c = array_merge($c, ['/usr/bin/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, '/usr/bin/php', '/usr/local/bin/php']);
    foreach ($c as $p) { if (@is_file($p) && @is_executable($p)) return $p; }
    return null;
}

/** يشغّل العامل في الخلفية فوراً (وإلا يلتقطه cron خلال دقيقة). */
function storageWorkerKick(): bool
{
    if (!storageCanExec()) return false;
    $php = storagePhpCli();
    if (!$php) return false;
    $script = dirname(__DIR__, 2) . '/tools/storage_worker.php';
    @exec('nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' run > /dev/null 2>&1 &');
    return true;
}

/**
 * ينفّذ المهام المعلّقة. يعيد عدد الملفات المنقولة.
 * $budget: أقصى ثوانٍ (0 = بلا حد).
 */
function storageWorkerRun(int $budget = 0, ?callable $log = null): int
{
    $pdo = db();
    $log = $log ?: static function ($m) {};
    $lock = (int) $pdo->query("SELECT GET_LOCK('shs_storage_worker', 0)")->fetchColumn();
    if ($lock !== 1) { $log('عامل آخر يعمل الآن'); return 0; }
    $moved = 0; $t0 = time();
    try {
        storageSettingSet('storage_worker_at', (string) time());
        // مهمة «قيد التشغيل» بلا نبض منذ دقيقتين = عامل انقطع ⇒ نستأنفها
        $pdo->exec("UPDATE storage_jobs SET status='queued' WHERE status='running' AND (heartbeat_at IS NULL OR heartbeat_at < (NOW() - INTERVAL 2 MINUTE))");
        while (true) {
            if ($budget > 0 && time() - $t0 >= $budget) break;
            $job = $pdo->query("SELECT * FROM storage_jobs WHERE status IN ('queued','cancelling') ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$job) break;
            $moved += storageJobProcess($job, $budget > 0 ? max(1, $budget - (time() - $t0)) : 0, $log);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('shs_storage_worker')");
    }
    return $moved;
}

function storageJobStatus(int $jobId): string
{
    $st = db()->prepare("SELECT status FROM storage_jobs WHERE id = ?");
    $st->execute([$jobId]);
    return (string) $st->fetchColumn();
}

function storageJobProcess(array $job, int $budget, callable $log): int
{
    $pdo = db();
    $jobId = (int) $job['id'];
    if ($job['status'] === 'cancelling') { storageJobFinish($jobId, 'cancelled'); return 0; }

    $pdo->prepare("UPDATE storage_jobs SET status='running', started_at=COALESCE(started_at,NOW()), heartbeat_at=NOW(), error=NULL WHERE id=?")->execute([$jobId]);
    $dst = storageGet((int) $job['dest_storage_id'], true);
    $moved = 0; $t0 = time();
    $log("المهمة #$jobId: {$job['total_files']} ملف");

    while (true) {
        $st = $pdo->prepare("SELECT * FROM storage_job_items WHERE job_id = ? AND status IN ('pending','copying','verifying') ORDER BY id LIMIT 1");
        $st->execute([$jobId]);
        $item = $st->fetch(PDO::FETCH_ASSOC);
        if (!$item) break;                               // لا شيء متبقٍّ ⇒ النهاية الطبيعية حتى لو طُلب الإلغاء للتو
        if (storageJobStatus($jobId) === 'cancelling') { storageJobFinish($jobId, 'cancelled'); return $moved; }
        if ($budget > 0 && time() - $t0 >= $budget) {
            $pdo->prepare("UPDATE storage_jobs SET status='queued', heartbeat_at=NOW() WHERE id=? AND status='running'")->execute([$jobId]);
            return $moved;
        }

        // الوجهة قد تتعطّل أثناء النقل — لا نكتب على قرص غير مركّب
        storageMountTable(true);
        storageRefresh($dst); $dst = storageGet((int) $job['dest_storage_id'], true);
        if (!storageIsWritable($dst)) {
            storageJobFinish($jobId, 'failed', 'توقفت المهمة: الوجهة أصبحت ' . $dst['status'] . ' — ' . $dst['status_msg'] . '. ستُستأنف عند إعادة التشغيل بعد إصلاحها');
            return $moved;
        }
        $src = storageGet((int) $item['src_storage_id'], true);
        try {
            $res = storageMoveOne($job, $item, $src, $dst, $log);
        } catch (Throwable $e) {
            $res = ['ok' => false, 'error' => 'استثناء: ' . $e->getMessage()];
        }
        if (!empty($res['cancelled'])) { storageJobFinish($jobId, 'cancelled'); return $moved; }
        if ($res['ok']) {
            $moved++;
            $pdo->prepare("UPDATE storage_job_items SET status='done', error=? WHERE id=?")->execute([$res['warning'] ?? null, (int) $item['id']]);
            $pdo->prepare("UPDATE storage_jobs SET done_files = done_files + 1, heartbeat_at=NOW() WHERE id=?")->execute([$jobId]);
        } else {
            $pdo->prepare("UPDATE storage_job_items SET status='failed', error=? WHERE id=?")->execute([mb_substr((string) $res['error'], 0, 490), (int) $item['id']]);
            $pdo->prepare("UPDATE storage_jobs SET failed_files = failed_files + 1, heartbeat_at=NOW() WHERE id=?")->execute([$jobId]);
            $log('فشل: ' . $item['src_rel'] . ' — ' . $res['error']);
        }
    }

    $j = storageJobGet($jobId);
    $failed = (int) $j['failed_files']; $done = (int) $j['done_files'];
    $status = $failed === 0 ? 'done' : ($done > 0 ? 'partial' : 'failed');
    storageJobFinish($jobId, $status, $failed ? ($failed . ' ملف لم يُنقل — التفاصيل في قائمة العناصر، والأصل باقٍ في مكانه') : null);

    // إزالة المصدر بعد إفراغه — فقط عند نجاح كل شيء وخلوّه فعلاً من المحتوى المرتبط
    if ($status === 'done' && (int) $j['remove_source_after'] === 1) {
        $left = storageContentStats((int) $j['source_storage_id']);
        if ($left['episodes'] === 0) {
            $pdo->prepare("UPDATE storages SET admin_state='removed', status='offline', status_msg='أُزيل بعد نقل محتواه', updated_at=NOW() WHERE id=? AND is_legacy=0")
                ->execute([(int) $j['source_storage_id']]);
            storageBumpRev();
        }
    }
    storageRefresh($dst);
    if ($done > 0) storageBumpRev();
    return $moved;
}

function storageJobFinish(int $jobId, string $status, ?string $error = null): void
{
    db()->prepare("UPDATE storage_jobs SET status=?, error=COALESCE(?, error), finished_at=NOW(), current_file=NULL WHERE id=?")
        ->execute([$status, $error, $jobId]);
}

/** نقل ملف واحد بالتسلسل الآمن الموصوف أعلى الملف. */
function storageMoveOne(array $job, array $item, ?array $src, array $dst, callable $log): array
{
    $pdo = db();
    $jobId = (int) $job['id'];
    if (!$src || !storageIsReadable($src)) return ['ok' => false, 'error' => 'التخزين المصدر غير متاح'];
    $srcAbs = storageAbsExistingInside($src, (string) $item['src_rel']);
    if ($srcAbs === null || !is_file($srcAbs)) {
        /* استئناف بعد انقطاع وقع بين «تحديث القاعدة/حذف الأصل» و«تعليم العنصر منتهياً»:
           الأصل اختفى لأن النقل اكتمل فعلاً. نتحقق من ذلك بالدليل لا بالافتراض:
           الملف على الوجهة بالحجم المتوقع، والسجلات (إن وُجدت) تشير إليه. */
        $dstDone = storageAbs($dst, (string) $item['dst_rel']);
        if ($dstDone && is_file($dstDone) && ((int) $item['bytes'] === 0 || (int) filesize($dstDone) === (int) $item['bytes'])) {
            $linkedOk = true;
            if ($item['episode_id']) {
                $c = $pdo->prepare("SELECT COUNT(*) FROM episodes WHERE id=? AND storage_id=? AND relative_path=?");
                $c->execute([(int) $item['episode_id'], (int) $dst['id'], (string) $item['dst_rel']]);
                $linkedOk = (int) $c->fetchColumn() > 0;
            }
            if ($linkedOk) { $log('اكتمل سابقاً (استئناف): ' . $item['dst_rel']); return ['ok' => true, 'warning' => null]; }
        }
        return ['ok' => false, 'error' => 'الملف الأصلي غير موجود'];
    }
    $dstAbs = storageAbs($dst, (string) $item['dst_rel']);
    if ($dstAbs === null) return ['ok' => false, 'error' => 'مسار الوجهة غير صالح'];

    $size = (int) filesize($srcAbs);
    $pdo->prepare("UPDATE storage_job_items SET status='copying', bytes=? WHERE id=?")->execute([$size, (int) $item['id']]);
    $pdo->prepare("UPDATE storage_jobs SET current_file=?, heartbeat_at=NOW() WHERE id=?")->execute([mb_substr((string) $item['src_rel'], 0, 690), $jobId]);

    $dir = dirname($dstAbs);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return ['ok' => false, 'error' => 'تعذّر إنشاء المجلد على الوجهة: ' . $dir];

    // نقطة الأساس للتقدّم: مجموع ما اكتمل قبل هذا الملف
    $baseDone = (int) $pdo->query("SELECT COALESCE(SUM(bytes),0) FROM storage_job_items WHERE job_id = " . $jobId . " AND status = 'done'")->fetchColumn();

    $srcHash = null;
    if (is_file($dstAbs)) {
        // ملف بنفس الاسم على الوجهة: استئناف بعد انقطاع بين الإعادة والتسمية أو تعارض أسماء
        if ((int) filesize($dstAbs) === $size && hash_file('sha1', $dstAbs) === ($srcHash = hash_file('sha1', $srcAbs))) {
            $log('موجود ومطابق على الوجهة: ' . $item['dst_rel']);
        } else {
            return ['ok' => false, 'error' => 'يوجد ملف مختلف بنفس المسار على الوجهة: ' . $item['dst_rel']];
        }
    } else {
        $part = $dstAbs . '.shs-part';
        @unlink($part);
        $in = @fopen($srcAbs, 'rb');
        $out = @fopen($part, 'xb');
        if (!$in || !$out) { if ($in) fclose($in); if ($out) fclose($out); @unlink($part); return ['ok' => false, 'error' => 'تعذّر فتح الملفات للنسخ']; }
        $h = hash_init('sha1'); $copied = 0; $lastTick = microtime(true);
        /* حدّ سرعة اختياري: نقل تيرابايتات بسرعة القرص الكاملة يُجوّع قراءة البثّ
           للمشاهدين من نفس القرص. 0 = بلا حدّ. */
        $limit = (float) (storageSettings()['storage_move_limit_mb'] ?? 0) * 1048576;
        $tStart = microtime(true);
        while (!feof($in)) {
            $buf = fread($in, STORAGE_CHUNK);
            if ($buf === false) { fclose($in); fclose($out); @unlink($part); return ['ok' => false, 'error' => 'خطأ قراءة من المصدر']; }
            if ($buf === '') break;
            $w = fwrite($out, $buf);
            if ($w === false || $w !== strlen($buf)) { fclose($in); fclose($out); @unlink($part); return ['ok' => false, 'error' => 'خطأ كتابة على الوجهة (امتلأ القرص؟)']; }
            hash_update($h, $buf);
            $copied += $w;
            if ($limit > 0) {
                $ahead = $copied / $limit - (microtime(true) - $tStart);
                if ($ahead > 0) usleep((int) min(2000000, $ahead * 1000000));
            }
            if (microtime(true) - $lastTick >= 1.0) {
                $lastTick = microtime(true);
                $pdo->prepare("UPDATE storage_jobs SET done_bytes=?, heartbeat_at=NOW() WHERE id=?")->execute([$baseDone + $copied, $jobId]);
                if (storageJobStatus($jobId) === 'cancelling') { fclose($in); fclose($out); @unlink($part); return ['ok' => false, 'cancelled' => true, 'error' => 'أُلغي']; }
            }
        }
        fclose($in);
        fflush($out);
        if (function_exists('fsync')) { @fsync($out); }
        fclose($out);
        $srcHash = hash_final($h);

        // ── التحقق: الحجم ثم البصمة بإعادة القراءة من القرص الجديد ──
        $pdo->prepare("UPDATE storage_job_items SET status='verifying' WHERE id=?")->execute([(int) $item['id']]);
        clearstatcache(true, $part);
        if ((int) @filesize($part) !== $size || $copied !== $size) { @unlink($part); return ['ok' => false, 'error' => 'الحجم بعد النسخ لا يطابق الأصل']; }
        $dstHash = @hash_file('sha1', $part);
        if ($dstHash !== $srcHash) { @unlink($part); return ['ok' => false, 'error' => 'بصمة SHA-1 على الوجهة لا تطابق الأصل — لم يُحذف الأصل']; }
        if (!@rename($part, $dstAbs)) { @unlink($part); return ['ok' => false, 'error' => 'فشلت التسمية النهائية على الوجهة']; }
        @touch($dstAbs, (int) filemtime($srcAbs));
    }

    // ── تحديث قاعدة البيانات ثم (فقط بعدها) حذف الأصل ──
    $newUrl = storagePublicUrl($dst, (string) $item['dst_rel']);
    $oldUrlLegacy = !empty($src['is_legacy']) ? storagePublicUrl($src, (string) $item['src_rel']) : null;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE episodes SET storage_id=?, relative_path=?, stream_url=?, file_size=? WHERE storage_id=? AND relative_path=?")
            ->execute([(int) $dst['id'], (string) $item['dst_rel'], $newUrl, $size, (int) $src['id'], (string) $item['src_rel']]);
        // قنوات تشير لنفس الملف القديم (رُبط ملف مرفوع كقناة)
        if ($oldUrlLegacy !== null) {
            try {
                $pdo->prepare("UPDATE channels SET stream_url=? WHERE stream_url LIKE ?")
                    ->execute([$newUrl, '%/uploads/' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $item['src_rel'])]);
            } catch (Throwable $e) { /* لا عمود */ }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // السجلّ ما زال يشير للأصل السليم؛ النسخة الجديدة زائدة فنحذفها
        @unlink($dstAbs);
        return ['ok' => false, 'error' => 'فشل تحديث قاعدة البيانات — أُلغي النقل والأصل سليم: ' . $e->getMessage()];
    }
    $pdo->prepare("UPDATE storage_jobs SET done_bytes=? WHERE id=?")->execute([$baseDone + $size, $jobId]);

    $warning = null;
    if (!@unlink($srcAbs)) {
        $warning = 'نُقل بنجاح لكن تعذّر حذف النسخة الأصلية: ' . $item['src_rel'];
    } elseif (empty($src['is_legacy'])) {
        $root = rtrim((string) realpath((string) $src['mount_path']), '/');
        $d = dirname($srcAbs);
        while ($d !== $root && strpos($d, $root . '/') === 0 && @rmdir($d)) { $d = dirname($d); }
    }
    $log('تم: ' . $item['src_rel'] . ' → ' . $dst['name'] . ':' . $item['dst_rel']);
    return ['ok' => true, 'warning' => $warning];
}

} // function_exists
