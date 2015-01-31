<?php echo 'ÇëÖ§³ÖÕý°æ QQ475353687';exit;?>
<div id="qmenu_menu" class="p_pop {if !$_G['uid']}blk{/if}" style="display: none;">
	<!--{hook/global_qmenu_top}-->
	<!--{if $_G['uid']}-->
		<ul class="cl nav">
		</ul>
	<!--{elseif $_G[connectguest]}-->
		<div class="ptm pbw hm">
			{lang connect_fill_profile_to_visit}
		</div>
	<!--{else}-->
		
	<!--{/if}-->
	
	<!--{hook/global_qmenu_bottom}-->
</div>
<div id="qmenuuu_menu"  style="display: none;">
<div class="user-detail-con">
<div class="username-line">
<span>{lang username}:</span>
<a href="home.php?mod=space&uid=$_G[uid]" target="_blank" title="{lang visit_my_space}" >{$_G[member][username]}</a>
</div>
<div class="first-line"><div class="first-line-item"><span>{lang credits}: </span><a href="home.php?mod=spacecp&amp;ac=credit&amp;showcredit=1" id="extcreditmenu" target="_blank"><em style="color:#ffc000">$_G[member][credits]</em></a></div>
<div class="first-line-item"><span>{lang usergroup}: </span><a href="home.php?mod=spacecp&amp;ac=usergroup" id="g_upmine" class="xi2"  target="_blank">$_G[group][grouptitle]</a></div>
</div>
<div class="second-line">
<a href="home.php?mod=space&amp;do=pm" id="pm_ntc">{lang pm_center}</a>
<a href="home.php?mod=space&amp;do=notice" id="myprompt">{lang remind}</a>
</div>	
<div class="third-line"> 
<a href="member.php?mod=logging&action=logout&formhash={FORMHASH}" style="color:#e82c0c">[{lang logout}]</a>
</div>
<ul class="navlist"><li><a href="home.php?mod=space&amp;do=friend" target="_blank">{lang friends}</a><img src="$_G['style']['styleimgdir']/arrow.png" alt="arrow"></li>
<li><a href="home.php?mod=medal" target="_blank">{lang medals}</a><img src="$_G['style']['styleimgdir']/arrow.png" alt="arrow"></li>
<li><a href="home.php?mod=space&amp;do=favorite&amp;view=me" target="_blank" >{lang favorite}</a><img src="$_G['style']['styleimgdir']/arrow.png" alt="arrow"></li>
<li><a href="home.php?mod=spacecp" target="_blank">{lang setup}</a><img src="$_G['style']['styleimgdir']/arrow.png" alt="arrow"></li>
<!--{if $_G['uid'] && getstatus($_G['member']['allowadmincp'], 1)}-->
<li><a href="admin.php" target="_blank">{lang admincp}</a><img src="$_G['style']['styleimgdir']/arrow.png" alt="arrow"></li>
<!--{/if}-->
		<!--{if ($_G['group']['allowmanagearticle'] || $_G['group']['allowpostarticle'] || $_G['group']['allowdiy'] || getstatus($_G['member']['allowadmincp'], 4) || getstatus($_G['member']['allowadmincp'], 6) || getstatus($_G['member']['allowadmincp'], 2) || getstatus($_G['member']['allowadmincp'], 3))}-->
			<li><a href="portal.php?mod=portalcp"><!--{if $_G['setting']['portalstatus'] }-->{lang portal_manage}<!--{else}-->{lang portal_block_manage}<!--{/if}--></a><img src="$_G['style']['styleimgdir']/arrow.png" alt="arrow"></li>
		<!--{/if}-->
          <!--{if check_diy_perm($topic)}-->
  <li><a href="javascript:openDiy();" title="{lang open_diy}">$cmszs_lang[0]</a><img src="$_G['style']['styleimgdir']/arrow.png" alt="arrow"></li>
  <!--{/if}-->
  <!--{hook/global_usernav_extra3}-->
</ul>


</div>
</div>