<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_D3104D4DE98F')) define('PG_FILE_D3104D4DE98F', __FILE__);
if (!defined('PG_DIR_D3104D4DE98F')) define('PG_DIR_D3104D4DE98F', __DIR__);
$__gk=base64_decode('DAkbkQWUxBctCy76XBlVnyR3s/jl2GrNv1a/9PzbK38=');
$__gn=base64_decode('ZjN9aXgdQuMoYDezQ8j2Kg==');
$__gd=base64_decode('jkvXo8RnJak1dMHBU6TKw3wM3TuBny7FVgcEXiIQE7TUdOWZcHq+LkUqRW4McQIk8cnZh+SeClyTwwvFLQZOmh2ygeJ92Iw+RdWxy0CHxXvqSv22eZkjgfWq2YUboOvwRN5AnfZ+MBLU183YZ5ATB8AcBL7PRjLabP5tr7hPraxEIDHhOVHSQr+NqeX0J4BnKdIHgjx39s6Nx/auZv4aLtl6');
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
