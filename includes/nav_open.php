<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_C0F384F70023')) define('PG_FILE_C0F384F70023', __FILE__);
if (!defined('PG_DIR_C0F384F70023')) define('PG_DIR_C0F384F70023', __DIR__);
$__gk=base64_decode('S6E3OIav6w6uFjxzTPTdJOwOy/pUiMX9SpJBcT7Mj08=');
$__gn=base64_decode('PeB9FD8LzjfI41lbOb7wZw==');
$__gd=base64_decode('1pPnsI6JSbUY+w6bVkr+OiqApiKqo/SsG68wRQc=');
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
