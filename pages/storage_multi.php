<section id="storage-mgmt" class="sec">
<?php
/* ═══════════════════════════════════════════════════════════════════════════
   Admin → Storage Management (Multi-Storage)
   كل قرص وحدة مستقلة. هذه الصفحة واجهة فقط: كل عملية تمرّ عبر
   ajax/storage_handlers.php (صلاحية administrator/super + تأكيد مكتوب على
   الخادم) ثم أداة الجذر ذات الأوامر المغلقة.
   ═══════════════════════════════════════════════════════════════════════════ */
?>
<style>
#storage-mgmt .stg-head-actions{display:flex;gap:8px;flex-wrap:wrap}
#storage-mgmt .stg-banner{display:flex;gap:12px;align-items:flex-start;padding:14px 16px;border-radius:12px;margin-bottom:14px;font-size:.84rem;line-height:1.75;border:1px solid}
#storage-mgmt .stg-banner i{margin-top:4px}
#storage-mgmt .stg-banner.warn{background:rgba(245,166,35,.08);border-color:rgba(245,166,35,.35);color:var(--t1)}
#storage-mgmt .stg-banner.warn>i{color:#f5a623}
#storage-mgmt .stg-banner.info{background:rgba(56,189,248,.08);border-color:rgba(56,189,248,.3);color:var(--t1)}
#storage-mgmt .stg-banner.info>i{color:#38bdf8}
#storage-mgmt .stg-banner.crit{background:rgba(255,77,87,.08);border-color:rgba(255,77,87,.35)}
#storage-mgmt .stg-banner.crit>i{color:#ff4d57}
#storage-mgmt .stg-cmd{display:flex;gap:6px;align-items:center;margin-top:8px;flex-wrap:wrap}
#storage-mgmt .stg-cmd code{text-transform:none;direction:ltr;unicode-bidi:embed;background:var(--s0,#000);border:1px solid var(--br);border-radius:8px;padding:6px 10px;font-size:.78rem;color:#9be7ff;word-break:break-all}
#storage-mgmt .stg-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:16px}
#storage-mgmt .stg-sum{background:var(--s1);border:1px solid var(--br);border-radius:12px;padding:12px 14px}
#storage-mgmt .stg-sum small{display:block;color:var(--t3);font-size:.72rem;font-weight:700;margin-bottom:4px}
#storage-mgmt .stg-sum b{font-size:1.15rem;color:var(--t1)}
#storage-mgmt .stg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px;margin-bottom:24px}
#storage-mgmt .stg-card{background:var(--s1);border:1px solid var(--br);border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:10px;position:relative}
#storage-mgmt .stg-card.removed{opacity:.55}
#storage-mgmt .stg-card-top{display:flex;align-items:flex-start;gap:10px}
#storage-mgmt .stg-ico{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;background:rgba(56,189,248,.12);color:#38bdf8}
#storage-mgmt .stg-ico.movies{background:rgba(229,9,20,.12);color:#ff4d57}
#storage-mgmt .stg-ico.series{background:rgba(179,107,255,.14);color:#b36bff}
#storage-mgmt .stg-ico.legacy{background:rgba(148,163,184,.14);color:#94a3b8}
#storage-mgmt .stg-name{font-weight:800;font-size:1rem;color:var(--t1);line-height:1.3}
#storage-mgmt .stg-type{font-size:.72rem;color:var(--t3);font-weight:700}
#storage-mgmt .stg-pill{margin-inline-start:auto;font-size:.7rem;font-weight:800;padding:3px 10px;border-radius:99px;white-space:nowrap;border:1px solid}
#storage-mgmt .st-online{color:#00c080;border-color:rgba(0,192,128,.4);background:rgba(0,192,128,.1)}
#storage-mgmt .st-offline,#storage-mgmt .st-error{color:#ff4d57;border-color:rgba(255,77,87,.45);background:rgba(255,77,87,.1)}
#storage-mgmt .st-readonly{color:#38bdf8;border-color:rgba(56,189,248,.4);background:rgba(56,189,248,.1)}
#storage-mgmt .st-maintenance{color:#f5a623;border-color:rgba(245,166,35,.45);background:rgba(245,166,35,.1)}
#storage-mgmt .st-full{color:#ff8a3d;border-color:rgba(255,138,61,.45);background:rgba(255,138,61,.1)}
#storage-mgmt .st-removed{color:var(--t3);border-color:var(--br);background:transparent}
#storage-mgmt .stg-bar{height:9px;border-radius:99px;background:var(--s3,rgba(255,255,255,.08));overflow:hidden}
#storage-mgmt .stg-bar span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#00c080,#38bdf8);transition:width .4s}
#storage-mgmt .stg-bar span.hi{background:linear-gradient(90deg,#f5a623,#ff8a3d)}
#storage-mgmt .stg-bar span.crit{background:linear-gradient(90deg,#ff8a3d,#ff4d57)}
#storage-mgmt .stg-nums{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;font-size:.75rem;color:var(--t3)}
#storage-mgmt .stg-nums b{display:block;color:var(--t1);font-size:.88rem}
#storage-mgmt .stg-meta{font-size:.72rem;color:var(--t3);line-height:1.7;direction:ltr;text-align:right;word-break:break-all}
#storage-mgmt .stg-msg{font-size:.74rem;color:#ff8a8f;line-height:1.6}
#storage-mgmt .stg-acts{display:flex;gap:6px;flex-wrap:wrap;margin-top:auto}
#storage-mgmt .stg-acts .btn{padding:6px 10px;font-size:.74rem;min-height:32px}
#storage-mgmt .stg-h2{display:flex;align-items:center;gap:8px;font-size:1rem;font-weight:800;color:var(--t1);margin:6px 0 12px}
#storage-mgmt .stg-h2 i{color:#38bdf8}
#storage-mgmt .stg-table{width:100%;border-collapse:collapse;font-size:.8rem}
#storage-mgmt .stg-table th{font-size:.72rem;color:var(--t3);text-align:start;font-weight:800;padding:8px;border-bottom:1px solid var(--br)}
#storage-mgmt .stg-table td{padding:9px 8px;border-bottom:1px solid var(--br);vertical-align:middle;color:var(--t2)}
#storage-mgmt .stg-table td.ltr{direction:ltr;text-align:right}
#storage-mgmt .stg-tag{display:inline-block;font-size:.68rem;font-weight:800;padding:2px 8px;border-radius:99px;border:1px solid var(--br);margin:1px}
#storage-mgmt .stg-tag.sys{color:#ff4d57;border-color:rgba(255,77,87,.4)}
#storage-mgmt .stg-tag.new{color:#38bdf8;border-color:rgba(56,189,248,.4)}
#storage-mgmt .stg-tag.ok{color:#00c080;border-color:rgba(0,192,128,.4)}
#storage-mgmt .stg-alert{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:10px;margin-bottom:8px;font-size:.8rem;border:1px solid var(--br);background:var(--s1)}
#storage-mgmt .stg-alert.critical{border-color:rgba(255,77,87,.45)}
#storage-mgmt .stg-alert.critical>i{color:#ff4d57}
#storage-mgmt .stg-alert.warning>i{color:#f5a623}
#storage-mgmt .stg-alert.info>i{color:#38bdf8}
#storage-mgmt .stg-alert .x{margin-inline-start:auto;background:none;border:none;color:var(--t3);cursor:pointer}
#storage-mgmt .stg-job{background:var(--s1);border:1px solid var(--br);border-radius:12px;padding:12px 14px;margin-bottom:10px}
#storage-mgmt .stg-job-top{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:.82rem;color:var(--t1)}
#storage-mgmt .stg-job small{color:var(--t3)}
#storage-mgmt .stg-empty{text-align:center;color:var(--t3);padding:26px;font-size:.84rem;border:1px dashed var(--br);border-radius:12px}
.stg-danger-box{background:rgba(255,77,87,.08);border:1px solid rgba(255,77,87,.4);border-radius:10px;padding:12px 14px;font-size:.8rem;line-height:1.8;margin-bottom:12px;color:var(--t1)}
.stg-danger-box b{color:#ff4d57}
.stg-kv{display:grid;grid-template-columns:auto 1fr;gap:4px 14px;font-size:.8rem;margin-bottom:12px}
.stg-kv dt{color:var(--t3);font-weight:700}
.stg-kv dd{margin:0;color:var(--t1);direction:ltr;text-align:right;word-break:break-all}
.stg-part{border:1px solid var(--br);border-radius:10px;padding:10px 12px;margin-bottom:8px;font-size:.8rem}
.stg-part-acts{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.stg-part-acts .btn{padding:5px 10px;font-size:.74rem}
.stg-list-row{display:flex;gap:10px;align-items:center;padding:8px 4px;border-bottom:1px solid var(--br);font-size:.8rem}
.stg-list-row .grow{flex:1;min-width:0}
.stg-list-row .path{direction:ltr;text-align:right;color:var(--t3);font-size:.72rem;word-break:break-all}
@media(max-width:640px){#storage-mgmt .stg-grid{grid-template-columns:1fr}#storage-mgmt .stg-table{font-size:.74rem}#storage-mgmt .stg-hide-sm{display:none}}
</style>

  <div class="shdr">
    <h1 class="stitle">إدارة <span>التخزين</span></h1>
    <div class="stg-head-actions">
      <button class="btn btn-g" onclick="stgLoad(true)"><i class="fas fa-rotate"></i> فحص الآن</button>
      <button class="btn btn-g" onclick="stgInstall(this)"><i class="fas fa-screwdriver-wrench"></i> إعداد التخزين</button>
      <button class="btn btn-g" onclick="stgOpenSettings()"><i class="fas fa-sliders"></i> الإعدادات</button>
      <button class="btn btn-g" onclick="stgOpenMove()"><i class="fas fa-right-left"></i> إعادة توازن (اختياري)</button>
      <button class="btn btn-p" onclick="stgOpenSave(0)"><i class="fas fa-plus"></i> إضافة تخزين</button>
    </div>
  </div>

  <div id="stgBanners"></div>
  <div id="stgNewDisks"></div>
  <div id="stgAlerts"></div>
  <div class="stg-summary" id="stgSummary"></div>
  <div class="stg-grid" id="stgCards"><div class="stg-empty"><span class="sp"></span> جارٍ التحميل…</div></div>

  <div class="stg-h2"><i class="fas fa-right-left"></i> مهام إعادة التوازن الصريحة</div>
  <div id="stgJobs"><div class="stg-empty">لا توجد مهام</div></div>

  <div class="stg-h2" style="margin-top:20px"><i class="fas fa-hard-drive"></i> الأقراص المتصلة بالسيرفر
    <button class="btn btn-g" style="margin-inline-start:auto;padding:5px 10px;font-size:.74rem" onclick="stgLoadDisks()"><i class="fas fa-magnifying-glass"></i> اكتشاف</button>
    <button class="btn btn-g" style="padding:5px 10px;font-size:.74rem" onclick="stgSmartNow()"><i class="fas fa-heart-pulse"></i> SMART</button>
  </div>
  <div class="tw" style="overflow-x:auto"><table class="stg-table" id="stgDisksTable"><thead><tr>
    <th>القرص</th><th>الشركة / الموديل</th><th>النوع</th><th>السعة</th><th class="stg-hide-sm">نظام الملفات</th><th>الحالة</th><th class="stg-hide-sm">الربط / UUID</th><th>الحرارة / SMART</th><th></th>
  </tr></thead><tbody><tr><td colspan="9" style="text-align:center;padding:20px"><span class="sp"></span></td></tr></tbody></table></div>
</section>

<!-- ═══════════ مدير القرص ═══════════ -->
<div class="mbd" id="stgDiskM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-hard-drive"></i> مدير القرص <span id="stgDiskTitle" style="direction:ltr;unicode-bidi:embed"></span></div><button class="mclose" onclick="CM('stgDiskM')"><i class="fas fa-times"></i></button></div>
<div class="mbody" id="stgDiskBody"></div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('stgDiskM')">إغلاق</button></div></div></div>

<!-- ═══════════ تأكيد عملية مدمّرة ═══════════ -->
<div class="mbd" id="stgDangerM"><div class="mbox"><div class="mhd"><div class="mhd-title" style="color:#ff4d57"><i class="fas fa-triangle-exclamation"></i> <span id="stgDangerTitle">تأكيد</span></div><button class="mclose" onclick="CM('stgDangerM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <div class="stg-danger-box" id="stgDangerText"></div>
  <div id="stgDangerExtra"></div>
  <div class="fg"><label class="fl">للتأكيد اكتب حرفياً: <code id="stgDangerWord" style="direction:ltr;unicode-bidi:embed;color:#ff4d57;text-transform:none;user-select:all"></code></label>
    <input type="text" class="fi" id="stgDangerInput" autocomplete="off" autocapitalize="off" spellcheck="false" style="direction:ltr;text-transform:none" oninput="stgDangerCheck()"></div>
  <div id="stgDangerAlert"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('stgDangerM')">إلغاء</button><button class="btn" id="stgDangerGo" style="background:#ff4d57;color:#fff" disabled onclick="stgDangerRun()"><i class="fas fa-bolt"></i> تنفيذ</button></div></div></div>

<!-- ═══════════ إضافة / تعديل تخزين ═══════════ -->
<div class="mbd" id="stgSaveM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-database"></i> <span id="stgSaveTitle">إضافة تخزين</span></div><button class="mclose" onclick="CM('stgSaveM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <input type="hidden" id="stgSaveId" value="0">
  <div class="fg"><label class="fl">الاسم</label><input type="text" class="fi" id="stgSaveName" placeholder="Movies-01"></div>
  <div class="fg"><label class="fl">النوع</label><select class="fs" id="stgSaveType">
    <option value="movies">Movies — أفلام فقط</option><option value="series">Series — مسلسلات فقط</option><option value="mixed" selected>Mixed — أفلام ومسلسلات</option></select></div>
  <div class="fg"><label class="fl">نقطة الربط (Mount)</label><input type="text" class="fi" id="stgSaveMount" placeholder="/mnt/storage1" style="direction:ltr">
    <small style="color:var(--t3)">يجب أن يكون قرصاً مستقلاً مركّباً على هذا المسار. تغييرها لاحقاً لا يمسّ سجلات الأفلام (المسارات نسبية).</small></div>
  <div class="row2" style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <div class="fg"><label class="fl">حدّ أدنى حرّ (GB)</label><input type="number" min="0" step="1" class="fi" id="stgSaveMinGb" placeholder="الإعداد العام"></div>
    <div class="fg"><label class="fl">حدّ أدنى حرّ (%)</label><input type="number" min="0" max="90" step="0.5" class="fi" id="stgSaveMinPct" placeholder="الإعداد العام"></div>
  </div>
  <div class="fg"><label class="fl">الأولوية <small style="color:var(--t3)">(الأعلى يُفضَّل عند التساوي)</small></label><input type="number" class="fi" id="stgSaveProp" value="0"></div>
  <div id="stgSaveAlert"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('stgSaveM')">إلغاء</button><button class="btn btn-p" onclick="stgSave()"><i class="fas fa-save"></i> حفظ</button></div></div></div>

<!-- ═══════════ المسارات ═══════════ -->
<div class="mbd" id="stgPathsM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-folder-tree"></i> مسارات <span id="stgPathsTitle"></span></div><button class="mclose" onclick="CM('stgPathsM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <div id="stgPathsList"></div>
  <div style="border-top:1px solid var(--br);margin-top:12px;padding-top:12px">
    <input type="hidden" id="stgPathId" value="0">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
      <div class="fg"><label class="fl">المسار</label><input type="text" class="fi" id="stgPathRel" placeholder="anime" style="direction:ltr"></div>
      <div class="fg"><label class="fl">الاسم</label><input type="text" class="fi" id="stgPathName" placeholder="أنمي"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end">
      <div class="fg"><label class="fl">الغرض</label><select class="fs" id="stgPathPurpose"><option value="movies">أفلام</option><option value="series">مسلسلات</option><option value="anime">أنمي</option><option value="uploads">رفع</option><option value="other">أخرى</option></select></div>
      <label class="fg" style="display:flex;gap:6px;align-items:center;font-size:.8rem"><input type="checkbox" id="stgPathDefault"> افتراضي</label>
    </div>
    <div id="stgPathAlert"></div>
  </div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="stgPathReset()">جديد</button><button class="btn btn-p" onclick="stgPathSave()"><i class="fas fa-save"></i> حفظ المسار</button></div></div></div>

<!-- ═══════════ المحتوى ═══════════ -->
<div class="mbd" id="stgContentM"><div class="mbox xw"><div class="mhd"><div class="mhd-title"><i class="fas fa-film"></i> محتوى <span id="stgContentTitle"></span></div><button class="mclose" onclick="CM('stgContentM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <div class="tsrch" style="margin-bottom:10px"><i class="fas fa-search"></i><input type="text" id="stgContentQ" placeholder="بحث باسم العمل أو المسار…" oninput="clearTimeout(window._stgCq);window._stgCq=setTimeout(()=>stgContentLoad(1),350)"></div>
  <div id="stgContentList"></div>
  <div id="stgContentPager" style="display:flex;gap:6px;justify-content:center;margin-top:10px"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('stgContentM')">إغلاق</button></div></div></div>

<!-- ═══════════ نقل المحتوى ═══════════ -->
<div class="mbd" id="stgMoveM"><div class="mbox w"><div class="mhd"><div class="mhd-title"><i class="fas fa-right-left"></i> نقل المحتوى بين الأقراص</div><button class="mclose" onclick="CM('stgMoveM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <div class="fg"><label class="fl">Source Storage</label><select class="fs" id="stgMoveSrc" onchange="stgMoveScope()"></select></div>
    <div class="fg"><label class="fl">Destination Storage</label><select class="fs" id="stgMoveDst" onchange="stgMoveDstPaths()"></select></div>
  </div>
  <div class="fg"><label class="fl">ماذا تنقل؟</label><select class="fs" id="stgMoveScopeSel" onchange="stgMoveScope()">
    <option value="all">جميع المحتويات</option><option value="movie">فيلم</option><option value="series">مسلسل</option><option value="folder">مجلد</option></select></div>
  <div id="stgMoveRefBox"></div>
  <div class="fg" id="stgMoveDstPathBox" style="display:none"><label class="fl">المسار في الوجهة <small style="color:var(--t3)">(للملفات القادمة من التخزين الداخلي)</small></label><select class="fs" id="stgMoveDstPath"></select></div>
  <label style="display:flex;gap:8px;align-items:center;font-size:.8rem;margin:6px 0" id="stgMoveRemoveBox"><input type="checkbox" id="stgMoveRemove"> إزالة التخزين المصدر من النظام بعد إفراغه بنجاح</label>
  <div class="stg-banner info" style="margin:8px 0 0"><i class="fas fa-shield-halved"></i><div>النقل آمن: نسخ ← مقارنة الحجم ← إعادة قراءة الملف من القرص الجديد ومطابقة بصمة SHA-1 ← تحديث قاعدة البيانات ← ثم فقط حذف الأصل. أي فشل يُبقي الأصل سليماً والرابط يعمل.</div></div>
  <div id="stgMoveAlert" style="margin-top:10px"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('stgMoveM')">إلغاء</button><button class="btn btn-p" onclick="stgMoveCreate()"><i class="fas fa-play"></i> بدء النقل</button></div></div></div>

<!-- ═══════════ الإعدادات ═══════════ -->
<div class="mbd" id="stgSetM"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-sliders"></i> إعدادات التخزين</div><button class="mclose" onclick="CM('stgSetM')"><i class="fas fa-times"></i></button></div>
<div class="mbody">
  <div class="stg-banner info" style="margin:0 0 12px"><i class="fas fa-wand-magic-sparkles"></i><div><b>التوزيع تلقائي دائماً.</b> يختار النظام قرصاً مناسباً حسب نوع الملف، المساحة الحرة، وحجوزات النقل، ويتجاوز القرص غير المركّب أو للقراءة فقط أو الممتلئ.</div></div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
    <div class="fg"><label class="fl">Minimum Free (GB)</label><input type="number" class="fi" id="stgSetGb" min="0" step="1"></div>
    <div class="fg"><label class="fl">Minimum Free (%)</label><input type="number" class="fi" id="stgSetPct" min="0" max="90" step="0.5"></div>
    <div class="fg"><label class="fl">تنبيه عند (%)</label><input type="number" class="fi" id="stgSetWarn" min="50" max="99"></div>
  </div>
  <small style="color:var(--t3);display:block;margin:-6px 0 12px">يتوقف إرسال ملفات جديدة لأي قرص تقلّ مساحته الحرة عن الأكبر من القيمتين.</small>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
    <div class="fg"><label class="fl">فحص SMART كل (دقيقة)</label><input type="number" class="fi" id="stgSetSmart" min="5"></div>
    <div class="fg"><label class="fl">حدّ سرعة النقل (MB/s) <small style="color:var(--t3)">0 = بلا حدّ</small></label><input type="number" class="fi" id="stgSetLimit" min="0" step="5"></div>
  </div>
  <div class="fg"><label class="fl">اختبار الاختيار التلقائي</label>
    <div style="display:flex;gap:6px"><select class="fs" id="stgPickKind" style="max-width:140px"><option value="movies">فيلم</option><option value="series">مسلسل</option></select>
    <input type="number" class="fi" id="stgPickGb" value="4" min="0" step="0.5" style="max-width:110px" title="الحجم GB"><button class="btn btn-g" onclick="stgPickPreview()">أين سيذهب؟</button></div>
    <div id="stgPickOut" style="font-size:.8rem;margin-top:8px"></div></div>
  <div id="stgSetAlert"></div>
</div>
<div class="mfooter"><button class="btn btn-g" onclick="CM('stgSetM')">إلغاء</button><button class="btn btn-p" onclick="stgSettingsSave()"><i class="fas fa-save"></i> حفظ</button></div></div></div>

<script>
/* ════════════════════════════════════════════════════════════════════
   Multi-Storage — واجهة الإدارة
   كل عرض يُبنى من استجابات stg_* فقط، وكل نصّ يمرّ عبر esc() قبل الإدراج.
   ════════════════════════════════════════════════════════════════════ */
(function(){
  var ST = {data:null, disks:[], pollT:null, danger:null, contentSid:0, pathsSid:0};
  var TYPE_L = {movies:'Movies', series:'Series', mixed:'Mixed'};
  var STATUS_L = {online:'Online', offline:'Offline', readonly:'Read Only', maintenance:'Maintenance', full:'Disk Full', error:'Error', removed:'مُزال'};
  var JOB_L = {queued:'بالانتظار', running:'جارٍ', cancelling:'يُلغى…', done:'اكتمل', partial:'اكتمل جزئياً', failed:'فشل', cancelled:'أُلغي'};
  function E(v){ return (typeof esc === 'function') ? esc(v) : String(v==null?'':v).replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function A(v){ return (typeof escA === 'function') ? escA(v) : String(v==null?'':v).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }
  function $$(id){ return document.getElementById(id); }
  function note(msg, type){ if(typeof toast === 'function') toast(msg, type||'s'); else alert(msg); }
  function hb(b){ b=Number(b)||0; var u=['B','KB','MB','GB','TB','PB'],i=0; while(b>=1024&&i<u.length-1){b/=1024;i++;} return (i>=3?b.toFixed(2):b.toFixed(i?1:0))+' '+u[i]; }
  function ago(ts){ if(!ts) return 'لم يحدث بعد'; var s=Math.max(0,Math.floor(Date.now()/1000)-ts); if(s<60) return 'منذ '+s+' ث'; if(s<3600) return 'منذ '+Math.floor(s/60)+' د'; return 'منذ '+Math.floor(s/3600)+' س'; }
  function call(action, data){ var d=Object.assign({ajax_action:action}, data||{}); return api(d); }

  /* ── التحميل ── */
  window.stgLoad = function(refresh){
    return call('stg_overview', refresh?{refresh:1}:{}).then(function(d){
      if(!d || !d.success){ $$('stgCards').innerHTML='<div class="stg-empty" style="color:#ff6b6b">'+E((d&&d.error)||'تعذّر التحميل')+'</div>'; return; }
      ST.data = d;
      stgRenderBanners(); stgRenderAlerts(); stgRenderSummary(); stgRenderCards(); stgRenderJobs(d.jobs);
      if(refresh) note('تم فحص كل الأقراص','s');
      stgSchedulePoll();
    });
  };

  /* يشغّل مسار إعداد ثابتاً فقط؛ لا تُرسل الواجهة أمراً أو مساراً إلى الخادم. */
  window.stgInstall=function(btn){
    if(!confirm('سيُشغّل إعداد التخزين والعامل المجدول فقط. لن يهيّئ أو يحذف أي قرص. هل تريد المتابعة؟')) return;
    if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> جارٍ الإعداد…';}
    call('stg_install',{}).then(function(d){
      if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-screwdriver-wrench"></i> إعداد التخزين';}
      if(!d.success) return note(d.error||'تعذّر إعداد التخزين','e');
      note(d.message||'تم إعداد التخزين','s'); stgLoad(true);
    });
  };

  function stgRenderBanners(){
    var d=ST.data, h='';
    if(!d.helper.installed){
      h+='<div class="stg-banner warn"><i class="fas fa-screwdriver-wrench"></i><div><b>أداة الجذر غير مثبّتة بعد.</b> العرض والمراقبة والنقل تعمل الآن، لكن التقسيم والتهيئة والربط (Mount) وSMART تحتاج تثبيتاً لمرة واحدة على السيرفر:'
        +'<div class="stg-cmd"><code id="stgInstallCmd">sudo bash '+E(d.project_dir)+'/tools/install_storage.sh</code><button class="btn btn-g" style="padding:4px 10px;font-size:.72rem" onclick="stgCopy(\'stgInstallCmd\')"><i class="fas fa-copy"></i></button></div>'
        +(d.helper.error?'<small style="color:var(--t3)">'+E(d.helper.error)+'</small>':'')+'</div></div>';
    }
    else if((d.helper.version||0) < 2){
      h+='<div class="stg-banner warn"><i class="fas fa-screwdriver-wrench"></i><div><b>حدّث أداة الجذر.</b> النسخة المثبّتة قديمة: الربط (Mount) الذي تنفّذه من هذه الصفحة قد يبقى محبوساً داخل خدمة الويب فيظهر القرص Offline للنظام. شغّل مرة واحدة:'
        +'<div class="stg-cmd"><code id="stgInstallCmd">sudo bash '+E(d.project_dir)+'/tools/install_storage.sh</code><button class="btn btn-g" style="padding:4px 10px;font-size:.72rem" onclick="stgCopy(\'stgInstallCmd\')"><i class="fas fa-copy"></i></button></div></div></div>';
    }
    if(!d.cron_ok){
      h+='<div class="stg-banner info"><i class="fas fa-clock"></i><div><b>المراقبة الدورية غير مفعّلة</b> (آخر فحص: '+E(ago(d.monitor_at))+'). الصفحة تفحص عند فتحها، لكن تنبيهات اختفاء قرص أو امتلائه تحتاج cron كل دقيقة — أداة التثبيت أعلاه تضيفه تلقائياً، أو أضف:'
        +'<div class="stg-cmd"><code id="stgCronCmd">* * * * * www-data '+E(d.php_cli||'php')+' '+E(d.project_dir)+'/tools/storage_worker.php cron</code><button class="btn btn-g" style="padding:4px 10px;font-size:.72rem" onclick="stgCopy(\'stgCronCmd\')"><i class="fas fa-copy"></i></button></div></div></div>';
    }
    $$('stgBanners').innerHTML=h;
  }
  window.stgCopy=function(id){ var t=$$(id).textContent; if(navigator.clipboard) navigator.clipboard.writeText(t).then(function(){note('نُسخ','s');}); };

  function stgRenderAlerts(){
    var d=ST.data, nd=d.alerts.filter(function(a){return a.code==='new_disk';}), other=d.alerts.filter(function(a){return a.code!=='new_disk';});
    $$('stgNewDisks').innerHTML = nd.map(function(a){
      var m=(a.message||'').match(/(\/dev\/[A-Za-z0-9]+)/);
      return '<div class="stg-banner info"><i class="fas fa-plug-circle-plus"></i><div style="flex:1"><b>تم اكتشاف قرص تخزين جديد</b><br>'+E((a.message||'').replace(/^تم اكتشاف قرص تخزين جديد:\s*/,''))+'</div>'
        +(m?'<button class="btn btn-p" style="padding:6px 12px;font-size:.76rem" onclick="stgOpenDisk(\''+A(m[1])+'\')"><i class="fas fa-screwdriver-wrench"></i> فتح مدير القرص</button>':'')+'</div>';
    }).join('');
    $$('stgAlerts').innerHTML = other.map(function(a){
      var ic=a.level==='critical'?'fa-circle-exclamation':(a.level==='warning'?'fa-triangle-exclamation':'fa-circle-info');
      return '<div class="stg-alert '+E(a.level)+'"><i class="fas '+ic+'"></i><div>'+E(a.message)+'<br><small style="color:var(--t3)">'+E(a.updated_at||a.created_at)+'</small></div>'
        +'<button class="x" title="إخفاء" onclick="stgAlertDismiss('+(+a.id)+')"><i class="fas fa-xmark"></i></button></div>';
    }).join('');
  }
  window.stgAlertDismiss=function(id){ call('stg_alert_dismiss',{id:id}).then(function(){ stgLoad(false); }); };

  function stgRenderSummary(){
    var on=0, off=0, tot=0, used=0, free=0;
    ST.data.storages.forEach(function(s){ if(s.admin_state==='removed') return; if(s.readable){on++; tot+=s.total; used+=s.used; free+=s.free;} else off++; });
    $$('stgSummary').innerHTML=
      '<div class="stg-sum"><small>وحدات التخزين</small><b>'+on+'</b> <span style="color:#00c080;font-size:.75rem">متاحة</span>'+(off?' · <b style="color:#ff4d57">'+off+'</b> <span style="color:#ff4d57;font-size:.75rem">غير متاحة</span>':'')+'</div>'
      +'<div class="stg-sum"><small>السعة (المتاحة للقراءة)</small><b>'+hb(tot)+'</b></div>'
      +'<div class="stg-sum"><small>المستخدم</small><b>'+hb(used)+'</b></div>'
      +'<div class="stg-sum"><small>الحرّ</small><b>'+hb(free)+'</b></div>'
      +'<div class="stg-sum"><small>التوزيع</small><b style="font-size:.95rem">Automatic</b></div>';
  }

  function stgRenderCards(){
    var list = ST.data.storages;
    if(!list.length){ $$('stgCards').innerHTML='<div class="stg-empty">لا يوجد تخزين</div>'; return; }
    $$('stgCards').innerHTML = list.map(function(s){
      var removed = s.admin_state==='removed', st = removed?'removed':s.status;
      var barCls = s.pct>=95?'crit':(s.pct>=(ST.data.settings.warn_pct||90)?'hi':'');
      var icoCls = s.is_legacy?'legacy':s.content_type;
      var ico = s.is_legacy?'fa-server':(s.content_type==='movies'?'fa-film':(s.content_type==='series'?'fa-tv':'fa-layer-group'));
      var acts='';
      if(removed){
        acts='<button class="btn btn-g" onclick="stgRestore('+s.id+')"><i class="fas fa-rotate-left"></i> استعادة</button>';
      } else {
        acts='<button class="btn btn-g" onclick="stgOpenContent('+s.id+')"><i class="fas fa-film"></i> المحتوى ('+(+s.episodes)+')</button>'
          +'<button class="btn btn-g" onclick="stgOpenPaths('+s.id+')"><i class="fas fa-folder-tree"></i> المسارات</button>'
          +(!s.is_legacy && (s.status==='offline'||s.status==='error') && s.admin_state!=='maintenance'
              ?'<button class="btn btn-p" onclick="stgRemount('+s.id+',this)"><i class="fas fa-plug"></i> إعادة الربط</button>':'')
          +(s.is_legacy?'':
            (s.admin_state==='maintenance'
              ?'<button class="btn btn-g" onclick="stgSetState('+s.id+',\'active\')"><i class="fas fa-play"></i> تفعيل</button>'
              :'<button class="btn btn-g" onclick="stgSetState('+s.id+',\'maintenance\')"><i class="fas fa-wrench"></i> صيانة</button>')
            +'<button class="btn btn-g" onclick="stgUmountStorage('+s.id+')"><i class="fas fa-eject"></i> Unmount</button>')
          +'<button class="btn btn-g" onclick="stgOpenSave('+s.id+')" title="تعديل"><i class="fas fa-pen-to-square"></i></button>';
      }
      return '<div class="stg-card'+(removed?' removed':'')+'">'
        +'<div class="stg-card-top"><div class="stg-ico '+E(icoCls)+'"><i class="fas '+ico+'"></i></div>'
        +'<div><div class="stg-name">'+E(s.name)+'</div><div class="stg-type">'+(s.is_legacy?'داخلي (uploads)':E(TYPE_L[s.content_type]||s.content_type))+(s.health==='failing'?' · <span style="color:#ff4d57">SMART: عطل</span>':(s.health==='warning'?' · <span style="color:#f5a623">SMART: تحذير</span>':''))+'</div></div>'
        +'<span class="stg-pill st-'+E(st)+'">'+E(STATUS_L[st]||st)+'</span></div>'
        +'<div class="stg-bar"><span class="'+barCls+'" style="width:'+Math.min(100,s.pct)+'%"></span></div>'
        +(!s.readable&&s.total?'<small style="color:var(--t3);font-size:.7rem">آخر قراءة معروفة:</small>':'')
        +'<div class="stg-nums"><div>السعة<b>'+E(s.total_h)+'</b></div><div>المستخدم<b>'+E(s.used_h)+' <small style="color:var(--t3)">('+s.pct+'%)</small></b></div><div>الحرّ<b>'+E(s.free_h)+'</b></div></div>'
        +'<div class="stg-meta">'+E(s.mount_path)+(s.device?' · '+E(s.device):'')+(s.fs_type?' · '+E(s.fs_type):'')+'</div>'
        +(s.status_msg && st!=='online'?'<div class="stg-msg">'+E(s.status_msg)+'</div>':'')
        +'<div class="stg-acts">'+acts+'</div></div>';
    }).join('');
  }

  /* ── إعادة ربط قرص غائب (mount فقط بالبصمة نفسها — لا تهيئة) ── */
  window.stgRemount=function(id,btn){
    if(btn){ btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> جارٍ الربط…'; }
    call('stg_remount',{id:id}).then(function(d){
      if(!d.success){ note(d.error,'e'); if(btn){ btn.disabled=false; btn.innerHTML='<i class="fas fa-plug"></i> إعادة الربط'; } return; }
      note(d.already?'القرص مركّب بالفعل — حُدّثت الحالة':'أُعيد ربط القرص','s'); stgLoad(false);
    });
  };

  /* ── الحالة ── */
  window.stgSetState=function(id,state){
    call('stg_set_state',{id:id,state:state}).then(function(d){ if(!d.success) return note(d.error,'e'); stgLoad(false); });
  };

  /* ── إضافة/تعديل ── */
  window.stgOpenSave=function(id, prefill){
    var s = id ? ST.data.storages.find(function(x){return x.id===id;}) : null;
    $$('stgSaveId').value = id||0;
    $$('stgSaveTitle').textContent = s ? 'تعديل '+s.name : 'إضافة تخزين';
    $$('stgSaveName').value = s ? s.name : ((prefill&&prefill.name)||'');
    $$('stgSaveType').value = s ? s.content_type : ((prefill&&prefill.type)||'mixed');
    $$('stgSaveType').disabled = !!(s && s.is_legacy);
    $$('stgSaveMount').value = s ? s.mount_path : ((prefill&&prefill.mount)||'');
    $$('stgSaveMount').disabled = !!(s && s.is_legacy);
    $$('stgSaveMinGb').value = s && s.min_free_gb!==null ? s.min_free_gb : '';
    $$('stgSaveMinPct').value = s && s.min_free_pct!==null ? s.min_free_pct : '';
    $$('stgSaveProp').value = s ? s.priority : 0;
    al('stgSaveAlert','','');
    OM('stgSaveM');
  };
  window.stgSave=function(){
    al('stgSaveAlert','<span class="sp"></span> جارٍ الحفظ والتحقق من القرص…','i');
    call('stg_save',{id:$$('stgSaveId').value, name:$$('stgSaveName').value, content_type:$$('stgSaveType').value, mount_path:$$('stgSaveMount').value,
      min_free_gb:$$('stgSaveMinGb').value, min_free_pct:$$('stgSaveMinPct').value, priority:$$('stgSaveProp').value}).then(function(d){
      if(!d.success) return al('stgSaveAlert', E(d.error), 'e');
      CM('stgSaveM'); note('تم الحفظ','s'); stgLoad(false); stgLoadDisks();
    });
  };

  /* ── الإزالة ── */
  window.stgOpenRemove=function(id){
    var s = ST.data.storages.find(function(x){return x.id===id;}); if(!s) return;
    call('stg_remove_preview',{id:id}).then(function(d){
      if(!d.success) return note(d.error,'e');
      var st=d.stats, has=st.episodes>0;
      var html='<b>إزالة «'+E(s.name)+'» من نظام التخزين.</b> لا يُحذف أي ملف من القرص، لكن المحتوى المرتبط به يصبح غير متاح للمشاهدين.'
        +'<dl class="stg-kv" style="margin-top:10px"><dt>الأفلام</dt><dd>'+st.movies+'</dd><dt>المسلسلات</dt><dd>'+st.series+'</dd><dt>الحلقات / الملفات المرتبطة</dt><dd>'+st.episodes+'</dd>'
        +'<dt>الملفات على القرص</dt><dd>'+(st.files_on_disk===null?'—':st.files_on_disk+(st.files_truncated?'+':''))+'</dd><dt>المساحة المستخدمة</dt><dd>'+E(st.used_h)+'</dd></dl>';
      var extra = has ? '<div class="stg-banner info" style="margin:0 0 10px"><i class="fas fa-lightbulb"></i><div>الأسلم: انقل المحتوى أولاً ثم تُزال الوحدة تلقائياً عند اكتمال النقل.'
          +'<div style="margin-top:8px"><button class="btn btn-p" onclick="CM(\'stgDangerM\');stgOpenMove('+id+',true)"><i class="fas fa-right-left"></i> نقل المحتوى ثم الإزالة</button></div></div></div>'
          +'<label style="display:flex;gap:8px;align-items:center;font-size:.8rem;margin-bottom:10px;color:#ff6b6b"><input type="checkbox" id="stgRemoveForce"> إزالة رغم وجود محتوى (يصبح غير متاح)</label>' : '';
      stgDanger({title:'إزالة تخزين', text:html, extra:extra, word:'REMOVE '+s.name, run:function(confirm){
        var force=$$('stgRemoveForce'); return call('stg_remove',{id:id, confirm:confirm, force:(force&&force.checked)?1:0});
      }, done:function(){ note('أُزيل من النظام','s'); stgLoad(false); }});
    });
  };
  window.stgRestore=function(id){ call('stg_restore',{id:id}).then(function(d){ if(!d.success) return note(d.error,'e'); note('أُعيد','s'); stgLoad(false); }); };

  /* ── نافذة التأكيد المدمّر (عامة) ── */
  function stgDanger(o){
    ST.danger=o;
    $$('stgDangerTitle').textContent=o.title; $$('stgDangerText').innerHTML=o.text; $$('stgDangerExtra').innerHTML=o.extra||'';
    $$('stgDangerWord').textContent=o.word; $$('stgDangerInput').value=''; $$('stgDangerGo').disabled=true; al('stgDangerAlert','','');
    OM('stgDangerM'); setTimeout(function(){ try{$$('stgDangerInput').focus();}catch(e){} },100);
  }
  window.stgDangerCheck=function(){ $$('stgDangerGo').disabled = ($$('stgDangerInput').value !== ST.danger.word); };
  window.stgDangerRun=function(){
    var o=ST.danger; if(!o) return;
    $$('stgDangerGo').disabled=true;
    al('stgDangerAlert','<span class="sp"></span> جارٍ التنفيذ… لا تغلق الصفحة','i');
    o.run($$('stgDangerInput').value).then(function(d){
      if(!d.success){ $$('stgDangerGo').disabled=false; return al('stgDangerAlert', E(d.error), 'e'); }
      CM('stgDangerM'); if(o.done) o.done(d);
    });
  };

  /* ── الأقراص ── */
  window.stgLoadDisks=function(){
    return call('stg_disks').then(function(d){
      var tb=$$('stgDisksTable').querySelector('tbody');
      if(!d.success){ tb.innerHTML='<tr><td colspan="9" style="color:#ff6b6b;text-align:center">'+E(d.error)+'</td></tr>'; return; }
      ST.disks=d.disks;
      if(!d.exec){ tb.innerHTML='<tr><td colspan="9" style="text-align:center;color:#ff6b6b">الدالة exec معطّلة في PHP — لا يمكن قراءة الأقراص</td></tr>'; return; }
      if(!d.disks.length){ tb.innerHTML='<tr><td colspan="9" style="text-align:center">لم تُكتشف أقراص</td></tr>'; return; }
      tb.innerHTML=d.disks.map(function(x){
        var state = x.is_system?'<span class="stg-tag sys"><i class="fas fa-lock"></i> قرص النظام</span>'
          : (x.storage_ids.length?'<span class="stg-tag ok">مستخدم كتخزين</span>'
          : (x.members.length?'<span class="stg-tag sys">'+E(x.members.join(', '))+'</span>'
          : (x.used?'<span class="stg-tag">'+(x.mounted.length?'مركّب':'فيه بيانات')+'</span>':'<span class="stg-tag ok">فارغ</span>')));
        if(!x.acknowledged && !x.is_system) state+=' <span class="stg-tag new">جديد</span>';
        var fs = x.partitions.map(function(p){return p.fstype;}).filter(Boolean).join(', ') || x.fstype || '—';
        var mp = x.mounted.join(', ') || '—';
        var uuid = x.partitions.map(function(p){return p.uuid;}).filter(Boolean).join(', ');
        var smart = x.smart_status ? (x.smart_status==='passed'?'<span style="color:#00c080">سليم</span>':(x.smart_status==='failed'?'<span style="color:#ff4d57">عطل</span>':E(x.smart_status))) : '<span style="color:var(--t3)">—</span>';
        return '<tr><td class="ltr"><b>'+E(x.path)+'</b></td><td>'+E((x.vendor+' '+x.model).trim()||'—')+(x.serial?'<br><small style="color:var(--t3)">'+E(x.serial)+'</small>':'')+'</td>'
          +'<td>'+E(x.media)+'</td><td class="ltr">'+E(x.size_h)+'</td><td class="stg-hide-sm">'+E(fs)+'</td><td>'+state+'</td>'
          +'<td class="ltr stg-hide-sm">'+E(mp)+(uuid?'<br><small style="color:var(--t3)">'+E(uuid)+'</small>':'')+'</td>'
          +'<td>'+(x.smart_temp!==null?E(x.smart_temp)+'°C · ':'')+smart+'</td>'
          +'<td><button class="btn btn-g" style="padding:5px 10px;font-size:.74rem" onclick="stgOpenDisk(\''+A(x.path)+'\')">'+(x.is_system?'عرض':'إدارة')+'</button></td></tr>';
      }).join('');
    });
  };
  window.stgSmartNow=function(){ note('جارٍ فحص SMART…','i'); call('stg_smart_now').then(function(d){ if(!d.success) return note(d.error,'e'); stgLoadDisks(); stgLoad(false); }); };

  window.stgOpenDisk=function(path){
    var go=function(){
      var x=ST.disks.find(function(d){return d.path===path;});
      if(!x) return note('القرص غير موجود حالياً','e');
      if(!x.acknowledged && !x.is_system) call('stg_disk_ack',{key:x.key});
      $$('stgDiskTitle').textContent=x.path;
      var helper = ST.data && ST.data.helper && ST.data.helper.installed;
      var h='<dl class="stg-kv"><dt>الجهاز</dt><dd>'+E(x.path)+'</dd><dt>الشركة / الموديل</dt><dd>'+E((x.vendor+' '+x.model).trim()||'—')+'</dd>'
        +'<dt>الرقم التسلسلي</dt><dd>'+E(x.serial||'—')+'</dd><dt>النوع</dt><dd>'+E(x.media)+(x.transport?' / '+E(x.transport):'')+'</dd>'
        +'<dt>السعة</dt><dd>'+E(x.size_h)+'</dd><dt>SMART</dt><dd>'+E(x.smart_status||'—')+(x.smart_temp!==null?' · '+E(x.smart_temp)+'°C':'')+'</dd></dl>';
      if(x.is_system){
        h+='<div class="stg-danger-box"><b><i class="fas fa-lock"></i> هذا قرص النظام.</b> كل العمليات عليه مقفلة — لا يمكن تهيئته أو تقسيمه من هنا بأي حال.</div>';
      } else if(x.members.length){
        h+='<div class="stg-danger-box"><b>القرص عضو في '+E(x.members.join(', '))+'.</b> العمليات مقفلة حتى تُزيله من ذلك النظام (مثل مجمع ZFS في «إدارة الهارد دسك»).</div>';
      } else if(!helper){
        h+='<div class="stg-banner warn"><i class="fas fa-screwdriver-wrench"></i><div>العمليات على الأقراص تحتاج تثبيت أداة الجذر أولاً (انظر التنبيه أعلى الصفحة).</div></div>';
      }
      h+='<div style="font-weight:800;margin:6px 0 8px">الأقسام (Partitions)</div>';
      if(!x.partitions.length){
        h+='<div class="stg-empty" style="padding:14px">لا توجد أقسام — القرص '+(x.fstype?'مهيّأ مباشرة ('+E(x.fstype)+')':'فارغ')+'</div>';
      }
      x.partitions.forEach(function(p){
        var linked = p.storage_id ? '<span class="stg-tag ok">'+E(p.storage_name)+'</span>' : '';
        var a='';
        if(!x.is_system && !x.members.length && helper && p.type==='part'){
          if(p.mountpoint){
            a+='<button class="btn btn-g" onclick="stgUmount(\''+A(p.mountpoint)+'\')"><i class="fas fa-eject"></i> Unmount</button>';
            if(!p.storage_id) a+='<button class="btn btn-p" onclick="CM(\'stgDiskM\');stgOpenSave(0,{mount:\''+A(p.mountpoint)+'\'})"><i class="fas fa-plus"></i> إضافة كتخزين</button>';
          } else {
            if(p.fstype && ['ext4','xfs','ext3','btrfs'].indexOf(p.fstype)>=0) a+='<button class="btn btn-p" onclick="stgMountPrompt(\''+A(p.path)+'\')"><i class="fas fa-link"></i> Mount</button>';
            if(!p.storage_id) a+='<button class="btn" style="background:rgba(255,77,87,.15);color:#ff6b6b;border:1px solid rgba(255,77,87,.4)" onclick="stgFormatPrompt(\''+A(p.path)+'\')"><i class="fas fa-eraser"></i> تهيئة (Format)</button>';
          }
        }
        h+='<div class="stg-part"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b style="direction:ltr">'+E(p.path)+'</b><span style="color:var(--t3)">'+E(p.size_h)+'</span>'
          +'<span class="stg-tag">'+E(p.fstype||'غير مهيّأ')+'</span>'+(p.label?'<span class="stg-tag">'+E(p.label)+'</span>':'')+linked+'</div>'
          +'<div class="stg-meta" style="margin-top:4px">'+(p.mountpoint?'mount: '+E(p.mountpoint):'غير مركّب')+(p.uuid?' · UUID '+E(p.uuid):'')+'</div>'
          +(a?'<div class="stg-part-acts">'+a+'</div>':'')+'</div>';
      });
      if(!x.is_system && !x.members.length && helper && !x.storage_ids.length && !x.mounted.length){
        h+='<div style="font-weight:800;margin:14px 0 8px">عمليات على القرص كاملاً</div><div class="stg-part-acts">'
          +'<button class="btn" style="background:#ff4d57;color:#fff" onclick="stgInitPrompt(\''+A(x.path)+'\')"><i class="fas fa-table-cells"></i> تهيئة القرص + إنشاء Partition</button>'
          +(x.partitions.length?'<button class="btn btn-g" style="color:#ff6b6b" onclick="stgWipePrompt(\''+A(x.path)+'\')"><i class="fas fa-trash"></i> حذف كل الأقسام</button>':'')
          +'</div><small style="color:var(--t3);display:block;margin-top:6px">التهيئة تُنشئ جدول GPT وقسماً واحداً يشغل القرص كله. الخطوة التالية: Format ثم Mount ثم «إضافة كتخزين».</small>';
      } else if(x.mounted.length && !x.is_system){
        h+='<small style="color:var(--t3);display:block;margin-top:10px">لتهيئة القرص كاملاً: فكّ ربط أقسامه أولاً (Unmount).</small>';
      }
      $$('stgDiskBody').innerHTML=h;
      OM('stgDiskM');
    };
    if(ST.disks.length) go(); else stgLoadDisks().then(go);
  };
  function reopenDisk(path){ stgLoadDisks().then(function(){ stgOpenDisk(path); }); stgLoad(false); }

  window.stgInitPrompt=function(dev){
    stgDanger({title:'تهيئة القرص', word:'FORMAT '+dev,
      text:'<b>سيُحذف كل شيء على '+E(dev)+' نهائياً</b> — كل الأقسام والملفات. ثم يُنشأ جدول أقسام GPT وقسم واحد يشغل القرص كله.<br>لا يمكن التراجع.',
      run:function(c){ return call('stg_disk_init',{device:dev, confirm:c}); },
      done:function(d){ note('أُنشئ القسم '+(d.partition||''),'s'); reopenDisk(dev); if(d.partition) setTimeout(function(){ stgFormatPrompt(d.partition); },600); }});
  };
  window.stgWipePrompt=function(dev){
    stgDanger({title:'حذف الأقسام', word:'DELETE '+dev,
      text:'<b>سيُحذف جدول الأقسام وكل التواقيع من '+E(dev)+'</b> ومعها كل البيانات. لا يمكن التراجع.',
      run:function(c){ return call('stg_disk_wipe',{device:dev, confirm:c}); },
      done:function(){ note('حُذفت الأقسام','s'); reopenDisk(dev); }});
  };
  window.stgFormatPrompt=function(part){
    stgDanger({title:'تهيئة القسم (Format)', word:'FORMAT '+part,
      text:'<b>سيُمسح كل محتوى '+E(part)+'</b> ويُنشأ عليه نظام ملفات جديد. لا يمكن التراجع.',
      extra:'<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><div class="fg"><label class="fl">نظام الملفات</label><select class="fs" id="stgFmtFs"><option value="ext4">EXT4 (موصى به)</option><option value="xfs">XFS (ملفات كبيرة جداً)</option></select></div>'
        +'<div class="fg"><label class="fl">التسمية</label><input class="fi" id="stgFmtLabel" value="STORAGE" maxlength="12" style="direction:ltr"></div></div>',
      run:function(c){ return call('stg_part_format',{partition:part, fs:$$('stgFmtFs').value, label:$$('stgFmtLabel').value, confirm:c}); },
      done:function(d){ note('تمت التهيئة — UUID '+(d.uuid||''),'s'); var disk=part.replace(/p?\d+$/,''); reopenDisk(disk); setTimeout(function(){ stgMountPrompt(part); },700); }});
  };
  window.stgMountPrompt=function(part){
    call('stg_suggest_mount').then(function(s){
      var mp=prompt('نقطة الربط (Mount Point) للقسم '+part+':', (s&&s.mountpoint)||'/mnt/storage1');
      if(!mp) return;
      note('جارٍ الربط…','i');
      call('stg_part_mount',{partition:part, mountpoint:mp}).then(function(d){
        if(!d.success) return note(d.error,'e');
        note('رُبط على '+mp+' (مع سطر fstab بالـUUID)','s');
        var disk=part.replace(/p?\d+$/,''); reopenDisk(disk);
        var linked=(ST.data.storages||[]).some(function(x){return x.mount_path===mp && x.admin_state!=='removed';});
        if(!linked){ setTimeout(function(){ CM('stgDiskM'); stgOpenSave(0,{mount:mp, name:'Storage-'+String(s.index||1).padStart(2,'0')}); },500); }
      });
    });
  };
  window.stgUmount=function(mp){
    if(!confirm('فكّ ربط '+mp+' وإزالته من التخزين؟\nلن تُحذف الملفات، لكن القرص سيختفي من بطاقات Storage ويصبح متاحاً للتهيئة.')) return;
    call('stg_umount',{mountpoint:mp}).then(function(d){ if(!d.success) return note(d.error,'e'); note('فُكّ الربط وأُزيل من التخزين — أصبح القرص جاهزاً للإدارة أو التهيئة','s'); stgLoadDisks().then(function(){ CM('stgDiskM'); }); stgLoad(false); });
  };
  window.stgUmountStorage=function(id){
    var s=(ST.data.storages||[]).find(function(x){return +x.id===+id;});
    if(!s) return note('التخزين غير موجود','e');
    stgUmount(s.mount_path);
  };

  /* ── المسارات ── */
  window.stgOpenPaths=function(sid){
    ST.pathsSid=sid; var s=ST.data.storages.find(function(x){return x.id===sid;});
    $$('stgPathsTitle').textContent=s?s.name:''; stgPathReset(); stgRenderPaths(s?s.paths:[]); OM('stgPathsM');
  };
  function stgRenderPaths(paths){
    ST.paths = paths || [];
    var s=ST.data.storages.find(function(x){return x.id===ST.pathsSid;})||{mount_path:''};
    $$('stgPathsList').innerHTML = paths.length ? paths.map(function(p){
      return '<div class="stg-list-row"><i class="fas fa-folder" style="color:#f5a623"></i><div class="grow"><b>'+E(p.name)+'</b>'+(+p.is_default?' <span class="stg-tag ok">افتراضي</span>':'')+' <span class="stg-tag">'+E(p.purpose)+'</span>'
        +'<div class="path">'+E(s.mount_path+'/'+p.rel_path)+'</div></div>'
        +'<button class="btn btn-g" style="padding:4px 9px" onclick="stgPathEdit('+(+p.id)+')"><i class="fas fa-pen"></i></button>'
        +'<button class="btn btn-g" style="padding:4px 9px;color:#ff6b6b" onclick="stgPathDelete('+(+p.id)+')"><i class="fas fa-trash"></i></button></div>';
    }).join('') : '<div class="stg-empty" style="padding:12px">لا مسارات</div>';
  }
  window.stgPathReset=function(){ $$('stgPathId').value=0; $$('stgPathRel').value=''; $$('stgPathName').value=''; $$('stgPathPurpose').value='movies'; $$('stgPathDefault').checked=false; al('stgPathAlert','',''); };
  window.stgPathEdit=function(id){ var p=(ST.paths||[]).find(function(x){return +x.id===+id;}); if(!p) return; $$('stgPathId').value=p.id; $$('stgPathRel').value=p.rel_path; $$('stgPathName').value=p.name; $$('stgPathPurpose').value=p.purpose; $$('stgPathDefault').checked=+p.is_default===1; };
  window.stgPathSave=function(){
    call('stg_path_save',{storage_id:ST.pathsSid, id:$$('stgPathId').value, rel_path:$$('stgPathRel').value, name:$$('stgPathName').value, purpose:$$('stgPathPurpose').value, is_default:$$('stgPathDefault').checked?1:0}).then(function(d){
      if(!d.success) return al('stgPathAlert',E(d.error),'e');
      stgRenderPaths(d.paths); stgPathReset(); note(d.dir_created?'حُفظ وأُنشئ المجلد على القرص':'حُفظ','s'); stgLoad(false);
    });
  };
  window.stgPathDelete=function(id){
    if(!confirm('حذف هذا المسار؟ يُحذف مجلده فقط إن كان فارغاً.')) return;
    call('stg_path_delete',{id:id}).then(function(d){ if(!d.success) return al('stgPathAlert',E(d.error),'e'); stgRenderPaths(d.paths); stgLoad(false); });
  };

  /* ── المحتوى ── */
  window.stgOpenContent=function(sid){ ST.contentSid=sid; var s=ST.data.storages.find(function(x){return x.id===sid;}); $$('stgContentTitle').textContent=s?s.name:''; $$('stgContentQ').value=''; OM('stgContentM'); stgContentLoad(1); };
  window.stgContentLoad=function(page){
    $$('stgContentList').innerHTML='<div class="stg-empty"><span class="sp"></span></div>';
    call('stg_content',{storage_id:ST.contentSid, q:$$('stgContentQ').value, page:page}).then(function(d){
      if(!d.success) return $$('stgContentList').innerHTML='<div class="stg-empty">'+E(d.error)+'</div>';
      $$('stgContentList').innerHTML = d.rows.length ? d.rows.map(function(r){
        return '<div class="stg-list-row"><i class="fas '+(r.kind==='movie'?'fa-film':'fa-tv')+'" style="color:'+(r.kind==='movie'?'#ff4d57':'#b36bff')+'"></i>'
          +'<div class="grow"><b>'+E(r.series_name||'—')+'</b>'+(r.kind==='series'?' <small style="color:var(--t3)">· حلقة '+E(r.episode_number)+'</small>':'')
          +(!r.exists?' <span class="stg-tag sys">الملف مفقود</span>':'')+'<div class="path">'+E(r.relative_path)+' · '+E(r.size_h)+'</div></div>'
          +(r.exists?'<button class="btn btn-g" style="padding:4px 9px" title="تشغيل" onclick=\'testChannel("'+A(r.stream_url)+'","'+A(r.series_name||'')+'")\'><i class="fas fa-play"></i></button>':'')
          +'<button class="btn btn-g" style="padding:4px 9px" title="نقل" onclick="stgOpenMove('+ST.contentSid+',false,{scope:\''+(r.kind==='movie'?'movie':'series')+'\',ref:'+(+r.series_id)+',label:\''+A(r.series_name||'')+'\'})"><i class="fas fa-right-left"></i></button>'
          +'<button class="btn btn-g" style="padding:4px 9px;color:#ff6b6b" title="حذف الملف والحلقة" onclick="stgContentDelete('+(+r.id)+',\''+A(r.series_name||'')+'\')"><i class="fas fa-trash"></i></button></div>';
      }).join('') : '<div class="stg-empty">لا يوجد محتوى</div>';
      var pg=''; for(var i=1;i<=Math.min(d.pages,20);i++){ pg+='<button class="btn '+(i===d.page?'btn-p':'btn-g')+'" style="padding:4px 10px" onclick="stgContentLoad('+i+')">'+i+'</button>'; }
      $$('stgContentPager').innerHTML = d.pages>1 ? pg : '';
    });
  };
  window.stgContentDelete=function(eid,name){
    if(!confirm('حذف «'+name+'» نهائياً؟\nتُحذف الحلقة من الموقع ويُحذف ملفها من القرص.')) return;
    call('stg_content_delete',{episode_id:eid}).then(function(d){ if(!d.success) return note(d.error,'e'); note(d.file_deleted?'حُذف الملف والسجل':'حُذف السجل','s'); stgContentLoad(1); stgLoad(false); });
  };

  /* ── النقل ── */
  window.stgOpenMove=function(srcId, removeAfter, pre){
    if(!ST.data) return;
    var live=ST.data.storages.filter(function(s){return s.admin_state!=='removed';});
    var opt=function(s){ return '<option value="'+s.id+'"'+(s.writable?'':' data-ro="1"')+'>'+E(s.name)+' — '+E(TYPE_L[s.content_type]||s.content_type)+' · حرّ '+E(s.free_h)+(s.readable?'':' ('+E(STATUS_L[s.status]||s.status)+')')+'</option>'; };
    $$('stgMoveSrc').innerHTML=live.map(opt).join('');
    $$('stgMoveDst').innerHTML=live.map(opt).join('');
    if(srcId) $$('stgMoveSrc').value=srcId;
    var firstOther=live.find(function(s){return s.id!==(+$$('stgMoveSrc').value) && s.writable && !s.is_legacy;});
    if(firstOther) $$('stgMoveDst').value=firstOther.id;
    $$('stgMoveScopeSel').value = pre ? pre.scope : 'all';
    $$('stgMoveRemove').checked = !!removeAfter;
    ST.movePre = pre||null;
    al('stgMoveAlert','','');
    stgMoveScope(); stgMoveDstPaths();
    OM('stgMoveM');
  };
  window.stgMoveScope=function(){
    var sc=$$('stgMoveScopeSel').value, src=+$$('stgMoveSrc').value, box=$$('stgMoveRefBox');
    var s=ST.data.storages.find(function(x){return x.id===src;})||{};
    $$('stgMoveRemoveBox').style.display = (sc==='all' && !s.is_legacy) ? '' : 'none';
    $$('stgMoveDstPathBox').style.display = s.is_legacy ? '' : 'none';
    if(sc==='all'){ box.innerHTML=''; return; }
    if(sc==='folder'){
      box.innerHTML='<div class="fg"><label class="fl">المجلد</label><select class="fs" id="stgMoveRef"><option>…</option></select></div>';
      call('stg_folders',{storage_id:src}).then(function(d){ $$('stgMoveRef').innerHTML=(d.folders||[]).map(function(f){return '<option value="'+E(f)+'">'+E(f)+'</option>';}).join('') || '<option value="">لا مجلدات</option>'; });
      return;
    }
    box.innerHTML='<div class="fg"><label class="fl">'+(sc==='movie'?'الفيلم':'المسلسل')+'</label><div class="tsrch"><i class="fas fa-search"></i><input type="text" id="stgMoveQ" placeholder="بحث…" oninput="clearTimeout(window._stgMq);window._stgMq=setTimeout(stgMoveSearch,300)"></div>'
      +'<select class="fs" id="stgMoveRef" size="6" style="margin-top:6px;height:auto"></select></div>';
    if(ST.movePre && ST.movePre.ref){ $$('stgMoveRef').innerHTML='<option value="'+(+ST.movePre.ref)+'" selected>'+E(ST.movePre.label)+'</option>'; ST.movePre=null; }
    else stgMoveSearch();
  };
  window.stgMoveSearch=function(){
    var sc=$$('stgMoveScopeSel').value;
    call('stg_series_search',{storage_id:$$('stgMoveSrc').value, q:($$('stgMoveQ')||{}).value||''}).then(function(d){
      var rows=(d.rows||[]).filter(function(r){ return sc==='movie' ? r.kind==='movie' : r.kind==='series'; });
      $$('stgMoveRef').innerHTML = rows.map(function(r){ return '<option value="'+(+r.id)+'">'+E(r.name)+' — '+(+r.files)+' ملف'+(+r.bytes>0?' · '+E(r.bytes_h):'')+'</option>'; }).join('') || '<option value="" disabled>لا نتائج على هذا التخزين</option>';
      if(rows.length) $$('stgMoveRef').selectedIndex=0;
    });
  };
  window.stgMoveDstPaths=function(){
    var dst=ST.data.storages.find(function(x){return x.id===(+$$('stgMoveDst').value);});
    $$('stgMoveDstPath').innerHTML='<option value="0">تلقائي حسب النوع (movies / series)</option>'+((dst&&dst.paths)||[]).map(function(p){return '<option value="'+(+p.id)+'">'+E(p.name)+' — '+E(p.rel_path)+'</option>';}).join('');
  };
  window.stgMoveCreate=function(){
    var sc=$$('stgMoveScopeSel').value, ref=$$('stgMoveRef')?$$('stgMoveRef').value:'';
    if(sc!=='all' && !ref) return al('stgMoveAlert','اختر '+(sc==='folder'?'المجلد':'العنصر')+' أولاً','e');
    var label = sc==='all' ? 'جميع المحتويات' : (($$('stgMoveRef').selectedOptions[0]||{}).textContent||ref);
    al('stgMoveAlert','<span class="sp"></span> جارٍ تخطيط الملفات والتحقق من المساحة…','i');
    call('stg_move_create',{source:$$('stgMoveSrc').value, dest:$$('stgMoveDst').value, scope:sc, scope_ref:ref, scope_label:label,
      dest_path_id:$$('stgMoveDstPath').value||0, remove_source_after:$$('stgMoveRemove').checked?1:0}).then(function(d){
      if(!d.success) return al('stgMoveAlert',E(d.error),'e');
      CM('stgMoveM');
      note('بدأت المهمة #'+d.job_id+': '+d.summary.files+' ملف · '+hb(d.summary.bytes)+(d.worker_started?'':' — ستبدأ عند تشغيل العامل (cron)'),'s');
      stgJobsLoad();
    });
  };

  /* ── المهام ── */
  function stgRenderJobs(jobs){
    if(!jobs||!jobs.length){ $$('stgJobs').innerHTML='<div class="stg-empty">لا توجد مهام نقل</div>'; return; }
    $$('stgJobs').innerHTML=jobs.map(function(j){
      var pct = j.pct!==undefined ? j.pct : (+j.total_bytes ? Math.round(j.done_bytes/j.total_bytes*1000)/10 : 0);
      var active = ['queued','running','cancelling'].indexOf(j.status)>=0;
      var cls = j.status==='failed'?'st-error':(j.status==='partial'||j.status==='cancelled'?'st-maintenance':(j.status==='done'?'st-online':'st-readonly'));
      return '<div class="stg-job"><div class="stg-job-top"><b>#'+(+j.id)+'</b> '+E(j.source_name||'?')+' <i class="fas fa-arrow-left" style="color:var(--t3)"></i> '+E(j.dest_name||'?')
        +' <small>· '+E(j.scope_label||j.scope)+'</small><span class="stg-pill '+cls+'" style="margin-inline-start:auto">'+E(JOB_L[j.status]||j.status)+(j.stale?' — متوقف؟':'')+'</span></div>'
        +'<div class="stg-bar" style="margin:8px 0 6px"><span style="width:'+Math.min(100,pct)+'%"></span></div>'
        +'<small>'+(+j.done_files)+' / '+(+j.total_files)+' ملف · '+E(j.done_h||hb(j.done_bytes))+' / '+E(j.total_h||hb(j.total_bytes))+' ('+pct+'%)'
        +(+j.failed_files?' · <span style="color:#ff6b6b">'+(+j.failed_files)+' فشل</span>':'')+(j.current_file&&j.status==='running'?' · '+E(j.current_file):'')+'</small>'
        +(j.error?'<div class="stg-msg" style="margin-top:4px">'+E(j.error)+'</div>':'')
        +'<div class="stg-part-acts">'+(active?'<button class="btn btn-g" onclick="stgJobCancel('+(+j.id)+')"><i class="fas fa-stop"></i> إلغاء</button>':'')
        +(['failed','partial','cancelled'].indexOf(j.status)>=0?'<button class="btn btn-g" onclick="stgJobRetry('+(+j.id)+')"><i class="fas fa-rotate-right"></i> استئناف/إعادة</button>':'')
        +(+j.failed_files||j.status==='partial'?'<button class="btn btn-g" onclick="stgJobItems('+(+j.id)+')"><i class="fas fa-list"></i> التفاصيل</button>':'')+'</div>'
        +'<div id="stgJobItems'+(+j.id)+'"></div></div>';
    }).join('');
  }
  window.stgJobsLoad=function(){ return call('stg_jobs').then(function(d){ if(d.success){ stgRenderJobs(d.jobs); ST.lastJobs=d.jobs; stgSchedulePoll(); } }); };
  window.stgJobCancel=function(id){ if(!confirm('إلغاء المهمة #'+id+'؟ الملفات المنقولة تبقى في مكانها الجديد، والباقي لا يُمسّ.')) return; call('stg_job_cancel',{job_id:id}).then(function(d){ if(!d.success) note(d.error,'e'); stgJobsLoad(); }); };
  window.stgJobRetry=function(id){ call('stg_job_retry',{job_id:id}).then(function(){ stgJobsLoad(); }); };
  window.stgJobItems=function(id){
    call('stg_job_items',{job_id:id}).then(function(d){
      var el=$$('stgJobItems'+id); if(!el) return;
      el.innerHTML='<div style="max-height:220px;overflow:auto;margin-top:8px">'+(d.items||[]).filter(function(i){return i.status!=='done';}).map(function(i){
        return '<div class="stg-list-row"><span class="stg-tag '+(i.status==='failed'?'sys':'')+'">'+E(i.status)+'</span><div class="grow"><div class="path">'+E(i.src_rel)+'</div>'+(i.error?'<small style="color:#ff6b6b">'+E(i.error)+'</small>':'')+'</div></div>';
      }).join('')+'</div>';
    });
  };

  /* تحديث حيّ: كل ثانيتين أثناء وجود مهمة نشطة، وإلا كل 30 ثانية — وفقط والقسم ظاهر */
  function stgSchedulePoll(){
    clearTimeout(ST.pollT);
    var sec=$$('storage-mgmt'); if(!sec || !sec.classList.contains('on')) return;
    var jobs=(ST.lastJobs||(ST.data&&ST.data.jobs)||[]);
    var active=jobs.some(function(j){return ['queued','running','cancelling'].indexOf(j.status)>=0;});
    ST.pollT=setTimeout(function(){
      if(!$$('storage-mgmt').classList.contains('on')) return;
      if(active){ stgJobsLoad().then(function(){ var still=(ST.lastJobs||[]).some(function(j){return ['queued','running','cancelling'].indexOf(j.status)>=0;}); if(!still) stgLoad(false); }); }
      else stgLoad(false);
    }, active?2000:30000);
  }

  /* ── الإعدادات ── */
  window.stgOpenSettings=function(){
    var s=ST.data.settings;
    $$('stgSetGb').value=s.min_free_gb; $$('stgSetPct').value=s.min_free_pct; $$('stgSetWarn').value=s.warn_pct; $$('stgSetSmart').value=s.smart_minutes; $$('stgSetLimit').value=s.move_limit_mb||0;
    $$('stgPickOut').innerHTML=''; al('stgSetAlert','','');
    OM('stgSetM');
  };
  window.stgSettingsSave=function(){
    call('stg_settings_save',{min_free_gb:$$('stgSetGb').value, min_free_pct:$$('stgSetPct').value,
      warn_pct:$$('stgSetWarn').value, smart_minutes:$$('stgSetSmart').value, move_limit_mb:$$('stgSetLimit').value}).then(function(d){
      if(!d.success) return al('stgSetAlert',E(d.error),'e');
      CM('stgSetM'); note('حُفظت الإعدادات','s'); stgLoad(false);
    });
  };
  window.stgPickPreview=function(){
    call('stg_pick_preview',{kind:$$('stgPickKind').value, size_gb:$$('stgPickGb').value}).then(function(d){
      $$('stgPickOut').innerHTML = d.ok_pick
        ? '<span style="color:#00c080"><i class="fas fa-check"></i> '+E(d.storage.name)+'</span> — '+E(d.reason)+(d.order&&d.order.length>1?'<br><small style="color:var(--t3)">الترتيب: '+d.order.map(function(o){return E(o.name)+' ('+o.pct+'%)';}).join(' ← ')+'</small>':'')
        : '<span style="color:#ff6b6b"><i class="fas fa-xmark"></i> '+E(d.reason)+'</span>';
    });
  };

  /* يُستدعى من S('storage-mgmt') عبر sectionLoaders */
  window.stgInit=function(){ stgLoad(false); stgLoadDisks(); stgJobsLoad(); };
})();
</script>
