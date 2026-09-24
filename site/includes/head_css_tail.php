/* Public language selector */
.site-language-menu{position:relative;flex:0 0 auto;z-index:20}.site-language-menu summary{list-style:none;display:flex;align-items:center;justify-content:center;gap:5px;min-width:48px;height:40px;padding:0 9px;border:1.5px solid rgba(255,255,255,.1);border-radius:20px;background:rgba(255,255,255,.08);color:#ccc;cursor:pointer;transition:background .18s ease,border-color .18s ease,color .18s ease}.site-language-menu summary::-webkit-details-marker{display:none}.site-language-menu summary:hover,.site-language-menu[open] summary{background:rgba(255,255,255,.13);border-color:rgba(255,255,255,.22);color:#fff}.site-language-menu .lcn{display:inline-flex;font-size:.9rem}.site-language-code{font-size:.68rem;font-weight:800;letter-spacing:.04em}.site-language-options{position:absolute;top:calc(100% + 8px);left:0;min-width:132px;padding:6px;border:1px solid rgba(255,255,255,.1);border-radius:9px;background:#171717;box-shadow:0 12px 28px rgba(0,0,0,.38)}.site-language-options a{display:block;padding:8px 10px;border-radius:6px;color:var(--text-muted);font-size:.78rem;font-weight:700;white-space:nowrap;transition:background .16s ease,color .16s ease}.site-language-options a:hover,.site-language-options a.active{background:rgba(255,255,255,.08);color:#fff}.site-language-options a.active::after{content:'✓';float:right;color:var(--red);font-weight:900}@media(max-width:640px){.site-language-menu summary{width:36px;min-width:36px;height:36px;padding:0;border-radius:50%}.site-language-code{display:none}.site-language-options{left:auto;right:0;min-width:122px}}/* Direction-aware language menu */
[dir="rtl"] .site-language-options{right:0;left:auto;text-align:right}[dir="ltr"] .site-language-options{left:0;right:auto;text-align:left}.site-language-options a.active{background:rgba(229,9,20,.14);color:#fff}.site-language-options a.active::after{float:inline-end}@media(max-width:640px){[dir="rtl"] .site-language-options{right:0;left:auto}[dir="ltr"] .site-language-options{left:0;right:auto}}
</style>
<?php if (!empty($custom_css_db)): ?>
<style id="siteCustomTheme"><?php echo strip_tags($custom_css_db); ?>
</style>
<?php endif; ?>
<?php if (($settings['active_theme'] ?? 'default') === 'default'): ?>
<style id="siteVisualPolish">
/* Public visual polish: a restrained, consistent dark surface for every accent color. */
:root{--bg:#0c0e11;--bg2:#12151a;--bg3:#181b21;--surface:#15181d;--border:rgba(255,255,255,.09);--text:#f4f5f7;--text-dim:#c3c7ce;--text-muted:#8b919c;--radius:10px;--radius-lg:14px;--radius-xl:18px;--shadow:0 18px 46px rgba(0,0,0,.42)}
html,body{background:#0c0e11!important;color:var(--text)}
.navbar,.navbar.scrolled,body.shsx-has-hero .navbar:not(.scrolled){background:rgba(12,14,18,.94)!important;border-bottom-color:rgba(255,255,255,.07)!important;box-shadow:0 8px 24px rgba(0,0,0,.22)!important;-webkit-backdrop-filter:blur(18px) saturate(105%)!important;backdrop-filter:blur(18px) saturate(105%)!important}
.search-wrap input{background:#171b23!important;border-color:rgba(255,255,255,.11)!important;box-shadow:none!important}.search-wrap input:focus{background:#1b2029!important;border-color:color-mix(in srgb,var(--accent) 58%,#fff 0%)!important;box-shadow:0 0 0 3px rgba(255,255,255,.05)!important}
.cat-navbar{background:rgba(12,14,18,.9)!important;-webkit-backdrop-filter:blur(14px) saturate(105%)!important;backdrop-filter:blur(14px) saturate(105%)!important}.cat-nav-btn.active{background:var(--red)!important;box-shadow:none!important}
.shsx-bg{filter:blur(14px) saturate(82%) brightness(.38)!important}.shsx-scrim{background:linear-gradient(to left,rgba(8,10,13,.35) 0%,rgba(8,10,13,.72) 48%,rgba(8,10,13,.96) 88%),linear-gradient(to top,#0c0e11 0%,rgba(12,14,17,.62) 36%,rgba(0,0,0,.48) 100%)!important}.shsx-btn-play{box-shadow:none!important}.shsx-btn-info{background:rgba(20,23,28,.82)!important;backdrop-filter:none!important}
.ch-card,.sr-card,.ep-card{background:#15171b!important;box-shadow:0 5px 16px rgba(0,0,0,.24)!important}.ch-card:hover,.sr-card:hover,.ep-card:hover{box-shadow:0 10px 24px rgba(0,0,0,.34)!important}
.site-footer{direction:rtl;background:#101216;border-top:1px solid rgba(255,255,255,.08);color:#eef0f3}.site-footer__inner{max-width:960px;margin:0 auto;padding:44px 28px 36px}.site-footer__brand{text-align:center}.site-footer__name{color:var(--red);font-size:clamp(1.35rem,3vw,1.7rem);font-weight:900;letter-spacing:-.04em;line-height:1.2}.site-footer__rights{margin:9px 0 0;color:#9298a2;font-size:.82rem;line-height:1.7}.site-footer__contact{margin-top:32px}.site-footer__contact-title{display:flex;align-items:center;gap:12px;max-width:320px;margin:0 auto 18px;color:#aeb4be;font-size:.75rem;font-weight:700;letter-spacing:.02em}.site-footer__contact-title span{height:1px;flex:1;background:rgba(255,255,255,.11)}.site-footer__contact-title b{font:inherit;white-space:nowrap}.site-footer__socials{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:10px}.site-footer__social{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-width:138px;min-height:42px;padding:0 16px;border:1px solid rgba(255,255,255,.14);border-radius:10px;background:rgba(29,33,40,.66);box-shadow:inset 0 1px 0 rgba(255,255,255,.08),0 8px 20px rgba(0,0,0,.16);-webkit-backdrop-filter:blur(14px) saturate(120%);backdrop-filter:blur(14px) saturate(120%);color:#e7e9ed;font-size:.83rem;font-weight:700;text-decoration:none;transition:background .18s ease,border-color .18s ease,transform .18s ease,box-shadow .18s ease}.site-footer__social-icon{width:18px;height:18px;flex:0 0 18px}.site-footer__social:hover{transform:translateY(-1px);background:rgba(42,47,56,.82);box-shadow:inset 0 1px 0 rgba(255,255,255,.12),0 10px 22px rgba(0,0,0,.24);color:#fff}.site-footer__social:active{transform:translateY(0)}.site-footer__social:focus-visible{outline:2px solid var(--accent);outline-offset:3px}.site-footer__social--whatsapp .site-footer__social-icon{color:#35c96a}.site-footer__social--facebook .site-footer__social-icon{color:#65a3ff}.site-footer__social--email .site-footer__social-icon{color:#ff666e}.site-footer__social--whatsapp:hover{border-color:rgba(53,201,106,.42)}.site-footer__social--facebook:hover{border-color:rgba(101,163,255,.42)}.site-footer__social--email:hover{border-color:rgba(255,102,110,.42)}
@media(max-width:640px){.site-footer__inner{padding:36px 18px 30px}.site-footer__contact{margin-top:26px}.site-footer__socials{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.site-footer__social{min-width:0;padding:0 12px;font-size:.8rem}.site-footer__social--email{grid-column:1/-1;max-width:calc(50% - 5px);justify-self:center;width:100%}.site-footer__rights{font-size:.78rem}.shsx-bg{filter:blur(12px) saturate(78%) brightness(.34)!important}}
/* Shared glass layer: reserved for navigation and utility surfaces, not media cards. */
@supports ((-webkit-backdrop-filter:blur(1px)) or (backdrop-filter:blur(1px))){
  .navbar,.navbar.scrolled,body.shsx-has-hero .navbar:not(.scrolled),.cat-navbar{background:rgba(14,17,22,.74)!important;-webkit-backdrop-filter:blur(18px) saturate(115%)!important;backdrop-filter:blur(18px) saturate(115%)!important}
  .site-language-menu summary,.site-language-options,.shs-catmenu-panel,.fp-panel,.np-panel,.m3u-panel,.ep-panel{background:rgba(21,25,31,.76)!important;-webkit-backdrop-filter:blur(18px) saturate(112%)!important;backdrop-filter:blur(18px) saturate(112%)!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.07),0 16px 36px rgba(0,0,0,.3)!important}
  .cat-nav-btn{background:rgba(255,255,255,.055)!important}
}
/* Android/WebKit may skip painting content-visibility:auto images inside horizontal rows. */
.ch-thumb img,.sr-poster img,.ep-thumb-video{content-visibility:visible!important;visibility:visible!important;opacity:1!important;display:block!important}
@media(max-width:768px){.netflix-slider-row{content-visibility:visible!important;contain:layout style!important}.ch-thumb img,.sr-poster img{content-visibility:visible!important}}
@media(max-width:660px){
  .shsx-bg{inset:0!important;filter:saturate(74%) brightness(.48)!important;transform:none!important;background-position:center 14%!important}
  .shsx-scrim{background:linear-gradient(to top,#0c0e11 0%,rgba(12,14,17,.96) 25%,rgba(12,14,17,.62) 49%,rgba(8,10,13,.22) 73%,rgba(0,0,0,.42) 100%)!important}
  .shsx-art{display:block!important;position:absolute!important;z-index:2!important;top:calc(var(--shsx-top,88px) + 18px)!important;left:50%!important;width:clamp(112px,34vw,156px)!important;transform:translateX(-50%)!important;border-color:rgba(255,255,255,.16)!important;box-shadow:0 16px 36px rgba(0,0,0,.52)!important}
  .shsx-copy{position:relative;z-index:3!important}
}
@media(max-width:660px){
  .shsx-hero{min-height:clamp(640px,165vw,720px)!important}
  .shsx-inner{justify-content:flex-start!important;padding:calc(var(--shsx-top,88px) + 12px) 18px 56px!important}
  .shsx-art{top:calc(var(--shsx-top,88px) + 12px)!important;width:clamp(96px,28vw,116px)!important}
  .shsx-copy{margin-top:clamp(180px,44vw,205px)!important}
}

/* Player controls: keep every action within narrow touch screens. */
@media (min-width:769px) and (max-width:1024px){.p-vol-slider-wrap{width:56px!important;opacity:1!important;margin-right:6px!important}}
@media (max-width:768px){
  .p-bottom{padding:18px 12px max(14px,env(safe-area-inset-bottom))!important}
  .p-tools{gap:8px!important;min-width:0!important}
  .p-tools-l,.p-tools-r{gap:6px!important;min-width:0!important}
  .p-time-txt{display:none!important}
  .p-vol-slider-wrap{display:none!important;width:0!important;opacity:0!important;margin:0!important}
  .p-vol-wrap{flex:0 0 auto!important;overflow:visible!important}
}
</style>
<?php endif; ?>
<style id="sitePublicBehavior">
/* Keep responsive fixes active for every public theme. */
.site-footer{direction:rtl;background:#101216;border-top:1px solid rgba(255,255,255,.08);color:#eef0f3}.site-footer__inner{max-width:960px;margin:0 auto;padding:44px 28px 36px}.site-footer__brand{text-align:center}.site-footer__name{color:var(--red,#e50914);font-size:clamp(1.35rem,3vw,1.7rem);font-weight:900;letter-spacing:-.04em;line-height:1.2}.site-footer__rights{margin:9px 0 0;color:#9298a2;font-size:.82rem;line-height:1.7}.site-footer__contact{margin-top:32px}.site-footer__contact-title{display:flex;align-items:center;gap:12px;max-width:320px;margin:0 auto 18px;color:#aeb4be;font-size:.75rem;font-weight:700;letter-spacing:.02em}.site-footer__contact-title span{height:1px;flex:1;background:rgba(255,255,255,.11)}.site-footer__contact-title b{font:inherit;white-space:nowrap}.site-footer__socials{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:10px}.site-footer__social{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-width:138px;min-height:42px;padding:0 16px;border:1px solid rgba(255,255,255,.14);border-radius:10px;background:rgba(29,33,40,.66);box-shadow:inset 0 1px 0 rgba(255,255,255,.08),0 8px 20px rgba(0,0,0,.16);-webkit-backdrop-filter:blur(14px) saturate(120%);backdrop-filter:blur(14px) saturate(120%);color:#e7e9ed;font-size:.83rem;font-weight:700;text-decoration:none}.site-footer__social-icon{width:18px;height:18px;flex:0 0 18px}.site-footer__social--whatsapp .site-footer__social-icon{color:#35c96a}.site-footer__social--facebook .site-footer__social-icon{color:#65a3ff}.site-footer__social--email .site-footer__social-icon{color:#ff666e}.ch-thumb img,.sr-poster img,.ep-thumb-video{content-visibility:visible!important;visibility:visible!important;opacity:1!important;display:block!important}
@media(max-width:768px){.netflix-slider-row{content-visibility:visible!important;contain:layout style!important}.ch-thumb img,.sr-poster img{content-visibility:visible!important}.site-footer__inner{padding:36px 18px 30px}.site-footer__contact{margin-top:26px}.site-footer__socials{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.site-footer__social{min-width:0;padding:0 12px;font-size:.8rem}.site-footer__social--email{grid-column:1/-1;max-width:calc(50% - 5px);justify-self:center;width:100%}.site-footer__rights{font-size:.78rem}.p-bottom{padding:18px 12px max(14px,env(safe-area-inset-bottom))!important}.p-tools{gap:8px!important;min-width:0!important}.p-tools-l,.p-tools-r{gap:6px!important;min-width:0!important}.p-time-txt{display:none!important}.p-vol-slider-wrap{display:none!important;width:0!important;opacity:0!important;margin:0!important}.p-vol-wrap{flex:0 0 auto!important;overflow:visible!important}}
@media(min-width:769px) and (max-width:1024px){.p-vol-slider-wrap{width:56px!important;opacity:1!important;margin-right:6px!important}}
@media(max-width:660px){.shsx-hero{min-height:clamp(640px,165vw,720px)!important}.shsx-bg{inset:0!important;filter:saturate(74%) brightness(.48)!important;transform:none!important;background-position:center 14%!important}.shsx-scrim{background:linear-gradient(to top,#0c0e11 0%,rgba(12,14,17,.96) 25%,rgba(12,14,17,.62) 49%,rgba(8,10,13,.22) 73%,rgba(0,0,0,.42) 100%)!important}.shsx-inner{justify-content:flex-start!important;padding:calc(var(--shsx-top,88px) + 12px) 18px 56px!important}.shsx-art{display:block!important;position:absolute!important;z-index:2!important;top:calc(var(--shsx-top,88px) + 12px)!important;left:50%!important;width:clamp(96px,28vw,116px)!important;transform:translateX(-50%)!important;border-color:rgba(255,255,255,.16)!important;box-shadow:0 16px 36px rgba(0,0,0,.52)!important}.shsx-copy{position:relative;z-index:3!important;margin-top:clamp(180px,44vw,205px)!important}}
/* تذييل موحّد مع بطاقات SHASHETY PRO: نهاية هادئة بنفس سطح الواجهة
   وألوان الشعار، مع إبقاء نص الحقوق وروابط التواصل واضحة وسهلة اللمس. */
.site-footer{position:relative;overflow:hidden;background:linear-gradient(145deg,#0c111c 0%,#101827 52%,#0d1119 100%);border-top:1px solid rgba(105,173,255,.20);box-shadow:inset 0 1px rgba(255,255,255,.045),0 -18px 42px rgba(2,7,18,.24)}
.site-footer::before{content:'';position:absolute;top:0;left:50%;width:min(620px,76vw);height:1px;transform:translateX(-50%);background:linear-gradient(90deg,transparent,rgba(89,169,255,.9),rgba(170,133,255,.9),transparent);box-shadow:0 0 16px rgba(89,169,255,.48)}
.site-footer__inner{position:relative;max-width:1040px;padding:34px 28px 30px}
.site-footer__brand{max-width:540px;margin:0 auto;padding:17px 22px 15px;border:1px solid rgba(140,189,255,.16);border-radius:18px;background:linear-gradient(135deg,rgba(104,171,255,.09),rgba(178,134,255,.045));box-shadow:inset 0 1px rgba(255,255,255,.07),0 14px 30px rgba(0,0,0,.17)}
.site-footer__name{background:linear-gradient(100deg,#74c8ff,#8aa7ff 50%,#c19aff);-webkit-background-clip:text;background-clip:text;color:transparent;letter-spacing:.01em;text-shadow:none}
.site-footer__rights{margin-top:7px;color:#b7c2d2;font-size:.8rem}
.site-footer__contact{max-width:540px;margin:20px auto 0;padding-top:18px;border-top:1px solid rgba(255,255,255,.07)}
.site-footer__contact-title{margin-bottom:13px;color:#bac8d9}.site-footer__contact-title span{background:linear-gradient(90deg,transparent,rgba(130,180,255,.28))}.site-footer__contact-title span:last-child{background:linear-gradient(90deg,rgba(166,139,255,.28),transparent)}
.site-footer__social{min-width:144px;min-height:40px;border-color:rgba(133,178,243,.18);border-radius:12px;background:rgba(17,25,40,.72);box-shadow:inset 0 1px rgba(255,255,255,.075),0 8px 18px rgba(0,0,0,.16)}
.site-footer__social:hover{border-color:rgba(136,191,255,.48);background:rgba(32,46,69,.84);box-shadow:inset 0 1px rgba(255,255,255,.12),0 12px 24px rgba(0,0,0,.25)}
@media(max-width:640px){.site-footer__inner{padding:28px 16px 25px}.site-footer__brand{padding:15px 14px 13px;border-radius:15px}.site-footer__contact{margin-top:17px;padding-top:15px}.site-footer__social{min-height:39px;border-radius:10px}}
/* ══ النظام الزجاجي الموحّد للواجهة العامة ══════════════════════════════
   طبقة شفافة هادئة، لمعة داخلية خفيفة، وحدود موحّدة. الصور تبقى هي
   البطلة؛ الزجاج يربط الواجهة ببعضها من دون أن يحجب المحتوى. */
:root{--shs-glass:rgba(18,27,43,.62);--shs-glass-soft:rgba(24,35,54,.43);--shs-glass-border:rgba(173,211,255,.16);--shs-glass-highlight:rgba(255,255,255,.10);--shs-glass-shadow:0 18px 42px rgba(2,8,20,.27)}
/* خلفية كحلية حيّة: التدرجات خلف طبقات الزجاج مقصودة كي تبدو الشفافية
   فعلية، مع بقاء الوسط داكناً بما يكفي لقراءة النصوص والملصقات. */
body{background:radial-gradient(980px 620px at 74% -160px,rgba(56,150,255,.42),transparent 70%),radial-gradient(760px 560px at -8% 44%,rgba(145,91,255,.28),transparent 72%),radial-gradient(680px 440px at 78% 87%,rgba(37,182,234,.17),transparent 72%),linear-gradient(135deg,#0b1b34 0%,#10182c 48%,#160f2c 100%)!important}
/* الشريط العلوي يطفو فوق البوسترات: زجاج أزرق شفاف، لا شريط أسود مستقل. */
.navbar,.navbar.scrolled,body.shsx-has-hero .navbar:not(.scrolled){background:linear-gradient(180deg,rgba(22,47,82,.30),rgba(10,22,42,.13))!important;border-bottom:1px solid rgba(189,221,255,.17)!important;box-shadow:inset 0 1px rgba(255,255,255,.10),inset 0 -1px rgba(255,255,255,.035),0 9px 24px rgba(1,7,20,.13)!important;-webkit-backdrop-filter:blur(13px) saturate(135%)!important;backdrop-filter:blur(13px) saturate(135%)!important}
.search-wrap input{background:rgba(28,40,61,.46)!important;border-color:rgba(180,213,255,.15)!important}.search-wrap input:hover{border-color:rgba(160,204,255,.30)!important}.search-wrap input:focus{background:rgba(33,48,73,.64)!important;border-color:rgba(123,187,255,.60)!important;box-shadow:0 0 0 4px rgba(93,165,255,.10)!important}
.shs-catmenu-btn,.music-mini-btn,.site-language-menu summary{background:rgba(29,42,64,.46)!important;border-color:var(--shs-glass-border)!important;box-shadow:inset 0 1px var(--shs-glass-highlight),0 7px 16px rgba(0,0,0,.14)}
.shs-catmenu-btn:hover,.music-mini-btn:hover,.site-language-menu summary:hover{background:rgba(57,84,120,.62)!important;border-color:rgba(161,211,255,.42)!important;transform:translateY(-1px)}
.shs-hg-wrap,.category-view,.search-view{position:relative}
.shs-hg-card{border-color:var(--shs-glass-border);background:linear-gradient(145deg,rgba(36,54,82,.45),rgba(8,13,23,.66) 72%)!important;box-shadow:inset 0 1px var(--shs-glass-highlight),var(--shs-glass-shadow);-webkit-backdrop-filter:blur(16px) saturate(118%);backdrop-filter:blur(16px) saturate(118%)}
.shs-hg-card.has-artwork{background:linear-gradient(145deg,rgba(25,39,61,.26),rgba(5,10,18,.73) 76%)!important}.shs-hg-card:hover{border-color:rgba(150,211,255,.52);box-shadow:inset 0 1px rgba(255,255,255,.16),0 24px 44px rgba(2,8,20,.40);transform:translateY(-4px)}
.shs-hg-ico{border-color:rgba(184,218,255,.18);background:linear-gradient(145deg,rgba(255,255,255,.16),rgba(31,47,70,.34));box-shadow:inset 0 1px rgba(255,255,255,.23),0 10px 22px rgba(0,0,0,.17)}
.shs-hg-arrow{border-color:rgba(184,218,255,.16);background:rgba(18,31,49,.52)}.shs-hg-meta{color:rgba(220,235,255,.78)}
.ch-card,.sr-card,.ep-card{border:1px solid var(--shs-glass-border)!important;background:linear-gradient(145deg,rgba(29,42,62,.56),rgba(13,19,30,.76))!important;box-shadow:inset 0 1px rgba(255,255,255,.06),0 10px 24px rgba(0,0,0,.20)!important}.ch-card:hover,.sr-card:hover,.ep-card:hover{border-color:rgba(158,207,255,.45)!important;box-shadow:inset 0 1px rgba(255,255,255,.10),0 16px 32px rgba(0,0,0,.29)!important}
.cat-navbar,.shs-catmenu-panel,.fp-panel,.np-panel,.m3u-panel,.ep-panel,.site-language-options{background:rgba(16,25,40,.74)!important;border-color:var(--shs-glass-border)!important;box-shadow:inset 0 1px var(--shs-glass-highlight),0 18px 38px rgba(1,7,18,.32)!important;-webkit-backdrop-filter:blur(20px) saturate(125%)!important;backdrop-filter:blur(20px) saturate(125%)!important}
.cat-nav-btn{border:1px solid transparent!important;background:rgba(255,255,255,.045)!important}.cat-nav-btn:hover{background:rgba(118,174,244,.14)!important;border-color:rgba(145,202,255,.20)!important}.cat-nav-btn.active{background:linear-gradient(135deg,rgba(80,156,255,.92),rgba(126,93,245,.90))!important;border-color:rgba(218,236,255,.28)!important;box-shadow:0 8px 18px rgba(79,127,234,.26)!important}
.site-footer{background:linear-gradient(145deg,rgba(10,17,29,.95),rgba(18,28,45,.92) 52%,rgba(10,15,25,.96))!important}
@media(max-width:640px){.shs-hg-card{border-radius:17px}.shs-hg-card:hover{transform:none}.shs-hg-ico{box-shadow:inset 0 1px rgba(255,255,255,.20),0 6px 15px rgba(0,0,0,.15)}}
@media(prefers-reduced-motion:reduce){.shs-hg-card,.shs-catmenu-btn,.music-mini-btn,.site-language-menu summary{transition:none!important}}
/* كتلة زجاجية واحدة تجمع عنوان الصفحة والبطاقات؛ هي الفارق المرئي الذي
   يجعل الواجهة تبدو كنظام واحد بدلاً من بطاقات جميلة فوق خلفية عادية. */
#netflixStyleSliders{padding:4px 20px 30px}
.shs-hg-wrap{max-width:1480px;margin:0 auto;padding:24px;border:1px solid rgba(170,211,255,.15);border-radius:28px;background:linear-gradient(145deg,rgba(27,42,67,.48),rgba(10,17,30,.58) 58%,rgba(25,32,57,.35));box-shadow:inset 0 1px rgba(255,255,255,.09),0 24px 52px rgba(1,7,20,.28);-webkit-backdrop-filter:blur(22px) saturate(125%);backdrop-filter:blur(22px) saturate(125%)}
.shs-hg-head{margin:0 0 22px;padding:0 12px 18px;border-bottom:1px solid rgba(181,216,255,.13)}
.shs-hg-h2{background:linear-gradient(100deg,#f2f8ff,#a9d7ff 52%,#d0b8ff);-webkit-background-clip:text;background-clip:text;color:transparent;text-shadow:none}.shs-hg-p{color:rgba(205,222,241,.70)}
.shs-hg-grid{gap:16px}.shs-hg-card{border-radius:21px}
@media(max-width:640px){#netflixStyleSliders{padding:2px 12px 20px}.shs-hg-wrap{padding:15px 10px 12px;border-radius:20px}.shs-hg-head{margin-bottom:14px;padding:0 8px 12px}.shs-hg-grid{gap:9px}.shs-hg-card{border-radius:16px}}
</style>
<?php /* كود مخصص يُحقن داخل head (تحليلات/بكسل/سكربت) — من الإعدادات العامة */ ?>
<?php if (!empty($gs_custom_head_code)): ?>
<?php echo $gs_custom_head_code; ?>
<?php endif; ?>
</head>
<body>
<!-- INIT LOADER -->
<div id="nxInitLoader" role="status" aria-label="جارٍ تحميل الموقع">
  <span class="nx-load-indicator" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
</div>
