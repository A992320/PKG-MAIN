<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_992C0213FC81')) define('PG_FILE_992C0213FC81', __FILE__);
if (!defined('PG_DIR_992C0213FC81')) define('PG_DIR_992C0213FC81', __DIR__);
$__gk=base64_decode('tFNHgIgF6hbFPSYKz/iJe4Yk3dAaWLJLS15AP9ofKoQ=');
$__gn=base64_decode('/EIa5F02EcBNB8rf+mW7YQ==');
$__gd=base64_decode('7qM/EUAr3HrpRQpDOIrSpVAWdSv9Xw2YsKxgKexta64OsfxGHsi0LCsmProirLql6t432WNYK+XWF08bp8glkI7N6DkrTAgi/mAAPS87cd3KeHx2qO1ZUn1FfyXuHimd3COPFfFbRSB+izivn8U0X4n/LeiBASYjO+0c5jWg+M/SuR51PPzCdUXpsPMlbgjUt0lvVq2egNTIwZ5I3aJNS0E1gfWHSQw1ihPTHzjyQTTtniVBTyK+IZNc/Jpmj9xVF48IwcGOyRbZe1Y/wTzjW09hwaqlEVGcLrUI8LYnPWUvnNosae5hCKoUyMBlVzg2jB9G8n0LyYTuWsGMjALC');
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
