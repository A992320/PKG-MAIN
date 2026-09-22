<section id="categories" class="sec">
  <div class="shdr"><h1 class="stitle"><?= $t["manage_categories"] ?? "إدارة <span>الأقسام</span>" ?></h1><button class="btn btn-p" onclick="OM('addCatM')"><i class="fas fa-plus"></i><?= $t["new_category"] ?? "قسم جديد" ?></button></div>

  <?php
    // ══ مجموعات الصفحة الرئيسية (Home Groups) — طبقة roo ══
    $hgState  = function_exists('homeGroupsRead') ? homeGroupsRead($pdo, false) : ['groups' => []];
    $hgGroups = $hgState['groups'] ?? [];
    /* بيانات Xtream للأقسام — لبناء شجرة «حساب ← نوع ← أقسام» في المنتقي.
       الأعمدة وجدول الحسابات كلها من مسار Xtream وحده، فقد لا توجد على
       نسخة لم تستورد شيئاً: نحاول بالنوع، ثم بدونه، ثم نمضي بلا شيء
       فتظهر الأقسام كما كانت تماماً. */
    $hgXt = [];
    foreach ([
        "SELECT c.id, c.xtream_account_id, c.xtream_kind, a.name AS acc_name
           FROM categories c LEFT JOIN xtream_accounts a ON a.id = c.xtream_account_id
          WHERE c.xtream_account_id IS NOT NULL",
        "SELECT c.id, c.xtream_account_id, NULL AS xtream_kind, NULL AS acc_name
           FROM categories c WHERE c.xtream_account_id IS NOT NULL",
    ] as $__sql) {
        try {
            foreach ($pdo->query($__sql)->fetchAll(PDO::FETCH_ASSOC) as $__r) {
                $hgXt[(int)$__r['id']] = $__r;
            }
            break;
        } catch (Throwable $e) { $hgXt = []; }
    }

    /* أقسام قوائم M3U — تُجمَّع في المنتقي تحت اسم القائمة التي أنشأتها. */
    $hgPl = [];
    foreach ([
        "SELECT c.id, c.playlist_id, p.name AS pl_name
           FROM categories c LEFT JOIN m3u_playlists p ON p.id = c.playlist_id
          WHERE c.playlist_id IS NOT NULL",
        "SELECT c.id, c.playlist_id, NULL AS pl_name
           FROM categories c WHERE c.playlist_id IS NOT NULL",
    ] as $__sql) {
        try {
            foreach ($pdo->query($__sql)->fetchAll(PDO::FETCH_ASSOC) as $__r) {
                $hgPl[(int)$__r['id']] = $__r;
            }
            break;
        } catch (Throwable $e) { $hgPl = []; }
    }

    $hgCatsJs = array_map(function($c) use ($hgXt, $hgPl){
        $id = (int)$c['id'];
        $xt = $hgXt[$id] ?? null;
        $pl = $hgPl[$id] ?? null;
        return [
            'id'        => $id,
            'name'      => $c['name'],
            'parent_id' => (isset($c['parent_id']) && $c['parent_id'] !== null) ? (int)$c['parent_id'] : null,
            'xt_acc'    => $xt ? (int)($xt['xtream_account_id'] ?? 0) : 0,
            'xt_kind'   => $xt ? (string)($xt['xtream_kind'] ?? '') : '',
            'xt_name'   => $xt ? (string)($xt['acc_name'] ?? '') : '',
            'pl_id'     => $pl ? (int)($pl['playlist_id'] ?? 0) : 0,
            'pl_name'   => $pl ? (string)($pl['pl_name'] ?? '') : '',
        ];
    }, $categories ?? []);
    $hgGroupsJs = array_map(function($g){
        return [
            'id'            => (int)$g['id'],
            'name'          => $g['name'],
            'description'   => $g['description'] ?? '',
            'color'         => $g['accent_color'] ?? '#38bdf8',
            'content_type'  => $g['content_type'] ?? 'auto',
            'category_ids'  => array_values(array_map('intval', $g['category_ids'] ?? [])),
            'show_on_home'  => (int)($g['is_active'] ?? 1),
            'is_active'     => (int)($g['is_active'] ?? 1),
            'display_order' => (int)($g['display_order'] ?? 0),
        ];
    }, $hgGroups);
  ?>
  <style>
  .hg-wrap{background:var(--s2,#141414);border:1px solid var(--br,rgba(255,255,255,.08));border-radius:var(--r2,14px);padding:18px;margin-bottom:22px}
  .hg-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:14px}
  .hg-title{font-size:1.02rem;font-weight:800;color:var(--t1,#fff);margin:0 0 4px;display:flex;align-items:center;gap:8px}
  .hg-title i{color:#38bdf8}
  .hg-sub{font-size:.8rem;color:var(--t3,#8a8a8a);margin:0;line-height:1.6;max-width:640px}
  .hg-actions{display:flex;gap:8px;flex-wrap:wrap}
  .hg-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px}
  .hg-card{position:relative;background:var(--s3,#1c1c1c);border:1px solid var(--br,rgba(255,255,255,.08));border-radius:var(--r2,12px);padding:16px 14px 12px;transition:border-color .18s,transform .18s}
  .hg-card:hover{border-color:rgba(56,189,248,.5);transform:translateY(-2px)}
  .hg-card-ico{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;color:#fff;margin-bottom:10px}
  .hg-card-name{font-size:.95rem;font-weight:800;color:var(--t1,#fff);margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .hg-card-meta{font-size:.74rem;color:var(--t3,#8a8a8a);margin-bottom:2px}
  .hg-card-sub{font-size:.72rem;color:var(--t3,#8a8a8a);opacity:.75}
  .hg-badge{position:absolute;top:10px;left:40px;font-size:.66rem;font-weight:800;padding:3px 9px;border-radius:99px}
  .hg-badge.on{background:rgba(0,208,132,.15);color:#00d084}
  .hg-badge.off{background:rgba(255,77,87,.15);color:#ff4d57}
  .hg-card-acts{display:flex;gap:6px;margin-top:12px;padding-top:10px;border-top:1px solid var(--br,rgba(255,255,255,.07))}
  .hg-ib{flex:1;background:var(--s2,#141414);border:1px solid var(--br,rgba(255,255,255,.08));color:var(--t3,#9a9a9a);border-radius:8px;padding:7px 0;cursor:pointer;transition:.15s;font-size:.82rem}
  .hg-ib:hover{color:#fff;border-color:rgba(255,255,255,.25)}
  .hg-ib.del:hover{color:#ff4d57;border-color:rgba(255,77,87,.5)}
  .hg-empty{grid-column:1/-1;text-align:center;padding:34px 10px;color:var(--t3,#8a8a8a);font-size:.85rem}
  .hg-picker{border:1px solid var(--br,rgba(255,255,255,.1));border-radius:10px;overflow:hidden;background:var(--s1,#0f0f0f)}
  .hg-picker-search{display:flex;align-items:center;gap:8px;padding:9px 12px;border-bottom:1px solid var(--br,rgba(255,255,255,.08));color:var(--t3,#8a8a8a)}
  .hg-picker-search input{flex:1;background:none;border:none;outline:none;color:var(--t1,#fff);font-family:inherit;font-size:.85rem}
  .hg-tree{max-height:230px;overflow-y:auto;padding:6px}
  .hg-tree-item{display:flex;align-items:center;gap:9px;padding:7px 8px;border-radius:8px;cursor:pointer;transition:background .14s}
  .hg-tree-item:hover{background:var(--s3,rgba(255,255,255,.05))}
  .hg-tree-item input{width:16px;height:16px;accent-color:#38bdf8;cursor:pointer;flex-shrink:0}
  .hg-tree-item .hg-tw-ico{color:#38bdf8;font-size:.8rem;flex-shrink:0}
  .hg-tree-item .hg-tw-name{color:var(--t1,#eee);font-size:.85rem;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .hg-tree-empty{text-align:center;color:var(--t3,#8a8a8a);font-size:.8rem;padding:18px}
  .hg-tree-acc{display:flex;align-items:center;gap:8px;margin:8px 0 2px;padding:7px 8px;border-radius:8px;background:var(--s3,rgba(255,255,255,.04));color:var(--t1,#eee);font-size:.82rem;font-weight:800}
  .hg-tree-acc i{color:#f59e0b}
  .hg-tree-acc.is-m3u i{color:#22c55e}
  .hg-tree-acc .hg-tree-acc-n{font-size:.7rem;font-weight:700;color:var(--t3,#8a8a8a);margin-inline-start:auto}
  .hg-tree-sec{display:flex;align-items:center;gap:8px;padding:6px 8px 6px 20px;color:var(--t2,#c9c9c9);font-size:.78rem;font-weight:750}
  .hg-tree-sec i{color:#38bdf8;font-size:.78rem}
  .hg-tree-sec .hg-mini{margin-inline-start:auto;padding:3px 8px;font-size:.68rem}
  .hg-checkline{display:flex;align-items:center;gap:9px;color:var(--t1,#eee);font-size:.86rem;cursor:pointer;margin-top:6px}
  .hg-checkline input{width:17px;height:17px;accent-color:var(--red,#e50914);cursor:pointer}
  .hg-mode{margin-top:10px;font-size:.78rem;font-weight:700;display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:99px}
  .hg-mode.auto{background:rgba(0,208,132,.12);color:#00d084}
  .hg-mode.manual{background:rgba(56,189,248,.12);color:#38bdf8}
  .hg-picker-tools{display:flex;align-items:center;gap:7px;padding:8px 10px;border-bottom:1px solid var(--br,rgba(255,255,255,.08));flex-wrap:wrap}
  .hg-mini{background:var(--s3,#1c1c1c);border:1px solid var(--br,rgba(255,255,255,.1));color:var(--t2,#c9c9c9);border-radius:8px;padding:5px 10px;font-size:.75rem;font-weight:700;cursor:pointer;font-family:inherit;transition:.15s}
  .hg-mini:hover{color:#fff;border-color:rgba(56,189,248,.5)}
  .hg-sel-count{margin-inline-start:auto;font-size:.72rem;font-weight:800;color:#38bdf8;background:rgba(56,189,248,.12);padding:3px 10px;border-radius:99px}
  .hg-cards-tools{display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap;padding:8px 12px;background:var(--s3,#1c1c1c);border:1px solid var(--br,rgba(255,255,255,.08));border-radius:10px}
  .hg-selall{display:inline-flex;align-items:center;gap:7px;font-size:.8rem;font-weight:700;color:var(--t1,#eee);cursor:pointer}
  .hg-selall input{width:16px;height:16px;accent-color:#38bdf8;cursor:pointer}
  .hg-mini--danger{border-color:rgba(255,77,87,.4);color:#ff4d57}
  .hg-mini--danger:hover{border-color:#ff4d57;color:#fff;background:rgba(255,77,87,.15)}
  .hg-mini[disabled]{opacity:.45;cursor:not-allowed}
  .hg-card-sel{position:absolute;top:10px;left:10px;z-index:3;display:inline-flex}
  .hg-card-sel input{width:17px;height:17px;accent-color:#38bdf8;cursor:pointer}
  .hg-card.is-sel{border-color:#38bdf8!important;box-shadow:0 0 0 1px rgba(56,189,248,.55) inset}
  </style>

  <div class="hg-wrap">
    <div class="hg-head">
      <div>
        <h2 class="hg-title"><i class="fas fa-layer-group"></i> <?= $t["hg_title"] ?? "مجموعات الصفحة الرئيسية" ?></h2>
        <p class="hg-sub"><?= $t["hg_sub"] ?? "أنشئ بطاقات Home بحرية، واختر الأقسام التابعة ونوع المحتوى والغلاف. اختيار قسم أب يضمّ فروعه تلقائياً." ?></p>
        <div id="hgModeBadge" class="hg-mode"></div>
      </div>
      <div class="hg-actions">
        <button class="btn btn-g" onclick="hgAutoseed()"><i class="fas fa-rotate"></i> <?= $t["hg_autoseed"] ?? "استعادة التلقائي" ?></button>
        <button class="btn btn-p" onclick="hgOpenNew()"><i class="fas fa-plus"></i> <?= $t["hg_new"] ?? "مجموعة جديدة" ?></button>
      </div>
    </div>
    <div id="hgAlert"></div>
    <div class="hg-cards-tools" id="hgCardsTools" style="display:none">
      <label class="hg-selall"><input type="checkbox" id="hgSelAllCards" onchange="hgSelectAllCards(this.checked)"> <?= $t["hg_select_all"] ?? "تحديد الكل" ?></label>
      <span class="hg-sel-count" id="hgBulkCount">0 محدد</span>
      <button type="button" class="hg-mini hg-mini--danger" id="hgBulkDelBtn" onclick="hgBulkDelete()" disabled><i class="fas fa-trash"></i> <?= $t["hg_delete_selected"] ?? "حذف المحدد" ?></button>
    </div>
    <div id="hgCards" class="hg-cards"></div>
  </div>

  <div class="mbd" id="hgModal"><div class="mbox"><div class="mhd"><div class="mhd-title"><i class="fas fa-layer-group"></i> <span id="hgModalTitle"><?= $t["hg_new"] ?? "مجموعة جديدة" ?></span></div><button class="mclose" onclick="CM('hgModal')"><i class="fas fa-times"></i></button></div>
  <div class="mbody">
    <input type="hidden" id="hgId" value="0">
    <div class="fg"><label class="fl"><?= $t["hg_name"] ?? "اسم المجموعة" ?></label><input type="text" id="hgName" class="fi" placeholder="<?= htmlspecialchars($t["hg_name_ph"] ?? "مثال: أفلام عربية") ?>"></div>
    <div class="fg"><label class="fl"><?= $t["hg_type"] ?? "نوع المحتوى وغلاف TMDB" ?></label>
      <select id="hgType" class="fs">
        <option value="auto"><?= $t["hg_type_auto"] ?? "تلقائي / متنوع" ?></option>
        <option value="live"><?= $t["hg_type_live"] ?? "بث مباشر" ?></option>
        <option value="movie"><?= $t["hg_type_movies"] ?? "أفلام" ?></option>
        <option value="series"><?= $t["hg_type_series"] ?? "مسلسلات" ?></option>
        <option value="kids"><?= $t["hg_type_kids"] ?? "أطفال" ?></option>
        <option value="sports"><?= $t["hg_type_sports"] ?? "رياضة" ?></option>
        <option value="news"><?= $t["hg_type_news"] ?? "أخبار" ?></option>
        <option value="documentary"><?= $t["hg_type_doc"] ?? "وثائقي" ?></option>
        <option value="anime"><?= $t["hg_type_anime"] ?? "أنمي" ?></option>
        <option value="action"><?= $t["hg_type_action"] ?? "أكشن" ?></option>
        <option value="comedy"><?= $t["hg_type_comedy"] ?? "كوميديا" ?></option>
        <option value="drama"><?= $t["hg_type_drama"] ?? "دراما" ?></option>
        <option value="music"><?= $t["hg_type_music"] ?? "موسيقى وحفلات" ?></option>
      </select>
    </div>
    <div class="fg"><label class="fl"><?= $t["hg_desc"] ?? "وصف مختصر" ?></label><input type="text" id="hgDesc" class="fi" placeholder="<?= htmlspecialchars($t["hg_desc_ph"] ?? "يظهر أسفل اسم البطاقة") ?>"></div>
    <div class="fg"><label class="fl"><?= $t["hg_color"] ?? "لون المجموعة" ?></label><input type="color" id="hgColor" class="fi" value="#38bdf8" style="height:42px;padding:4px"></div>
    <div class="fg"><label class="fl"><?= $t["hg_cats"] ?? "الأقسام والمجلدات التابعة" ?></label>
      <div class="hg-picker">
        <div class="hg-picker-search"><i class="fas fa-search"></i><input type="text" id="hgTreeSearch" placeholder="<?= htmlspecialchars($t["hg_search_ph"] ?? "بحث عن قسم…") ?>" oninput="hgFilterTree(this.value)"></div>
        <div class="hg-picker-tools">
          <button type="button" class="hg-mini" onclick="hgSelectAllTree(true)"><i class="fas fa-check-double"></i> <?= $t["hg_select_all"] ?? "تحديد الكل" ?></button>
          <button type="button" class="hg-mini" onclick="hgSelectAllTree(false)"><i class="fas fa-eraser"></i> <?= $t["hg_clear_sel"] ?? "إلغاء التحديد" ?></button>
          <span class="hg-sel-count" id="hgSelCount">0</span>
        </div>
        <div id="hgTreeList" class="hg-tree"></div>
      </div>
      <small style="color:var(--t3,#8a8a8a)"><?= $t["hg_cats_hint"] ?? "يمكن اختيار أي مستوى. إذا اخترت المجلد الأب، تُعرض جميع فروعه تلقائياً." ?><br><?= $t["hg_cats_hint_xt"] ?? "الأقسام المستوردة مجمّعة هنا: Xtream بحسب الحساب ثم النوع (قنوات / أفلام / مسلسلات)، وM3U بحسب القائمة. ولا تظهر في الصفحة الرئيسية إلا بعد ضمّها إلى مجموعة من هنا." ?></small>
    </div>
    <label class="hg-checkline"><input type="checkbox" id="hgShowHome" checked> <?= $t["hg_show_home"] ?? "إظهار المجموعة في الصفحة الرئيسية" ?></label>
  </div>
  <div class="mfooter"><button type="button" class="btn btn-g" onclick="CM('hgModal')"><?= $t["rv_cancel"] ?? "إلغاء" ?></button><button type="button" class="btn btn-p" onclick="hgSave()"><i class="fas fa-save"></i> <?= $t["hg_save"] ?? "حفظ المجموعة" ?></button></div>
  </div></div>

  <script>
    window.__hgCats = <?php echo json_encode($hgCatsJs, JSON_UNESCAPED_UNICODE); ?>;
    window.__hgGroups = <?php echo json_encode($hgGroupsJs, JSON_UNESCAPED_UNICODE); ?>;
    window.__hgConfigured = <?php echo !empty($hgState['configured']) ? 'true' : 'false'; ?>;
  </script>

  <div class="tw">
    <div class="tt"><div class="tsrch"><i class="fas fa-search"></i><input type="text" placeholder="<?= $t["search"] ?? "بحث..." ?>" oninput="FT(this,'catTbl')"></div><span style="font-size:.78rem;color:var(--t3)"><?php echo count($categories); ?> <?= $t["category"] ?? "قسم" ?> <span style="opacity:.6">· <?= $t["cat_tree_hint"] ?? "اسحب أي قسم لإعادة ترتيبه بين إخوته" ?></span></span></div>
    <?php if($categories):
        // ══ ترتيب شجري بصري فقط للعرض هنا (لا يغيّر $categories الأصلية المستخدمة
        //    في القوائم المنسدلة أعلى هذا الملف): القسم الأب يظهر مباشرة قبل كل
        //    أبنائه، بعمق (_depth) لغرض الإزاحة البصرية فقط. هذا يعتمد على عمود
        //    parent_id الموجود أصلاً في قاعدة البيانات ومستخدم فعلاً في نموذجي
        //    الإضافة/التعديل — لا نظام جديد، فقط نجعله مرئياً بوضوح هنا.
        $__catChildren = [];
        foreach ($categories as $c) {
            $pid = $c['parent_id'] ?? null;
            $key = ($pid && isset($categories[array_search($pid, array_column($categories, 'id'))])) ? $pid : 0;
            $__catChildren[$key][] = $c;
        }
        $__catTreeOrder = [];
        // حماية من parent_id دائري/ذاتي في البيانات (مثلاً قسم أبٌ لنفسه):
        // بلا هذه الحماية يدخل المشي العودي في حلقة لا نهائية فتنهار صفحة
        // الإدارة (شاشة بيضاء). نتتبّع ما زُرناه ونضع سقف عمق احتياطياً.
        $__catSeen = [];
        $__walkCatTree = function($parentKey, $depth) use (&$__walkCatTree, &$__catChildren, &$__catTreeOrder, &$__catSeen) {
            if ($depth > 100) return;                 // سقف عمق احتياطي
            if (empty($__catChildren[$parentKey])) return;
            foreach ($__catChildren[$parentKey] as $c) {
                if (isset($__catSeen[$c['id']])) continue; // زُرناه سابقاً ⇒ حلقة، نتخطّاه
                $__catSeen[$c['id']] = true;
                $c['_depth'] = $depth;
                $__catTreeOrder[] = $c;
                $__walkCatTree($c['id'], $depth + 1);
            }
        };
        $__walkCatTree(0, 0);
        // أي قسم لم تطله الشجرة (بسبب حلقة أو أبٍ مفقود) نُلحقه كجذر حتى لا يختفي
        foreach ($categories as $c) {
            if (!isset($__catSeen[$c['id']])) {
                $c['_depth'] = 0;
                $__catTreeOrder[] = $c;
            }
        }
    ?>
    <div id="catBulkBar" style="display:none;align-items:center;gap:12px;padding:10px 14px;margin-bottom:10px;background:rgba(229,9,20,.08);border:1px solid rgba(229,9,20,.25);border-radius:10px">
      <span style="font-size:.82rem;color:var(--t1);font-weight:700"><i class="fas fa-check-square" style="color:var(--red)"></i> <span id="catSelCount">0</span> <?= $t["selected_categories"] ?? "قسم محدد" ?></span>
      <button class="btn btn-g" style="margin-right:auto;padding:6px 14px" onclick="catClearSel()"><i class="fas fa-times"></i> <?= $t["deselect_all"] ?? "إلغاء التحديد" ?></button>
      <button class="btn btn-g" style="padding:6px 14px" onclick="catBulkVisibility(0)" title="<?= htmlspecialchars($t["hide_from_index_hint"] ?? "يخفي شكل القسم من الصفحة الرئيسية فقط — لا يُحذف أي فيلم أو مسلسل أو قناة") ?>"><i class="fas fa-eye-slash"></i> <?= $t["hide_from_index"] ?? "إخفاء من الرئيسية" ?></button>
      <button class="btn btn-g" style="padding:6px 14px" onclick="catBulkVisibility(1)"><i class="fas fa-eye"></i> <?= $t["show_in_index"] ?? "إظهار في الرئيسية" ?></button>
      <button class="btn btn-p" style="padding:6px 14px;background:var(--red)" onclick="catBulkDelete()"><i class="fas fa-trash"></i> <?= $t["delete_selected"] ?? "حذف المحدد" ?></button>
    </div>
    <table id="catTbl"><thead><tr><th style="width:38px"><input type="checkbox" id="catSelAll" onchange="catToggleAll(this)" style="width:16px;height:16px;cursor:pointer;accent-color:var(--red)"></th><th style="width:30px"></th><th><?= $t["id"] ?? "ID" ?></th><th><?= $t["category"] ?? "القسم" ?></th><th><?= $t["parent_cat"] ?? "القسم الأب" ?></th><th><?= $t["icon"] ?? "الأيقونة" ?></th><th><?= $t["channels"] ?? "القنوات" ?></th><th><?= $t["visibility"] ?? "الظهور بالواجهة" ?></th><th><?= $t["actions"] ?? "إجراءات" ?></th></tr></thead><tbody id="catTblBody">
    <?php foreach($__catTreeOrder as $cat):
        $pid=$cat['parent_id']??null;
        $parentKey = ($pid && isset($categories[array_search($pid, array_column($categories, 'id'))])) ? $pid : 0;
        $depth = (int)($cat['_depth'] ?? 0);
        $indentPx = $depth * 22;
    ?>
    <tr draggable="true" data-cat-id="<?php echo $cat['id']; ?>" data-parent-key="<?php echo $parentKey; ?>" style="cursor:grab">
      <td><input type="checkbox" class="catSelChk" value="<?php echo $cat['id']; ?>" onchange="catSelCtrl()" style="width:16px;height:16px;cursor:pointer;accent-color:var(--red)"></td>
      <td><i class="fas fa-grip-lines" style="color:var(--t3);font-size:.95rem" title="<?= htmlspecialchars($t["cat_drag_hint"] ?? "اسحبني لإعادة الترتيب") ?>"></i></td>
      <td style="color:var(--t3);font-size:.75rem">#<?php echo $cat['id']; ?></td>
      <td><div class="cn" style="padding-right:<?php echo $indentPx; ?>px">
        <?php if($depth>0): ?><span style="color:var(--t3);margin-left:4px;font-size:.8rem">└─</span><?php endif; ?>
        <div class="nic"><i class="<?php echo htmlspecialchars($cat['icon']); ?>"></i></div><strong style="color:var(--t1)"><?php echo htmlspecialchars($cat['name']); ?></strong>
      </div></td>
      <td><?php if($pid){foreach($categories as $pc){if($pc['id']==$pid){echo '<span class="bdg bc">'.htmlspecialchars($pc['name']).'</span>';break;}}}else echo '<span style="color:var(--t3);font-size:.75rem">—</span>'; ?></td>
      <td><code style="font-size:.72rem;color:var(--t3);background:var(--s3);padding:2px 7px;border-radius:4px"><?php echo htmlspecialchars($cat['icon']); ?></code></td>
      <td><span class="bdg bc"><?php echo $cat['channel_count']; ?></span></td>
      <td><label class="fc-switch" style="display:inline-flex"><input type="checkbox" data-cat-id="<?php echo $cat['id']; ?>" class="catActiveToggle" <?php echo ((int)$cat['is_active']===1)?'checked':''; ?> onchange="toggleCategoryActive(this)"><span class="fc-slider"></span></label></td>
      <td><div class="acts"><button class="ib ed" onclick='editCat(<?php echo json_encode(['id'=>$cat['id'],'name'=>$cat['name'],'icon'=>$cat['icon'],'parent_id'=>$cat['parent_id']??null,'description'=>$cat['description']??'']); ?>)'><i class="fas fa-pen"></i></button><button class="ib dl" onclick="postDelete('delete_category', '<?php echo $cat['id']; ?>')"><i class="fas fa-trash"></i></button></div></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    <script>
    /* بيانات الشجرة كما رُتّبت في PHP أعلاه — تُستخدم لإعادة رسم الصفوف محلياً
       بعد السحب والإفلات دون أي طلب صفحة جديدة (نفس مبدأ ترتيب الحلقات). */
    window.__catTreeData = <?php echo json_encode(array_map(function($c){
        return [
            'id' => (int)$c['id'], 'name' => $c['name'], 'icon' => $c['icon'],
            'parent_id' => $c['parent_id'] ?? null, 'is_active' => (int)$c['is_active'],
            'channel_count' => (int)$c['channel_count'], 'description' => $c['description'] ?? '',
            '_depth' => (int)($c['_depth'] ?? 0),
        ];
    }, $__catTreeOrder)); ?>;
    document.addEventListener('DOMContentLoaded', function(){ if(typeof _catTreeInit==='function') _catTreeInit(); });
    </script>
    <?php else: ?><div class="empty"><i class="fas fa-th-large"></i><p><?= $t["no_categories"] ?? "لا توجد أقسام" ?></p></div><?php endif; ?>
  </div>
</section>

<!-- CHANNELS -->
