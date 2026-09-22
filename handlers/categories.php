<?php
// orig 2717-2767

// ══ Categories Handlers (إدارة الأقسام) ══
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])){
    // CSRF Check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['error'] = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
        header('Location: admin.php#categories');
        exit;
    }

    try {
        $name = htmlspecialchars(strip_tags($_POST['category_name']));
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $icon = htmlspecialchars(strip_tags($_POST['category_icon'] ?? 'fas fa-th-large'));
        $desc = htmlspecialchars(strip_tags($_POST['description'] ?? ''));
        
        $slug_new = "cat-".time()."-".rand(100,999);
        $pdo->prepare("INSERT INTO categories (name, parent_id, icon, description, slug) VALUES (?, ?, ?, ?, ?)")->execute([$name, $parent_id, $icon, $desc, $slug_new]);
        $_SESSION['success'] = '✅ تم إضافة القسم بنجاح.'; 
    } catch(PDOException $e) {
        error_log('[shashety] DB error: ' . $e->getMessage());
        $_SESSION['error'] = 'حدث خطأ في قاعدة البيانات، يرجى المحاولة مرة أخرى.';
    }
    header('Location: admin.php#categories'); 
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])){
    // CSRF Check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['error'] = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
        header('Location: admin.php#categories');
        exit;
    }

    try {
        $id = (int)$_POST['category_id'];
        $name = htmlspecialchars(strip_tags($_POST['category_name']));
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $icon = htmlspecialchars(strip_tags($_POST['category_icon'] ?? 'fas fa-th-large'));

        /* ══ حماية سلامة الشجرة (parent_id) ══
           منع القسم من أن يكون أباً لنفسه، أو أن يُسنَد إلى أحد أحفاده،
           فذلك ينشئ حلقة دائرية تُعطب المشي العودي في صفحة الأقسام وتُفسد
           التنقّل في الموقع. نتحقّق قبل الحفظ ونرفض بوضوح بدل السماح بحالة
           بيانات فاسدة. */
        if ($parent_id !== null) {
            if ($parent_id === $id) {
                $_SESSION['error'] = 'لا يمكن جعل القسم أباً لنفسه.';
                header('Location: admin.php#categories');
                exit;
            }
            // نتأكد أن الأب المطلوب ليس ضمن أحفاد هذا القسم (منع الحلقة)
            $rows = $pdo->query("SELECT id, parent_id FROM categories")->fetchAll(PDO::FETCH_ASSOC);
            $childrenOf = [];
            $exists = false;
            foreach ($rows as $r) {
                $childrenOf[(int)($r['parent_id'] ?? 0)][] = (int)$r['id'];
                if ((int)$r['id'] === $parent_id) $exists = true;
            }
            if (!$exists) {
                $_SESSION['error'] = 'القسم الأب المحدَّد غير موجود.';
                header('Location: admin.php#categories');
                exit;
            }
            // BFS على أحفاد $id — لو كان $parent_id بينهم فهي حلقة
            $stack = $childrenOf[$id] ?? [];
            $seen  = [];
            $isDescendant = false;
            while ($stack) {
                $cur = array_pop($stack);
                if (isset($seen[$cur])) continue;   // حماية إضافية من بيانات دائرية قائمة
                $seen[$cur] = true;
                if ($cur === $parent_id) { $isDescendant = true; break; }
                foreach ($childrenOf[$cur] ?? [] as $ch) $stack[] = $ch;
            }
            if ($isDescendant) {
                $_SESSION['error'] = 'لا يمكن نقل القسم إلى قسم فرعي تابع له (سينشئ حلقة).';
                header('Location: admin.php#categories');
                exit;
            }
        }

        $pdo->prepare("UPDATE categories SET name=?, parent_id=?, icon=? WHERE id=?")->execute([$name, $parent_id, $icon, $id]);
        $_SESSION['success'] = '✅ تم تعديل القسم بنجاح.'; 
    } catch(PDOException $e) {
        error_log('[shashety] DB error: ' . $e->getMessage());
        $_SESSION['error'] = 'حدث خطأ في قاعدة البيانات، يرجى المحاولة مرة أخرى.';
    }
    header('Location: admin.php#categories'); 
    exit;
}

if(isset($_POST['delete_category']) && $_SERVER['REQUEST_METHOD'] === 'POST'){
    // CSRF Check
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $_SESSION['error'] = 'انتهت صلاحية الجلسة، يرجى إعادة المحاولة.';
        header('Location: admin.php#categories');
        exit;
    }

    try {
        $id = (int)$_POST['delete_category'];
        /* نُعيد أبناء هذا القسم إلى المستوى الأعلى (parent_id = NULL) قبل الحذف
           حتى لا تبقى مراجع parent_id معلّقة تشير إلى قسم محذوف — فتلك تُفسد
           بناء الشجرة والتنقّل. الأبناء يبقون كأقسام رئيسية بدل أن يختفوا. */
        $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?")->execute([$id]);
        // حذف القسم
        $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        $_SESSION['success'] = '✅ تم حذف القسم بنجاح.'; 
    } catch(PDOException $e) {
        $_SESSION['error'] = 'لا يمكن الحذف (قد يكون هناك قنوات مرتبطة بهذا القسم).';
    }
    header('Location: admin.php#categories'); 
    exit;
}

// ══ Channels Handlers ══
