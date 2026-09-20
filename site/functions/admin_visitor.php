<?php
/* Protected by PHP Guard 1.0. Do not edit this generated file. */
if (!defined('PG_FILE_C8EC515EF76B')) define('PG_FILE_C8EC515EF76B', __FILE__);
if (!defined('PG_DIR_C8EC515EF76B')) define('PG_DIR_C8EC515EF76B', __DIR__);
$__gk=base64_decode('jhhddQvhlMcE8ehmBUuejEfYQJCcHDRQN5zfo9NtQHU=');
$__gn=base64_decode('aku/98d7Xzk03Khe+cUVXw==');
$__gd=base64_decode('PoYDnv538chgoLmdbeFnpyNuri46ZSe997o8y7HW7Uw9FpTWKRHdVvFYoUN/0ax31WY21vU9zKv0a3DfWnxfZrJ5gyvm4IImK1naiMBF9CZdQBO7Lkv3NvNgJqH+ecINARg6uUUl9dT+wUwG9d0oa+bpxjtd8TpsJPSdLHP272mJu80NixfNXmfWsodnlnc/FpALWeKTLv2qx9zD4deJ5A4hfZphSNXKJTRQcsjRF4+uCixCkflIccBV7yDE6w/J2JYGjGlnNveuPhrsWtDVTlA4FQzc7AwkSB09X8UygyOZyCB1SeyYjkvIrwP/cycITriRdLF/gGFvQj2N0OoYzWjcTSKc9WbZZgLNAQwQYr5GtU9XzLWZTiWqX4+VkbYC+jk4znnyisdJX+RVtr/jVFzJM/PiI/oOswTTXF24+/Fgk6CFDlU+');
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
