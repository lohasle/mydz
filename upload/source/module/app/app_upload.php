<?php

/*
*      [Huoyue.org!] (C)2015-2099 Huoyue.org Email:4767960@qq.com QQ:4767960.
*
*      This is NOT a freeware, Authorized by huoyue.org to use
*
*      $File:/source/module/app|app_upload.php 2015-01-23 17:42:28 Huoyue $


*/


if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
if($_G['group']['allowpostattach'] || $_G['group']['allowpostimage']){
	if($_G['forum']['status'] == 3 && $_G['forum']['level']) {
		$levelinfo = C::t('forum_grouplevel')->fetch($_G['forum']['level']);
		if($postpolicy = $levelinfo['postpolicy']) {
			$postpolicy = dunserialize($postpolicy);
			$forumattachextensions = $postpolicy['attachextensions'];
		}
	} else {
		$forumattachextensions = $_G['forum']['attachextensions'];
	}
	if($forumattachextensions) {
		$_G['group']['attachextensions'] = $forumattachextensions;
	}
	$allowupload = !$_G['group']['maxattachnum'] || $_G['group']['maxattachnum'] && $_G['group']['maxattachnum'] > getuserprofile('todayattachs');;
	if(!$allowupload) {
		uploadmsg(6);
	}
	foreach($_FILES as $file){
		$upload = new discuz_upload();
		$upload->init($file, 'forum');
		$attach = &$upload->attach;

		if($upload->error()) {
			uploadmsg(2);
		}

		if($_G['group']['attachextensions'] && (!preg_match("/(^|\s|,)".preg_quote($upload->attach['ext'], '/')."($|\s|,)/i", $_G['group']['attachextensions']) || !$upload->attach['ext'])) {
			uploadmsg(1);
		}
		if(empty($upload->attach['size'])) {
			uploadmsg(2);
		}

		if($_G['group']['maxattachsize'] && $upload->attach['size'] > $_G['group']['maxattachsize']) {
			$error_sizelimit = $_G['group']['maxattachsize'];
			uploadmsg(3);
		}

		loadcache('attachtype');
		if($_G['fid'] && isset($_G['cache']['attachtype'][$_G['fid']][$upload->attach['ext']])) {
			$maxsize = $_G['cache']['attachtype'][$_G['fid']][$upload->attach['ext']];
		} elseif(isset($_G['cache']['attachtype'][0][$upload->attach['ext']])) {
			$maxsize = $_G['cache']['attachtype'][0][$upload->attach['ext']];
		}
		if(isset($maxsize)) {
			if(!$maxsize) {
				$error_sizelimit = 'ban';
				uploadmsg(4);
			} elseif($upload->attach['size'] > $maxsize) {
				$error_sizelimit = $maxsize;
				uploadmsg(5);
			}
		}

		if($upload->attach['size'] && $_G['group']['maxsizeperday']) {
			$todaysize = getuserprofile('todayattachsize') + $upload->attach['size'];
			if($todaysize >= $_G['group']['maxsizeperday']) {
				$error_sizelimit = 'perday|'.$_G['group']['maxsizeperday'];
				uploadmsg(11);
			}
		}
		updatemembercount($_G['uid'], array('todayattachs' => 1, 'todayattachsize' => $upload->attach['size']));
		$upload->save();
		if($upload->error() == -103) {
			uploadmsg(8);
		} elseif($upload->error()) {
			uploadmsg(9);
		}
		$thumb = $remote = $width = 0;
//		if($_GET['type'] == 'image' && !$upload->attach['isimage']) {
//			uploadmsg(7);
//		}
		if($upload->attach['isimage']) {
			if(!in_array($upload->attach['imageinfo']['2'], array(1,2,3,6))) {
				uploadmsg(7);
			}
			if($_G['setting']['showexif']) {
				require_once libfile('function/attachment');
				$exif = getattachexif(0, $upload->attach['target']);
			}
			if($_G['setting']['thumbsource'] || $_G['setting']['thumbstatus']) {
				require_once libfile('class/image');
				$image = new image;
			}
			if($_G['setting']['thumbsource'] && $_G['setting']['sourcewidth'] && $_G['setting']['sourceheight']) {
				$thumb = $image->Thumb($upload->attach['target'], '', $_G['setting']['sourcewidth'], $_G['setting']['sourceheight'], 1, 1) ? 1 : 0;
				$width = $image->imginfo['width'];
				$upload->attach['size'] = $image->imginfo['size'];
			}
			if($_G['setting']['thumbstatus']) {
				$thumb = $image->Thumb($upload->attach['target'], '', $_G['setting']['thumbwidth'], $_G['setting']['thumbheight'], $_G['setting']['thumbstatus'], 0) ? 1 : 0;
				$width = $image->imginfo['width'];
			}
			if($_G['setting']['thumbsource'] || !$_G['setting']['thumbstatus']) {
				list($width) = @getimagesize($upload->attach['target']);
			}
		}
//		if($_GET['type'] != 'image' && $upload->attach['isimage']) {
//			$upload->attach['isimage'] = -1;
//		}
		$aid = getattachnewaid($_G['uid']);
		$insert = array(
		'aid' => $aid,
		'dateline' => $_G['timestamp'],
		'filename' => dhtmlspecialchars(censor($upload->attach['name'])),
		'filesize' => $upload->attach['size'],
		'attachment' => $upload->attach['attachment'],
		'isimage' => $upload->attach['isimage'],
		'uid' =>$_G['uid'],
		'thumb' => $thumb,
		'remote' => $remote,
		'width' => $width,
		);
		C::t('forum_attachment_unused')->insert($insert);
		if($upload->attach['isimage'] && $_G['setting']['showexif']) {
			C::t('forum_attachment_exif')->insert($aid, $exif);
		}
		$_GET['attachnew'][$aid]=array('description'=>'','readperm'=>'','price'=>0);
//		$json['data']=$aid;
	}
}else{
	error_json('postattachperm_none_nopermission');
}

if($json['code']!=0){
	$json['success']=false;
	json_echo($json);
}
function uploadmsg($num){
	error_json('file_upload_error_'.$num);
}
?>

