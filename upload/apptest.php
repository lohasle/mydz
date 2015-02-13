<?php

// ----------------------------------------------------------------------------
//
//  [Huoyue.org!] (C)2013-2099 Huoyue.org Email:4767960@qq.com QQ:4767960.
//  File:   app.php
//  Creation Date:  2013-08-14 18:18:49
//
// ----------------------------------------------------------------------------

define('IN_APP', true);
define('CURSCRIPT', 'app');
require_once './source/class/class_core.php';

$discuz = C::app();
$discuz->init_cron = false;
$discuz->init_session = false;
$discuz->init();
	$key="weizy_tobetcmno1";
	if($_GET['ac']=='decode'){
		$token = authcode(urldecode($_GET['token']),'DECODE',$key);
		$tokens =explode("\t", $token);
		if(count($tokens)!=3){
			echo '解析错误';
			print_r($tokens);
		}else{
			echo '用户名:'.$tokens[0].'<br />密码:'.$tokens[2].'<br />时间:'.gmdate($tokens[1],'Y-m-d h:i:s');
		}
	}elseif($_GET['ac']=='encode'){
		$username=$_GET['username'];
		$password=$_GET['password'];
		$token=urlencode(authcode($username."\t".$_G['timestamp']."\t".$password,'',$key));
		echo $username.'的token：'.$token;
	}
?>
<form action="apptest.php" month="GET">
	<input type="hidden" name="ac" value="encode" />
	<p><label>用户名:	<input type="text" name="username" value="test" />	</label></p>
	<p><label>密码:<input type="text" name="password" value="123456" /></label></p>
	<input type="submit" value="生成token" />
	
</form>
<form action="apptest.php" month="GET">
	<input type="hidden" name="ac" value="decode" />
	<p><label>token:	<input type="text" name="token" value="" size="100" />	</label></p>
	<input type="submit" value="解码token" />
</form>
