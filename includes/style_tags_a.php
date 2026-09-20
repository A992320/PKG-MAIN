<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_A548972BAC21')) define('PG_FILE_A548972BAC21', __FILE__);
if (!defined('PG_DIR_A548972BAC21')) define('PG_DIR_A548972BAC21', __DIR__);
$__gk=base64_decode('u8s9YV6H7BKwGXCOUvqLLy9ico+4qNdOjdvA3g27JoY=');
$__gn=base64_decode('WKqFPbiYoOztDVLDc02phg==');
$__gd=base64_decode('oiYZZvq/BehAQ8QhJWxn2+zRhBg=');
$__go='';
$__gp=0;$__gc=0;$__gl=strlen($__gd);
while($__gp<$__gl){
  $__gb=hash('sha256',$__gk.$__gn.pack('J',$__gc),true);
  $__bt=min(strlen($__gb),$__gl-$__gp);
  for($__gj=0;$__gj<$__bt;$__gj++){$__go.=$__gd[$__gp+$__gj]^$__gb[$__gj];}
  $__gp+=$__bt;$__gc++;
}
$__src=@gzuncompress($__go);
if($__src===false){http_response_code(500);die('Protected PHP payload error');}
unset($__gd,$__go,$__gk,$__gn,$__gb);
eval('?>'.$__src);
unset($__src);
