<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  Multi-Storage — طبقة البيانات والترحيل
 * ───────────────────────────────────────────────────────────────────────────
 *  كل قرص وحدة تخزين مستقلة: صفّ في `storages` بمسار ربطه (mount) وهويّة
 *  نظام ملفاته (UUID). لا دمج ولا RAID ولا LVM — تعطّل قرص يعطّل محتواه وحده.
 *
 *  كل حلقة/فيلم يحمل (storage_id + relative_path) لا مساراً مطلقاً. المسار
 *  النهائي = storages.mount_path + '/' + relative_path. فتغيير نقطة الربط
 *  مستقبلاً يعني تعديل صفّ واحد في storages لا آلاف السجلات.
 *
 *  التوافق:
 *   • لا يُحذف ولا يُعاد تسمية أي عمود أو جدول قائم.
 *   • stream_url يبقى المصدر الذي يقرؤه كل الكود الحالي (API/المشغّل/الحذف).
 *     للأقراص الجديدة يُكتب رابطاً مستقراً عبر media.php لا يتضمّن نقطة الربط.
 *   • مجلد uploads الحالي يُسجَّل تلقائياً كتخزين «داخلي» (is_legacy=1)،
 *     وروابطه تبقى كما هي حرفياً — فلا يتغيّر أي سلوك قائم.
 *   • أعمدة وجداول بـ SHOW COLUMNS لا بـ ADD COLUMN IF NOT EXISTS
 *     (الأخيرة MariaDB فقط وتفشل على MySQL).
 *   • الترحيل خارج حارس ‎.schema_ok‎ وله علامته الخاصة، ولا تُكتب العلامة
 *     إلا بعد التحقق من اكتمال كل شيء فعلاً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('storageSchemaFlagFile')) {

function storageSchemaFlagFile(): string
{
    return dirname(__DIR__, 2) . '/storage/.multi_storage_v1';
}

/** أعمدة جدول كقاموس بأحرف صغيرة. */
function storageTableColumns(PDO $pdo, string $table): array
{
    $out = [];
    try {
        foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $out[strtolower((string) $c['Field'])] = true;
        }
    } catch (Throwable $e) { /* الجدول غير موجود */ }
    return $out;
}

function storageTableExists(PDO $pdo, string $table): bool
{
    try { $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1"); return true; }
    catch (Throwable $e) { return false; }
}

/**
 * جلسات الرفع المتجزئ. تبقى مستقلة عن ترحيل v1 لأن مواقع التثبيت التي
 * اكتمل عندها الترحيل تملك علامة .multi_storage_v1 ولن تدخل DDL القديم.
 * كل جلسة تحجز وجهتها قبل بدء أول جزء، حتى لا تلتقي رفعتان على نفس المساحة.
 */
function storageEnsureUploadSessionSchema(PDO $pdo): bool
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS storage_upload_sessions (
            token CHAR(48) NOT NULL PRIMARY KEY,
            storage_id INT NOT NULL,
            rel_path VARCHAR(700) NOT NULL,
            part_rel VARCHAR(700) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            total_bytes BIGINT UNSIGNED NOT NULL,
            received_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'uploading',
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            KEY idx_sus_state (status, updated_at),
            KEY idx_sus_storage (storage_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    } catch (Throwable $e) {
        error_log('storage upload-session schema: ' . $e->getMessage());
        return false;
    }
}

/**
 * ينشئ الجداول ويضيف الأعمدة ويسجّل التخزين الداخلي ويربط الحلقات الموجودة به.
 * آمن للتكرار. يعيد true عند اكتمال كل شيء.
 */
function storageEnsureSchema(?PDO $pdo = null, bool $force = false): bool
{
    static $done = null;
    if ($done !== null && !$force) return $done;

    $flag = storageSchemaFlagFile();
    if (!$force && is_file($flag)) { return $done = true; }

    if (!$pdo) { $pdo = function_exists('db') ? db() : null; }
    if (!$pdo instanceof PDO) return $done = false;

    $ok = true;
    $ddl = [
        "CREATE TABLE IF NOT EXISTS storages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            content_type VARCHAR(20) NOT NULL DEFAULT 'mixed',
            device VARCHAR(255) NULL,
            fs_uuid VARCHAR(64) NULL,
            fs_type VARCHAR(20) NULL,
            mount_path VARCHAR(500) NOT NULL,
            is_legacy TINYINT(1) NOT NULL DEFAULT 0,
            admin_state VARCHAR(20) NOT NULL DEFAULT 'active',
            status VARCHAR(20) NOT NULL DEFAULT 'offline',
            status_msg VARCHAR(500) NULL,
            health VARCHAR(20) NOT NULL DEFAULT 'unknown',
            total_bytes BIGINT UNSIGNED NULL,
            used_bytes BIGINT UNSIGNED NULL,
            free_bytes BIGINT UNSIGNED NULL,
            min_free_gb INT NULL,
            min_free_pct DECIMAL(5,2) NULL,
            priority INT NOT NULL DEFAULT 0,
            last_check_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            KEY idx_st_state (admin_state, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS storage_paths (
            id INT AUTO_INCREMENT PRIMARY KEY,
            storage_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            rel_path VARCHAR(255) NOT NULL,
            purpose VARCHAR(20) NOT NULL DEFAULT 'other',
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sp_storage (storage_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS storage_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(20) NOT NULL DEFAULT 'move',
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            source_storage_id INT NULL,
            dest_storage_id INT NOT NULL,
            dest_path_id INT NULL,
            scope VARCHAR(20) NOT NULL DEFAULT 'all',
            scope_ref VARCHAR(500) NULL,
            scope_label VARCHAR(255) NULL,
            remove_source_after TINYINT(1) NOT NULL DEFAULT 0,
            total_files INT NOT NULL DEFAULT 0,
            done_files INT NOT NULL DEFAULT 0,
            failed_files INT NOT NULL DEFAULT 0,
            total_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            done_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            current_file VARCHAR(700) NULL,
            error TEXT NULL,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME NULL,
            heartbeat_at DATETIME NULL,
            finished_at DATETIME NULL,
            KEY idx_sj_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS storage_job_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id INT NOT NULL,
            episode_id INT NULL,
            src_storage_id INT NOT NULL,
            src_rel VARCHAR(700) NOT NULL,
            dst_rel VARCHAR(700) NOT NULL,
            bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            error VARCHAR(500) NULL,
            KEY idx_sji_job (job_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS storage_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alert_key VARCHAR(191) NOT NULL,
            storage_id INT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'warning',
            code VARCHAR(40) NOT NULL,
            message VARCHAR(500) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            resolved_at DATETIME NULL,
            KEY idx_sa_active (is_active, level),
            KEY idx_sa_key (alert_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS storage_disks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            disk_key VARCHAR(191) NOT NULL,
            path VARCHAR(100) NULL,
            model VARCHAR(150) NULL,
            vendor VARCHAR(100) NULL,
            serial VARCHAR(150) NULL,
            media VARCHAR(10) NULL,
            size_bytes BIGINT UNSIGNED NULL,
            acknowledged TINYINT(1) NOT NULL DEFAULT 0,
            present TINYINT(1) NOT NULL DEFAULT 1,
            smart_status VARCHAR(20) NULL,
            smart_temp INT NULL,
            smart_checked_at DATETIME NULL,
            first_seen DATETIME NULL,
            last_seen DATETIME NULL,
            UNIQUE KEY uq_sd_key (disk_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($ddl as $sql) {
        try { $pdo->exec($sql); }
        catch (PDOException $e) { $ok = false; error_log('storage schema: ' . $e->getMessage()); }
    }

    // ── أعمدة الحلقات: موقع الملف (قرص + مسار نسبي) ──
    $epCols = storageTableColumns($pdo, 'episodes');
    if ($epCols) {
        $need = [
            'storage_id'    => "ADD COLUMN storage_id INT NULL DEFAULT NULL",
            'relative_path' => "ADD COLUMN relative_path VARCHAR(700) NULL DEFAULT NULL",
            'file_size'     => "ADD COLUMN file_size BIGINT UNSIGNED NULL DEFAULT NULL",
        ];
        foreach ($need as $col => $ddlCol) {
            if (isset($epCols[$col])) continue;
            try { $pdo->exec("ALTER TABLE episodes {$ddlCol}"); }
            catch (PDOException $e) { $ok = false; error_log('episodes.' . $col . ': ' . $e->getMessage()); }
        }
        try { $pdo->exec("ALTER TABLE episodes ADD INDEX idx_ep_storage (storage_id)"); }
        catch (PDOException $e) { /* الفهرس موجود */ }
    }

    // ── تسجيل مجلد uploads كتخزين داخلي + ربط الحلقات الموجودة به ──
    try { storageRegisterLegacy($pdo); }
    catch (Throwable $e) { $ok = false; error_log('storage legacy: ' . $e->getMessage()); }

    // التحقق الفعلي قبل كتابة العلامة
    foreach (['storages', 'storage_paths', 'storage_jobs', 'storage_job_items', 'storage_alerts', 'storage_disks'] as $t) {
        if (!storageTableExists($pdo, $t)) { $ok = false; }
    }
    $epCols = storageTableColumns($pdo, 'episodes');
    if ($epCols && (!isset($epCols['storage_id']) || !isset($epCols['relative_path']))) { $ok = false; }

    if ($ok) {
        $dir = dirname($flag);
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        @file_put_contents($flag, date('c'), LOCK_EX);
    }
    return $done = $ok;
}

/**
 * التخزين الداخلي = مجلد uploads القائم. روابطه تبقى رابط Apache المباشر
 * كما هي، لكن يصبح له storage_id حتى يستطيع النظام نقل محتواه لاحقاً
 * إلى الأقراص الجديدة ويعرف موقع كل ملف.
 */
function storageRegisterLegacy(PDO $pdo): int
{
    $uploads = realpath(dirname(__DIR__, 2) . '/uploads');
    if ($uploads === false) {
        @mkdir(dirname(__DIR__, 2) . '/uploads', 0755, true);
        $uploads = realpath(dirname(__DIR__, 2) . '/uploads');
    }
    if ($uploads === false) return 0;

    $id = (int) $pdo->query("SELECT id FROM storages WHERE is_legacy = 1 ORDER BY id LIMIT 1")->fetchColumn();
    if ($id <= 0) {
        $pdo->prepare("INSERT INTO storages (name, content_type, mount_path, is_legacy, admin_state, status, health, priority)
                       VALUES (?, 'mixed', ?, 1, 'active', 'online', 'ok', -100)")
            ->execute(['التخزين الداخلي (uploads)', $uploads]);
        $id = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO storage_paths (storage_id, name, rel_path, purpose, is_default) VALUES (?,?,?,?,?)");
        foreach ([['رفع عام', 'videos', 'uploads', 1], ['مدمجة', 'merged', 'other', 0], ['حلقات منشورة', 'series', 'series', 0]] as $p) {
            $ins->execute([$id, $p[0], $p[1], $p[2], $p[3]]);
        }
    } else {
        // المشروع قد يُنقل لمجلد آخر: نقطة الربط تتبعه دائماً
        $pdo->prepare("UPDATE storages SET mount_path = ? WHERE id = ? AND mount_path <> ?")->execute([$uploads, $id, $uploads]);
    }

    /* ربط الحلقات القائمة: رابطها يشير إلى /uploads/(videos|merged|series)/<file>.
       نحفظ الجزء بعد uploads/ كمسار نسبي. لا نلمس stream_url إطلاقاً. */
    $st = $pdo->query("SELECT id, stream_url FROM episodes
                        WHERE storage_id IS NULL AND stream_url LIKE '%/uploads/%'");
    $upd = $pdo->prepare("UPDATE episodes SET storage_id = ?, relative_path = ?, file_size = ? WHERE id = ? AND storage_id IS NULL");
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $p = (string) parse_url((string) $row['stream_url'], PHP_URL_PATH);
        if ($p === '') { $p = (string) $row['stream_url']; }
        $p = rawurldecode($p);
        $pos = strpos($p, '/uploads/');
        if ($pos === false) continue;
        $rel = ltrim(substr($p, $pos + 9), '/');
        if (!preg_match('#^(videos|merged|series)/[^/]+$#', $rel)) continue;   // ملفات فيديو فقط
        if (strpos($rel, '..') !== false) continue;
        $abs = $uploads . '/' . $rel;
        $upd->execute([$id, $rel, is_file($abs) ? (int) @filesize($abs) : null, (int) $row['id']]);
    }
    return $id;
}

} // function_exists
