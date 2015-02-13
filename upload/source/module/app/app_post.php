<?php

// ----------------------------------------------------------------------------
//
//  [Huoyue.org!] (C)2013-2099 Huoyue.org Email:4767960@qq.com QQ:4767960.
//  File:   app_post.php
//  Creation Date:  2013-08-14 18:17:51
//
// ----------------------------------------------------------------------------


if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

define('NOROBOT', TRUE);
$dos = array('newthread', 'edit', 'reply','topicadmin');

$do = (!empty($_GET['do']) && in_array($_GET['do'], $dos))?$_GET['do']:'newthread';
$ckuser = $_G['member'];

if($_G['setting']['newbiespan'] && $_G['timestamp']-$ckuser['regdate']<$_G['setting']['newbiespan']*60) {
	error_json('no_privilege_newbiespan', '', array('newbiespan' => $_G['setting']['newbiespan']), array());
}
if($_G['setting']['need_avatar'] && empty($ckuser['avatarstatus'])) {
	error_json('no_privilege_avatar', '', array(), array());
}
if($_G['setting']['need_email'] && empty($ckuser['emailstatus'])) {
	error_json('no_privilege_email', '', array(), array());
}
if($_G['setting']['need_friendnum']) {
	space_merge($ckuser, 'count');
	if($ckuser['friends'] < $_G['setting']['need_friendnum']) {
		error_json('no_privilege_friendnum', '', array('friendnum' => $_G['setting']['need_friendnum']), array());
	}
}
unset($ckuser);
require_once libfile('class/credit');
require_once libfile('function/post');


$pid = intval(getgpc('pid'));
$sortid = intval(getgpc('sortid'));
$typeid = intval(getgpc('typeid'));
$special = intval(getgpc('special'));

parse_str($_GET['extra'], $_GET['extra']);
$_GET['extra'] = http_build_query($_GET['extra']);

$postinfo = array('subject' => '');
$thread = array('readperm' => '', 'pricedisplay' => '', 'hiddenreplies' => '');

$_G['forum_dtype'] = $_G['forum_checkoption'] = $_G['forum_optionlist'] = $tagarray = $_G['forum_typetemplate'] = array();



if($sortid) {
	require_once libfile('post/threadsorts', 'include');
}

if($_G['forum']['status'] == 3) {
	if(!helper_access::check_module('group')) {
		error_json('group_status_off');
	}
	require_once libfile('function/group');
	$status = groupperm($_G['forum'], $_G['uid'], 'post');
	if($status == -1) {
		error_json('forum_not_group', 'index.php');
	} elseif($status == 1) {
		error_json('forum_group_status_off');
	} elseif($status == 2) {
		error_json('forum_group_noallowed', "forum.php?mod=group&fid=$_G[fid]");
	} elseif($status == 3) {
		error_json('forum_group_moderated');
	} elseif($status == 4) {
		if($_G['uid']) {
			error_json('forum_group_not_groupmember', "", array('fid' => $_G['fid']), array('showmsg' => 1));
		} else {
			error_json('forum_group_not_groupmember_guest', "", array('fid' => $_G['fid']), array('showmsg' => 1, 'login' => 1));
		}
	} elseif($status == 5) {
		error_json('forum_group_moderated', "", array('fid' => $_G['fid']), array('showmsg' => 1));
	}
}

if(empty($_GET['do'])) {
	$_GET['do'] = 'newthread';
} elseif($_GET['do'] == 'albumphoto') {
	require libfile('post/albumphoto', 'include');
} elseif(($_G['forum']['simple'] & 1) || $_G['forum']['redirect']) {
	error_json('forum_disablepost');
}

if($_G['forum']['viewperm'] && !forumperm($_G['forum']['viewperm']) && !$_G['forum']['allowview']) {
	error_json('viewperm_none_nopermission');
} elseif($_G['forum']['formulaperm']) {
	formulaperm1($_G['forum']['formulaperm']);
}

if($_G['forum']['password']&&($_GET['pw'] != $_G['forum']['password'])) {
	error_json('forum_passwd_incorrect');
}
require_once libfile('function/discuzcode');

$space = array();
space_merge($space, 'field_home');

if($_GET['do'] == 'edit' || $_GET['do'] == 'reply') {
	$thread = C::t('forum_thread')->fetch($_G['tid']);
	if(!$_G['forum_auditstatuson'] && !($thread['displayorder']>=0 || (in_array($thread['displayorder'], array(-4, -2)) && $thread['authorid']==$_G['uid']))) {
		$thread = array();
	}
	if(!empty($thread)) {

		if($thread['readperm'] && $thread['readperm'] > $_G['group']['readaccess'] && !$_G['forum']['ismoderator'] && $thread['authorid'] != $_G['uid']) {
			error_json('thread_nopermission', NULL, array('readperm' => $thread['readperm']), array('login' => 1));
		}

		$_G['fid'] = $thread['fid'];
		$special = $thread['special'];

	} else {
		error_json('thread_nonexistence');
	}

	if($thread['closed'] == 1 && !$_G['forum']['ismoderator']) {
		error_json('post_thread_closed');
	}
}

$extra = !empty($_GET['extra']) ? rawurlencode($_GET['extra']) : '';

$subject = isset($_GET['subject']) ? dhtmlspecialchars(censor(get_url_word($_GET['subject']))) : '';
$subject = !empty($subject) ? str_replace("\t", ' ', $subject) : $subject;
$message = isset($_GET['message']) ? censor(get_url_word($_GET['message'])) : '';
$polloptions = isset($polloptions) ? censor(trim($polloptions)) : '';
$readperm = isset($_GET['readperm']) ? intval($_GET['readperm']) : 0;
$price = isset($_GET['price']) ? intval($_GET['price']) : 0;
$specialextra = !empty($_GET['specialextra']) ? $_GET['specialextra'] : '';
if(empty($message)){
	error_json('post_sm_isnull');
}
$editorid = 'e';
$_G['setting']['editoroptions'] = str_pad(decbin($_G['setting']['editoroptions']), 3, 0, STR_PAD_LEFT);
$editormode = $_G['setting']['editoroptions']{0};
$allowswitcheditor = $_G['setting']['editoroptions']{1};
$editor = array(
'editormode' => $editormode,
'allowswitcheditor' => $allowswitcheditor,
'allowhtml' => $_G['forum']['allowhtml'],
'allowsmilies' => $_G['forum']['allowsmilies'],
'allowbbcode' => $_G['forum']['allowbbcode'],
'allowimgcode' => $_G['forum']['allowimgcode'],
'allowresize' => 1,
'allowchecklength' => 1,
'allowtopicreset' => 1,
'textarea' => 'message',
'simplemode' => !isset($_G['cookie']['editormode_'.$editorid]) ? !$_G['setting']['editoroptions']{2} : $_G['cookie']['editormode_'.$editorid],
);
if($specialextra) {
	$special = 127;
}
if(!$_G['group']['disablepostctrl'] && !$special) {
	if($_G['setting']['maxpostsize'] && strlen($message) > $_G['setting']['maxpostsize']) {
		error_json('post_message_toolong');
	} elseif($_G['setting']['minpostsize']) {
		$minpostsize = !$_G['setting']['minpostsize_mobile'] ? $_G['setting']['minpostsize'] : $_G['setting']['minpostsize_mobile'];
		if(strlen(preg_replace("/\[quote\].+?\[\/quote\]/is", '', $message)) < $minpostsize || strlen(preg_replace("/\[postbg\].+?\[\/postbg\]/is", '', $message)) < $minpostsize) {
			error_json('post_message_tooshort');
		}
	}
}
if($_GET['do'] == 'newthread') {
	if(empty($subject)){
		error_json('post_sm_isnull');
	}
	if(dstrlen($subject) > 80) {
		error_json('post_subject_toolong');
	}
	$policykey = 'post';
} elseif($_GET['do'] == 'reply') {
	$policykey = 'reply';
} else {
	$policykey = '';
}
if($policykey) {
	$postcredits = $_G['forum'][$policykey.'credits'] ? $_G['forum'][$policykey.'credits'] : $_G['setting']['creditspolicy'][$policykey];
}
$_GET['ajaxdata'] = 'json';
if($_GET['do'] == 'reply') {
	check_allow_action('allowreply');
} else {
	check_allow_action('allowpost');
}
if($special == 4) {
	$_G['setting']['activityfield'] = $_G['setting']['activityfield'] ? dunserialize($_G['setting']['activityfield']) : array();
}
if($_FILES){
	require DISCUZ_ROOT.'./source/module/app/app_upload.php';
}
if($_GET['do'] == 'newthread' || $_GET['do'] == 'newtrade') {
	loadcache('groupreadaccess');
	if(empty($_G['forum']['fid']) || $_G['forum']['type'] == 'group') {
		error_json('forum_nonexistence');
	}

	if(($special == 1 && !$_G['group']['allowpostpoll']) || ($special == 2 && !$_G['group']['allowposttrade']) || ($special == 3 && !$_G['group']['allowpostreward']) || ($special == 4 && !$_G['group']['allowpostactivity']) || ($special == 5 && !$_G['group']['allowpostdebate'])) {
		error_json('group_nopermission', NULL, array('grouptitle' => $_G['group']['grouptitle']), array('login' => 1));
	}

	if(!$_G['uid'] && !((!$_G['forum']['postperm'] && $_G['group']['allowpost']) || ($_G['forum']['postperm'] && forumperm($_G['forum']['postperm'])))) {
		if(!defined('IN_MOBILE')) {
			error_json('postperm_login_nopermission', NULL, array(), array('login' => 1));
		} else {
			error_json('postperm_login_nopermission_mobile', NULL, array('referer' => rawurlencode(dreferer())), array('login' => 1));
		}
	} elseif(empty($_G['forum']['allowpost'])) {
		if(!$_G['forum']['postperm'] && !$_G['group']['allowpost']) {
			error_json('postperm_none_nopermission', NULL, array(), array('login' => 1));
		} elseif($_G['forum']['postperm'] && !forumperm($_G['forum']['postperm'])) {
			jsonnoperm('postperm', $_G['fid'], $_G['forum']['formulaperm']);
		}
	} elseif($_G['forum']['allowpost'] == -1) {
		error_json('post_forum_newthread_nopermission', NULL);
	}

	if(!$_G['uid'] && ($_G['setting']['need_avatar'] || $_G['setting']['need_email'] || $_G['setting']['need_friendnum'])) {
		error_json('postperm_login_nopermission', NULL, array(), array('login' => 1));
	}
	checklowerlimit('post', 0, 1, $_G['forum']['fid']);


	if($_GET['mygroupid']) {
		$mygroupid = explode('__', $_GET['mygroupid']);
		$mygid = intval($mygroupid[0]);
		if($mygid) {
			$mygname = $mygroupid[1];
			if(count($mygroupid) > 2) {
				unset($mygroupid[0]);
				$mygname = implode('__', $mygroupid);
			}
			$message .= '[groupid='.intval($mygid).']'.$mygname.'[/groupid]';
			C::t('forum_forum')->update_commoncredits(intval($mygroupid[0]));
		}
	}
	$modthread = C::m('forum_thread');
	$bfmethods = $afmethods = array();

	$params = array(
	'subject' => $subject,
	'message' => $message,
	'typeid' => $typeid,
	'sortid' => $sortid,
	'special' => $special,
	);

	$_GET['save'] = $_G['uid'] ? $_GET['save'] : 0;

	if ($_G['group']['allowsetpublishdate'] && $_GET['cronpublish'] && $_GET['cronpublishdate']) {
		$publishdate = strtotime($_GET['cronpublishdate']);
		if ($publishdate > $_G['timestamp']) {
			$_GET['save'] = 1;
		} else {
			$publishdate = $_G['timestamp'];
		}
	} else {
		$publishdate = $_G['timestamp'];
	}
	$params['publishdate'] = $publishdate;
	$params['save'] = $_GET['save'];

	$params['sticktopic'] = $_GET['sticktopic'];

	$params['digest'] = $_GET['addtodigest'];
	$params['readperm'] = $readperm;
	$params['isanonymous'] = $_GET['isanonymous'];
	$params['price'] = $_GET['price'];


	if(in_array($special, array(1, 2, 3, 4, 5))) {
		$specials = array(
		1 => 'extend_thread_poll',
		2 => 'extend_thread_trade',
		3 => 'extend_thread_reward',
		4 => 'extend_thread_activity',
		5 => 'extend_thread_debate'
		);
		$bfmethods[] = array('class' => $specials[$special], 'method' => 'before_newthread');
		$afmethods[] = array('class' => $specials[$special], 'method' => 'after_newthread');

		if(!empty($_GET['addfeed'])) {
			$modthread->attach_before_method('feed', array('class' => $specials[$special], 'method' => 'before_feed'));
			if($special == 2) {
				$modthread->attach_before_method('feed', array('class' => $specials[$special], 'method' => 'before_replyfeed'));
			}
		}
	}

	if($special == 1) {


	} elseif($special == 3) {


	} elseif($special == 4) {
	} elseif($special == 5) {


	} elseif($specialextra) {

		@include_once DISCUZ_ROOT.'./source/plugin/'.$_G['setting']['threadplugins'][$specialextra]['module'].'.class.php';
		$classname = 'threadplugin_'.$specialextra;
		if(class_exists($classname) && method_exists($threadpluginclass = new $classname, 'newthread_submit')) {
			$threadpluginclass->newthread_submit($_G['fid']);
		}
		$special = 127;
		$params['special'] = 127;
		$params['message'] .= chr(0).chr(0).chr(0).$specialextra;

	}

	$params['typeexpiration'] = $_GET['typeexpiration'];

	$params['ordertype'] = $_GET['ordertype'];

	$params['hiddenreplies'] = $_GET['hiddenreplies'];

	$params['allownoticeauthor'] = $_GET['allownoticeauthor'];
	$params['tags'] = get_url_word($_GET['tags']);
	$params['bbcodeoff'] = $_GET['bbcodeoff'];
	$params['smileyoff'] = $_GET['smileyoff'];
	$params['parseurloff'] = $_GET['parseurloff'];
	$params['usesig'] = $_GET['usesig'];
	$params['htmlon'] = $_GET['htmlon'];
	if($_G['group']['allowimgcontent']) {
		$params['imgcontent'] = $_GET['imgcontent'];
		$params['imgcontentwidth'] = $_G['setting']['imgcontentwidth'] ? intval($_G['setting']['imgcontentwidth']) : 100;
	}

	$params['geoloc'] = diconv($_GET['geoloc'], 'UTF-8');

	if($_GET['rushreply']) {
		$bfmethods[] = array('class' => 'extend_thread_rushreply', 'method' => 'before_newthread');
		$afmethods[] = array('class' => 'extend_thread_rushreply', 'method' => 'after_newthread');
	}

	$bfmethods[] = array('class' => 'extend_thread_replycredit', 'method' => 'before_newthread');
	$afmethods[] = array('class' => 'extend_thread_replycredit', 'method' => 'after_newthread');

	if($sortid) {
		$bfmethods[] = array('class' => 'extend_thread_sort', 'method' => 'before_newthread');
		$afmethods[] = array('class' => 'extend_thread_sort', 'method' => 'after_newthread');
	}
	$bfmethods[] = array('class' => 'extend_thread_allowat', 'method' => 'before_newthread');
	$afmethods[] = array('class' => 'extend_thread_allowat', 'method' => 'after_newthread');
	$afmethods[] = array('class' => 'extend_thread_image', 'method' => 'after_newthread');

	if(!empty($_GET['adddynamic'])) {
		$afmethods[] = array('class' => 'extend_thread_follow', 'method' => 'after_newthread');
	}

	$modthread->attach_before_methods('newthread', $bfmethods);
	$modthread->attach_after_methods('newthread', $afmethods);
	$return = $modthread->newthread($params);
//	$data=$modthread->param;


	dsetcookie('clearUserdata', 'forum');
	if($specialextra) {
		$classname = 'threadplugin_'.$specialextra;
		if(class_exists($classname) && method_exists($threadpluginclass = new $classname, 'newthread_submit_end')) {
			$threadpluginclass->newthread_submit_end($_G['fid'], $modthread->tid);
		}
	}
	if(!$modthread->param('modnewthreads') && !empty($_GET['addfeed'])) {
		$modthread->feed();
	}
	$data['fid']=$fid = $_G['fid'];
	$data['tid']=$tid = $modthread->tid;
	$data['pid']=$pid = $modthread->pid;
	$data['pw']=$_GET['pw'];

	$json['data']=$data;

} elseif($_GET['do'] == 'reply') {
	require_once libfile('function/forumlist');
	$isfirstpost = 0;
	$_G['group']['allowimgcontent'] = 0;
	$showthreadsorts = 0;
	$quotemessage = '';

	if($special == 5) {
		$debate = array_merge($thread, daddslashes(C::t('forum_debate')->fetch($_G['tid'])));
		$firststand = C::t('forum_debatepost')->get_firststand($_G['tid'], $_G['uid']);
		$stand = $firststand ? $firststand : intval($_GET['stand']);

		if($debate['endtime'] && $debate['endtime'] < TIMESTAMP) {
			error_json('debate_end');
		}
	}

	if(!$_G['uid'] && !((!$_G['forum']['replyperm'] && $_G['group']['allowreply']) || ($_G['forum']['replyperm'] && forumperm($_G['forum']['replyperm'])))) {
		error_json('replyperm_login_nopermission', NULL, array(), array('login' => 1));
	} elseif(empty($_G['forum']['allowreply'])) {
		if(!$_G['forum']['replyperm'] && !$_G['group']['allowreply']) {
			error_json('replyperm_none_nopermission', NULL, array(), array('login' => 1));
		} elseif($_G['forum']['replyperm'] && !forumperm($_G['forum']['replyperm'])) {
			jsonnoperm('replyperm', $_G['forum']['fid']);
		}
	} elseif($_G['forum']['allowreply'] == -1) {
		error_json('post_forum_newreply_nopermission', NULL);
	}

	if(!$_G['uid'] && ($_G['setting']['need_avatar'] || $_G['setting']['need_email'] || $_G['setting']['need_friendnum'])) {
		error_json('replyperm_login_nopermission', NULL, array(), array('login' => 1));
	}

	if(empty($thread)) {
		error_json('thread_nonexistence');
	} elseif($thread['price'] > 0 && $thread['special'] == 0 && !$_G['uid']) {
		error_json('group_nopermission', NULL, array('grouptitle' => $_G['group']['grouptitle']), array('login' => 1));
	}

	checklowerlimit('reply', 0, 1, $_G['forum']['fid']);

	if($_G['setting']['commentnumber'] && !empty($_GET['comment'])) {
		$post = C::t('forum_post')->fetch('tid:'.$_G['tid'], $_GET['pid']);
		if(!$post) {
			error_json('post_nonexistence', NULL);
		}
		if($thread['closed'] && !$_G['forum']['ismoderator'] && !$thread['isgroup']) {
			error_json('post_thread_closed');
		} elseif(!$thread['isgroup'] && $post_autoclose = checkautoclose($thread)) {
			error_json($post_autoclose, '', array('autoclose' => $_G['forum']['autoclose']));
		} elseif(checkflood()) {
			error_json('post_flood_ctrl', '', array('floodctrl' => $_G['setting']['floodctrl']));
		} elseif(checkmaxperhour('pid')) {
			error_json('post_flood_ctrl_posts_per_hour', '', array('posts_per_hour' => $_G['group']['maxpostsperhour']));
		}
		$commentscore = '';
		if(!empty($_GET['commentitem']) && !empty($_G['uid']) && $post['authorid'] != $_G['uid']) {
			foreach($_GET['commentitem'] as $itemk => $itemv) {
				if($itemv !== '') {
					$commentscore .= strip_tags(trim($itemk)).': <i>'.intval($itemv).'</i> ';
				}
			}
		}
		$comment = cutstr(($commentscore ? $commentscore.'<br />' : '').censor(trim(dhtmlspecialchars($_GET['message'])), '***'), 200, ' ');
		if(!$comment) {
			error_json('post_sm_isnull');
		}
		$data['id']=C::t('forum_postcomment')->insert(array(
		'tid' => $post['tid'],
		'pid' => $post['pid'],
		'author' => $_G['username'],
		'authorid' => $_G['uid'],
		'dateline' => TIMESTAMP,
		'comment' => $comment,
		'score' => $commentscore ? 1 : 0,
		'useip' => $_G['clientip'],
		),true);
		C::t('forum_post')->update('tid:'.$_G['tid'], $post['pid'], array('comment' => 1));
		$comments = $thread['comments'] ? $thread['comments'] + 1 : C::t('forum_postcomment')->count_by_tid($_G['tid']);
		C::t('forum_thread')->update($_G['tid'], array('comments' => $comments));
		!empty($_G['uid']) && updatepostcredits('+', $_G['uid'], 'reply', $_G['fid']);
		if(!empty($_G['uid']) && $_G['uid'] != $post['authorid']) {
			notification_add($post['authorid'], 'pcomment', 'comment_add', array(
			'tid' => $_G['tid'],
			'pid' => $post['pid'],
			'subject' => $thread['subject'],
			'from_id' => $_G['tid'],
			'from_idtype' => 'pcomment',
			'commentmsg' => cutstr(str_replace(array('[b]', '[/b]', '[/color]'), '', preg_replace("/\[color=([#\w]+?)\]/i", "", $comment)), 200)
			));
		}
		update_threadpartake($post['tid']);
		$pcid = C::t('forum_postcomment')->fetch_standpoint_by_pid($_GET['pid']);
		$pcid = $pcid['id'];
		if(!empty($_G['uid']) && $_GET['commentitem']) {
			$totalcomment = array();
			foreach(C::t('forum_postcomment')->fetch_all_by_pid_score($_GET['pid'], 1) as $comment) {
				$comment['comment'] = addslashes($comment['comment']);
				if(strexists($comment['comment'], '<br />')) {
					if(preg_match_all("/([^:]+?):\s<i>(\d+)<\/i>/", $comment['comment'], $a)) {
						foreach($a[1] as $k => $itemk) {
							$totalcomment[trim($itemk)][] = $a[2][$k];
						}
					}
				}
			}
			$totalv = '';
			foreach($totalcomment as $itemk => $itemv) {
				$totalv .= strip_tags(trim($itemk)).': <i>'.(floatval(sprintf('%1.1f', array_sum($itemv) / count($itemv)))).'</i> ';
			}

			if($pcid) {
				C::t('forum_postcomment')->update($pcid, array('comment' => $totalv, 'dateline' => TIMESTAMP + 1));
			} else {
				C::t('forum_postcomment')->insert(array(
				'tid' => $post['tid'],
				'pid' => $post['pid'],
				'author' => '',
				'authorid' => '-1',
				'dateline' => TIMESTAMP + 1,
				'comment' => $totalv
				));
			}
		}
		C::t('forum_postcache')->delete($post['pid']);
		$json['data']['tid']=$_G['tid'];
		$json['data']['pid']=$post['pid'];
		$json['data']['pw']=$_GET['pw'];
		json_echo($json);	
	}

	if($special == 127) {
		$postinfo = C::t('forum_post')->fetch_threadpost_by_tid_invisible($_G['tid']);
		$sppos = strrpos($postinfo['message'], chr(0).chr(0).chr(0));
		$specialextra = substr($postinfo['message'], $sppos + 3);
	}
	if(getstatus($thread['status'], 3)) {
		$rushinfo = C::t('forum_threadrush')->fetch($_G['tid']);
		if($rushinfo['creditlimit'] != -996) {
			$checkcreditsvalue = $_G['setting']['creditstransextra'][11] ? getuserprofile('extcredits'.$_G['setting']['creditstransextra'][11]) : $_G['member']['credits'];
			if($checkcreditsvalue < $rushinfo['creditlimit']) {
				$creditlimit_title = $_G['setting']['creditstransextra'][11] ? $_G['setting']['extcredits'][$_G['setting']['creditstransextra'][11]]['title'] : lang('forum/misc', 'credit_total');
				error_json('post_rushreply_creditlimit', '', array('creditlimit_title' => $creditlimit_title, 'creditlimit' => $rushinfo['creditlimit']));
			}
		}

	}

	$modpost = C::m('forum_post', $_G['tid']);
	$bfmethods = $afmethods = array();


	$params = array(
	'subject' => $subject,
	'message' => $message,
	'special' => $special,
	'extramessage' => $extramessage,
	'bbcodeoff' => $_GET['bbcodeoff'],
	'smileyoff' => $_GET['smileyoff'],
	'htmlon' => $_GET['htmlon'],
	'parseurloff' => $_GET['parseurloff'],
	'usesig' => $_GET['usesig'],
	'isanonymous' => $_GET['isanonymous'],
	'noticetrimstr' => $_GET['noticetrimstr'],
	'noticeauthor' => $_GET['noticeauthor'],
	'from' => $_GET['from'],
	'sechash' => $_GET['sechash'],
	'geoloc' => diconv($_GET['geoloc'], 'UTF-8'),
	);


	if(!empty($_GET['trade']) && $thread['special'] == 2 && $_G['group']['allowposttrade']) {
		$bfmethods[] = array('class' => 'extend_thread_trade', 'method' => 'before_newreply');
	}
	$attentionon = empty($_GET['attention_add']) ? 0 : 1;
	$attentionoff = empty($attention_remove) ? 0 : 1;
	$bfmethods[] = array('class' => 'extend_thread_rushreply', 'method' => 'before_newreply');
	if($_G['group']['allowat']) {
		$bfmethods[] = array('class' => 'extend_thread_allowat', 'method' => 'before_newreply');
	}
	$bfmethods[] = array('class' => 'extend_thread_comment', 'method' => 'before_newreply');
	$modpost->attach_before_method('newreply', array('class' => 'extend_thread_filter', 'method' => 'before_newreply'));
	if($_G['group']['allowat']) {
		$afmethods[] = array('class' => 'extend_thread_allowat', 'method' => 'after_newreply');
	}
	$afmethods[] = array('class' => 'extend_thread_rushreply', 'method' => 'after_newreply');
	$afmethods[] = array('class' => 'extend_thread_comment', 'method' => 'after_newreply');
	if(helper_access::check_module('follow') && !empty($_GET['adddynamic'])) {
		$afmethods[] = array('class' => 'extend_thread_follow', 'method' => 'after_newreply');
	}
	if($thread['replycredit'] > 0 && $thread['authorid'] != $_G['uid'] && $_G['uid']) {
		$afmethods[] = array('class' => 'extend_thread_replycredit', 'method' => 'after_newreply');
	}
	if($special == 5) {
		$afmethods[] = array('class' => 'extend_thread_debate', 'method' => 'after_newreply');
	}
	$afmethods[] = array('class' => 'extend_thread_image', 'method' => 'after_newreply');
	if($special == 2 && $_G['group']['allowposttrade'] && $thread['authorid'] == $_G['uid']) {
		$afmethods[] = array('class' => 'extend_thread_trade', 'method' => 'after_newreply');
	}
	$afmethods[] = array('class' => 'extend_thread_filter', 'method' => 'after_newreply');
	if($_G['forum']['allowfeed']) {
		if($special == 2 && !empty($_GET['trade'])) {
			$modpost->attach_before_method('replyfeed', array('class' => 'extend_thread_trade', 'method' => 'before_replyfeed'));
			$modpost->attach_after_method('replyfeed', array('class' => 'extend_thread_trade', 'method' => 'after_replyfeed'));
		} elseif($special == 3 && $thread['authorid'] != $_G['uid']) {
			$modpost->attach_before_method('replyfeed', array('class' => 'extend_thread_reward', 'method' => 'before_replyfeed'));
		} elseif($special == 5 && $thread['authorid'] != $_G['uid']) {
			$modpost->attach_before_method('replyfeed', array('class' => 'extend_thread_debate', 'method' => 'before_replyfeed'));
		}
	}




	if(!isset($_GET['addfeed'])) {
		$space = array();
		space_merge($space, 'field_home');
		$_GET['addfeed'] = $space['privacy']['feed']['newreply'];
	}

	$modpost->attach_before_methods('newreply', $bfmethods);
	$modpost->attach_after_methods('newreply', $afmethods);
	$_GET['inajax']=1;
	$return = $modpost->newreply($params);
//	$data=$modpost->param;
//	$data['pid']=$pid = $modpost->pid;
//	$data['tid']=$_G['tid'];

	if($specialextra) {

		@include_once DISCUZ_ROOT.'./source/plugin/'.$_G['setting']['threadplugins'][$specialextra]['module'].'.class.php';
		$classname = 'threadplugin_'.$specialextra;
		if(class_exists($classname) && method_exists($threadpluginclass = new $classname, 'newreply_submit_end')) {
			$threadpluginclass->newreply_submit_end($_G['fid'], $_G['tid']);
		}

	}
	if($modpost->pid && !$modpost->param('modnewreplies')) {

		if(!empty($_GET['addfeed'])) {
			$modpost->replyfeed();
		}
	}
		$json['data']['tid']=$_G['tid'];
		$json['data']['pid']=$pid = $modpost->pid;
		$json['data']['pw']=$_GET['pw'];
		json_echo($json);	
	$json['data']=$data;

} elseif($_GET['do'] == 'edit') {
	loadcache('groupreadaccess');
	$orig = get_post_by_tid_pid($_G['tid'], $pid);
	$post=get_post_by_pid($pid);
	if($special == 3) {
		$_GET['rewardprice']=$thread['price'];
		$_GET['price']=$thread['price'];
	}
	
	$isfirstpost = $orig['first'] ? 1 : 0;
	$sortid =  isset($_GET['sortid']) ? $sortid : $thread['sortid'];
	$typeid =  isset($_GET['typeid']) ? $typeid : $thread['typeid'];
	$subject = isset($_GET['subject']) ? $subject : $post['subject'];
	$message = isset($_GET['message']) ? $message : $post['message'];
	if($isfirstpost && (($special == 1 && !$_G['group']['allowpostpoll']) || ($special == 2 && !$_G['group']['allowposttrade']) || ($special == 3 && !$_G['group']['allowpostreward']) || ($special == 4 && !$_G['group']['allowpostactivity']) || ($special == 5 && !$_G['group']['allowpostdebate']))) {
		error_json('group_nopermission', NULL, array('grouptitle' => $_G['group']['grouptitle']), array('login' => 1));
	}

	if($_G['setting']['magicstatus']) {
		$magiclog = C::t('forum_threadmod')->fetch_by_tid_magicid($_G['tid'], 10);
		$magicid = $magiclog['magicid'];
		$_G['group']['allowanonymous'] = $_G['group']['allowanonymous'] || $magicid ? 1 : $_G['group']['allowanonymous'];
	}

	$isorigauthor = $_G['uid'] && $_G['uid'] == $orig['authorid'];
	$isanonymous = ($_G['group']['allowanonymous'] || $orig['anonymous']) && getgpc('isanonymous') ? 1 : 0;
	$audit = $orig['invisible'] == -2 || $thread['displayorder'] == -2 ? $_GET['audit'] : 0;

	if(empty($orig)) {
		error_json('post_nonexistence');
	} elseif((!$_G['forum']['ismoderator'] || !$_G['group']['alloweditpost'] || (in_array($orig['adminid'], array(1, 2, 3)) && $_G['adminid'] > $orig['adminid'])) && !(($_G['forum']['alloweditpost'] || $orig['invisible'] == -3)&& $isorigauthor)) {
		error_json('post_edit_nopermission', NULL);
	} elseif($isorigauthor && !$_G['forum']['ismoderator'] && $orig['invisible'] != -3) {
		$alloweditpost_status = getstatus($_G['setting']['alloweditpost'], $special + 1);
		if(!$alloweditpost_status && $_G['group']['edittimelimit'] && TIMESTAMP - $orig['dateline'] > $_G['group']['edittimelimit'] * 60) {
			error_json('post_edit_timelimit', NULL, array('edittimelimit' => $_G['group']['edittimelimit']));
		}
	}

	$thread['pricedisplay'] = $thread['price'] == -1 ? 0 : $thread['price'];

	if($special == 5) {
		$debate = array_merge($thread, daddslashes(C::t('forum_debate')->fetch($_G['tid'])));
		$firststand = C::t('forum_debatepost')->get_firststand($_G['tid'], $_G['uid']);

		if(!$isfirstpost && $debate['endtime'] && $debate['endtime'] < TIMESTAMP && !$_G['forum']['ismoderator']) {
			error_json('debate_end');
		}
		if($isfirstpost && $debate['umpirepoint'] && !$_G['forum']['ismoderator']) {
			error_json('debate_umpire_comment_invalid');
		}
	}

	$rushreply = getstatus($thread['status'], 3);


	if($isfirstpost && $isorigauthor && $_G['group']['allowreplycredit']) {
		if($replycredit_rule = C::t('forum_replycredit')->fetch($_G['tid'])) {
			if($thread['replycredit']) {
				$replycredit_rule['lasttimes'] = $thread['replycredit'] / $replycredit_rule['extcredits'];
			}
			$replycredit_rule['extcreditstype'] = $replycredit_rule['extcreditstype'] ? $replycredit_rule['extcreditstype'] : $_G['setting']['creditstransextra'][10];
		}
	}
	if($_GET['mygroupid']) {
		$mygroupid = explode('__', $_GET['mygroupid']);
		$mygid = intval($mygroupid[0]);
		if($mygid) {
			$mygname = $mygroupid[1];
			if(count($mygroupid) > 2) {
				unset($mygroupid[0]);
				$mygname = implode('__', $mygroupid);
			}
			$message .= '[groupid='.intval($mygid).']'.$mygname.'[/groupid]';
		}
	}
	$modpost = C::m('forum_post', $_G['tid'], $pid);

	$modpost->param('redirecturl', "forum.php?mod=viewthread&tid=$_G[tid]&page=$_GET[page]&extra=$extra".($vid && $isfirstpost ? "&vid=$vid" : '')."#pid$pid");

	if(empty($_GET['delete'])) {

		$feed = array();
		if($special == 127) {
			$message .= chr(0).chr(0).chr(0).$specialextra;
		}
		if($isfirstpost) {
			$modpost->attach_before_method('editpost', array('class' => 'extend_thread_sort', 'method' => 'before_editpost'));
			if($thread['special'] == 3) {
				$modpost->attach_before_method('editpost', array('class' => 'extend_thread_reward', 'method' => 'before_editpost'));
			}
			if($thread['special'] == 1) {
				$modpost->attach_before_method('editpost', array('class' => 'extend_thread_poll', 'method' => 'before_editpost'));
			}
			if($thread['special'] == 4 && $_G['group']['allowpostactivity']) {
				$modpost->attach_before_method('editpost', array('class' => 'extend_thread_activity', 'method' => 'before_editpost'));
			}
			if($thread['special'] == 5 && $_G['group']['allowpostdebate']) {
				$modpost->attach_before_method('editpost', array('class' => 'extend_thread_debate', 'method' => 'before_editpost'));
			}
			if($_G['group']['allowreplycredit']) {
				$modpost->attach_before_method('editpost', array('class' => 'extend_thread_replycredit', 'method' => 'before_editpost'));
			}
			if($rushreply) {
				$modpost->attach_before_method('editpost', array('class' => 'extend_thread_rushreply', 'method' => 'before_editpost'));
			}
			$modpost->attach_after_method('editpost', array('class' => 'extend_thread_follow', 'method' => 'after_editpost'));
		}

		if($_G['group']['allowat']) {
			$modpost->attach_before_method('editpost', array('class' => 'extend_thread_allowat', 'method' => 'before_editpost'));
			$modpost->attach_after_method('editpost', array('class' => 'extend_thread_allowat', 'method' => 'after_editpost'));
		}

		$modpost->attach_before_method('editpost', array('class' => 'extend_thread_image', 'method' => 'before_editpost'));

		if($special == '2' && $_G['group']['allowposttrade']) {
			$modpost->attach_before_method('editpost', array('class' => 'extend_thread_trade', 'method' => 'before_editpost'));
		}

		$modpost->attach_before_method('editpost', array('class' => 'extend_thread_filter', 'method' => 'before_editpost'));
		$modpost->attach_after_method('editpost', array('class' => 'extend_thread_filter', 'method' => 'after_editpost'));

		$json['data']=$param = array(
		'subject' => $subject,
		'message' => $message,
		'special' => $special,
		'sortid' => $sortid,
		'typeid' => $typeid,
		'isanonymous' => $isanonymous,

		'cronpublish' => $_GET['cronpublish'],
		'cronpublishdate' => $_GET['cronpublishdate'],
		'save' => $_GET['save'],

		'readperm' => $readperm,
		'price' => $_GET['price'],

		'ordertype' => $_GET['ordertype'],
		'hiddenreplies' => $_GET['hiddenreplies'],
		'allownoticeauthor' => $_GET['allownoticeauthor'],

		'audit' => $_GET['audit'],

		'tags' => $_GET['tags'],

		'bbcodeoff' => $_GET['bbcodeoff'],
		'smileyoff' => $_GET['smileyoff'],
		'parseurloff' => $_GET['parseurloff'],
		'usesig' => $_GET['usesig'],
		'htmlon' => $_GET['htmlon'],

		'extramessage' => $extramessage,
		);

		if($_G['group']['allowimgcontent']) {
			$param['imgcontent'] = $_GET['imgcontent'];
			$param['imgcontentwidth'] = $_G['setting']['imgcontentwidth'] ? intval($_G['setting']['imgcontentwidth']) : 100;
		}
		if($isfirstpost && $isorigauthor && $_G['group']['allowreplycredit']) {
			$param['replycredit_rule'] = $replycredit_rule;
		}
		$modpost->editpost($param);
		$json['data']['tid']=$_G['tid'];
		$json['data']['pid']=$pid;

	} else {
		if($thread['special'] == 3) {
			$modpost->attach_before_method('deletepost', array('class' => 'extend_thread_reward', 'method' => 'before_deletepost'));
		}
		if($rushreply) {
			$modpost->attach_before_method('deletepost', array('class' => 'extend_thread_rushreply', 'method' => 'before_deletepost'));
		}
		if($thread['replycredit'] && $isfirstpost) {
			$modpost->attach_before_method('deletepost', array('class' => 'extend_thread_replycredit', 'method' => 'before_deletepost'));
		}

		$modpost->attach_before_method('deletepost', array('class' => 'extend_thread_image', 'method' => 'before_deletepost'));

		if($thread['special'] == 2) {
			$modpost->attach_after_method('deletepost', array('class' => 'extend_thread_trade', 'method' => 'after_deletepost'));
		}
		if($isfirstpost) {
			$modpost->attach_after_method('deletepost', array('class' => 'extend_thread_sort', 'method' => 'after_deletepost'));
		}

		$modpost->attach_after_method('deletepost', array('class' => 'extend_thread_filter', 'method' => 'after_deletepost'));

		$param = array(
		'special' => $special,
		'isanonymous' => $isanonymous,
		);

		$modpost->deletepost($param);
	}

	if($specialextra) {

		@include_once DISCUZ_ROOT.'./source/plugin/'.$_G['setting']['threadplugins'][$specialextra]['module'].'.class.php';
		$classname = 'threadplugin_'.$specialextra;
		if(class_exists($classname) && method_exists($threadpluginclass = new $classname, 'editpost_submit_end')) {
			$threadpluginclass->editpost_submit_end($_G['fid'], $_G['tid']);
		}

	}

	if($_G['forum']['threadcaches']) {
		deletethreadcaches($_G['tid']);
	}

	$param = array('fid' => $_G['fid'], 'tid' => $_G['tid'], 'pid' => $pid);

	dsetcookie('clearUserdata', 'forum');

	if($_G['forum_auditstatuson']) {
		if($audit == 1) {
			updatemoderate($isfirstpost ? 'tid' : 'pid', $isfirstpost ? $_G['tid'] : $pid, '2');
		} else {
			updatemoderate($isfirstpost ? 'tid' : 'pid', $isfirstpost ? $_G['tid'] : $pid);
		}
	} else {
		if(!empty($_GET['delete'])){
			$json['data']=$param;
		}else{
			if($isfirstpost && $modpost->param('modnewthreads')) {
				C::t('forum_post')->update($thread['posttableid'], $pid, array('status' => 4), false, false, null, -2, null, 0);
				updatemoderate('tid', $_G['tid']);
			} elseif(!$isfirstpost && $modpost->param('modnewreplies')) {
				C::t('forum_post')->update($thread['posttableid'], $pid, array('status' => 4), false, false, null, -2, null, 0);
				updatemoderate('pid', $pid);
			}
		}
	}

}
function check_allow_action($action = 'allowpost') {
	global $_G;
	if(isset($_G['forum'][$action]) && $_G['forum'][$action] == -1) {
		error_json('forum_access_disallow');
	}
}
function recent_use_tag() {
	$tagarray = $stringarray = array();
	$string = '';
	$i = 0;
	$query = C::t('common_tagitem')->select(0, 0, 'tid', 'itemid', 'DESC', 10);
	foreach($query as $result) {
		if($i > 4) {
			break;
		}
		if($tagarray[$result['tagid']] == '') {
			$i++;
		}
		$tagarray[$result['tagid']] = 1;
	}
	if($tagarray) {
		$query = C::t('common_tag')->fetch_all(array_keys($tagarray));
		foreach($query as $result) {
			$tagarray[$result[tagid]] = $result['tagname'];
		}
	}
	return $tagarray;
}
function jsonnoperm($type, $fid, $formula = '') {
	global $_G;
	loadcache('usergroups');
	if($formula) {
		$formula = dunserialize($formula);
		$permmessage = stripslashes($formula['message']);
	}

	$usergroups = $nopermgroup = $forumnoperms = array();
	$nopermdefault = array(
	'viewperm' => array(),
	'getattachperm' => array(),
	'postperm' => array(7),
	'replyperm' => array(7),
	'postattachperm' => array(7),
	);
	$perms = array('viewperm', 'postperm', 'replyperm', 'getattachperm', 'postattachperm');

	foreach($_G['cache']['usergroups'] as $gid => $usergroup) {
		$usergroups[$gid] = $usergroup['type'];
		$grouptype = $usergroup['type'] == 'member' ? 0 : 1;
		$nopermgroup[$grouptype][] = $gid;
	}
	if($fid == $_G['forum']['fid']) {
		$forum = $_G['forum'];
	} else {
		$forum = C::t('forum_forumfield')->fetch($fid);
	}

	foreach($perms as $perm) {
		$permgroups = explode("\t", $forum[$perm]);
		$membertype = $forum[$perm] ? array_intersect($nopermgroup[0], $permgroups) : TRUE;
		$forumnoperm = $forum[$perm] ? array_diff(array_keys($usergroups), $permgroups) : $nopermdefault[$perm];
		foreach($forumnoperm as $groupid) {
			$nopermtype = $membertype && $groupid == 7 ? 'login' : ($usergroups[$groupid] == 'system' || $usergroups[$groupid] == 'special' ? 'none' : ($membertype ? 'upgrade' : 'none'));
			$forumnoperms[$fid][$perm][$groupid] = array($nopermtype, $permgroups);
		}
	}

	$v = $forumnoperms[$fid][$type][$_G['groupid']][0];
	$gids = $forumnoperms[$fid][$type][$_G['groupid']][1];
	$comma = $permgroups = '';
	if(is_array($gids)) {
		foreach($gids as $gid) {
			if($gid && $_G['cache']['usergroups'][$gid]) {
				$permgroups .= $comma.$_G['cache']['usergroups'][$gid]['grouptitle'];
				$comma = ', ';
			} elseif($_G['setting']['verify']['enabled'] && substr($gid, 0, 1) == 'v') {
				$vid = substr($gid, 1);
				$permgroups .= $comma.$_G['setting']['verify'][$vid]['title'];
				$comma = ', ';
			}

		}
	}

	$custom = 0;
	if($permmessage) {
		$message = $permmessage;
		$custom = 1;
	} else {
		if($v) {
			$message = $type.'_'.$v.'_nopermission';
		} else {
			$message = 'group_nopermission';
		}
	}

	error_json($message, NULL, array('fid' => $fid, 'permgroups' => $permgroups, 'grouptitle' => $_G['group']['grouptitle']), array('login' => 1), $custom);
}

function checkexpiration($expiration, $operation) {
	global $_G;
	if(!empty($expiration) && in_array($operation, array('recommend', 'stick', 'digest', 'highlight', 'close', 'open', 'bump'))) {
		$expiration = strtotime($expiration) - $_G['setting']['timeoffset'] * 3600 + date('Z');
		if(dgmdate($expiration, 'Ymd') <= dgmdate(TIMESTAMP, 'Ymd') || ($expiration > TIMESTAMP + 86400 * 180)) {
			error_json('admin_expiration_invalid', '', array('min'=>dgmdate(TIMESTAMP, 'Y-m-d'), 'max'=>dgmdate(TIMESTAMP + 86400 * 180, 'Y-m-d')));
		}
	} else {
		$expiration = 0;
	}
	return $expiration;
}

function set_stamp($typeid, $stampaction, &$threadlist, $expiration) {
	global $_G;
	$moderatetids = array_keys($threadlist);
	if(empty($threadlist)) {
		return false;
	}
	if(array_key_exists($typeid, $_G['cache']['stamptypeid'])) {
		if($stampaction == 'SPD') {
			C::t('forum_thread')->update($moderatetids, array('stamp'=>-1), true);
		} else {
			C::t('forum_thread')->update($moderatetids, array('stamp'=>$_G['cache']['stamptypeid'][$typeid]), true);
		}
		!empty($moderatetids) && updatemodlog($moderatetids, $stampaction, $expiration, 0, '', $_G['cache']['stamptypeid'][$typeid]);
	}
}

function get_expiration($tid, $action) {
	$tid = intval($tid);
	if(empty($tid) || empty($action)) {
		return '';
	}
	$row = C::t('forum_threadmod')->fetch_by_tid_action_status($tid, $action);
	return $row['expiration'] ? date('Y-m-d H:i', $row['expiration']) : '';
}
function formulaperm1($formula) {
	global $_G;
	if($_G['forum']['ismoderator']) {
		return TRUE;
	}

	$formula = dunserialize($formula);
	$medalperm = $formula['medal'];
	$permusers = $formula['users'];
	$permmessage = $formula['message'];
	if($_G['setting']['medalstatus'] && $medalperm) {
		$exists = 1;
		$_G['forum_formulamessage'] = '';
		$medalpermc = $medalperm;
		if($_G['uid']) {
			$memberfieldforum = C::t('common_member_field_forum')->fetch($_G['uid']);
			$medals = explode("\t", $memberfieldforum['medals']);
			unset($memberfieldforum);
			foreach($medalperm as $k => $medal) {
				foreach($medals as $r) {
					list($medalid) = explode("|", $r);
					if($medalid == $medal) {
						$exists = 0;
						unset($medalpermc[$k]);
					}
				}
			}
		} else {
			$exists = 0;
		}
		if($medalpermc) {
			loadcache('medals');
			foreach($medalpermc as $medal) {
				if($_G['cache']['medals'][$medal]) {
					$_G['forum_formulamessage'] .= '<img src="'.STATICURL.'image/common/'.$_G['cache']['medals'][$medal]['image'].'" style="vertical-align:middle;" />&nbsp;'.$_G['cache']['medals'][$medal]['name'].'&nbsp; ';
				}
			}
			error_json('forum_permforum_nomedal');
		}
	}
	$formulatext = $formula[0];
	$formula = $formula[1];
	if($_G['adminid'] == 1 || $_G['forum']['ismoderator'] || in_array($_G['groupid'], explode("\t", $_G['forum']['spviewperm']))) {
		return FALSE;
	}
	if($permusers) {
		$permusers = str_replace(array("\r\n", "\r"), array("\n", "\n"), $permusers);
		$permusers = explode("\n", trim($permusers));
		if(!in_array($_G['member']['username'], $permusers)) {
			error_json('forum_permforum_disallow');
		}
	}
	if(!$formula) {
		return FALSE;
	}
	if(strexists($formula, '$memberformula[')) {
		preg_match_all("/\\\$memberformula\['(\w+?)'\]/", $formula, $a);
		$profilefields = array();
		foreach($a[1] as $field) {
			switch($field) {
				case 'regdate':
					$formula = preg_replace("/\{(\d{4})\-(\d{1,2})\-(\d{1,2})\}/e", "'\'\\1-'.sprintf('%02d', '\\2').'-'.sprintf('%02d', '\\3').'\''", $formula);
				case 'regday':
					break;
				case 'regip':
				case 'lastip':
					$formula = preg_replace("/\{([\d\.]+?)\}/", "'\\1'", $formula);
					$formula = preg_replace('/(\$memberformula\[\'(regip|lastip)\'\])\s*=+\s*\'([\d\.]+?)\'/', "strpos(\\1, '\\3')===0", $formula);
				case 'buyercredit':
				case 'sellercredit':
					space_merge($_G['member'], 'status');break;
				case substr($field, 0, 5) == 'field':
					space_merge($_G['member'], 'profile');
					$profilefields[] = $field;break;
			}
		}
		$memberformula = array();
		if($_G['uid']) {
			$memberformula = $_G['member'];
			if(in_array('regday', $a[1])) {
				$memberformula['regday'] = intval((TIMESTAMP - $memberformula['regdate']) / 86400);
			}
			if(in_array('regdate', $a[1])) {
				$memberformula['regdate'] = date('Y-m-d', $memberformula['regdate']);
			}
			$memberformula['lastip'] = $memberformula['lastip'] ? $memberformula['lastip'] : $_G['clientip'];
		} else {
			if(isset($memberformula['regip'])) {
				$memberformula['regip'] = $_G['clientip'];
			}
			if(isset($memberformula['lastip'])) {
				$memberformula['lastip'] = $_G['clientip'];
			}
		}
	}
	@eval("\$formulaperm = ($formula) ? TRUE : FALSE;");
	if(!$formulaperm) {
			error_json('forum_permforum_nopermission');
	}
	return TRUE;
}
if($json['code']!=0){
	$json['success']=false;
}
//    todo 加入 通知后台程序
if($_GET['do']=='newthread'){
	//发表新帖
	$uc_from_user = $_G['username'];
	$uc_to_user = $thread['author'];

}else if($_GET['do']=='reply'){
	//回复帖子
	$uc_from_user = $_G['username'];
	$uc_to_user = $thread['author'];
	$thread_content = $_POST['message'];//回复的内容
}
json_echo($json);
?>

