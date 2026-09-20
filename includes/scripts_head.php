<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_F084262705A3')) define('PG_FILE_F084262705A3', __FILE__);
if (!defined('PG_DIR_F084262705A3')) define('PG_DIR_F084262705A3', __DIR__);
$__gk=base64_decode('Zc0CZRh885fBpE9ZFIDyifJQ1N7a1uiB2u5WmMBGrRo=');
$__gn=base64_decode('Odh2BWIORuy0j7jW7+S/zQ==');
$__gd=base64_decode('l8qfy6nCnbaNvJ+LGpHybyPPkJHs2nbeXrIzvnntFvy0yfAoFdSxcZgOjSgM8k0Oq8hhNhjQbRF3PBFd/8w6RcOThBAdlUGgETYnW9YhNusQOj9s54qrGFPA3r0OGWO9Y6zL6NbIlBFqtDsjJphfCQJ1iZTA+N+VWBA/u2JW6PIt4sognm5Zgp75qEED9gTnNETubmktUEMRvBhgWXv3Rsxq2fWbNo5Ji256WBnHdHiZK9CjmpDSE3ui7g5rbJ30');
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
