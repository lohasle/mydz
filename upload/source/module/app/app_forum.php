<?php

// ----------------------------------------------------------------------------
//
//  [Huoyue.org!] (C)2013-2099 Huoyue.org Email:4767960@qq.com QQ:4767960.
//  File:   app_forum.php
//  Creation Date:  2013-08-14 18:17:36
//
// ----------------------------------------------------------------------------


if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

$dos = array('newthread', 'edit', 'reply');

$do = (!empty($_GET['do']) && in_array($_GET['do'], $dos))?$_GET['do']:'newthread';
require_once libfile('function/forum');
require_once libfile('function/forumlist');
loadcache('forums');
$gid=$fid = intval(getgpc('gid'));

if($gid>0){
	$_G['mnid'] = 'mn_F'.$gid;
	$gquery = C::t('forum_forum')->fetch_all_info_by_fids($gid);
	$query = C::t('forum_forum')->fetch_all_info_by_fids(0, 1, 0, $gid, 1, 0, 0, $gquery[$gid]['type'] == 'forum' ? 'sub' : 'forum');
	if(!empty($_G['member']['accessmasks'])) {
		$fids = array_keys($query);
		$accesslist = C::t('forum_access')->fetch_all_by_fid_uid($fids, $_G['uid']);
		foreach($query as $key => $val) {
			$query[$key]['allowview'] = $accesslist[$key];
		}
	}
	if(empty($gquery) || empty($query)) {
		error_json('forum_nonexistence');
	}
	$query = array_merge($gquery, $query);
	$fids = array();
	foreach($query as $forum) {
		$forum['extra'] = dunserialize($forum['extra']);
		if(!is_array($forum['extra'])) {
			$forum['extra'] = array();
		}
		if($forum['type'] != $gquery[$gid]['type']) {
				$icon=$forum['icon'];
				if(forum($forum)) {
					$catlist[$forum['fup']]['forums'][] = array('fid'=> $forum['fid'],'name'=>$forum['name'],'description'=>$forum['description'],'icon'=> $icon ? $_G['siteurl'].get_forumimg($icon) : '' );
				}
		} else {
			$catlist[$forum['fid']] = array('fid'=> $forum['fid'],'name'=>$forum['name']);
		}
	}
}else{
	$forums = C::t('forum_forum')->fetch_all_by_status(1);
	$fids = array();
	foreach($forums as $forum) {
		$fids[$forum['fid']] = $forum['fid'];
	}

	$forum_access = array();
	if(!empty($_G['member']['accessmasks'])) {
		$forum_access = C::t('forum_access')->fetch_all_by_fid_uid($fids, $_G['uid']);
	}

	$forum_fields = C::t('forum_forumfield')->fetch_all($fids);

	foreach($forums as $forum) {
		if($forum_fields[$forum['fid']]['fid']) {
			$forum = array_merge($forum, $forum_fields[$forum['fid']]);
		}
		if($forum_access['fid']) {
			$forum = array_merge($forum, $forum_access[$forum['fid']]);
		}
		$forumname[$forum['fid']] = strip_tags($forum['name']);
		$forum['extra'] = empty($forum['extra']) ? array() : dunserialize($forum['extra']);
		if(!is_array($forum['extra'])) {
			$forum['extra'] = array();
		}

		if($forum['type'] != 'group') {
			if($forum['type'] == 'forum' && isset($catlist[$forum['fup']])) {
				$icon=$forum['icon'];
				if(forum($forum)) {
					$catlist[$forum['fup']]['forums'][] = array('fid'=> $forum['fid'],'name'=>$forum['name'],'description'=>$forum['description'],'icon'=> $icon ? $_G['siteurl'].get_forumimg($icon) : '' );
				}
			}
		} else {
			$catlist[$forum['fid']] = array('fid'=> $forum['fid'],'name'=>$forum['name']);
		}
	}
}
$json['data']=$catlist;
if($json['code']!=0){
	$json['success']=false;
}
json_echo($json);
?>

