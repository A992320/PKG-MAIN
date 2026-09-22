<?php
/**
 * مجموعات الصفحة الرئيسية القابلة للإدارة.
 *
 * هذه الطبقة مستقلة عن جدول categories عمداً: حذف بطاقة من Home لا يحذف
 * قسماً ولا قناة ولا فيلماً. جدول الربط يحدد فقط أي فروع تظهر تحت البطاقة.
 */

function homeGroupsAllowedTypes(): array
{
    return ['auto', 'live', 'movie', 'series', 'kids', 'sports', 'news', 'documentary', 'anime', 'action', 'comedy', 'drama', 'music'];
}

function homeGroupsEnsureSchema(): bool
{
    static $done = null;
    if ($done !== null) return $done;

    $flag = (defined('CACHE_DIR') ? CACHE_DIR : dirname(__DIR__) . '/storage/cache')
        . '/.home_groups_schema_v1';

    try {
        $pdo = function_exists('db') ? db() : ($GLOBALS['pdo'] ?? null);
        if (!$pdo instanceof PDO) return $done = false;

        if (is_file($flag)) {
            // لا نكتفي بوجود ملف العلامة: قد يبقى من نسخة قديمة بعد استعادة/تبديل
            // قاعدة بيانات لا تحتوي هذين الجدولين، فيفشل الحفظ لاحقاً بصمت لأن
            // الكود يظن أن الجدولين موجودان. نتحقق فعلياً قبل الوثوق بالعلامة.
            $t1 = $pdo->query("SHOW TABLES LIKE 'home_groups'")->fetchColumn();
            $t2 = $t1 ? $pdo->query("SHOW TABLES LIKE 'home_group_categories'")->fetchColumn() : false;
            if ($t1 && $t2) return $done = true;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS home_groups (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            content_type VARCHAR(24) NOT NULL DEFAULT 'auto',
            accent_color CHAR(7) NOT NULL DEFAULT '#38bdf8',
            display_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_home_groups_slug (slug),
            KEY idx_home_groups_active_order (is_active, display_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS home_group_categories (
            group_id INT UNSIGNED NOT NULL,
            category_id INT NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (group_id, category_id),
            KEY idx_home_group_categories_category (category_id),
            KEY idx_home_group_categories_order (group_id, display_order, category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!is_dir(dirname($flag))) @mkdir(dirname($flag), 0750, true);
        @file_put_contents($flag, date('c'), LOCK_EX);
        return $done = true;
    } catch (Throwable $e) {
        error_log('home_groups schema: ' . $e->getMessage());
        return $done = false;
    }
}

function homeGroupsIsConfigured(PDO $pdo): bool
{
    try {
        $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key='home_groups_configured' LIMIT 1");
        $st->execute();
        return (string)$st->fetchColumn() === '1';
    } catch (Throwable $e) {
        return false;
    }
}

function homeGroupsSetConfigured(PDO $pdo, bool $configured): void
{
    $value = $configured ? '1' : '0';
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('home_groups_configured',?)
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$value]);
    // بصمة مستقلة حتى يظهر تعديل الاسم/الربط/الترتيب فوراً، حتى إن بقي
    // عدد المجموعات وعدد الأقسام كما هما.
    $revision = sprintf('%.6F', microtime(true));
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('home_groups_revision',?)
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$revision]);
    if (function_exists('cacheDelete')) {
        @cacheDelete('site_settings');
        @cacheDelete('content_stamp');
    }
}

function homeGroupsMarkConfigured(PDO $pdo): void
{
    homeGroupsSetConfigured($pdo, true);
}

/**
 * @return array{configured:bool,groups:array<int,array<string,mixed>>}
 */
function homeGroupsRead(PDO $pdo, bool $activeOnly = false): array
{
    try {
        $where = $activeOnly ? 'WHERE g.is_active=1' : '';
        $groups = $pdo->query("SELECT g.id,g.name,g.slug,g.description,g.content_type,g.accent_color,
            g.display_order,g.is_active
            FROM home_groups g {$where}
            ORDER BY g.display_order ASC,g.id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$groups) {
            return ['configured' => homeGroupsIsConfigured($pdo), 'groups' => []];
        }

        $ids = array_map(static function ($row) { return (int)$row['id']; }, $groups);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT hgc.group_id,hgc.category_id,hgc.display_order
            FROM home_group_categories hgc
            INNER JOIN categories c ON c.id=hgc.category_id
            WHERE hgc.group_id IN ({$marks})
            ORDER BY hgc.group_id,hgc.display_order,hgc.category_id");
        $st->execute($ids);
        $mapped = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapped[(int)$row['group_id']][] = (int)$row['category_id'];
        }

        foreach ($groups as &$group) {
            $group['id'] = (int)$group['id'];
            $group['display_order'] = (int)$group['display_order'];
            $group['is_active'] = (int)$group['is_active'];
            $group['category_ids'] = $mapped[$group['id']] ?? [];
        }
        unset($group);

        return ['configured' => homeGroupsIsConfigured($pdo), 'groups' => $groups];
    } catch (Throwable $e) {
        error_log('home_groups read: ' . $e->getMessage());
        return ['configured' => false, 'groups' => []];
    }
}

function homeGroupsSlug(PDO $pdo, string $name, int $ignoreId = 0): string
{
    $ascii = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
    $base = $ascii !== '' ? substr($ascii, 0, 100) : 'group';
    $slug = $base;
    $suffix = 1;
    $check = $pdo->prepare('SELECT id FROM home_groups WHERE slug=? AND id<>? LIMIT 1');
    while (true) {
        $check->execute([$slug, $ignoreId]);
        if (!$check->fetchColumn()) return $slug;
        $suffix++;
        $slug = substr($base, 0, 92) . '-' . $suffix;
    }
}

function homeGroupsNormalizeOrder(PDO $pdo): void
{
    $ids = $pdo->query('SELECT id FROM home_groups ORDER BY display_order ASC,id ASC')->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare('UPDATE home_groups SET display_order=? WHERE id=?');
    foreach ($ids as $index => $id) $update->execute([$index + 1, (int)$id]);
}

/* ══════════════════════════════════════════════════════════════════════
   إضافات رفع فوق طبقة roo: توسيع الأقسام (اختيار أب يضمّ فروعه) + عدّ +
   غلاف تمثيلي حتمي — تُستخدم في api.php و ajax/handlers.php. لا تمسّ منطق
   roo، فقط دوال مساعدة إضافية للعرض.
   ══════════════════════════════════════════════════════════════════════ */
if (!function_exists('hgExpandIds')) {
    function hgExpandIds(PDO $pdo, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static function ($v) { return $v > 0; }));
        if (!$ids) return [];
        try {
            $rows = $pdo->query("SELECT id, parent_id FROM categories")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return $ids;
        }
        $childrenOf = [];
        foreach ($rows as $r) {
            $childrenOf[(int)($r['parent_id'] ?? 0)][] = (int)$r['id'];
        }
        $out = [];
        $stack = $ids;
        while ($stack) {
            $cur = (int)array_pop($stack);
            if (isset($out[$cur])) continue;
            $out[$cur] = $cur;
            foreach ($childrenOf[$cur] ?? [] as $ch) {
                if (!isset($out[$ch])) $stack[] = $ch;
            }
        }
        return array_values($out);
    }
}

if (!function_exists('hgGroupCounts')) {
    function hgGroupCounts(PDO $pdo, array $expandedIds): array
    {
        $expandedIds = array_values(array_filter(array_map('intval', $expandedIds), static function ($v) { return $v > 0; }));
        if (!$expandedIds) return ['categories' => 0, 'items' => 0];
        $list = implode(',', $expandedIds);
        $ch = 0; $sr = 0;
        try { $ch = (int)$pdo->query("SELECT COUNT(*) FROM channels WHERE is_active=1 AND category_id IN ($list)")->fetchColumn(); } catch (Throwable $e) {}
        try { $sr = (int)$pdo->query("SELECT COUNT(*) FROM series   WHERE is_active=1 AND category_id IN ($list)")->fetchColumn(); } catch (Throwable $e) {}
        return ['categories' => count($expandedIds), 'items' => $ch + $sr];
    }
}

if (!function_exists('hgGroupCover')) {
    function hgGroupCover(PDO $pdo, array $expandedIds, string $type): string
    {
        $expandedIds = array_values(array_filter(array_map('intval', $expandedIds), static function ($v) { return $v > 0; }));
        if (!$expandedIds) return '';
        $list = implode(',', $expandedIds);
        $fromSeries = static function () use ($pdo, $list): string {
            try { $u = $pdo->query("SELECT poster_url FROM series WHERE category_id IN ($list) AND poster_url IS NOT NULL AND poster_url<>'' ORDER BY display_order ASC, id DESC LIMIT 1")->fetchColumn(); return is_string($u) ? $u : ''; } catch (Throwable $e) { return ''; }
        };
        $fromChannels = static function () use ($pdo, $list): string {
            try { $u = $pdo->query("SELECT logo_url FROM channels WHERE category_id IN ($list) AND logo_url IS NOT NULL AND logo_url<>'' ORDER BY display_order ASC, id DESC LIMIT 1")->fetchColumn(); return is_string($u) ? $u : ''; } catch (Throwable $e) { return ''; }
        };
        if ($type === 'live') { $c = $fromChannels(); return $c !== '' ? $c : $fromSeries(); }
        $c = $fromSeries(); return $c !== '' ? $c : $fromChannels();
    }
}

/**
 * الوضع التلقائي: بناء بطاقات الصفحة الرئيسية من الأقسام مباشرةً (بلا صفوف
 * محفوظة)، فتُحسب في كل طلب — وأي قسم جديد يُضاف من لوحة الإدارة يظهر
 * تلقائياً في index بلا أي خطوة إضافية. بطاقة لكل قسم رئيسي له محتوى فعلي
 * (هو أو أحد فروعه)، ونوع/لون/غلاف البطاقة يُشتقّ من نوع محتواها الغالب.
 */
if (!function_exists('hgAutoGroups')) {
    function hgAutoGroups(PDO $pdo): array
    {
        /* الأقسام المستوردة (Xtream أو M3U) لا تدخل التقسيم التلقائي:
           استيراد واحد يُنشئ عشرات الأقسام الجذرية، فتغرق الصفحة الرئيسية
           ببطاقات لم يخترها أحد. تُعرض فقط عبر مجموعات الصفحة الرئيسية
           التي يحدّدها المدير.
           العمود قد يغيب على نسخة لم تستورد من Xtream قط، فنبني الشرط
           بعد التحقق بدل أن يسقط الاستعلام. */
        $xtWhere = '';
        try {
            $__cols = [];
            foreach ($pdo->query("SHOW COLUMNS FROM categories")->fetchAll(PDO::FETCH_ASSOC) as $__c) {
                $__cols[strtolower((string) $__c['Field'])] = true;
            }
            if (isset($__cols['xtream_account_id'])) { $xtWhere .= ' AND c.xtream_account_id IS NULL'; }
            if (isset($__cols['playlist_id']))       { $xtWhere .= ' AND c.playlist_id IS NULL'; }
        } catch (Throwable $e) { /* بلا شرط */ }

        try {
            $rows = $pdo->query("SELECT c.id, c.name, c.parent_id,
                    COALESCE(ch.cnt,0) AS ch_cnt, COALESCE(s.cnt,0) AS sr_cnt
                FROM categories c
                LEFT JOIN (SELECT category_id, COUNT(*) cnt FROM channels WHERE is_active=1 GROUP BY category_id) ch ON ch.category_id=c.id
                LEFT JOIN (SELECT category_id, COUNT(*) cnt FROM series   WHERE is_active=1 GROUP BY category_id) s  ON s.category_id=c.id
                WHERE COALESCE(c.is_active,1)=1" . $xtWhere . "
                ORDER BY COALESCE(c.display_order,0), c.id")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
        if (!$rows) return [];

        $byId = [];
        foreach ($rows as $r) { $byId[(int)$r['id']] = $r; }

        $children = [];
        $roots    = [];
        foreach ($rows as $r) {
            $id  = (int)$r['id'];
            $pid = (int)($r['parent_id'] ?? 0);
            if ($pid > 0 && isset($byId[$pid])) { $children[$pid][] = $id; }
            else { $roots[] = $id; }
        }

        // مشي تكراري مع حماية من أي حلقة parent_id دائرية في البيانات
        $subtreeOf = static function (int $rootId) use (&$children): array {
            $out = []; $stack = [$rootId]; $seen = [];
            while ($stack) {
                $cur = (int)array_pop($stack);
                if (isset($seen[$cur])) continue;
                $seen[$cur] = true;
                $out[] = $cur;
                foreach ($children[$cur] ?? [] as $ch) {
                    if (!isset($seen[$ch])) $stack[] = $ch;
                }
            }
            return $out;
        };

        $out = [];
        foreach ($roots as $root) {
            $ids = $subtreeOf($root);
            $chTotal = 0; $srTotal = 0; $navIds = [];
            foreach ($ids as $cid) {
                $ch = (int)($byId[$cid]['ch_cnt'] ?? 0);
                $sr = (int)($byId[$cid]['sr_cnt'] ?? 0);
                $chTotal += $ch; $srTotal += $sr;
                if ($ch + $sr > 0) $navIds[] = $cid;
            }
            $items = $chTotal + $srTotal;
            if ($items <= 0) continue;            // لا بطاقات فارغة
            if (!$navIds) $navIds = [$root];

            $type  = ($chTotal >= $srTotal) ? 'live' : 'movie';
            $color = ($type === 'live') ? '#38bdf8' : '#c084fc';
            $cover = function_exists('hgGroupCover') ? hgGroupCover($pdo, $ids, $type) : '';

            $out[] = [
                'id'             => (int)$root,
                'name'           => (string)($byId[$root]['name'] ?? ''),
                'description'    => '',
                'color'          => $color,
                'content_type'   => $type,
                'category_ids'   => array_values($navIds),
                'cover'          => $cover,
                'category_count' => count($navIds),
                'item_count'     => $items,
                'auto'           => true,
            ];
        }
        return $out;
    }
}
