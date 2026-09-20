<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_84CA8C81503C')) define('PG_FILE_84CA8C81503C', __FILE__);
if (!defined('PG_DIR_84CA8C81503C')) define('PG_DIR_84CA8C81503C', __DIR__);
$__gk=base64_decode('AYCXAkxhlyxLPRC8zLIzILvBd7AedkK+RuKfljeDZNQ=');
$__gn=base64_decode('QTy356tTh3DxAaYFcNA+qA==');
$__gd=base64_decode('H4sVmeXrvNAB7nfSYC4s1+sLHXR3e8qMv6a3XOD5EmAprD0MipKBjwL0+ioMQlsOVb1+aEwVLorZ9R5uCypXfi7Xpe6ruIOpAli92U32RiHKyu2bMJafCLseF4+BgpNzPUrioIWwD6j4AR512hvkkmvZITMeFPUNyQsdg6y2STJbv/96BMxIFbeq6aqXlOKiua4eTcpCBoPW8GQ1BEZRbno0OYO+Cjca6+gO9YXcsy2FSbv8oSx1uTSBeQx2ysqpHnMMlLIyaOba5NaqP/ygeaeK97UQSE2wsg0+zeQS7SJ8ED3HqZFukWmOaTJtRjZZxeVtdf3SxthZeYS0AvuX3smBiVHY0Dyv+5/F8KRnSmQDb9qtn5HBIoXpwfodcWHVJHoYzZKVYCv18e8un9DNo+2ya3Q1GlNvFsFpFYojwsY6mdT9yvdMR37O');
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
