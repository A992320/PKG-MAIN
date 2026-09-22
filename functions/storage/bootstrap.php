<?php
/**
 * نقطة الدخول الوحيدة لنظام Multi-Storage.
 *   require_once __DIR__ . '/functions/storage/bootstrap.php';
 * تُحمّل الوحدات وتضمن الجداول (مرة واحدة بعلامة ملف).
 */
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/disks.php';
require_once __DIR__ . '/jobs.php';

if (!function_exists('storageMonitorRun')) {
    /**
     * دورة المراقبة: حالة كل تخزين + كشف الأقراص + SMART + ربط الحلقات الجديدة.
     * كل خطوة معزولة: فشل خطوة لا يمنع الأخريات.
     */
    function storageMonitorRun(bool $forceSmart = false): array
    {
        $out = ['storages' => null, 'new_disks' => 0, 'smart' => null];
        try { $out['remounted'] = storageAutoRemount(); } catch (Throwable $e) { error_log('monitor remount: ' . $e->getMessage()); }
        try { $out['storages'] = storageRefreshAll(); } catch (Throwable $e) { error_log('monitor storages: ' . $e->getMessage()); }
        $disks = null;
        try { $disks = storageListDisks(); $out['new_disks'] = count(storageDetectDisks($disks)); } catch (Throwable $e) { error_log('monitor disks: ' . $e->getMessage()); }
        try { $out['smart'] = storageSmartRefresh($disks, $forceSmart); } catch (Throwable $e) { error_log('monitor smart: ' . $e->getMessage()); }
        // حلقات نُشرت بطرق لا تمرّ بالخطّاف (استيراد قديم مثلاً): ربط دوري خفيف
        try {
            $last = (int) (storageSettings()['storage_backfill_at'] ?? 0);
            if (time() - $last > 1800) {
                storageRegisterLegacy(db());
                storageSettingSet('storage_backfill_at', (string) time());
            }
        } catch (Throwable $e) {}
        try { storageSettingSet('storage_monitor_at', (string) time()); } catch (Throwable $e) {}
        return $out;
    }
}

if (function_exists('db')) {
    try {
        storageEnsureSchema(db());
        // جلسات الرفع المتجزئ لها ترحيل مستقل كي تعمل أيضاً على قواعد
        // البيانات التي انتهى عندها ترحيل التخزين الأول مسبقاً.
        storageEnsureUploadSessionSchema(db());
    } catch (Throwable $e) { error_log('storage bootstrap: ' . $e->getMessage()); }
}
