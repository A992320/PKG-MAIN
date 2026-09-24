<?php
$__adminUpdate = function_exists('adminUpdateStatus') ? adminUpdateStatus() : ['available' => false];
if (!empty($__adminUpdate['available'])):
    $uLocal = htmlspecialchars((string)$__adminUpdate['local'], ENT_QUOTES, 'UTF-8');
    $uRemote = htmlspecialchars((string)$__adminUpdate['remote'], ENT_QUOTES, 'UTF-8');
    $uChannel = htmlspecialchars((string)$__adminUpdate['channel'], ENT_QUOTES, 'UTF-8');
    $uLabel = htmlspecialchars((string)$__adminUpdate['label'], ENT_QUOTES, 'UTF-8');
    $uLog = $__adminUpdate['log'] ?? [];
?>
<style>
.admin-update-strip{position:sticky;top:0;z-index:70;display:flex;align-items:center;gap:14px;padding:12px 16px;margin:0 0 18px;border:1px solid rgba(52,211,153,.34);border-radius:13px;background:linear-gradient(100deg,rgba(16,185,129,.14),rgba(30,41,59,.82));box-shadow:0 10px 28px rgba(0,0,0,.18);backdrop-filter:blur(12px)}
.admin-update-strip__icon{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:0 0 auto;background:rgba(52,211,153,.16);color:#6ee7b7;font-size:1.05rem}
.admin-update-strip__body{min-width:0;flex:1}.admin-update-strip__title{font-weight:800;color:var(--t1,#fff);font-size:.94rem}.admin-update-strip__meta{margin-top:2px;color:var(--t3,#a8b3c7);font-size:.78rem}.admin-update-strip__actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.admin-update-strip button,.admin-update-strip a{font:inherit;font-size:.78rem;font-weight:800;text-decoration:none;cursor:pointer;border-radius:8px;padding:8px 11px}
.admin-update-strip__details{color:var(--t1,#fff);background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13)}
.admin-update-strip__install{color:#062b20;background:#6ee7b7;border:1px solid #6ee7b7}.admin-update-strip__ignore{color:var(--t3,#a8b3c7);background:transparent;border:1px solid rgba(255,255,255,.12)}
.admin-update-modal{position:fixed;inset:0;z-index:10050;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(2,6,23,.72);backdrop-filter:blur(7px)}.admin-update-modal.is-open{display:flex}
.admin-update-modal__card{width:min(620px,100%);max-height:min(78vh,680px);overflow:auto;border:1px solid rgba(52,211,153,.3);border-radius:16px;background:#101827;color:var(--t1,#fff);box-shadow:0 28px 80px rgba(0,0,0,.5)}
.admin-update-modal__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:17px 18px;border-bottom:1px solid rgba(255,255,255,.08)}.admin-update-modal__head b{font-size:1rem}.admin-update-modal__close{border:0;background:transparent;color:var(--t3,#a8b3c7);font-size:1.25rem;cursor:pointer}
.admin-update-log{padding:12px 18px 18px}.admin-update-log div{padding:9px 0;border-bottom:1px solid rgba(255,255,255,.07);font-size:.86rem;line-height:1.75;color:var(--t2,#d4d9e4)}.admin-update-log div:last-child{border:0}.admin-update-log div::before{content:'•';color:#6ee7b7;margin-left:8px}.admin-update-log__empty{color:var(--t3,#a8b3c7)}
@media(max-width:680px){.admin-update-strip{align-items:flex-start;flex-wrap:wrap}.admin-update-strip__actions{width:100%;padding-right:52px}.admin-update-strip__actions>*{flex:1;text-align:center}.admin-update-strip__ignore{flex:0 0 auto}}
</style>
<section class="admin-update-strip" aria-label="تحديث جديد متاح">
  <div class="admin-update-strip__icon"><i class="fas fa-cloud-arrow-down"></i></div>
  <div class="admin-update-strip__body">
    <div class="admin-update-strip__title">تحديث جديد متاح للإدارة</div>
    <div class="admin-update-strip__meta">v<?= $uLocal ?> ← v<?= $uRemote ?> · <?= $uLabel ?></div>
  </div>
  <div class="admin-update-strip__actions">
    <button class="admin-update-strip__details" type="button" onclick="adminUpdateLogOpen()">سجل التحديث</button>
    <a class="admin-update-strip__install" href="update.php?channel=<?= $uChannel ?>">تثبيت التحديث</a>
    <form method="post" action="admin.php" style="margin:0" onsubmit="return confirm('سيتم تجاوز إشعار الإصدار v<?= $uRemote ?> فقط. سيظهر أي إصدار أحدث تلقائياً. هل تريد المتابعة؟')">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="admin_update_notice_action" value="dismiss">
      <input type="hidden" name="remote_version" value="<?= $uRemote ?>">
      <button class="admin-update-strip__ignore" type="submit">تجاوز هذا الإصدار</button>
    </form>
  </div>
</section>
<div class="admin-update-modal" id="adminUpdateLog" role="dialog" aria-modal="true" aria-labelledby="adminUpdateLogTitle" onclick="if(event.target===this)adminUpdateLogClose()">
  <div class="admin-update-modal__card">
    <div class="admin-update-modal__head"><b id="adminUpdateLogTitle">سجل التحديث v<?= $uRemote ?></b><button class="admin-update-modal__close" type="button" onclick="adminUpdateLogClose()" aria-label="إغلاق">×</button></div>
    <div class="admin-update-log">
      <?php if (!empty($uLog)): foreach ($uLog as $line): ?>
        <div><?= htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endforeach; else: ?>
        <div class="admin-update-log__empty">لا توجد تفاصيل منشورة لهذا الإصدار.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
function adminUpdateLogOpen(){var el=document.getElementById('adminUpdateLog');if(el){el.classList.add('is-open');document.body.style.overflow='hidden';}}
function adminUpdateLogClose(){var el=document.getElementById('adminUpdateLog');if(el){el.classList.remove('is-open');document.body.style.overflow='';}}
document.addEventListener('keydown',function(e){if(e.key==='Escape')adminUpdateLogClose();});
</script>
<?php endif; ?>