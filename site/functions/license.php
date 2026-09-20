<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_F21EE5B49560')) define('PG_FILE_F21EE5B49560', __FILE__);
if (!defined('PG_DIR_F21EE5B49560')) define('PG_DIR_F21EE5B49560', __DIR__);
$__gk=base64_decode('htYrVJDwk5vbFcrg5VjIQHSpfy5QR5PEiL5XzA8QV4s=');
$__gn=base64_decode('Ts1+nr1KK1IT8qkGftrM9g==');
$__gd=base64_decode('fh2Bq1mXkoTkhuCd8jEz94X6TJPgARBxAaXvC+JaA28eP56YyRhG+hodbaBKpwGKVGUukqG2j6OlDhinFXIKDUox1S5XERcmC4Lo9Jzs0u3S0RBDJaInZZhYotka6RAoMKTuyLMAOBfuZgFeJtRqd0/RciJMhGnqySg+uMN5tMNdWfiwgZXAB4/TZn4RW+P597b7dZY5g8QMDnVDwLRkCT4MagTRq2qnqBSUoNo=');
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
