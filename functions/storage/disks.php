<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  Multi-Storage — اكتشاف الأقراص + عميل أداة الجذر + SMART
 * ───────────────────────────────────────────────────────────────────────────
 *  القراءة (lsblk و/proc) لا تحتاج صلاحيات، فالكشف والعرض يعملان دائماً.
 *  العمليات المعدِّلة (تقسيم/تهيئة/ربط) وSMART تمرّ حصراً عبر
 *  tools/shs-storage-helper المثبّتة في /usr/local/sbin بسطر sudoers واحد.
 *  لا يوجد أي مسار في PHP ينفّذ parted/mkfs/mount مباشرة.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('storageHelperPath')) {

function storageHelperPath(): string
{
    return defined('SHS_STORAGE_HELPER') ? (string) SHS_STORAGE_HELPER : '/usr/local/sbin/shs-storage-helper';
}

/**
 * تشغيل أداة الجذر. كل وسيط يمرّ عبر escapeshellarg، والأداة نفسها تتحقق
 * منه مجدداً بتعبير صارم — طبقتان مستقلتان.
 * @return array ['ok'=>bool, 'error'=>?string, ...بقية حقول JSON]
 */
function storageHelperRun(array $args, int $timeout = 180): array
{
    if (!storageCanExec()) return ['ok' => false, 'error' => 'الدالة exec معطّلة في إعدادات PHP (disable_functions)'];
    $helper = storageHelperPath();
    $cmd = 'sudo -n ' . escapeshellarg($helper);
    foreach ($args as $a) { $cmd .= ' ' . escapeshellarg((string) $a); }
    if (is_file('/usr/bin/timeout')) { $cmd = '/usr/bin/timeout ' . (int) $timeout . ' ' . $cmd; }
    $out = []; $rc = 0;
    @exec($cmd . ' 2>&1', $out, $rc);
    // آخر سطر JSON هو النتيجة (قد تسبقه تحذيرات النظام)
    for ($i = count($out) - 1; $i >= 0; $i--) {
        $line = trim((string) $out[$i]);
        if ($line !== '' && $line[0] === '{') {
            $j = json_decode($line, true);
            if (is_array($j)) return $j + ['ok' => false];
        }
    }
    $txt = trim(implode("\n", $out));
    if (stripos($txt, 'password is required') !== false || stripos($txt, 'a terminal is required') !== false || stripos($txt, 'not allowed') !== false) {
        return ['ok' => false, 'error' => 'أداة الجذر غير مسموحة لمستخدم الويب — شغّل أداة التثبيت مرة واحدة: sudo bash tools/install_storage.sh', 'not_installed' => true];
    }
    if ($rc === 127 || stripos($txt, 'command not found') !== false || stripos($txt, 'No such file') !== false) {
        return ['ok' => false, 'error' => 'أداة الجذر غير مثبّتة — شغّل: sudo bash tools/install_storage.sh', 'not_installed' => true];
    }
    return ['ok' => false, 'error' => 'استجابة غير متوقعة من أداة الجذر (رمز ' . $rc . '): ' . mb_substr($txt, 0, 300)];
}

/**
 * إعادة تشغيل مُثبّت التخزين من زر الإدارة. لا تُمرر من الواجهة أي وسائط:
 * المسار والمستخدم ثابتان، ويُرفض المصدر إن لم يكن root هو مالكه أو كان
 * قابلاً للكتابة من المجموعة/العموم. الاستثناء في sudo يطابق هذا الأمر فقط.
 */
function storageInstallerRun(int $timeout = 600): array
{
    if (!storageCanExec()) return ['ok' => false, 'error' => 'الدالة exec معطّلة في إعدادات PHP'];
    $script = realpath(dirname(__DIR__, 2) . '/tools/install_storage.sh');
    if ($script === false || !is_file($script)) return ['ok' => false, 'error' => 'ملف مُثبّت التخزين غير موجود'];
    $mode = (int) @fileperms($script) & 0777;
    if ((int) @fileowner($script) !== 0 || ($mode & 0022) !== 0) {
        return ['ok' => false, 'error' => 'رُفض التشغيل: ملف المُثبّت يجب أن يكون مملوكاً لـroot وغير قابل للكتابة من الويب'];
    }
    $cmd = 'sudo -n /bin/bash ' . escapeshellarg($script) . ' --web-user www-data';
    if (is_file('/usr/bin/timeout')) $cmd = '/usr/bin/timeout ' . max(30, min(900, $timeout)) . ' ' . $cmd;
    $out = []; $rc = 0; @exec($cmd . ' 2>&1', $out, $rc);
    $text = trim(implode("\n", $out));
    if ($rc !== 0) {
        if (stripos($text, 'not allowed') !== false || stripos($text, 'password is required') !== false) {
            return ['ok' => false, 'error' => 'زر الإعداد غير مفعّل بعد. شغّل المُثبّت الرئيسي مرة واحدة كـroot لتفعيل الاستثناء المحدد.'];
        }
        return ['ok' => false, 'error' => 'فشل إعداد التخزين: ' . mb_substr($text ?: 'رمز ' . $rc, 0, 400)];
    }
    if (function_exists('cacheDelete')) cacheDelete('storage_helper_status');
    return ['ok' => true, 'message' => 'تم إعداد أداة التخزين والعامل المجدول بنجاح'];
}

/** هل الأداة مثبّتة ومسموحة؟ (مع كاش قصير) */
function storageHelperStatus(bool $fresh = false): array
{
    if (!$fresh && function_exists('cacheGet')) {
        $c = cacheGet('storage_helper_status');
        if (is_array($c)) return $c;
    }
    $r = storageHelperRun(['version'], 15);
    $st = [
        'installed'  => !empty($r['ok']),
        'version'    => (int) ($r['version'] ?? 0),
        'allow_loop' => !empty($r['allow_loop']),
        'prefixes'   => (string) ($r['prefixes'] ?? '/mnt/ /srv/storage/ /media/storage/'),
        'error'      => empty($r['ok']) ? (string) ($r['error'] ?? '') : '',
    ];
    if (function_exists('cacheSet')) cacheSet('storage_helper_status', $st, 60);
    return $st;
}

/* ═════════════════════════════ lsblk ═════════════════════════════ */

function storageLsblk(): array
{
    if (!storageCanExec()) return [];
    // PATH أُضيف في util-linux 2.33؛ على الأقدم نعيد المحاولة بلا الأعمدة الحديثة
    $sets = [
        'NAME,PATH,TYPE,SIZE,ROTA,TRAN,MODEL,VENDOR,SERIAL,WWN,FSTYPE,UUID,LABEL,MOUNTPOINT,PKNAME,RM,RO,HOTPLUG',
        'NAME,TYPE,SIZE,ROTA,TRAN,MODEL,VENDOR,SERIAL,FSTYPE,UUID,LABEL,MOUNTPOINT,RM,RO',
        'NAME,TYPE,SIZE,ROTA,MODEL,FSTYPE,UUID,MOUNTPOINT',
    ];
    foreach ($sets as $cols) {
        $out = []; $rc = 0;
        @exec('lsblk -J -b -o ' . $cols . ' 2>/dev/null', $out, $rc);
        if ($rc === 0 && $out) {
            $j = json_decode(implode("\n", $out), true);
            if (is_array($j) && isset($j['blockdevices'])) return $j['blockdevices'];
        }
    }
    return [];
}

/** أقراص النظام (تحمل / أو /boot أو swap ...) — تُستبعد من كل عملية مدمّرة. */
function storageSystemDiskPaths(array $tree): array
{
    $critical = ['/', '/boot', '/boot/efi', '/usr', '/var', '/home', '[SWAP]'];
    $swaps = [];
    $raw = @file_get_contents('/proc/swaps');
    if (is_string($raw)) { foreach (array_slice(explode("\n", $raw), 1) as $l) { $f = preg_split('/\s+/', trim($l)); if (!empty($f[0])) $swaps[] = $f[0]; } }

    $out = [];
    $walk = static function (array $node, string $diskPath) use (&$walk, &$out, $critical, $swaps) {
        $path = (string) ($node['path'] ?? ('/dev/' . ($node['name'] ?? '')));
        $mp = $node['mountpoint'] ?? null;
        if (($mp !== null && in_array($mp, $critical, true)) || in_array($path, $swaps, true)) { $out[$diskPath] = true; }
        foreach (($node['children'] ?? []) as $ch) { $walk($ch, $diskPath); }
    };
    foreach ($tree as $d) {
        $p = (string) ($d['path'] ?? ('/dev/' . ($d['name'] ?? '')));
        $walk($d, $p);
    }
    // جذر على جهاز mapper/lvm: lsblk يضعه ابناً للقرص، فالمشي أعلاه يلتقطه
    return array_keys($out);
}

/**
 * قائمة الأقراص الفعلية بكل التفاصيل المطلوبة للعرض.
 */
function storageListDisks(): array
{
    $tree = storageLsblk();
    if (!$tree) return [];
    $system = array_flip(storageSystemDiskPaths($tree));
    $mounts = storageMountTable(true);
    $bySrc  = [];
    foreach ($mounts as $target => $m) { $bySrc[$m['source']][] = $target; }

    $allowLoop = false;
    $hs = storageHelperStatus();
    if (!empty($hs['allow_loop'])) $allowLoop = true;

    // التخزين المسجَّل: نربطه بالقرص عبر UUID أو نقطة الربط
    // التخزين الذي فُكّ ربطه وأُزيل لا يحجز القرص. يبقى سجله للمحتوى
    // القديم فقط، بينما يظهر الجهاز في «إدارة القرص» قابلاً للتهيئة.
    $storages = storageAll();
    $byUuid = []; $byMount = [];
    foreach ($storages as $s) {
        if (!empty($s['fs_uuid'])) $byUuid[strtolower((string) $s['fs_uuid'])] = $s;
        $byMount[rtrim((string) $s['mount_path'], '/')] = $s;
    }

    $known = [];
    try {
        foreach (db()->query("SELECT * FROM storage_disks")->fetchAll(PDO::FETCH_ASSOC) as $r) { $known[$r['disk_key']] = $r; }
    } catch (Throwable $e) {}

    $disks = [];
    foreach ($tree as $d) {
        $type = (string) ($d['type'] ?? '');
        if ($type !== 'disk' && !($type === 'loop' && $allowLoop)) continue;
        $path = (string) ($d['path'] ?? ('/dev/' . $d['name']));
        // أقراص الذاكرة (zram/ram) والأجهزة بلا سعة ليست أقراص تخزين
        if (preg_match('/^(zram|ram|fd|sr)\d+$/', (string) $d['name'])) continue;
        if ((int) ($d['size'] ?? 0) <= 0) continue;

        $parts = [];
        $flat = static function (array $n, int $depth) use (&$flat, &$parts, $bySrc, $mounts, $byUuid, $byMount) {
            foreach (($n['children'] ?? []) as $c) {
                $cp = (string) ($c['path'] ?? ('/dev/' . $c['name']));
                $mp = $c['mountpoint'] ?? null;
                if (!$mp && isset($bySrc[$cp])) $mp = $bySrc[$cp][0];     // lsblk بلا udev لا يعرف الربط أحياناً
                $fst = $c['fstype'] ?? null;
                if (!$fst && $mp && isset($mounts[$mp])) $fst = $mounts[$mp]['fstype'];
                if (!$fst && ($c['type'] ?? '') === 'part') { $fst = (storageDeviceProbe($cp)['fs'] ?? '') ?: null; }
                $uuid = $c['uuid'] ?? null;
                if (!$uuid) $uuid = storageDeviceUuid($cp) ?: null;
                $linked = null;
                if ($uuid && isset($byUuid[strtolower($uuid)])) $linked = $byUuid[strtolower($uuid)];
                elseif ($mp && isset($byMount[rtrim($mp, '/')])) $linked = $byMount[rtrim($mp, '/')];
                $parts[] = [
                    'path' => $cp, 'type' => $c['type'] ?? '', 'size' => (int) ($c['size'] ?? 0),
                    'fstype' => $fst, 'uuid' => $uuid, 'label' => $c['label'] ?? null,
                    'mountpoint' => $mp, 'depth' => $depth,
                    'storage_id' => $linked ? (int) $linked['id'] : null,
                    'storage_name' => $linked ? (string) $linked['name'] : null,
                ];
                $flat($c, $depth + 1);
            }
        };
        $flat($d, 0);

        $diskMp = $d['mountpoint'] ?? null;
        if (!$diskMp && isset($bySrc[$path])) $diskMp = $bySrc[$path][0];
        $members = [];
        foreach (array_merge([['fstype' => $d['fstype'] ?? null]], $parts) as $p) {
            if (in_array($p['fstype'] ?? '', ['zfs_member', 'LVM2_member', 'linux_raid_member', 'crypto_LUKS'], true)) $members[] = $p['fstype'];
        }
        $mounted = array_values(array_filter(array_merge([$diskMp], array_column($parts, 'mountpoint'))));

        $tran = strtolower((string) ($d['tran'] ?? ''));
        $media = $type === 'loop' ? 'LOOP' : ($tran === 'nvme' || strpos($path, 'nvme') !== false ? 'NVMe' : ((string) ($d['rota'] ?? '1') === '1' || $d['rota'] === true ? 'HDD' : 'SSD'));
        $serial = trim((string) ($d['serial'] ?? ''));
        $wwn    = trim((string) ($d['wwn'] ?? ''));
        $key = $serial !== '' ? 'sn:' . trim(($d['model'] ?? '') . '|' . $serial)
             : ($wwn !== '' ? 'wwn:' . $wwn : 'path:' . $path . ':' . (int) $d['size']);

        $isSystem = isset($system[$path]);
        $linkedIds = array_values(array_unique(array_filter(array_column($parts, 'storage_id'))));
        $k = $known[$key] ?? null;

        $disks[] = [
            'key' => $key, 'path' => $path, 'name' => (string) $d['name'], 'type' => $type,
            'model' => trim((string) ($d['model'] ?? '')), 'vendor' => trim((string) ($d['vendor'] ?? '')),
            'serial' => $serial, 'media' => $media, 'transport' => $tran, 'size' => (int) ($d['size'] ?? 0),
            'removable' => !empty($d['rm']) && $d['rm'] !== '0', 'readonly' => !empty($d['ro']) && $d['ro'] !== '0',
            'fstype' => $d['fstype'] ?? null, 'partitions' => $parts,
            // مركّب مباشرة (بلا أقسام) = مستخدم أيضاً — lsblk بلا udev لا يعرف نوعه
            'used' => (bool) ($parts || !empty($d['fstype']) || $mounted), 'mounted' => $mounted,
            'is_system' => $isSystem, 'members' => array_values(array_unique($members)),
            'storage_ids' => $linkedIds,
            'smart_status' => $k['smart_status'] ?? null, 'smart_temp' => isset($k['smart_temp']) ? (int) $k['smart_temp'] : null,
            'smart_checked_at' => $k['smart_checked_at'] ?? null,
            'acknowledged' => $k ? (int) $k['acknowledged'] === 1 : false,
            'first_seen' => $k['first_seen'] ?? null,
        ];
    }
    return $disks;
}

/**
 * يسجّل الأقراص في storage_disks ويُطلق إشعار «تم اكتشاف قرص تخزين جديد»
 * لكل قرص غير نظامي لم يُرَ من قبل. أوّل تشغيل: أقراص النظام والمستخدمة
 * في تخزين تُعلَّم كمعروفة، والقرص الإضافي غير المستخدم يُبلَّغ عنه (مفيد).
 */
function storageDetectDisks(?array $disks = null): array
{
    $disks = $disks ?? storageListDisks();
    $pdo = db();
    $new = [];
    try {
        $firstRun = (int) $pdo->query("SELECT COUNT(*) FROM storage_disks")->fetchColumn() === 0;
        $seenKeys = [];
        $sel = $pdo->prepare("SELECT id, acknowledged, present FROM storage_disks WHERE disk_key = ?");
        $ins = $pdo->prepare("INSERT INTO storage_disks (disk_key, path, model, vendor, serial, media, size_bytes, acknowledged, present, first_seen, last_seen)
                              VALUES (?,?,?,?,?,?,?,?,1,NOW(),NOW())");
        $upd = $pdo->prepare("UPDATE storage_disks SET path=?, model=?, vendor=?, media=?, size_bytes=?, present=1, last_seen=NOW() WHERE id=?");
        foreach ($disks as $d) {
            $seenKeys[] = $d['key'];
            $sel->execute([$d['key']]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $upd->execute([$d['path'], $d['model'], $d['vendor'], $d['media'], $d['size'], (int) $row['id']]);
                if ((int) $row['present'] === 0 && $d['storage_ids']) {
                    storageAlertResolve('disk:' . $d['key'] . ':missing');
                }
                if ((int) $row['acknowledged'] === 0 && !$d['is_system']) { $new[] = $d; }
                continue;
            }
            $ack = ($d['is_system'] || $d['storage_ids'] || ($firstRun && $d['mounted'])) ? 1 : 0;
            $ins->execute([$d['key'], $d['path'], $d['model'], $d['vendor'], $d['serial'], $d['media'], $d['size'], $ack]);
            if (!$ack) {
                $new[] = $d;
                storageAlertRaise('disk:' . $d['key'] . ':new', null, 'info', 'new_disk',
                    'تم اكتشاف قرص تخزين جديد: ' . $d['path'] . ' — ' . trim($d['vendor'] . ' ' . $d['model']) . ' — ' . storageHumanBytes($d['size']) . ' (' . $d['media'] . ')');
            }
        }
        // قرص كان حاضراً واختفى: إن كان يحمل تخزيناً فهو حدث حرج
        $rows = $pdo->query("SELECT id, disk_key, path, model FROM storage_disks WHERE present = 1")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            if (in_array($r['disk_key'], $seenKeys, true)) continue;
            $pdo->prepare("UPDATE storage_disks SET present = 0 WHERE id = ?")->execute([(int) $r['id']]);
            storageAlertRaise('disk:' . $r['disk_key'] . ':missing', null, 'critical', 'disk_missing',
                'اختفى القرص ' . $r['path'] . ' (' . $r['model'] . ') من السيرفر');
            storageAlertResolve('disk:' . $r['disk_key'] . ':new');
        }
    } catch (Throwable $e) { error_log('storageDetectDisks: ' . $e->getMessage()); }
    return $new;
}

function storageDiskAcknowledge(string $key): void
{
    db()->prepare("UPDATE storage_disks SET acknowledged = 1 WHERE disk_key = ?")->execute([$key]);
    storageAlertResolve('disk:' . $key . ':new');
}

/**
 * SMART لكل الأقراص الحاضرة (بفاصل زمني). يحدّث storage_disks، ويرفع تنبيهاً
 * حرجاً عند فشل الفحص ويعلّم التخزين على ذلك القرص failing فيُستبعد من
 * الكتابة الجديدة (تبقى القراءة متاحة لإنقاذ المحتوى).
 */
function storageSmartRefresh(?array $disks = null, bool $force = false): array
{
    $hs = storageHelperStatus();
    if (empty($hs['installed'])) return ['skipped' => 'helper'];
    $disks = $disks ?? storageListDisks();
    $minutes = max(5, (int) storageSettings()['storage_smart_minutes']);
    $done = [];
    foreach ($disks as $d) {
        if ($d['type'] !== 'disk') continue;
        if (!$force && $d['smart_checked_at'] && strtotime((string) $d['smart_checked_at']) > time() - $minutes * 60) continue;
        $r = storageHelperRun(['smart', $d['path']], 60);
        $status = 'unknown'; $temp = null;
        if (!empty($r['ok']) && !empty($r['supported']) && is_array($r['smart'] ?? null)) {
            $sm = $r['smart'];
            if (isset($sm['smart_status']['passed'])) $status = $sm['smart_status']['passed'] ? 'passed' : 'failed';
            if (isset($sm['temperature']['current'])) $temp = (int) $sm['temperature']['current'];
            // smartctl: البت 3 = القرص في حالة فشل؛ البت 4 = سمات تجاوزت العتبة سابقاً
            $rc = (int) ($r['rc'] ?? 0);
            if ($rc & 8) $status = 'failed';
            elseif (($rc & 16) && $status === 'passed') $status = 'warning';
            if ($rc & 2 && $status === 'unknown') $status = 'standby';
        } elseif (!empty($r['ok'])) {
            $status = 'unsupported';
        }
        try {
            db()->prepare("UPDATE storage_disks SET smart_status=?, smart_temp=?, smart_checked_at=NOW() WHERE disk_key=?")
                ->execute([$status, $temp, $d['key']]);
        } catch (Throwable $e) {}

        $akey = 'disk:' . $d['key'] . ':smart';
        if ($status === 'failed') {
            storageAlertRaise($akey, null, 'critical', 'smart_fail', 'SMART يُبلغ عن عطل في القرص ' . $d['path'] . ' (' . $d['model'] . ') — انقل محتواه فوراً');
        } elseif ($status === 'warning') {
            storageAlertRaise($akey, null, 'warning', 'smart_warn', 'سمات SMART تجاوزت العتبة سابقاً على ' . $d['path'] . ' (' . $d['model'] . ')');
        } else {
            storageAlertResolve($akey);
        }
        if ($temp !== null && $temp >= 60) {
            storageAlertRaise('disk:' . $d['key'] . ':temp', null, 'warning', 'disk_hot', 'حرارة القرص ' . $d['path'] . ' مرتفعة: ' . $temp . '°C');
        } else {
            storageAlertResolve('disk:' . $d['key'] . ':temp');
        }
        // انعكاس الصحة على التخزين الذي يسكن هذا القرص
        foreach ($d['storage_ids'] as $sid) {
            $health = $status === 'failed' ? 'failing' : ($status === 'warning' ? 'warning' : ($status === 'passed' ? 'ok' : 'unknown'));
            try { db()->prepare("UPDATE storages SET health=? WHERE id=?")->execute([$health, (int) $sid]); } catch (Throwable $e) {}
        }
        $done[] = ['disk' => $d['path'], 'status' => $status, 'temp' => $temp];
    }
    return $done;
}

function storageHumanBytes($b): string
{
    $b = (float) $b;
    $u = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
    return ($i >= 3 ? number_format($b, 2) : number_format($b, $i ? 1 : 0)) . ' ' . $u[$i];
}

} // function_exists
