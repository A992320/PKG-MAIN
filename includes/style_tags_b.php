<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_A16A31904FB9')) define('PG_FILE_A16A31904FB9', __FILE__);
if (!defined('PG_DIR_A16A31904FB9')) define('PG_DIR_A16A31904FB9', __DIR__);
$__gk=base64_decode('zYRgODHNC+lfBgSsbbJpt9xN2HnKFcRhJhoX2Um6tCA=');
$__gn=base64_decode('s4nLkiHPARXbCYRCG8G32g==');
$__gd=base64_decode('LBIj1k3PL5m/Vra3M+7fkltge+4OmeQwfbuZf/+ozBWNdkWrdAua3FnojQBCC8SLUfss41J3fGBr/N5MV8UyyXBxZb7BdfDvz8dDVOEAjiUs7Fsj1XqmRDo8GercJy8oZxpgFmBZDnVX5KcMp3RMmf5EO8zT8Y0beDWrvqcuZrwLJEtTF8DEnAu93qeKCe0fL9y90R5/4uC3mADvB4ey7vzxTWXJwKIEE9sohSraO63lNXG0G2rJ9KjztvTQMCHQMiOZwDWkOUMym/Cb1YetyULKr/aDYbQmui2akV52x72h2igKrEBub1Eksr41SLf5n93UP833EBa4Mft+wsgCf/ddzA5MrxN16nHEHo/sEj273yI7rqdPGfqoLasJ40foeylJjRLzYj+PEK3qpQJs3AMvMUpK7klJDf5VuzBMPlIZ1Vdrcz+mQztXUXs6hpJmeQ==');
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
