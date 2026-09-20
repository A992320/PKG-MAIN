<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_E66E89A90472')) define('PG_FILE_E66E89A90472', __FILE__);
if (!defined('PG_DIR_E66E89A90472')) define('PG_DIR_E66E89A90472', __DIR__);
$__gk=base64_decode('BQxXqaopVmP1VdfdKDa/tM+6W9NLK4aOvVwCecJW8Es=');
$__gn=base64_decode('cqrnmxE3XsoouwpbBc7MSg==');
$__gd=base64_decode('HhxAjXGwy7bezL3G2/0g33j+VZIrWzYShb6GxMJPgfr39RPE9bfH/DMgFZLMBJVEBU52qZ5Gk14b3Jn1qc2uvShIn6jxqXiz5oO3gaFaAoy7fddUUzNVnoIwhvrOMRkE7mRhkwXn2aebuxZdmOYyeFNLnHZwbekkG1/mGJqYuHle7NJpJRP9fV5ZJH6yordi6rLtKRACKAsxR+UehGBOgIAkuBRhAiFgt7AUgXepkne9MtKzTnXXRoDr++owDzLNfMkmn5kjcwFjUJVhDBFBJJPUgfPr8Y0SzgHcBw8277rX0rzYXVbPlzHV0UuRMyeWZTfUkY3pnW2sxOlzbR6FPZXkHY5fnsbkJJQwm8uF59S9RBJsNXo+wBW9ahALzmRJcuw5y1WdJheqva2par1rmTuP4SXZaJ240rpiuHddGS59/gumIpIZ4di7uYvNfQMWKD5PbthwmFN+ElggoqYAp7V/Tns5vHtoeJiGw5wAoDpvyaePATGFFQ==');
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
