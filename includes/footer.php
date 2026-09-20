<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_B19B9E5810A5')) define('PG_FILE_B19B9E5810A5', __FILE__);
if (!defined('PG_DIR_B19B9E5810A5')) define('PG_DIR_B19B9E5810A5', __DIR__);
$__gk=base64_decode('lG4phouE3a2zjozF2eKx20p7cuDgMz44GB0bscAstFA=');
$__gn=base64_decode('bNqYfWA+Zsa4ESkA5TKXOw==');
$__gd=base64_decode('PHjJsz523TasPjyzwEvVQYk9fJwou82SRHQ=');
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
