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
require './source/function/function_forum.php';
$modarray = array('setting','user', 'post', 'thread', 'forum', 'third', 'invite', 'task', 'medal', 'rss', 'follow','upload','report');
$modcachelist = array(
	'thread'	=> array('smilies', 'smileytypes', 'forums', 'usergroups', 'forums', 'forumstick',
			'stamps', 'bbcodes', 'smilies',	'custominfo', 'groupicon', 'stamps',
			'threadtableids', 'threadtable_info', 'posttable_info', 'diytemplatenameforum'),
	'post'		=> array('bbcodes_display', 'bbcodes', 'smileycodes', 'smilies', 'smileytypes',
			'domainwhitelist', 'albumcategory'),
);
$mod = !in_array(C::app()->var['mod'], $modarray) ? 'setting' : C::app()->var['mod'];

define('CURMODULE', $mod);
$cachelist = array();
if(isset($modcachelist[CURMODULE])) {
	$cachelist = $modcachelist[CURMODULE];
}
$discuz = C::app();
$discuz->init_cron = false;
$discuz->init_session = false;
C::app()->cachelist = $cachelist;
$discuz->init();

define('IN_MOBILE',2);
//define('IN_MOBILE_API',1);
//$mod = getgpc('mod');
//if(!in_array($mod,$modarray)) {
//	$mod = 'setting';
//	$_GET['do'] = 'home';
//}
loadforum();
$json=array('success'=> true,'code'=> 0,'data'=> array());
$authkey=$_G['config']['security']['authkey'];
$logintime=24*3600;
define('CURMODULE', 'app_'.$mod);
//if(isset($_GET['token'])){
	init_user();
//}
if($mod=='setting'){
	$json['data']=$_G['setting']['bbname'];//bbname//siteurl
	json_echo($json);
}
require_once libfile('app/'.$mod, 'module');

function json_echo($data){
	$json=array('success'=> true,'code'=> 0,'data'=> array());
	if(empty($data)){
		$json['success']=false;
	}elseif($data!=1){
		$data['data']=$data['data'] ? $data['data'] : ($data['code']==100 ? '未登录' : '');
		$json=$data;
	}
	if($_GET['debug']){
		echo '<pre>';
		print_r($json);
		echo '</pre>';
	}else{
		header('Content-Type:application/json;charset=utf-8');
		echo json_encode($json);
	}
	exit();
}
function get_url_word($str){
	return trim($str);
//	return diconv(trim($str),'gbk');
}

function insterthread($data) {
	global $_G,$pre_arr;
	require_once libfile('function/forum');
	require_once libfile('function/post');
	$subject 		= $data['subject'];
	$message 		= $data['message'];
	$author 		= $data['username'];
	$publishdate 	= TIMESTAMP;
	$closed 		= 0;
	$digest 		= 0;
	$replycredit	= 0;
	$isgroup		= 0;
	$moderated		= 0;
	$special		= 0;
	$displayorder = 0;

	$newthread = array(
	'fid' 			=> $data['fid'],
	'posttableid' 	=> 0,
	'readperm' 		=> 0,
	'price' 		=> 0,
	'typeid'		=> intval($data['typeid']),
	'sortid' 		=> 0,
	'author' 		=> $author,
	'authorid' 		=> $data['uid'],
	'subject' 		=> $subject,
	'dateline' 		=> $publishdate,
	'lastpost' 		=> $publishdate,
	'lastposter' 	=> $author,
	'displayorder' 	=> $displayorder,
	'digest' 		=> $digest,
	'special' 		=> $special,
	'attachment' 	=> 0,
	'moderated' 	=> $moderated,
	'status' 		=> 0,
	'isgroup' 		=> $isgroup,
	'replycredit' 	=> $replycredit,
	'closed' 		=> 0
	);
	$tid = C::t('forum_thread')->insert($newthread, true);
	useractionlog($data['uid'], 'tid');
	$bbcodeoff = 0;//checkbbcodes($message, 0);
	$smileyoff = 0;//checksmilies($message, 0);
	$parseurloff 	= 1;
	$htmlon			= 0;
	$usesig 		= 1;
	$tagstr			= '';
	$pinvisible 	= 0;
	$pid = insertpost(array(
	'fid' => $data['fid'],
	'tid' => $tid,
	'first' => '1',
	'author' => $data['username'],
	'authorid' => $data['uid'],
	'subject' => $subject,
	'dateline' => $publishdate,
	'message' => $message,
	'useip' => $_G['clientip'],
	'invisible' => $pinvisible,
	'anonymous' => $isanonymous,
	'usesig' => $usesig,
	'htmlon' => $htmlon,
	'bbcodeoff' => $bbcodeoff,
	'smileyoff' => $smileyoff,
	'parseurloff' => $parseurloff,
	'attachment' => '0',
	'tags' => $tagstr,
	'replycredit' => 0,
	'status' => 0
	));

	updatepostcredits('+',  $data['uid'], 'post', $data['fid']);
	if($isgroup) {
		C::t('forum_groupuser')->update_counter_for_user($data['uid'], $data['fid'], 1);
	}

	$subject = str_replace("\t", ' ', $subject);
	$lastpost = "$tid\t".$subject."\t$_G[timestamp]\t$author";
	C::t('forum_forum')->update($data['fid'], array('lastpost' => $lastpost));
	C::t('forum_forum')->update_forum_counter($data['fid'], 1, 1, 1);
	if($_G['forum']['type'] == 'sub') {
		C::t('forum_forum')->update($_G['forum']['fup'], array('lastpost' => $lastpost));
	}
	if($_G['forum']['status'] == 3) {
		C::t('forum_forumfield')->update($data['fid'], array('lastupdate' => TIMESTAMP));
		require_once libfile('function/grouplog');
		updategroupcreditlog($data['fid'], $data['uid']);
	}
	return array('tid'=>$tid, 'pid'=>$pid);
}
function insterpost($data) {
	global $_G;
	require_once libfile('function/forum');
	require_once libfile('function/post');
	$subject 		= $data['subject'];
	$message 		= $data['message'];
	$author 		= $data['username'];
	$authorid  		= $data['uid'];
	$publishdate 	= TIMESTAMP;
	$closed 		= 0;
	$digest 		= 0;
	$replycredit	= 0;
	$isgroup		= 0;
	$moderated		= 0;
	$special		= 0;
	$displayorder = 0;

	$bbcodeoff = 0;//checkbbcodes($message, 0);
	$smileyoff = 0;//checksmilies($message, 0);
	$parseurloff 	= 1;
	$htmlon			= 0;
	$usesig 		= 1;
	$tagstr			= '';
	$pinvisible 	= 0;
	$thread=C::t('forum_thread')->fetch($data['tid']);
	$pid = insertpost(array(
	'fid' => $thread['fid'],
	'tid' => $thread['tid'],
	'first' => '0',
	'author' => $data['username'],
	'authorid' => $data['uid'],
	'subject' => $subject,
	'dateline' => $publishdate,
	'message' => $message,
	'useip' => $_G['clientip'],
	'invisible' => $pinvisible,
	'anonymous' => $isanonymous,
	'usesig' => $usesig,
	'htmlon' => $htmlon,
	'bbcodeoff' => $bbcodeoff,
	'smileyoff' => $smileyoff,
	'parseurloff' => $parseurloff,
	'attachment' => '0',
	'tags' => $tagstr,
	'replycredit' => 0,
	'status' => 0
	));

	useractionlog($thread['uid'], 'pid');

	include_once libfile('function/stat');
	updatestat($thread['isgroup'] ? 'grouppost' : 'post');
	$updatethreaddata = $heatthreadset ? $heatthreadset : array();
	$postionid = C::t('forum_post')->fetch_maxposition_by_tid($thread['posttableid'], $data['tid']);
	$updatethreaddata[] = DB::field('maxposition', $postionid);


	$fieldarr = array(
	'lastposter' => array($author),
	'replies' => 1
	);
	if($thread['lastpost'] < $_G['timestamp']) {
		$fieldarr['lastpost'] = array($_G['timestamp']);
	}
	$row = C::t('forum_threadaddviews')->fetch($thread['tid']);
	if(!empty($row)) {
		C::t('forum_threadaddviews')->update($thread['tid'], array('addviews' => 0));
		$fieldarr['views'] = $row['addviews'];
	}
	$updatethreaddata = array_merge($updatethreaddata, C::t('forum_thread')->increase($thread['tid'], $fieldarr, false, 0, true));

	if($thread['replycredit'] > 0 &&  $thread['authorid'] != $data['uid'] && $data['uid']) {

		$replycredit_rule = C::t('forum_replycredit')->fetch($thread['tid']);
		if(!empty($replycredit_rule['times'])) {
			$have_replycredit = C::t('common_credit_log')->count_by_uid_operation_relatedid($data['uid'], 'RCA', $thread['tid']);
			if($replycredit_rule['membertimes'] - $have_replycredit > 0 && $thread['replycredit'] - $replycredit_rule['extcredits'] >= 0) {
				$replycredit_rule['extcreditstype'] = $replycredit_rule['extcreditstype'] ? $replycredit_rule['extcreditstype'] : $_G['setting']['creditstransextra'][10];
				if($replycredit_rule['random'] > 0) {
					$rand = rand(1, 100);
					$rand_replycredit = $rand <= $replycredit_rule['random'] ? true : false ;
				} else {
					$rand_replycredit = true;
				}
				if($rand_replycredit) {
					updatemembercount($data['uid'], array($replycredit_rule['extcreditstype'] => $replycredit_rule['extcredits']), 1, 'RCA', $thread['tid']);
					C::t('forum_post')->update('tid:'.$thread['tid'], $pid, array('replycredit' => $replycredit_rule['extcredits']));
					$updatethreaddata[] = DB::field('replycredit', $thread['replycredit'] - $replycredit_rule['extcredits']);
				}
			}
		}
	}

	updatepostcredits('+',  $data['uid'], 'post', $thread['fid']);
	$subject = str_replace("\t", ' ', $subject);
	$lastpost = "$tid\t".$subject."\t$_G[timestamp]\t$author";
	C::t('forum_forum')->update($thread['fid'], array('lastpost' => $lastpost));
	C::t('forum_forum')->update_forum_counter($thread['fid'], 1, 1, 1);
	if($_G['forum']['type'] == 'sub') {
		C::t('forum_forum')->update($_G['forum']['fup'], array('lastpost' => $lastpost));
	}
	if($updatethreaddata) {
		C::t('forum_thread')->update($thread['tid'], $updatethreaddata, false, false, 0, true);
	}
	return array('tid'=> $thread['tid'], 'pid'=> $pid,'position'=> $postionid);
}

function updatethread($data) {
	global $_G;
	require_once libfile('function/forum');
	require_once libfile('function/post');
	$tid = $data['tid'];
	$subject = $data['subject'];
	$message = $data['message'];
	$author = $data['username'];
	$publishdate 	= TIMESTAMP;

	$newthread = array(
	'subject' => $subject,
	'lastpost' => $publishdate,
	'lastposter' => $author
	);
	C::t('forum_thread')->update($tid,$newthread);
	useractionlog($data['uid'], 'tid');
	$postdata=array(
	'subject' => $subject,
	'message' => $message,
	);
	$return=C::t('forum_post')->update_by_tid(0, $tid, $postdata,false, false,1);
	$subject = str_replace("\t", ' ', $subject);
	$lastpost = "$tid\t".$subject."\t$_G[timestamp]\t$author";
	C::t('forum_forum')->update($data['fid'], array('lastpost' => $lastpost));
	return $return;

}
function pre_get_uid($username,$isuid=0){
	global $_G;
	if($isuid>0){
		if(C::t('common_member')->fetch_all_username_by_uid(array($username))){
			$uid=intval($username);
		}else{
			$uid=C::t('common_member_archive')->fetch($username);
		}
	}else{
		$uid=C::t('common_member')->fetch_uid_by_username($username);
		if(empty($uid)){
			$uid=C::t('common_member_archive')->fetch_uid_by_username($username);
		}
	}
	if(empty($uid)){
		loaducenter();
		if($data = uc_get_user($username)){
			list($uid, $username, $email) = $data;
		}
	}
	return intval($uid);
}
//$data['username'], $data['password'], $data['email']
// return -100 user is exited
// other http://faq.comsenz.com/library/UCenter/interface/interface_user.htm uc_user_register
function adduser($data) {
	global $_G,$pre_arr;
	$uid=pre_get_uid($data['username']);
	if($uid>0){
		return -100;
	}
	$uid = uc_user_register($data['username'], $data['password'], $data['email']);
	if($uid > 0){
		loadcache('fields_register');
		$init_arr = explode(',', $_G['setting']['initcredits']);
		$password = md5(random(10));
		C::t('common_member')->insert($uid, $data['username'], $password, $data['email'], 'Manual Acting', $pre_arr['groupid'], $init_arr, 0);
	}
	return $uid;
}
function init_user() {
	global $_G,$authkey;
	$key="weizy_tobetcmno1";
	$token = authcode(urldecode($_GET['token']),'DECODE',$key);
	$tokens =explode("\t", $token);
//	test($tokens);
	list($discuz_username,$logintime, $discuz_pw) = empty($tokens) || count($tokens) < 3 ? array('', '', '') : $tokens;
	if($discuz_username) {
		loaducenter();
		list($uid, $username, $password, $email) =uc_user_login($discuz_username, $discuz_pw);
		if($uid>0){
			$discuz_uid =   $uid;
			$user = getuserbyuid($discuz_uid, 1);
			if(empty($user)){
				DB::insert('common_member', array(
				'uid' => $uid,
				'username' => $username,
				'password' => md5(random(10)),
				'email' => $email,
				'adminid' => 0,
				'groupid' => $_G['setting']['regverify'] ? 8 : $_G['setting']['newusergroupid'],
				'regdate' => TIMESTAMP,
				'credits' => $init_arr[0],
				'timeoffset' => 9999
				));
				DB::insert('common_member_status', array(
				'uid' => $uid,
				'regip' => $_G['clientip'],
				'lastip' => $_G['clientip'],
				'lastvisit' => TIMESTAMP,
				'lastactivity' => TIMESTAMP,
				'lastpost' => 0,
				'lastsendmail' => 0
				));
				DB::insert('common_member_profile', array('uid' => $uid));
				DB::insert('common_member_field_forum', array('uid' => $uid));
				DB::insert('common_member_field_home', array('uid' => $uid));
				DB::insert('common_member_count', array('uid' => $uid));
				$user = getuserbyuid($discuz_uid, 1);
			}
		}
	}
	

	if(!empty($user)) {
		if(isset($user['_inarchive'])) {
			C::t('common_member_archive')->move_to_master($discuz_uid);
		}
		$_G['member'] = $user;
		$_G['member']['lastvisit'] = TIMESTAMP - 3600;
		C::t('common_member_status')->update($discuz_uid, array('lastvisit'=>$_G['member']['lastvisit']));
		$cachelist[] = 'usergroup_'.$_G['member']['groupid'];
		if($user && $user['adminid'] > 0 && $user['groupid'] != $user['adminid']) {
			$cachelist[] = 'admingroup_'.$_G['member']['adminid'];
		}
	
		setglobal('groupid', getglobal('groupid', 'member'));
		!empty($cachelist) && loadcache($cachelist);
	
		if($_G['member'] && $_G['group']['radminid'] == 0 && $_G['member']['adminid'] > 0 && $_G['member']['groupid'] != $_G['member']['adminid'] && !empty($_G['cache']['admingroup_'.$_G['member']['adminid']])) {
			$_G['group'] = array_merge($_G['group'], $_G['cache']['admingroup_'.$_G['member']['adminid']]);
		}
	
		if($_G['group']['allowmakehtml'] && isset($_GET['_makehtml'])) {
			$_G['makehtml'] = 1;
			init_guest();
			loadcache(array('usergroup_7'));
			$_G['group'] = $_G['cache']['usergroup_7'];
			unset($_G['inajax']);
		}
	
		setglobal('uid', getglobal('uid', 'member'));
		setglobal('username', getglobal('username', 'member'));
		setglobal('adminid', getglobal('adminid', 'member'));
		setglobal('groupid', getglobal('groupid', 'member'));
		if($_G['member']['newprompt']) {
			$_G['member']['newprompt_num'] = C::t('common_member_newprompt')->fetch($_G['member']['uid']);
			$_G['member']['newprompt_num'] = unserialize($_G['member']['newprompt_num']['data']);
			$_G['member']['category_num'] = helper_notification::get_categorynum($_G['member']['newprompt_num']);
		}
	} else {
		$user = array();
		init_guest();
	}


}
function init_guest() {
	global $_G;
	$username = '';
	$groupid = 7;
	if(!empty($_G['cookie']['con_auth_hash']) && ($openid = authcode($_G['cookie']['con_auth_hash']))) {
		$_G['connectguest'] = 1;
		$username = 'QQ_'.substr($openid, -6);
		$_G['setting']['cacheindexlife'] = 0;
		$_G['setting']['cachethreadlife'] = 0;
		$groupid = $_G['setting']['connect']['guest_groupid'] ? $_G['setting']['connect']['guest_groupid'] : $_G['setting']['newusergroupid'];
	}
		loadcache(array('usergroup_7'));
		$_G['group'] = $_G['cache']['usergroup_7'];
	setglobal('member', array( 'uid' => 0, 'username' => $username, 'adminid' => 0, 'groupid' => $groupid, 'credits' => 0, 'timeoffset' => 9999));
	
	setglobal('uid', 0);
	setglobal('username', $username);
	setglobal('adminid', 0);
	setglobal('groupid', 0);
}
function error_json($message, $url_forward = '', $values = array(), $extraparam = array(), $custom = 0){
	$json=array('success'=> false,'code'=>$message ,'data'=> lang('app', $message, $values));
	json_echo($json);
}
function test($json){
	echo '<pre>';
	print_r($json);
	exit('</pre>');
}
		?>

