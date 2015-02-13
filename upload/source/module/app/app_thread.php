<?php

// ----------------------------------------------------------------------------
//
//  [Huoyue.org!] (C)2013-2099 Huoyue.org Email:4767960@qq.com QQ:4767960.
//  File:   app_thread.php
//  Creation Date:  2013-08-14 18:13:28
//
// ----------------------------------------------------------------------------


if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
$dos = array('list','my', 'edit', 'reply','tag','search','search','hot','bestanswer','favorite');

$page=intval($_GET['page']);
$page=$page>1 ? $page : 1;
$prepage = intval($_GET['prepage']);
$prepage=$prepage>0 ? $prepage : 10;
$start=($page-1)*$prepage;
$list = $userlist = array();
$hiddennum = $count = $pricount = 0;

$f_index = '';
$ordersql = 't.dateline DESC';
require_once libfile('function/forum');
require_once libfile('function/forumlist');
require_once libfile('function/misc');
if(empty($_GET['do'])||$_GET['do']=='list'){
	if(empty($_G['forum']['fid'])){
		error_json('forum_nonexistence');
	}
	if($_G['forum']['viewperm'] && !forumperm($_G['forum']['viewperm']) && !$_G['forum']['allowview']) {
		error_json('viewperm_none_nopermission');
	} elseif($_G['forum']['formulaperm']) {
		formulaperm1($_G['forum']['formulaperm']);
	}

	if($_G['forum']['password']&&($_GET['pw'] != $_G['forum']['password'])) {
		error_json('forum_passwd_incorrect');
	}
	if($_G['forum']['price'] && !$_G['forum']['ismoderator']) {
		$membercredits = C::t('common_member_forum_buylog')->get_credits($_G['uid'], $_G['fid']);
		$paycredits = $_G['forum']['price'] - $membercredits;
		if($paycredits > 0) {
			if(getuserprofile('extcredits'.$_G['setting']['creditstransextra'][1]) < $paycredits) {
				error_json('forum_pay_incorrect');
			}
			updatemembercount($_G['uid'], array($_G['setting']['creditstransextra'][1] => -$paycredits), 1, 'FCP', $_G['fid']);
			C::t('common_member_forum_buylog')->update_credits($_G['uid'], $_G['fid'], $_G['forum']['price']);
		}
	}
}
	
if(empty($_GET['do'])){
	$thread = & $_G['forum_thread'];
	$forum = & $_G['forum'];
	if(empty($thread)){
		error_json('thread_nonexistence');
	}
	
	if($_G['forum_thread']['readperm'] && $_G['forum_thread']['readperm'] > $_G['group']['readaccess'] && !$_G['forum']['ismoderator'] && $_G['forum_thread']['authorid'] != $_G['uid']) {
		error_json('thread_nopermission');
	}
	$_G['forum_threadpay'] = FALSE;
	if($_G['forum_thread']['price'] > 0 && $_G['forum_thread']['special'] == 0) {
		if($_G['setting']['maxchargespan'] && TIMESTAMP - $_G['forum_thread']['dateline'] >= $_G['setting']['maxchargespan'] * 3600) {
			C::t('forum_thread')->update($_G['tid'], array('price' => 0), false, false, $archiveid);
			$_G['forum_thread']['price'] = 0;
		} else {
			$exemptvalue = $_G['forum']['ismoderator'] ? 128 : 16;
			if(!($_G['group']['exempt'] & $exemptvalue) && $_G['forum_thread']['authorid'] != $_G['uid']) {
				if(!(C::t('common_credit_log')->count_by_uid_operation_relatedid($_G['uid'], 'BTC', $_G['tid']))) {
					
					if(!isset($_G['setting']['extcredits'][$_G['setting']['creditstransextra'][1]])) {
						error_json('credits_transaction_disabled');
					}
					$extcredit = 'extcredits'.$_G['setting']['creditstransextra'][1];
					$payment = C::t('common_credit_log')->count_stc_by_relatedid($_G['tid'], $_G['setting']['creditstransextra'][1]);
					$thread['payers'] = $payment['payers'];
					$thread['netprice'] = !$_G['setting']['maxincperthread'] || ($_G['setting']['maxincperthread'] && $payment['income'] < $_G['setting']['maxincperthread']) ? floor($thread['price'] * (1 - $_G['setting']['creditstax'])) : 0;
					$thread['creditstax'] = sprintf('%1.2f', $_G['setting']['creditstax'] * 100).'%';
					$thread['endtime'] = $_G['setting']['maxchargespan'] ? dgmdate($_G['forum_thread']['dateline'] + $_G['setting']['maxchargespan'] * 3600, 'u') : 0;
					$thread['price'] = $_G['forum_thread']['price'];
					$firstpost = C::t('forum_post')->fetch_threadpost_by_tid_invisible($_G['tid']);
					if($firstpost) {
						$member = getuserbyuid($firstpost['authorid']);
						$firstpost['groupid'] = $member['groupid'];
					}
					$pid = $firstpost['pid'];
					$freemessage = array();
					$freemessage[$pid]['message'] = '';
					if(preg_match_all("/\[free\](.+?)\[\/free\]/is", $firstpost['message'], $matches)) {
						foreach($matches[1] AS $match) {
							$freemessage[$pid]['message'] .= discuzcode($match, $firstpost['smileyoff'], $firstpost['bbcodeoff'], sprintf('%00b', $firstpost['htmlon']), $_G['forum']['allowsmilies'], $_G['forum']['allowbbcode'] ? -$firstpost['groupid'] : 0, $_G['forum']['allowimgcode'], $_G['forum']['allowhtml'], ($_G['forum']['jammer'] && $post['authorid'] != $_G['uid'] ? 1 : 0), 0, $post['authorid'], $_G['forum']['allowmediacode'], $pid).'<br />';
						}
					}
					
					$attachtags = array();
					if($_G['group']['allowgetattach'] || $_G['group']['allowgetimage']) {
						if(preg_match_all("/\[attach\](\d+)\[\/attach\]/i", $freemessage[$pid]['message'], $matchaids)) {
							$attachtags[$pid] = $matchaids[1];
						}
					}
					
					if($attachtags) {
						require_once libfile('function/attachment');
						parseattach($pid, $attachtags, $freemessage);
					}
					
					$thread['freemessage'] = $freemessage[$pid]['message'];
					unset($freemessage);
					$_G['forum_threadpay'] = TRUE;
				}
			}
		}
	}
	$tid=$_G['tid'];
	require_once libfile('function/discuzcode');
		$posttableid = $thread['posttableid'];
		$postlist= C::t('forum_post')->fetch_all_by_tid('tid:'.$_G['tid'], $_G['tid'], true, 'asc', $start, $prepage);
		$posts=array();
		foreach($postlist as $pid => $value){
			$postlist[$pid]['avatar']=avatar($value['authorid'], 'small',true);
			
//			$postlist[$pid]['space']=get_post_author($value['authorid']);
			$value['groupid'] = $_G['cache']['usergroups'][$postlist[$pid]['space']['groupid']] ? $postlist[$pid]['space']['groupid'] : 7;
			$forum_allowbbcode = $_G['forum']['allowbbcode'] ? -$value['groupid'] : 0;
			$postlist[$pid]['message'] = discuzcode($value['message'], $value['smileyoff'], $value['bbcodeoff'], $value['htmlon'] & 1, $_G['forum']['allowsmilies'], $forum_allowbbcode, ($_G['forum']['allowimgcode'] && $_G['setting']['showimages'] ? 1 : 0), $_G['forum']['allowhtml'], ($_G['forum']['jammer'] && $value['authorid'] != $_G['uid'] ? 1 : 0), 0, $value['authorid'], $_G['cache']['usergroups'][$value['groupid']]['allowmediacode'] && $_G['forum']['allowmediacode'], $value['pid'], $_G['setting']['lazyload'], $value['dbdateline'], $value['first']);
			
			$posts[$pid]=array(
				'pid' => $value['pid'],
				'author' => $value['author'],
			  'authorid' => $value['authorid'],
			  'avatar'=> $value['avatar'],
			  'dateline' => dgmdate($value['dateline'],'u'),
			  'attachment' => $value['attachment'],
			  'position'=> $value['position'],
			);
			if($value['attachment']) {
				if((!empty($_G['setting']['guestviewthumb']['flag']) && !$_G['uid']) || $_G['group']['allowgetattach'] || $_G['group']['allowgetimage']) {
					$_G['forum_attachpids'][] = $value['pid'];
					$postlist[$pid]['attachment'] = 0;
					if(preg_match_all("/\[attach\](\d+)\[\/attach\]/i", $postlist[$pid]['message'], $matchaids)) {
						$_G['forum_attachtags'][$value['pid']] = $matchaids[1];
					}
				} else {
					$postlist[$pid]['message'] = preg_replace("/\[attach\](\d+)\[\/attach\]/i", '', $postlist[$pid]['message']);
				}
			}
		}
		if($_G['forum_attachpids']) {
			require_once libfile('function/attachment');
			parseattach($_G['forum_attachpids'], $_G['forum_attachtags'], $postlist, $skipaids);
		}
//		print_r($_G['forum_attachpids']);
//		test('1111111111111');
		if($_G['forum_attachpids']){
			
			foreach($_G['forum_attachpids'] as $pid){
				if($postlist[$pid]['imagelist']){
					foreach($postlist[$pid]['imagelist'] as $key=>$imgaid){
						$attach=$postlist[$pid]['attachments'][$imgaid];
						$postlist[$pid]['imagelist'][$key]=($attach['remote'] ? $_G['setting']['attachurl'] :$_G['siteurl']).$attach['url'].$attach['attachment'];
					}
				}
			}
		}
		foreach($postlist as $pid => $value){
			$posts[$pid]['message'] = $value['message'];
			$posts[$pid]['imagelist']= $value['imagelist'];
			$posts[$pid]['imagelistcount']= intval($value['imagelistcount']);
		}


	$json['data']['count'] =$thread['replies'];
	$json['data']['page'] =$page;
	$json['data']['prepage'] =$prepage;
	$json['data']['pw'] =$_GET['pw'];
	$json['data']['thread']=array(
	'tid' => $thread['tid'],
	'author' => $thread['author'],
  'authorid' => $thread['authorid'],
  'avatar'=>avatar($thread['authorid'], 'small',true),
  'subject' => $thread['subject'],
  'dateline' => dgmdate($thread['dateline'],'u'),
	'displayorder' => $thread['displayorder'],
	'digest' => $thread['digest'],
	'attachment' => $thread['attachment'] ,
	);
	$json['data']['posts'] =$posts;
	json_echo($json);
}else{
	if($_GET['do'] == 'favorite') {

		$op=in_array($_GET['op'],array('add','del','list')) ? $_GET['op'] : 'list';
		if($op == 'list'){
			$uid=$_G['uid'];
			if(empty($uid)) {
				error_json('to_login');
			}
		}else{
			if(empty($_G['uid'])) {
				error_json('to_login');
			}else{
				$tid=intval($_GET['tid']);
				if($tid>0){
					$thread = C::t('forum_thread')->fetch($tid);
					$title = $thread['subject'];
				}
			}
		}
		if($op == 'add') {
			$idtype = 'tid';
			if(empty($title)) {
				error_json('thread_nonexistence');
			}
			$fav = C::t('home_favorite')->fetch_by_id_idtype($tid, $idtype, $_G['uid']);
			if($fav) {
				error_json('favorite_repeat');
			}
			$fav_count = C::t('home_favorite')->count_by_id_idtype($tid, $idtype);
			require_once libfile('function/home');
			$arr = array(
			'uid' => intval($_G['uid']),
			'idtype' => $idtype,
			'id' => $tid,
			'spaceuid' => $_G['uid'],
			'title' => getstr($title, 255),
			'description' => '',
			'dateline' => TIMESTAMP
			);
			$favid = C::t('home_favorite')->insert($arr, true);
			if($_G['setting']['cloud_status']) {
				$favoriteService = Cloud::loadClass('Service_Client_Favorite');
				$favoriteService->add($arr['uid'], $favid, $arr['id'], $arr['idtype'], $arr['title'], $arr['description'], TIMESTAMP);
			}
			C::t('forum_thread')->increase($tid, array('favtimes'=>1));
			require_once libfile('function/forum');
			update_threadpartake($tid);
			$json['data']=array('favid' => $favid, 'type' => 'add');
		}elseif($op == 'del') {
			$favid = intval($_GET['favid']);
			if($favid>0){
				$thevalue = C::t('home_favorite')->fetch($favid);
			}elseif(!empty($thread)){
				$thevalue = C::t('home_favorite')->fetch_by_id_idtype($tid,'tid',$_G['uid']);
			}
			if(empty($thevalue) || $thevalue['uid'] != $_G['uid']) {
				error_json('favorite_does_not_exist');
			}
			$favid = $thevalue['favid'];
			C::t('home_favorite')->delete($favid);
			if($_G['setting']['cloud_status']) {
				$favoriteService = Cloud::loadClass('Service_Client_Favorite');
				$favoriteService->remove($_G['uid'], $favid);
			}
			if($thevalue['idtype'] == 'tid'){
				if(empty($thread)){
					$thread = C::t('forum_thread')->fetch($thevalue['id']);
				}
				$favtimes=intval($thread['favtimes']-1);
				C::t('forum_thread')->update($thread['tid'], array('favtimes'=>$favtimes));
				require_once libfile('function/forum');
				update_threadpartake($thread['tid']);
			}
			$json['data']=array('favid' => $favid, 'type' => 'del');
		}elseif($op == 'list'){
			$json['data']['count']=$count = C::t('home_favorite')->count_by_uid_idtype($uid,'tid');
			if($count) {
				$list = C::t('home_favorite')->fetch_all_by_uid_idtype($uid,'tid', 0, $start, $perpage);
			}
			$json['data']['list']=$list;

		}
		json_echo($json);
	}elseif($_GET['do'] == 'postreview') {

		if(!$_G['setting']['repliesrank'] || empty($_G['uid'])) {
			error_json('to_login');
		}
		$opArray = array('support', 'against');
		$post = C::t('forum_post')->fetch('tid:'.$_GET['tid'], $_GET['pid'], false);
	
		if(!in_array($_GET['op'], $opArray) || empty($post) || $post['first'] == 1 || ($_G['setting']['threadfilternum'] && $_G['setting']['filterednovote'] && getstatus($post['status'], 11))) {
			error_json('undefined_action');
		}
	
		$hotreply = C::t('forum_hotreply_number')->fetch_by_pid($post['pid']);
		if($_G['uid'] == $post['authorid']) {
			error_json('noreply_yourself_error');
		}
	
		if(empty($hotreply)) {
			$hotreply['pid'] = C::t('forum_hotreply_number')->insert(array(
				'pid' => $post['pid'],
				'tid' => $post['tid'],
				'support' => 0,
				'against' => 0,
				'total' => 0,
			), true);
		} else {
			if(C::t('forum_hotreply_member')->fetch($post['pid'], $_G['uid'])) {
				error_json('noreply_voted_error');
			}
		}
	
		$typeid = $_GET['op'] == 'support' ? 1 : 0;
	
		C::t('forum_hotreply_number')->update_num($post['pid'], $typeid);
		C::t('forum_hotreply_member')->insert(array(
			'tid' => $post['tid'],
			'pid' => $post['pid'],
			'uid' => $_G['uid'],
			'attitude' => $typeid,
		));
	
		$hotreply[$_GET['op']]++;
		$json['data']=array(
			'tid' => $post['tid'],
			'pid' => $post['pid'],
			'uid' => $_G['uid'],
			'op' => 'support',
			'count'=> $hotreply[$_GET['op']],
		);
	
		json_echo($json);
	}elseif($_GET['do']=='list'){
		$orderby	= isset($_GET['orderby']) ? (in_array($_GET['orderby'],array('lastpost','dateline','replies','views','heats','recommends')) ? $_GET['orderby'] : 'lastpost') : 'lastpost';
		$ascdesc = $_GET['ascdesc']=='asc' ? 'asc' : 'DESC';
		require_once libfile('function/post');
		require_once libfile('function/search');
		require_once libfile('function/discuzcode');
		$sql =' AND t.fid ='.$_G['fid']." AND t.isgroup='0'";
		$json['data']['count']=$count = $_G['forum']['threads'];
		$json['data']['page'] =$page;
		$json['data']['prepage'] =$prepage;
		$json['data']['orderby'] =$orderby;
		$json['data']['ascdesc'] =$ascdesc;
		$json['data']['pw'] =$_GET['pw'];
		$_G['forum']['icon']=$_G['forum']['icon'] ? $_G['siteurl'].get_forumimg($_G['forum']['icon']) : '';
		$json['data']['forum'] =$_G['forum'];
		if($count) {
			$filterarr['inforum'] = $_G['fid'];
			$filterarr['sticky'] = 0;
			$_order = "displayorder DESC, $orderby $ascdesc";
			$threadlist =C::t('forum_thread')->fetch_all_search($filterarr, 0, $start, $prepage, $_order, '', '');
			foreach($threadlist as $key => $data){
				$data['avatar'] = avatar($data['authorid'], 'small',true);
				$data['pic'] =STATICURL.'image/common/nophoto.gif';
				$data['picflag'] =0;
				if($data['attachment'] == '2'){
					$pic=DB::fetch_first("SELECT  attachment, remote FROM ".DB::table('forum_threadimage')." WHERE tid ='".$data['tid']."'");
					if($pic){
						$data['pic'] =($pic['remote'] ? $_G['setting']['ftp']['attachurl'] : $_G['siteurl'].$_G['setting']['attachurl']).'forum/'.$pic['attachment'];
						$data['picflag'] = $pic['remote'] ? 2 : 1;
					}
				}
				$threadlist[$key] =procthread1($data);
			}
			$json['data']['list']=$threadlist;
		}
		json_echo($json);
	}elseif($_GET['do']=='my'){
		$uid=$_G['uid'];
		if(empty($uid)){
			error_json('to_login');
		}
		$viewtype = in_array($_GET['type'], array('reply', 'thread', 'postcomment')) ? $_GET['type'] : 'thread';

		require_once libfile('function/forumlist');
		$forumlist = forumselect(FALSE, 0, intval(0));
		$json['data']= get_my_threads($uid,$viewtype, 0, '', '', $start, $prepage);
		$json['data']['page'] =$page;
		$json['data']['prepage'] =$prepage;
		json_echo($json);
	}
}
function attachimg($aid){
	global $_G;
	$aid=intval($aid);
	$attach = C::t('forum_attachment_n')->fetch('aid:'.$aid,$aid, array(1, -1));
	$pic =$_G['siteurl'].STATICURL.'image/common/nophoto.gif';
	$src =$attach['attachment'] ? ($attach['remote'] ? $_G['setting']['attachurl'] :$_G['siteurl']).'forum/'.$attach['attachment']:$pic;
	return '<img src="'.$src.'" />';
}
function get_post_author($uid){
	global $space;
	if(empty($space[$uid])){
		$space[$uid]=getuserbyuid($uid);
		$space[$uid]['avatar'] = avatar($uid, 'small',true);
		$space[$uid]['realname'] = DB::result_first('SELECT realname FROM '.DB::table("common_member_profile")." WHERE uid= '".$uid."' ORDER BY uid DESC");
	}
	return $space[$uid];
}
function returnSquarePoint($lat, $lng,$distance = 1000){
	$earth_radius=6378137;
	$dlng =  2 * asin(sin($distance / (2 * $earth_radius)) / cos(deg2rad($lat)));
	$dlng = abs(rad2deg($dlng));

	$dlat = $distance/$earth_radius;
	$dlat = abs(rad2deg($dlat));

	return array(
	'lat'=>array($lat - $dlat,$lat + $dlat),
	'lng'=>array($lng-$dlng,$lng + $dlng)
	);
}

function get_my_threads($uid,$viewtype, $fid = 0, $filter = '', $searchkey = '', $start = 0, $perpage = 20, $theurl = '') {
	global $_G;
	$fid = $fid ? intval($fid) : null;
	loadcache('forums');
	$dglue = '=';
	if($viewtype == 'thread') {
		$authorid = $uid;
		$displayorder = -1;
		$dglue = '!=';
		if($filter == 'recyclebin') {
			$displayorder = -1;
		} elseif($filter == 'aduit') {
			$displayorder = -2;
		} elseif($filter == 'ignored') {
			$displayorder = -3;
		} elseif($filter == 'save') {
			$displayorder = -4;
		} elseif($filter == 'close') {
			$closed = 1;
		} elseif($filter == 'common') {
			$closed = 0;
			$displayorder = 0;
			$dglue = '>=';
		}
		$gids = $fids = $forums = array();
		foreach(C::t('forum_thread')->fetch_all_by_authorid_displayorder($uid, $displayorder, $dglue, $closed, $searchkey, $start, $perpage, null, $fid) as $tid => $value) {
			
			if(!isset($_G['cache']['forums'][$value['fid']])) {
				$gids[$value['fid']] = $value['fid'];
			} else {
				$forumnames[$value['fid']] = array('fid'=> $value['fid'], 'name' => $_G['cache']['forums'][$value['fid']]['name']);
			}
			$list[] = procthread1($value);
		}

		if(!empty($gids)) {
			$gforumnames = C::t('forum_forum')->fetch_all_name_by_fid($gids);
			foreach($gforumnames as $fid => $val) {
				$forumnames[$fid] = $val;
			}
		}
		$listcount = C::t('forum_thread')->count_by_authorid($authorid);
	}elseif($viewtype == 'reply') {
		$invisible = null;

		if($filter == 'recyclebin') {
			$invisible = -5;
		} elseif($filter == 'aduit') {
			$invisible = -2;
		} elseif($filter == 'save' || $filter == 'ignored') {
			$invisible = -3;
			$displayorder = -4;
		} elseif($filter == 'close') {
			$closed = 1;
		} elseif($filter == 'common') {
			$invisible = 0;
			$displayorder = 0;
			$dglue = '>=';
			$closed = 0;
		}
		require_once libfile('function/post');

		$query = DB::query('SELECT DISTINCT(tid) FROM '.DB::table('forum_post').' WHERE authorid='.$uid.' and first=0 GROUP BY tid ORDER BY dateline desc '.DB::limit($start, $perpage));
		while($post = DB::fetch($query)) {
			$tids[] = $post['tid'];
		}
		if(!empty($tids)) {
			$threads = C::t('forum_thread')->fetch_all_by_tid_displayorder($tids, $displayorder, $dglue, array(), $closed);
			foreach($threads as $tid => $thread) {
				if(!isset($_G['cache']['forums'][$thread['fid']])) {
					$gids[$thread['fid']] = $thread['fid'];
				} else {
					$forumnames[$thread[fid]] = array('fid' => $thread['fid'], 'name' => $_G['cache']['forums'][$thread[fid]]['name']);
				}
				$pid = DB::result_first('SELECT pid FROM '.DB::table('forum_post').' WHERE authorid='.$uid.' and tid='.$thread['tid'].' and first=0  ORDER BY dateline desc ');

				$list[$pid] = procthread1($thread);
			}
			if(!empty($list)){
				krsort($list);
			}
			if(!empty($gids)) {
				$groupforums = C::t('forum_forum')->fetch_all_name_by_fid($gids);
				foreach($groupforums as $fid => $val) {
					$forumnames[$fid] = $val;
				}
			}
			$listcount = count(DB::fetch_all('SELECT DISTINCT(tid) FROM '.DB::table('forum_post').' WHERE authorid='.$uid.' and first=0  GROUP BY tid ORDER BY dateline desc '));
		}
	}
	return array('forumnames' => $forumnames, 'count' => $listcount, 'list' => $list);
}

function guide_procthread($thread) {
	global $_G;
	$todaytime = strtotime(dgmdate(TIMESTAMP, 'Ymd'));
	$thread['lastposterenc'] = rawurlencode($thread['lastposter']);
	$thread['multipage'] = '';
	$topicposts = $thread['special'] ? $thread['replies'] : $thread['replies'] + 1;
	if($topicposts > $_G['ppp']) {
		$pagelinks = '';
		$thread['pages'] = ceil($topicposts / $_G['ppp']);
		for($i = 2; $i <= 6 && $i <= $thread['pages']; $i++) {
			$pagelinks .= "<a href=\"forum.php?mod=viewthread&tid=$thread[tid]&amp;extra=$extra&amp;page=$i\">$i</a>";
		}
		if($thread['pages'] > 6) {
			$pagelinks .= "..<a href=\"forum.php?mod=viewthread&tid=$thread[tid]&amp;extra=$extra&amp;page=$thread[pages]\">$thread[pages]</a>";
		}
		$thread['multipage'] = '&nbsp;...'.$pagelinks;
	}

	if($thread['highlight']) {
		$string = sprintf('%02d', $thread['highlight']);
		$stylestr = sprintf('%03b', $string[0]);

		$thread['highlight'] = ' style="';
		$thread['highlight'] .= $stylestr[0] ? 'font-weight: bold;' : '';
		$thread['highlight'] .= $stylestr[1] ? 'font-style: italic;' : '';
		$thread['highlight'] .= $stylestr[2] ? 'text-decoration: underline;' : '';
		$thread['highlight'] .= $string[1] ? 'color: '.$_G['forum_colorarray'][$string[1]] : '';
		$thread['highlight'] .= '"';
	} else {
		$thread['highlight'] = '';
	}

	$thread['recommendicon'] = '';
	if(!empty($_G['setting']['recommendthread']['status']) && $thread['recommends']) {
		foreach($_G['setting']['recommendthread']['iconlevels'] as $k => $i) {
			if($thread['recommends'] > $i) {
				$thread['recommendicon'] = $k+1;
				break;
			}
		}
	}

	$thread['moved'] = $thread['heatlevel'] = $thread['new'] = 0;
	$thread['icontid'] = $thread['forumstick'] || !$thread['moved'] && $thread['isgroup'] != 1 ? $thread['tid'] : $thread['closed'];
	$thread['folder'] = 'common';
	$thread['weeknew'] = TIMESTAMP - 604800 <= $thread['dbdateline'];
	if($thread['replies'] > $thread['views']) {
		$thread['views'] = $thread['replies'];
	}
	if($_G['setting']['heatthread']['iconlevels']) {
		foreach($_G['setting']['heatthread']['iconlevels'] as $k => $i) {
			if($thread['heats'] > $i) {
				$thread['heatlevel'] = $k + 1;
				break;
			}
		}
	}
	$thread['istoday'] = $thread['dateline'] > $todaytime ? 1 : 0;
	$thread['dbdateline'] = $thread['dateline'];
	$thread['dateline'] = dgmdate($thread['dateline'], 'u', '9999', getglobal('setting/dateformat'));
	$thread['dblastpost'] = $thread['lastpost'];
	$thread['lastpost'] = dgmdate($thread['lastpost'], 'u');

	if(in_array($thread['displayorder'], array(1, 2, 3, 4))) {
		$thread['id'] = 'stickthread_'.$thread['tid'];
	} else {
		$thread['id'] = 'normalthread_'.$thread['tid'];
	}
	$thread['rushreply'] = getstatus($thread['status'], 3);
	return $thread;
}

function getpic($tid) {
	global $_G;
	if(!$tid) {
		return '';
	}
	$pic = DB::fetch_first("SELECT attachment, remote FROM ".DB::table(getattachtablebytid($tid))." WHERE tid='$tid' AND isimage IN (1, -1) ORDER BY dateline DESC LIMIT 0,1");
	return $pic;
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
function procthread1($thread){
	$avatar = avatar($thread['authorid'], 'small',true);
	return array('tid' => $thread['tid'],'subject' => $thread['subject'],'author' => $thread['author'],'authorid' => $thread['authorid'],'avatar' => $avatar, 'replies' => $thread['replies'],'dateline'=>dgmdate($thread['dateline'], 'u', '9999', getglobal('setting/dateformat')) );
}
if($json['code']!=0){
	$json['success']=false;
}
json_echo($json);
?>

