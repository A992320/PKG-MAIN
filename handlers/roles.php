<?php
// orig 2899-2929
// ══ جلب دور المستخدم الحالي ══
$_admin_role = 'normal';
$_admin_sections = [];
$_admin_user_id = 0;
$_admin_display = $_SESSION['admin_username'] ?? 'مدير';
/* ══ عطل: كان هذا الملف يُنزّل دور المدير إلى normal في كل تحميل صفحة ══
   login.php يسجّل الدور الصحيح في الجلسة (administrator للحساب القديم في
   جدول users مثلاً)، ثم كان هذا الملف يستعلم admin_users بالاسم، فإن لم
   يجد صفّاً يترك $_admin_role على قيمته الابتدائية 'normal' ويكتبها فوق
   الجلسة. النتيجة: تدخل كمدير، وأول تحميل للوحة يسلبك الصلاحية بصمت.

   وأثره كان يظهر في نداءات AJAX تحديداً — admin.php يُدرِج ajax/router.php
   قبل هذا الملف، فالنداء يقرأ ما كُتب في الجلسة من التحميل السابق:
   'normal'. فيردّ get_login_logs وget_admin_users بـ«ليس لديك صلاحية»،
   وتظهر في الواجهة كـ«خطأ في التحميل» بلا أي دليل على السبب.

   التمييز الصحيح ثلاثي:
     • صفّ موجود ونشط  → الدور من قاعدة البيانات (المصدر الأوثق)
     • صفّ موجود معطّل → تنزيل فعليّ للدور، فالتعطيل مقصود ويجب احترامه
     • لا صفّ إطلاقاً  → حساب قديم من جدول users: نُبقي دور الجلسة كما
                          ضبطه login.php ولا نلمسه */
try {
    $__au_stmt = $pdo->prepare("SELECT id, role, allowed_sections, display_name, COALESCE(is_active, 1) AS is_active
                                FROM admin_users WHERE username = ? LIMIT 1");
    $__au_stmt->execute([$_SESSION['admin_username'] ?? '']);
    $__au_row = $__au_stmt->fetch(PDO::FETCH_ASSOC);

    if ($__au_row && (int) $__au_row['is_active'] === 1) {
        $_admin_role = $__au_row['role'];
        $_admin_user_id = (int) $__au_row['id'];
        $_admin_display = $__au_row['display_name'] ?: ($_SESSION['admin_username'] ?? '');
        $_admin_sections = json_decode($__au_row['allowed_sections'] ?: '[]', true) ?: [];
        // تحديث وقت آخر دخول
        $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$__au_row['id']]);
    } elseif ($__au_row) {
        // الحساب معطّل من اللوحة — التنزيل هنا مقصود
        $_admin_role = 'normal';
        $_admin_sections = [];
        $_admin_user_id = (int) $__au_row['id'];
    } else {
        // حساب لا وجود له في admin_users (الحساب القديم في users) —
        // نحترم ما سجّله login.php ولا نُسقِط صلاحيته.
        $_admin_role = $_SESSION['admin_role'] ?? 'normal';
        $__sec = $_SESSION['admin_sections'] ?? ($_SESSION['allowed_sections'] ?? '[]');
        if (is_string($__sec)) { $__sec = json_decode($__sec, true) ?: []; }
        $_admin_sections = is_array($__sec) ? $__sec : [];
        $_admin_user_id = (int) ($_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? 0);
    }

    $_SESSION['admin_role'] = $_admin_role;
    $_SESSION['admin_sections'] = $_admin_sections;
    $_SESSION['admin_user_id'] = $_admin_user_id;
} catch(PDOException $e) {
    // فشل جلب دور المدير من admin_users (مثلاً DB مؤقتاً غير متاحة) — لا نكسر الصفحة،
    // لكن نُسجّل السبب بدل الصمت الكامل حتى يمكن تشخيصه لاحقاً.
    if (function_exists('logTo')) logTo('error', 'roles: فشل جلب دور المدير', ['err' => $e->getMessage()]);
}

// قائمة كل المستخدمين المسؤولين (لاستعمالها لاحقاً)
$_all_admin_users = [];
try {
    $_all_admin_users = $pdo->query("SELECT id, username, display_name, role, allowed_sections, is_active, created_at, last_login FROM admin_users ORDER BY FIELD(role,'administrator','super','normal','custom'), id")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    if (function_exists('logTo')) logTo('error', 'roles: فشل جلب قائمة المدراء', ['err' => $e->getMessage()]);
}


/* ⚠ هذا السطر كان أثقل عبء في admin.php:
   كان يجلب كل صفوف series (١٩٣ ألفاً بعد استيراد Xtream) في كل تحميل
   للصفحة، ثم تُحقَن كاملةً كـ JSON داخل الصفحة (includes/main_js.php).
   خادمٌ يقرأ ويرتّب ويشفّر ميغابايتات، ومتصفحٌ يحلّلها — عند كل فتح.

   لكن غرض هذه القائمة اختيار «مجلد رفع» تُحفظ فيه ملفات مرفوعة يدوياً،
   لا استعراض أفلام Xtream المستوردة. المستورَد يحمل xtream_account_id،
   واليدوي لا. فنفلتر عليه: القائمة تعود عشرات المجلدات بدل مئات
   الآلاف. وحدّ 2000 سقف أمان لو أنشأ أحد عدداً كبيراً يدوياً. */
try {
    $st = $pdo->query(
        "SELECT id, name FROM series
         WHERE xtream_account_id IS NULL OR xtream_account_id = 0
         ORDER BY name ASC LIMIT 2000"
    );
    $all_folders_list = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // احتياط: لو لم يوجد العمود، نحدّ العدد على الأقل كي لا نُحمّل كل شيء
    $all_folders_list = $pdo->query("SELECT id, name FROM series ORDER BY id DESC LIMIT 500")
                            ->fetchAll(PDO::FETCH_ASSOC);
}
?>
