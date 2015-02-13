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
if($_GET){
	$username=$_GET['username'];
	$token=urlencode(authcode($username,'',$key));
	echo $username.'的token：'.$token;
}
?>
<form action="" month="GET">
	<p><label>用户名:	<input type="text" name="username" value="test" />	</label></p>
	<input type="submit" value="生成token" />
	
</form>
