<?PHP
/*
    @filename   userAction.class.php
    @version    1.0
    @author     appkan www.appkan.com jeffxie
    @contact    jeffxie@gmail.com
    @update     2013-08-31
    @comment    用户相关操作
 **/
class userAction{
    private $_G;
    public $ver;
    public function __construct() {
        global $_G;
        $this->_G = array_map('addslashes', $_GET);//防注入过滤
        $this->_G = array_map('inject_check',$this->_G);//防注入
        require_once('source/discuz_version.php');
        $this->ver = array('charset'=> strtoupper(CHARSET),'version'=>DISCUZ_VERSION);
        if(!function_exists('uc_user_login')) {
            loaducenter();
        }
    }
    //过滤空值为null  ok
    public function strnull($str)
    {
        $mode = array("/(\\\)/");
        if(empty($str))
        {
            return 0;
        }
        else{
            if(is_array($str))
            {
                foreach($str as $k=>$v)
                {
                    $v = stripslashes($v);
                    $str[$k] = preg_replace("'([\r\n])[\s]+'", "",$v);
                }
                return $str;
            }
            else{
                $str = stripslashes($str);
                return preg_replace("'([\r\n])[\s]+'", "",$str);
                }
        }
    }
    //登录 ok
    public function login() {
        $username = $this->_G['username'];
        $password = $this->_G['password'];
        if(!$username || !$password)
        {
            $this->jsonexit("{\"status\":\"error:parameter username or password is null.\"}");
        }
        $status = uc_user_login($username, $password);
        $this->jsonexit($status);
    }
    //编辑用户 ok
    public function user_edit() {
        $username = $this->_G['username'];
        $oldpassword = $this->_G['oldpassword'];
        $newpassword = $this->_G['newpassword'];
        $emailnew = $this->_G['emailnew'];
        if(!$username || !$oldpassword  || !$newpassword)
        {
            $this->jsonexit("{\"status\":\"error:parameter username or oldpassword or newpassword is null.\"}");
        }
        $ucresult = uc_user_edit($username,$oldpassword,$newpassword,$emailnew);
        $this->jsonexit("{\"status\":\"$ucresult\"}");
    }


    //预留接口：同步登录,暂无用 ok
    public function synlogin() {
        $username = $this->_G['username'];
        $password = $this->_G['password'];
        list($uid, $username, $password, $email) = uc_user_login($username, $password);
        if($uid > 0) {
            $this->jsonexit(array("loginstatus"=>$uid,"synlogstatus"=>uc_user_synlogin($uid)));
        } elseif($uid == -1) {
            $this->jsonexit(-1);
        } elseif($uid == -2) {
            $this->jsonexit(-2);
        }

    }
//注册用户 ok
    public function register() {
        $username = $this->_G['username'];
        $password = $this->_G['password'];
        $email = $this->_G['email'];
        if(!$username || !$password  || !$email)
        {
            $this->jsonexit("{\"status\":\"error:parameter username or password or email is null.\"}");
        }
        $uid = uc_user_register($username, $password, $email);
        if( $uid > 0 ){
        //查询用户
        $regresult = DB::fetch_first('SELECT * FROM %t WHERE uid=%d',array('ucenter_members',$uid));
        if($regresult)//登录的数据，为了注册discuz
        {
        	C::t("common_member")->insert($uid, $username, $regresult['password'], $email, '127.0.0.1','0' ,'' , $adminid = 0);

        }
        }
        $this->jsonexit($uid);
	}

//以上为用户模块操作结束。
/****************************************************************************************************************************/
    //读取所有板块,父级板块+子板块
    public function getfid()
    {
        $fid = DB::fetch_all('SELECT * FROM %t WHERE type=%s AND status=1 ',array('forum_forum','group'));
        for($i=0;$i<count($fid);$i++)
        {
            $fid[$i]['forum'] = DB::fetch_all('SELECT * FROM %t WHERE type=%s AND status=1 AND fup=%d',array('forum_forum','forum',$fid[$i]['fid']));
        }
        echo $this->jsonexit($fid);
    }
    //新增加功能，获取板块的icon小图标2013-11-5
    public function getForumIcon($fid='') {
        $fid = $this->_G['fid']?$this->_G['fid']:$fid;
        $icon = DB::fetch_first('SELECT * FROM %t WHERE fid=%d',array('forum_forumfield',$fid));
        if(!$icon['icon'])
        {
            return 'http://'.$_SERVER['SERVER_NAME'].'/static/image/common/forum.gif';
        }
        else{
            return 'http://'.$_SERVER['SERVER_NAME'].'/data/attachment/common/'.$icon['icon'];
        }
    }

	//获取所有子板块
	public function getForums()
	{
        $fid = DB::fetch_all('SELECT * FROM %t WHERE type=%s AND status=1 AND fup!=0',array('forum_forum','forum'));
		//print_r($fid);
		foreach($fid as $k=>$v)
		{
			$lastpost = split('	',$v['lastpost']);
			$fid[$k]['lastpost_message'] = $lastpost[1];
			$fid[$k]['lastpost_time'] = date('Y-m-d H:i:s',$lastpost[2]);
			$fid[$k]['lastpost_author'] = $lastpost[3];
			$fid[$k]['lastpost_date'] = daterange(time(),$lastpost[2]);//距离发帖时间
            $fid[$k]['icon'] = $this->getForumIcon($v['fid']);
			//echo $fid[$v]['lastpost']);die;
			//print_r(split(' ',$fid[$v]['lastpost']));die;
		}
		//print_r($fid);die;
        $this->jsonexit($fid);
	}
    //检查版块是否存在
    public function getforumstatus() {
        $fid = intval($this->_G['fid']);
        if($fid)
        {
            $this->jsonexit(DB::fetch_first("SELECT * FROM %t WHERE type=%s AND status=1 AND fid=%d",array('forum_forum','forum',$fid)));
        }
        else{
            $this->jsonexit('-1');
        }
    }
    //读取最新的帖子主题日
    public function readnewthread()
    {
        require_once libfile('function/post');
        $tid = DB::fetch_all('SELECT a.*,b.message FROM %t a,%t b WHERE a.tid=b.tid AND b.first=1 ORDER BY a.dateline DESC LIMIT 10',array('forum_thread','forum_post'));
		foreach($tid as $k=>$v)
		{
			$tid[$k]['dateline'] = date('Y-m-d H:i:s',$tid[$k]['dateline']);//距离发帖时间

			//echo $fid[$v]['lastpost']);die;
			//print_r(split(' ',$fid[$v]['lastpost']));die;
		}
        $this->jsonexit($tid);
    }
	//热门帖子
    public function gethotthread()
    {
        require_once libfile('function/post');
        $tid = DB::fetch_all('SELECT a.*,b.message FROM %t a,%t b WHERE a.tid=b.tid AND b.first=1 ORDER BY a.views DESC LIMIT 10',array('forum_thread','forum_post'));
		foreach($tid as $k=>$v)
		{
			$tid[$k]['dateline'] = date('Y-m-d H:i:s',$tid[$k]['dateline']);//距离发帖时间

			//echo $fid[$v]['lastpost']);die;
			//print_r(split(' ',$fid[$v]['lastpost']));die;
		}
        $this->jsonexit($tid);
    }

    //读取最新的帖子主题日
    public function getmyreadnewthread()
    {
        require_once libfile('function/post');
		$uid = intval($this->_G['uid']);
        $limit = intval($this->_G['limit']);
        if($limit)
        {
            $limitSQL = ' LIMIT '.$limit;
        }
        else{
            $limitSQL = ' LIMIT 10';
        }
		if($uid)
		{
			$tid = DB::fetch_all('SELECT a.*,b.message FROM %t a,%t b WHERE a.tid=b.tid AND b.first=1 AND b.authorid=%d ORDER BY a.dateline DESC'.$limitSQL,array('forum_thread','forum_post',$uid));
			foreach($tid as $k=>$v)
			{
				$tid[$k]['dateline'] = date('Y-m-d H:i:s',$tid[$k]['dateline']);

				//echo $fid[$v]['lastpost']);die;
				//print_r(split(' ',$fid[$v]['lastpost']));die;
			}
			$this->jsonexit($tid);
		}
		$this->jsonexit('error uid.');
    }
    //根据板块FID读取帖子列表(主题)),需要传入参数FID
    public function readtid()
    {
        require_once libfile('function/post');
		//page data
		$getcount = DB::result_first('SELECT count(*) count FROM %t a,%t b WHERE a.tid=b.tid AND b.first=1 AND a.fid=%d ',array('forum_thread','forum_post',intval($this->_G['fid'])));//数据总量
		$page = intval(getgpc('page')) ? intval($this->_G['page']) : 1;
		$perpage = 5;
		$start = ($page - 1) * $perpage;
		//$multipage = multi($getcount, $perpage, $page, $url."&orderby=$orderby");//获取分页
		$multipage = array('getcount'=>$getcount, 'perpage'=>$perpage, 'page'=>$page, 'url'=>$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);//获取分页数据;
        if(intval($this->_G['page']))
        {
            $tid = DB::fetch_all('SELECT a.*,b.message FROM %t a,%t b WHERE a.tid=b.tid AND b.first=1 AND a.fid=%d ORDER BY a.dateline DESC LIMIT '.$start.','. $perpage,array('forum_thread','forum_post',intval($this->_G['fid'])));
        }
        else{
            $tid = DB::fetch_all('SELECT a.*,b.message FROM %t a,%t b WHERE a.tid=b.tid AND b.first=1 AND a.fid=%d ORDER BY a.dateline DESC',array('forum_thread','forum_post',intval($this->_G['fid'])));
        }
        for($i=0;$i<count($tid);$i++)
        {
            $detail[$i]['id'] = $tid[$i]['tid'];
            $detail[$i]['typeid'] = urlencode($this->readtype($tid[$i]['typeid']));//分类：
            $detail[$i]['title'] = urlencode($tid[$i]['subject']);//标题：
            $detail[$i]['createdAt'] = $tid[$i]['dateline'];//时间
            $imgtmp = $this->getdetailimg($tid[$i]['message']);//封面图片：帖子里第一张图
            $imgtmplink = $this->getimglink($tid[$i]['message']);
            if($imgtmp && $imgtmplink)
            {
                $imgtmp = array_merge($imgtmplink,$imgtmp);
            }
            else{
                $imgtmp = $imgtmp?$imgtmp:$imgtmplink;
            }
            $detail[$i]['imageUrls'] = $this->strnull($imgtmp[0]); //封面图片：帖子里第一张图
            unset($imgtmp[0]);
            sort($imgtmp);
            $detail[$i]['images'] = $this->strnull($imgtmp);//图片地址：帖子里第二张图开始
            $detail[$i]['text'] = $this->strnull(urlencode(trim(messagecutstr($tid[$i]['message'], 140))));//内容摘要：第一段文字
            $detail[$i]['message'] = $this->strnull(($this->ctag($tid[$i]['message'])));
        }
        $multipage[pagecount] = intval(round($getcount/$perpage));
        $this->jsonexit($detail,$multipage);
    }
    //根据typeid读取帖子列表(主题)),需要传入参数plugin.php?id=iphone:user&func=readtypetid&typeid=0
    public function readtypetid()
    {
        require_once libfile('function/post');
       $page = intval(getgpc('page')) ? intval($this->_G['page']) : 1;
       $perpage = 5;
       $start = ($page - 1) * $perpage;
       $getcount = DB::result_first('SELECT count(*) count FROM %t a,%t b WHERE a.tid=b.tid AND b.first=1 AND a.typeid=%d',array('forum_thread','forum_post',intval($this->_G['typeid'])));//数据总量
       //$multipage = multi($getcount, $perpage, $page, $url."&orderby=$orderby");//获取分页
       $multipage = array('getcount'=>$getcount, 'perpage'=>$perpage, 'page'=>$page, 'url'=>$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);//获取分页数据;
        if(intval($this->_G['page']))
        {
            $tid = DB::fetch_all('SELECT a.*,b.message FROM %t a,%t b WHERE a.tid=b.tid AND b.first=1 AND a.typeid=%d ORDER BY a.dateline DESC LIMIT '.$start.','.$perpage,array('forum_thread','forum_post',intval($this->_G['typeid'])));
        }
        else{
            $tid = DB::fetch_all('SELECT a.*,b.message FROM %t a,%t b WHERE a.tid=b.tid AND b.first=1 AND a.typeid=%d ORDER BY a.dateline DESC',array('forum_thread','forum_post',intval($this->_G['typeid'])));
        }

        for($i=0;$i<count($tid);$i++)
        {
            $detail[$i]['id'] = $tid[$i]['tid'];
            $detail[$i]['typeid'] = urlencode($this->readtype($tid[$i]['typeid']));//分类：
            $detail[$i]['title'] = urlencode($tid[$i]['subject']);//标题：
            $detail[$i]['createdAt'] = $tid[$i]['dateline'];//时间
            $imgtmp = $this->getdetailimg($tid[$i]['message']);//封面图片：帖子里第一张图
            $imgtmplink = $this->getimglink($tid[$i]['message']);
            if($imgtmp && $imgtmplink)
            {
                $imgtmp = array_merge($imgtmplink,$imgtmp);
            }
            else{
                $imgtmp = $imgtmp?$imgtmp:$imgtmplink;
            }
            $detail[$i]['imageUrls'] = $this->strnull($imgtmp[0]); //封面图片：帖子里第一张图
            unset($imgtmp[0]);
            sort($imgtmp);
            $detail[$i]['images'] = $this->strnull($imgtmp);//图片地址：帖子里第二张图开始
            $detail[$i]['text'] = $this->strnull(urlencode(trim(messagecutstr($tid[$i]['message'], 140))));//内容摘要：第一段文字
            $detail[$i]['message'] = $this->strnull(urlencode($this->ctag($tid[$i]['message'])));
        }
        $multipage[pagecount] = intval(round($getcount/$perpage));
        $this->jsonexit($detail,$multipage);
    }
    //获取图片外链
    public function getimglink($str)
    {
        $preg = "/<img.*?src=\"(.+?)\".*?>/";
        $preg = "/\[img.*?\](.*?)\[\/img\]/";
        preg_match_all($preg,$str,$n);
        return $n[1];
    }
    //获取贴子分类
    public function readtype($id)
    {
        if($id!=0)
        {
            //$typeid = DB::fetch_first('SELECT * FROM %t WHERE typeid=%d',array('forum_threadtype',$id));
            $typeid = DB::fetch_first('SELECT * FROM %t WHERE typeid=%d',array('forum_threadclass',$id));
            if(!$typeid['name'])
            {
                $typeid = '无分类';
            }
            else{
                $typeid = $typeid['name'];
            }
        }
        else{
            $typeid = '无分类';
        }
        return $typeid;

    }
    //读取帖子详情页+回复
    //http://localhost/apkbus/plugin.php?id=iphone:user&func=readpid&tid=110
    public function readpid()
    {
        require_once libfile('function/post');
        $pid = DB::fetch_all('SELECT a.*,b.typeid,u.uid ,u.username,u.titleimgurl FROM %t a,%t b,%t u WHERE a.tid = b.tid AND a.authorid = u.uid AND a.tid=%d',array('forum_post','forum_thread' ,'common_member',intval($this->_G['tid'])));
        for($i=0;$i<count($pid);$i++)
        {
            $detail[$i]['id'] = $pid[$i]['pid'];
            $detail[$i]['fid'] = $pid[$i]['fid'];//板块
            $detail[$i]['typeid'] = urlencode($this->readtype($pid[$i]['typeid']));//分类：
            $detail[$i]['title'] = urlencode($pid[$i]['subject']);//标题：
            $detail[$i]['author'] = $pid[$i]['author'];//作者
            $detail[$i]['createdAt'] = $pid[$i]['dateline'];//时间
            $detail[$i]['text'] = $this->strnull(urlencode(trim(messagecutstr($pid[$i]['message'], 140))));//内容摘要：第一段文字
            $detail[$i]['message'] = $this->strnull(urlencode($this->ctag($pid[$i]['message']))); //正文内容：
            $imgtmp = $this->strnull($this->getdetailimg($pid[$i]['message']));//封面图片：帖子里第一张图
            $imgtmplink = $this->strnull($this->getimglink($pid[$i]['message']));
            if($imgtmp!='null' && $imgtmplink)
            {
                $imgtmp = array_merge($imgtmplink,$imgtmp);
                //echo 1;
            }
            else{
                $imgtmp = $imgtmp!='null'?$imgtmp:$imgtmplink;
                //echo 2;
            }
           // print_r($imgtmp);die;
            $detail[$i]['img'] = $this->strnull($imgtmp[0]);//封面图片：帖子里第一张图
            if(!empty($imgtmp))
            {

                unset($imgtmp[0]);
            }
            sort($imgtmp);
            $detail[$i]['images'] = $this->strnull($imgtmp);//图片地址：帖子里第二张图开始
            $url = preg_match_all("'http\:\/\/(.*?)(\.html|\.htm|\/htm|\/applist)'isx",$pid[$i]['message'],$n);
            $detail[$i]['url'] = $this->strnull($n[0]);

			$detail[$i]['titleimgurl'] = $detail[$i]['titleimgurl'];
            //视频地址：帖子里url
        }
       $this->jsonexit($detail);
    }
    //发表新帖;先写入主题表，然后写入帖子表
    public function posttid()
    {
        $data['subject'] = addslashes($this->setcode(urldecode($this->_G['subject'])));
        $data['dateline'] = time();
        $data['fid'] = intval($this->_G['fid']);
        $data['author'] = addslashes($this->_G['username']);
        $data['authorid'] = intval($this->_G['uid']);
        $data['lastpost'] = time();
        $data['lastposter'] = addslashes($this->_G['username']);
        $data['views'] = 1;
        if($data['subject'] && $data['author'] && $data['authorid'])
        {
            $data['tid'] = C::t('forum_thread')->insert($data,true);
            unset($data['lastpost']);
            unset($data['lastposter']);
            unset($data['views']);
            $data['message'] = addslashes($this->setcode(urldecode($this->_G['message'])));
            $data['first'] = 1;
            $data['position'] = 1;
            if($data['message'])
            {
                $pid = C::t('forum_post_tableid')->insert(array('pid' => null), true);
                $data = array_merge($data, array('pid' => $pid));
                if(DB::insert('forum_post',$data))
                {
                    $this->jsonexit($data);
                }
                else{
                    $this->jsonexit("{\"status\":\"-1\"}");
                }
            }
        }
        $this->jsonexit("{\"status\":\"-1\"}");
    }
    public function setcode($string)
    {
        $version = $this->ver;
        if($version['charset'] == 'GBK')
        {
            $string = iconv('UTF-8',"GBK",$string);
        }
        return addslashes($string);//安全过滤
    }

    //回复帖子
    //http://localhost/apkbus/plugin.php?id=iphone:user&func=reply&fid=39&tid=110&username=xie1234&titleimgurl=32133131&ip=31233313&subject=test&message=fdsfsfds
    public function reply()
    {
     //插入帖子表 $this->_G['username']
    	$uid = $this->register(addslashes($this->_G['username']) ,addslashes($this->_G['titleimgurl'])
    					,$this->_G['ip']);
        $tiddata = DB::fetch_first('SELECT * FROM %t WHERE tid=%d',array('forum_thread',$this->_G['tid']));
        $data['replies'] = $tiddata['replies']+1;
        $data['lastpost'] = time();
        C::t('forum_thread')->update($data['tid'],$data);//更新主题表信息
     	$pid = C::t('forum_post_tableid')->insert(array('pid' => null), true);
	    $data = array_merge($data, array('pid' => $pid));
        $data['tid'] = $this->_G['tid'];
        unset($data['replies']);
        unset($data['lastpost']);
        $data['first'] = 0;
        $data['dateline'] = time();
        //-------------------------------------------------
        $pidsql = 'SELECT max( pid ) FROM `pre_forum_post` WHERE 1';
 	    $num = DB::result_first($pidsql);
        $pid = $num+1;
        $newdata['pid'] = $pid;
        $newdata['fid'] = intval($this->_G['fid']);
        $newdata['tid'] = intval($this->_G['tid']);
        $newdata['first'] = 0;
        $newdata['author'] = addslashes($uid['username']);
        $newdata['authorid'] = $uid['uid'];
        $newdata['dateline'] = time();
        $newdata['subject'] = addslashes($this->_G['subject']);
        $newdata['message'] = addslashes($this->_G['message']);
        $newdata['useip'] = 1;
        $newdata['invisible'] = 0;
        $newdata['anonymous'] = 0;
        $newdata['position'] = 0;
        $newdata['usesig'] = 1;

        //-------------------------------------------------
        if(DB::insert('forum_post',$newdata))
        {
            $this->jsonexit("{\"status\":\"1\"}");
        }
        else{
            $this->jsonexit("{\"status\":\"-1\"}");
        }
    }
    //编辑帖子
    public function eidttid()
    {
        $pid = $this->_G['pid'];
        $data['subject'] = addslashes($this->_G['subject']);
        $data['message'] = addslashes($this->_G['message']);
        if($data['subject'] && $data['message'])
        {
            if(C::t('forum_post')->update($tableid=0, $pid, $data))
            {
                $this->jsonexit("{\"status\":\"1\"}");
            }
            else{
                $this->jsonexit("{\"status\":\"-1\"}");
            }
        }
        $this->jsonexit("{\"status\":\"-1\"}");
    }
    //删除帖子，要注意判断是否是自己的帖子
    public function deltid()
    {
        return;
    }

    //如管理员身份，可以删除，置顶，加粗帖子/plugin.php?id=iphone:user&func=manager&pid=153&op=del
    ///plugin.php?id=iphone:user&func=manager&tid=100&op=stick
    ///plugin.php?id=iphone:user&func=manager&tid=100&op=highlight
    public function manager()
    {
        if($this->_G['groupid']==1)
        {
           switch($this->_G['op'])
           {
                case 'del':
                    $pid = intval($this->_G['pid']);
                    $tid = DB::fetch_first('SELECT first,tid FROM %t WHERE pid=%d',array('forum_post',$pid));
                    if($tid['first']==1)
                    {
                        $result = C::t('forum_thread')->delete_by_tid($tid['tid']);
                        $result = C::t('forum_post')->delete_by_tid($tableid=0,$tid['tid']);

                    }
                    else{
                        $result = C::t('forum_post')->delete($tableid=0,$pid);//单独删除回复
                    }
                    if($result)
                    {
                        $this->jsonexit("{\"status\":\"1\"}");
                    }
                    else{
                        $this->jsonexit("{\"status\":\"-1\"}");
                    }
                    break;
                case 'highlight'://加粗
                    //样式太多，只能选择突出的样式,比如加粗
                    $data['highlight'] = 45;//加粗
                    $result = DB::update('forum_thread',$data,array('tid'=>intval($this->_G['tid'])));

                    if($result)
                    {
                        $this->jsonexit("{\"status\":\"1\"}");
                    }
                    else{
                        $this->jsonexit("{\"status\":\"-1\"}");
                    }
                    break;
                case 'stick'://置顶
                    //$data['displayorder'] = 1;1为置顶，0为去除置顶
                    $result = C::t('forum_thread')->update_displayorder_by_tid_displayorder(intval($this->_G['tid']), intval($this->_G['olddisplayorder']), intval($this->_G['newdisplayorder']));
                    if($result)
                    {
                        $this->jsonexit("{\"status\":\"1\"}");
                    }
                    else{
                        $this->jsonexit("{\"status\":\"-1\"}");
                    }
                    break;
           }
        }
        else{
            $this->jsonexit("{\"status\":\"-1\"}");//权限不够
        }
    }

    //返回用户基本信息/plugin.php?id=iphone:user&func=getuserinfo
    public function getuserinfo()
    {
        $this->jsonexit(C::t('common_member')->fetch_by_username(addslashes($this->_G['username'])));
    }

    //发送短消息/plugin.php?id=iphone:user&func=sendnotic&username=test&subject=fdsfsd&message=fdsfdsfffsd
    public function sendnotic()
    {
        $username = addslashes($this->_G['username']);
        $subject = addslashes($this->_G['subject']);
        $message = addslashes($this->_G['message']);
        $touid = C::t('common_member')->fetch_uid_by_username($username);
        if($touid && $subject && $message)
        {
            $return = sendpm($touid, $subject, $message, '', $pmid, 0);
            if($return)
            {
                $this->jsonexit("{\"status\":\"1\"}");
            }
            else{
                $this->jsonexit("{\"status\":\"-1\"}");
            }
        }
    }
    public function getdetailimg($message)
    {
        //require_once libfile('function/attachment');
        $content = $message;
        preg_match_all('/\[attach\](\d+)\[\/attach\]/',$content,$newcontent);
        $aids = $newcontent[1];
        for($j=0;$j<count($aids);$j++)
        {
            for($i=0;$i<10;$i++)
            {
                $data = C::t('forum_attachment_n')->fetch_all($tableid=$i,$aids[$j]);
                if($data)
                {
                    $imgs[] = 'data/attachment/forumdata/'.$data[0]['attachment'];
                }
            }
        }
        return $imgs;
    }
    //读取帖子中的图片/plugin.php?id=iphone:user&func=getimg&message=fds[attach]48[/attach]ffdfs
    public function getimg()
    {
        //require_once libfile('function/attachment');
        $tid = $this->_G['tid'];
        $pid = $this->_G['pid'];
        $data_p=C::t('forum_post')->fetch($tid,$pid);
        $content = $data_p['message'];
        preg_match('/\[attach\](\d+)\[\/attach\]/',$content,$newcontent);
        $aids = $newcontent[1];
        for($i=0;$i<10;$i++)
        {
            $data = C::t('forum_attachment_n')->fetch_all($tableid=$i,$aids );
            if($data)
            {
                $img = $data;
            }
        }

        if($img[0]['attachment'])
        {
            $this->jsonexit("{\"status\":\"data/attachment/forum/".$img[0]['attachment']."\"}");
        }
        else{
            $this->jsonexit("{\"status\":\"-1\"}");
        }
    }
    //获取当前编码,版本号
    public function version() {

        if($this->_G['f'] == 'version')
        {
            $this->jsonexit($this->ver);
        }

    }
    /*
    * 功能：打印$this->_G['jsoncallback']函数 + JSON格式数据并结束程序
    * 参数：JSON字符串如{'state':'massges'}或PHP数组，如是数据则会转为JSON字符串
    * 返回值：无
    */
    public function jsonexit($string,$page='') {
        $k = @$this->_G['jsoncallback'];
        $version = $this->ver;//死循环???
        if($version['charset'] == 'GBK')
        {
            $string = json_encode($this->arrayRecursive($string, 'urlencode', true));
        }
        else
        {
            $string = json_encode($string);
        }

        if($k)
        {

            $string = $k."({data:".$string."})";
            echo $string;die;

        }
        else{
            echo "{\"data\":$string}";die;
        }


    }

/**************************************************************
 *
*    使用特定function对数组中所有元素做处理
*    @param    string    &$array        要处理的字符串
*    @param    string    $function    要执行的函数
*    @return boolean    $apply_to_keys_also        是否也应用到key上
*    @access public
*
*************************************************************/
public function arrayRecursive(&$array, $function, $apply_to_keys_also = false)
{
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $this->arrayRecursive($array[$key], $function, $apply_to_keys_also);
        } else {
            $array[$key] = $function($value);
        }

        if ($apply_to_keys_also && is_string($key)) {
            $new_key = $function($key);
            if ($new_key != $key) {
                $array[$new_key] = $array[$key];
                unset($array[$key]);
            }
        }
    }
    return $array;
}
    public function ischarset($text)
    {
        $e=mb_detect_encoding($text, array('UTF-8', 'GBK'));
        return $e;
    }

    public function filterpic($string) {
    	global $_G;
    	$default = '<img src="'.$_G['siteurl'].'source/plugin/iphone/template/images/default.png"/>';
    	if(is_array($string)) {
    		foreach($string as $key => $value) {
    			$string[$key] = $this->filterpic($value);
    		}
    	} else {
    		$string = preg_replace('/<img.*?(src|file)=["\'](.*?)["\'].*?[^h]>/i', $default, $string);
    	}
    	return $string;
    }
    /*
    * 功能：对字符串或数组进行utf8编码
    * 参数：字符串或数组
    * 返回值：编码后的字符串或数组
    */
    public function gbktoutf8($string) {
    	if(is_array($string)) {
    		foreach($string as $key => $value) {
    			$string[$key] = $this->gbktoutf8($value);
    		}
    	} else {
    			$string = $this->diconv($string, CHARSET, "utf-8");
    	}
    	return $string;
    }
    public function diconv($str, $in_charset, $out_charset = CHARSET, $ForceTable = FALSE) {
    	global $_G;

    	$in_charset = strtoupper($in_charset);
    	$out_charset = strtoupper($out_charset);

    	if(empty($str) || $in_charset == $out_charset) {
    		return $str;
    	}

    	$out = '';

    	if(!$ForceTable) {
    		if(function_exists('iconv')) {
    			$out = iconv($in_charset, $out_charset.'//IGNORE', $str);
    		} elseif(function_exists('mb_convert_encoding')) {
    			$out = mb_convert_encoding($str, $out_charset, $in_charset);
    		}
    	}

    	if($out == '') {
    		require_once libfile('class/chinese');
    		$chinese = new Chinese($in_charset, $out_charset, true);
    		$out = $chinese->Convert($str);
    	}

    	return $out;
    }

    /*
    * 功能：对字符串或数组进行本地化编码
    * 参数：字符串或数组
    * 返回值：编码后的字符串或数组
    */
    public function utf8togbk($string) {
    	if(is_array($string)) {
    		foreach($string as $key => $value) {
    			$string[$key] = $this->utf8togbk($value);
    		}
    	} else {
    		$string = $this->diconv($string, "utf-8", CHARSET);
    	}
    	return $string;
    }
    //过滤html和编辑器标签
    public function ctag($content)
    {
$content = str_replace(array(
			'[/color]', '[/backcolor]', '[/size]', '[/font]', '[/align]', '[b]', '[/b]', '[s]', '[/s]', '[hr]', '[/p]',
			'[i=s]', '[i]', '[/i]', '[u]', '[/u]', '[list]', '[list=1]', '[list=a]',
			'[list=A]', "\r\n[*]", '[*]', '[/list]', '[indent]', '[/indent]', '[/float]','[/url]'
			), array(

			), preg_replace(array(
			"/\[color=([#\w]+?)\]/i",
			"/\[color=(rgb\([\d\s,]+?\))\]/i",
			"/\[backcolor=([#\w]+?)\]/i",
			"/\[backcolor=(rgb\([\d\s,]+?\))\]/i",
			"/\[size=(\d{1,2}?)\]/i",
			"/\[size=(\d{1,2}(\.\d{1,2}+)?(px|pt)+?)\]/i",
			"/\[font=([^\[\<]+?)\]/i",
			"/\[align=(left|center|right)\]/i",
			"/\[p=(\d{1,2}|null), (\d{1,2}|null), (left|center|right)\]/i",
			"/\[float=left\]/i",
			"/\[float=right\]/i",
            "/\[url=(.*)\]/i"

			), array(
			"",
			"",
			"",
			"",
			"",
			"",
			"",
			"",
			"",
			"",
			"",
            "",
            ""
			), $content));
            $content = preg_replace('/\[img.*?\].*?\[\/img\]/','',$content);
            //$content = preg_replace('/\[img(.*)+\]','',$content);
            return $content;
    }
}
function daterange($endday,$staday,$format='Y-m-d',$color='',$range=3)
{
	$value = $endday - $staday;
	if($value < 0)
	{
		return '';
	}
	elseif($value >= 0 && $value < 59)
	{
		$return=($value+1).lang('plugin/appkan','second');
	}
	elseif($value >= 60 && $value < 3600)
	{
		$min = intval($value / 60);
		$return=$min.lang('plugin/appkan','minute');
	}
	elseif($value >=3600 && $value < 86400)
	{
		$h = intval($value / 3600);
		$return=$h.lang('plugin/appkan','hour');
	}
	elseif($value >= 86400)
	{
		$d = intval($value / 86400);
		if ($d>$range)
		{
		return date($format,$staday);
		}
		else
		{
		$return=$d.lang('plugin/appkan','day');
		}
	}
	if ($color)
	{
	$return="<span style=\"color:{$color}\">".$return."</span>";
	}
	return $return;
}
//防止注入
function inject_check($sql_str) {
    if(eregi('select|insert|update|delete|\'|\*|/\.\.\/\/|\/\.\/|/|union|load_file|outfile',$sql_str))
    {
        exit('非法访问 ');
    }
    else{
        return $sql_str;
    }
    // 进行过滤
}
?>