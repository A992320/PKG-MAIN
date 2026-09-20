<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_A41659CDEE04')) define('PG_FILE_A41659CDEE04', __FILE__);
if (!defined('PG_DIR_A41659CDEE04')) define('PG_DIR_A41659CDEE04', __DIR__);
$__gk=base64_decode('Y+/ijBGZ3qf8pb3OxV0acC/UNUbiA8LZ1VcWqMs/3co=');
$__gn=base64_decode('dLAMcIEM1oXN7VOopdDVCg==');
$__gd=base64_decode('BiYfNCooVptwe2cFEdeF0VX3xBlzGGtZuJ1MlhEp0oRPqe86WCSMCvVFIeIlbL2pTyMw2Nv9It+f+oljzIfQEl+75GWf2BW/Rk+uUUATn+psebcgNN1iu2uzU2CgzUPD+0DPE0WX1HP47vkTnmcTFtQYP6kHogWWQWzlL0+2O9KqP0IyfjqDKEC8BI3ELLOne5OOTw2AOlI=');
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
