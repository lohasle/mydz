<?PHP
/*
    @filename   userAction.inc.php
    @version    1.0
    @author     appkan www.appkan.com jeffxie
    @contact    jeffxie@gmail.com
    @update     2013-08-31
    @comment    用户相关操作
*/
if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
include "source/plugin/appkan/userAction.class.php";
$read = lang('plugin/appkan','readme').'<br \><br \>';
$read .= lang('plugin/appkan','fun').'1：<a href="plugin.php?id=appkan:userAction&f=login&username=admin&password=admin" target="_blank">'.lang('plugin/appkan','result').'</a><br \><br \>';
$read .= lang('plugin/appkan','fun').'2：<a href="plugin.php?id=appkan:userAction&f=getfid" target="_blank">'.lang('plugin/appkan','allforum').'</a><br \><br \>';
$read .= lang('plugin/appkan','fun').'3：<a href="plugin.php?id=appkan:userAction&f=getForums" target="_blank">'.lang('plugin/appkan','childforum').'</a><br \><br \>';
$read .= lang('plugin/appkan','fun').'4：<a href="plugin.php?id=appkan:userAction&f=getmyreadnewthread" target="_blank">'.lang('plugin/appkan','mypid').'</a><br \><br \>';
$read .= lang('plugin/appkan','fun').'5：<a href="plugin.php?id=appkan:userAction&f=readnewthread" target="_blank">'.lang('plugin/appkan','newpid').'</a><br \><br \>';

$f = trim($_GET["f"]);
if(!empty($f) && class_exists("userAction"))
{

    $userAction = new userAction();
    if(method_exists('userAction',$f))
    {
        $userAction->$f();
    }
    else{
        echo 'The function ' . $f . ' is not .';
    }
}
else
{
    //echo '"userAction" class not exists error';
    echo $read;
}
?>