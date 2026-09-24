<?php
session_start();

/* ============================================================
   حماية صفحة استرجاع كلمة المرور
   ============================================================ */

$ACCESS_HASH = '$2a$12$YpW9d29rLw31pK6BHzKywunP4CPvTg1NLVoVWW.GKS9ccElsQSjgC';
$login_error = '';

/* تسجيل الخروج */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();

    header('Location: password_admin.php');
    exit;
}

/* تسجيل الدخول */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['access_password'])
) {
    $entered_password = (string)$_POST['access_password'];

    if (password_verify($entered_password, $ACCESS_HASH)) {

        session_regenerate_id(true);

        $_SESSION['password_admin_access'] = true;

        header('Location: password_admin.php');
        exit;

    } else {
        $login_error = 'كلمة المرور غير صحيحة';
    }
}


/* ============================================================
   إذا لم يسجل الدخول
   ============================================================ */

if (empty($_SESSION['password_admin_access'])) {
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>تسجيل الدخول - استرجاع كلمة المرور</title>

<style>

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:'Segoe UI',Tahoma,Arial,sans-serif;
    background:#0f0f0f;
    color:#eee;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.wrap{
    width:100%;
    max-width:440px;
}

.card{
    background:#1a1a1a;
    border:1px solid #2a2a2a;
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 20px 60px rgba(0,0,0,.6);
}

.card-head{
    background:#E50914;
    padding:28px;
    text-align:center;
}

.lock{
    font-size:38px;
    margin-bottom:10px;
}

.card-head h1{
    font-size:1.35rem;
    font-weight:700;
    color:#fff;
    margin-bottom:6px;
}

.card-head p{
    font-size:.82rem;
    color:rgba(255,255,255,.75);
}

.card-body{
    padding:28px;
}

.error{
    background:rgba(229,9,20,.1);
    border:1px solid rgba(229,9,20,.25);
    color:#ff6b6b;
    padding:12px 14px;
    border-radius:8px;
    margin-bottom:18px;
    font-size:.85rem;
    text-align:center;
}

label{
    display:block;
    color:#888;
    font-size:.82rem;
    margin-bottom:9px;
}

input[type=password]{
    width:100%;
    background:#101010;
    border:1px solid #303030;
    color:#fff;
    border-radius:8px;
    padding:14px 15px;
    font-size:1rem;
    outline:none;
}

input[type=password]:focus{
    border-color:#E50914;
    box-shadow:0 0 0 3px rgba(229,9,20,.10);
}

button{
    display:block;
    width:100%;
    margin-top:18px;
    padding:14px;
    background:#E50914;
    color:#fff;
    border:0;
    border-radius:8px;
    font-size:1rem;
    font-weight:700;
    cursor:pointer;
}

button:hover{
    background:#f01020;
}

.info{
    margin-top:18px;
    padding-top:16px;
    border-top:1px solid #252525;
    text-align:center;
    color:#555;
    font-size:.75rem;
}

</style>

</head>

<body>

<div class="wrap">

    <div class="card">

        <div class="card-head">

            <div class="lock">🔐</div>

            <h1>تسجيل الدخول</h1>

            <p>صفحة استرجاع كلمة مرور المدير</p>

        </div>

        <div class="card-body">

            <?php if ($login_error !== ''): ?>

            <div class="error">
                ❌ <?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <?php endif; ?>

            <form method="POST" action="">

                <label>كلمة المرور</label>

                <input
                    type="password"
                    name="access_password"
                    placeholder="أدخل كلمة المرور"
                    autocomplete="current-password"
                    autofocus
                    required
                >

                <button type="submit">
                    🔐 تسجيل الدخول
                </button>

            </form>

            <div class="info">
                Protected Password Recovery
            </div>

        </div>

    </div>

</div>

</body>
</html>

<?php

    exit;
}


/* ============================================================
   انتهت حماية LOGIN

   من هنا يبدأ كود استرجاع الباسورد الأصلي
   ============================================================ */


require_once 'config.php';


/* ============================================================
   بيانات المدير الأصلية
   ============================================================ */

$username = 'admin';

$password = 'Ali1992320';

$results = [];

$hash = password_hash(
    $password,
    PASSWORD_BCRYPT,
    ['cost' => 12]
);


/* ============================================================
   1. تحديث جدول users
   ============================================================ */

try {

    $s = $pdo->prepare("
        UPDATE users
        SET password=?
        WHERE username=?
    ");

    $s->execute([
        $hash,
        $username
    ]);

    $results[] = [
        'ok',
        'جدول users: تم تحديث كلمة المرور (' .
        $s->rowCount() .
        ' صف)'
    ];

} catch (PDOException $e) {

    $results[] = [
        'err',
        'جدول users: ' . $e->getMessage()
    ];
}


/* ============================================================
   2. إنشاء جدول admin_users
   ============================================================ */

try {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admin_users` (

            `id`
            INT AUTO_INCREMENT PRIMARY KEY,

            `username`
            VARCHAR(100)
            NOT NULL
            UNIQUE,

            `password_hash`
            VARCHAR(255)
            NOT NULL,

            `display_name`
            VARCHAR(100),

            `role`
            VARCHAR(20)
            DEFAULT 'normal',

            `allowed_sections`
            TEXT,

            `is_active`
            TINYINT(1)
            DEFAULT 1,

            `created_at`
            TIMESTAMP
            DEFAULT CURRENT_TIMESTAMP,

            `last_login`
            TIMESTAMP NULL

        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
    ");

    $results[] = [
        'ok',
        'جدول admin_users: تم الإنشاء بنجاح'
    ];

} catch (PDOException $e) {

    $results[] = [
        'err',
        'إنشاء admin_users: ' . $e->getMessage()
    ];
}


/* ============================================================
   3. تحديث أو إنشاء المستخدم admin
   ============================================================ */

try {

    $check = $pdo->prepare("
        SELECT id, role
        FROM admin_users
        WHERE username=?
    ");

    $check->execute([
        $username
    ]);

    $existing = $check->fetch(
        PDO::FETCH_ASSOC
    );


    if ($existing) {

        $update = $pdo->prepare("
            UPDATE admin_users

            SET
                password_hash=?,
                role='administrator',
                is_active=1,
                display_name='Admin',
                allowed_sections='[]'

            WHERE username=?
        ");

        $update->execute([
            $hash,
            $username
        ]);

        $results[] = [
            'ok',
            'admin_users: تم التحديث — role=administrator (كان: ' .
            $existing['role'] .
            ')'
        ];

    } else {

        $insert = $pdo->prepare("
            INSERT INTO admin_users
            (
                username,
                password_hash,
                display_name,
                role,
                allowed_sections,
                is_active
            )

            VALUES
            (
                ?,
                ?,
                'Admin',
                'administrator',
                '[]',
                1
            )
        ");

        $insert->execute([
            $username,
            $hash
        ]);

        $results[] = [
            'ok',
            'admin_users: تم الإنشاء — role=administrator — ID=' .
            $pdo->lastInsertId()
        ];
    }

} catch (PDOException $e) {

    $results[] = [
        'err',
        'admin_users خطأ: ' .
        $e->getMessage()
    ];
}


/* ============================================================
   4. التحقق النهائي
   ============================================================ */

$final = null;

try {

    $st = $pdo->prepare("
        SELECT
            id,
            username,
            role,
            is_active

        FROM admin_users

        WHERE username=?
    ");

    $st->execute([
        $username
    ]);

    $final = $st->fetch(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $results[] = [
        'err',
        'خطأ في التحقق: ' .
        $e->getMessage()
    ];
}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1.0"
>

<title>إصلاح صلاحيات المدير</title>

<style>

*{
    box-sizing:border-box;
    margin:0;
    padding:0
}

body{

    font-family:
        'Segoe UI',
        Tahoma,
        sans-serif;

    background:#0f0f0f;

    color:#eee;

    min-height:100vh;

    display:flex;

    align-items:center;

    justify-content:center;

    padding:20px;
}

.wrap{

    width:100%;

    max-width:520px;
}

.card{

    background:#1a1a1a;

    border:1px solid #2a2a2a;

    border-radius:12px;

    overflow:hidden;

    box-shadow:
        0 20px 60px
        rgba(0,0,0,.6);
}

.card-head{

    background:#E50914;

    padding:24px 28px;

    text-align:center;
}

.card-head h1{

    font-size:1.3rem;

    font-weight:700;

    color:#fff;

    margin-bottom:4px;
}

.card-head p{

    font-size:.8rem;

    color:
        rgba(255,255,255,.75);
}

.card-body{

    padding:28px;
}

.row{

    display:flex;

    align-items:flex-start;

    gap:10px;

    padding:11px 14px;

    border-radius:8px;

    margin-bottom:8px;

    font-size:.85rem;

    line-height:1.5;
}

.row.ok{

    background:
        rgba(0,208,132,.1);

    border:
        1px solid
        rgba(0,208,132,.25);

    color:#00D084;
}

.row.err{

    background:
        rgba(229,9,20,.1);

    border:
        1px solid
        rgba(229,9,20,.25);

    color:#ff6b6b;
}

.row.info{

    background:
        rgba(76,201,240,.1);

    border:
        1px solid
        rgba(76,201,240,.2);

    color:#4CC9F0;
}

.final{

    background:#111;

    border:1px solid #2a2a2a;

    border-radius:8px;

    padding:16px;

    margin:20px 0;
}

.final h3{

    font-size:.75rem;

    color:#555;

    text-transform:uppercase;

    letter-spacing:.1em;

    margin-bottom:12px;
}

.frow{

    display:flex;

    justify-content:
        space-between;

    align-items:center;

    padding:8px 0;

    border-bottom:
        1px solid #1e1e1e;

    font-size:.875rem;
}

.frow:last-child{

    border-bottom:none;
}

.lbl{

    color:#666;
}

.val{

    font-weight:700;

    color:#eee;
}

.val.red{

    color:#E50914;
}

.val.green{

    color:#00D084;
}

.warn{

    background:
        rgba(245,166,35,.08);

    border:
        1px solid
        rgba(245,166,35,.2);

    border-radius:8px;

    padding:12px 16px;

    font-size:.8rem;

    color:#f5a623;

    margin-bottom:20px;
}

.btn{

    display:block;

    width:100%;

    padding:14px;

    background:#E50914;

    color:#fff;

    font-size:1rem;

    font-weight:700;

    text-align:center;

    border-radius:8px;

    text-decoration:none;

    margin-bottom:10px;
}

.btn:hover{

    background:#f01020;
}

.logout{

    display:block;

    width:100%;

    padding:12px;

    background:#252525;

    color:#aaa;

    font-size:.85rem;

    text-align:center;

    border-radius:8px;

    text-decoration:none;
}

.logout:hover{

    background:#303030;

    color:#fff;
}

</style>

</head>


<body>


<div class="wrap">

<div class="card">


<div class="card-head">

<h1>
🔧 إصلاح صلاحيات المدير
</h1>

<p>
المستخدم:
<?= htmlspecialchars(
    $username,
    ENT_QUOTES,
    'UTF-8'
) ?>
</p>

</div>


<div class="card-body">


<?php foreach ($results as $r): ?>


<div class="row <?= htmlspecialchars($r[0], ENT_QUOTES, 'UTF-8') ?>">


<?php if ($r[0] === 'ok'): ?>

✅

<?php elseif ($r[0] === 'err'): ?>

❌

<?php else: ?>

ℹ️

<?php endif; ?>


<span>

<?= htmlspecialchars(
    $r[1],
    ENT_QUOTES,
    'UTF-8'
) ?>

</span>


</div>


<?php endforeach; ?>



<?php if ($final): ?>


<div class="final">


<h3>
النتيجة النهائية
</h3>


<div class="frow">

<span class="lbl">
ID
</span>

<span class="val">

<?= (int)$final['id'] ?>

</span>

</div>



<div class="frow">

<span class="lbl">
اسم المستخدم
</span>

<span class="val">

<?= htmlspecialchars(
    $final['username'],
    ENT_QUOTES,
    'UTF-8'
) ?>

</span>

</div>



<div class="frow">

<span class="lbl">
كلمة المرور
</span>

<span class="val">

<?= htmlspecialchars(
    $password,
    ENT_QUOTES,
    'UTF-8'
) ?>

</span>

</div>



<div class="frow">

<span class="lbl">
الصلاحية
</span>

<span class="val red">

<?= htmlspecialchars(
    $final['role'],
    ENT_QUOTES,
    'UTF-8'
) ?>

<?= $final['role'] === 'administrator'
    ? ' 👑'
    : ''
?>

</span>

</div>



<div class="frow">

<span class="lbl">
الحالة
</span>

<span class="val green">

<?= $final['is_active']
    ? '✅ نشط'
    : '❌ معطل'
?>

</span>

</div>


</div>


<?php else: ?>


<div class="row err">

❌

<span>
لم يتم إنشاء المستخدم — راجع الأخطاء أعلاه
</span>

</div>


<?php endif; ?>



<div class="warn">

⚠️ احذف ملف
<strong>password_admin.php</strong>
بعد الانتهاء من استرجاع الحساب.

</div>



<a
    href="login.php"
    class="btn"
>
🔐 تسجيل الدخول الآن
</a>


<a
    href="?logout=1"
    class="logout"
>
🚪 تسجيل خروج من صفحة الاسترجاع
</a>


</div>

</div>

</div>


</body>

</html>