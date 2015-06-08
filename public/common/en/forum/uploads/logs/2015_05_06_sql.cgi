Wed, 06 May 2015 14:16:27 +0000 (Severity: 0)
178.76.195.226 - http://pixel.dev.grapheme.ru/en/forum/admin/?adsess=qsqj3aesjpi0s0t36osktkahc5&app=core&module=customization&controller=themes&id=1&do=saveTemplate
Duplicate entry '4213688f69cc22f254a6a550d0a2637f' for key 'template_unique_key'
INSERT INTO `ipb_core_theme_templates` ( `template_set_id`, `template_group`, `template_location`, `template_app`, `template_content`, `template_name`, `template_data`, `template_added_to`, `template_user_added`, `template_user_edited`, `template_removable`, `template_version`, `template_unique_key`, `template_updated` ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
Array
(
    [0] => 1
    [1] => profile
    [2] => front
    [3] => core
    [4] => {{if !\IPS\Request::i()->isAjax()}}
<!-- When altering this template be sure to also check for similar in the hovercard -->
<div data-controller='core.front.profile.main'>
	{template="profileHeader" app="core" location="front" group="profile" params="$member, false"}
	<br>

	<div data-role="profileContent">
{{endif}}
		<div class='ipsColumns ipsColumns_collapseTablet' data-controller="core.front.profile.body">
			<div class='ipsColumn ipsColumn_fixed ipsColumn_veryWide' id='elProfileInfoColumn'>
				<div class='ipsAreaBackground_light ipsPad'>
					{{if settings.reputation_enabled and settings.reputation_show_profile}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom'>
							{{if member.group['gbw_view_reps']}}
								<a href="{url="app=core&module=members&controller=profile&id={$member->member_id}&do=reputation" seoTemplate="profile_reputation" seoTitle="$member->members_seo_name"}" data-action="repLog" title="{lang="members_reputation" sprintf="$member->name"}">
							{{endif}}
								<div class='cProfileRepScore ipsPad_half {{if $member->pp_reputation_points > 1}}cProfileRepScore_positive{{elseif $member->pp_reputation_points < 0}}cProfileRepScore_negative{{else}}cProfileRepScore_neutral{{endif}}'>
									<h2 class='ipsType_minorHeading'>{lang="profile_reputation"}</h2>
									<span class='cProfileRepScore_points'>{$member->pp_reputation_points}</span>
									{{if $member->reputation()}}
										<span class='cProfileRepScore_title'>{$member->reputation()}</span>
									{{endif}}
									{{if $member->reputationImage()}}
										<div class='ipsAreaBackground_reset ipsAreaBackground_rounded ipsPad_half ipsType_center'>
											<img src='{$member->reputationImage()}' alt=''>
										</div>
									{{endif}}
								</div>
							{{if member.group['gbw_view_reps']}}
								<p class='ipsType_reset ipsPad_half ipsType_right ipsType_light ipsType_small'>
									{lang="replog_show_activity"} <i class='fa fa-caret-right'></i>
								</p>
							</a>
							{{endif}}
						</div>
					{{endif}}
					
					{{if \IPS\Settings::i()->warn_on AND ( \IPS\Member::loggedIn()->modPermission('mod_see_warn') or ( \IPS\Settings::i()->warn_show_own and \IPS\Member::loggedIn()->member_id == $member->member_id ) )}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom'>
							<div id='elWarningInfo' class='ipsPad {{if $member->mod_posts || $member->restrict_post || $member->temp_ban}}ipsAreaBackground_negative{{endif}} ipsClearfix'>
								<i class='ipsPos_left {{if $member->warn_level > 0 || $member->mod_posts || $member->restrict_post || $member->temp_ban}}fa fa-exclamation-triangle{{else}}fa fa-circle-o ipsType_light{{endif}}'></i>
								<div>
									<h2 class='ipsType_sectionHead'>{lang="member_warn_level" pluralize="$member->warn_level"}</h2>
									<br>
									{{if !$member->mod_posts && !$member->restrict_post && !$member->temp_ban}}
										<span>{lang="no_restrictions_applied"}</span>
										<br>
									{{else}}
										<span>{lang="restrictions_applied"}</span>
										<ul>
											{{if $member->mod_posts}}
												<li data-ipsTooltip title="{{if $member->mod_posts == -1}}{lang="moderation_modq_perm"}{{else}}{lang="moderation_modq_temp" sprintf="\IPS\DateTime::ts( $member->mod_posts )"}{{endif}}">
													{lang="moderation_modq"}
												</li>
											{{endif}}
											{{if $member->restrict_post}}
												<li data-ipsTooltip title="{{if $member->restrict_post == -1}}{lang="moderation_nopost_perm"}{{else}}{lang="moderation_nopost_temp" sprintf="\IPS\DateTime::ts( $member->restrict_post )"}{{endif}}">
													{lang="moderation_nopost"}
												</li>
											{{endif}}
											{{if $member->temp_ban}}
												<li data-ipsTooltip title="{{if $member->temp_ban == -1}}{lang="moderation_banned_perm"}{{else}}{lang="moderation_banned_temp" sprintf="\IPS\DateTime::ts( $member->temp_ban )"}{{endif}}">
													{lang="moderation_banned"}
												</li>
											{{endif}}
										</ul>
									{{endif}}
									{{if ( member.canWarn( $member ) || \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer') ) and $member->member_id != \IPS\Member::loggedIn()->member_id }}
										<br>
										<ul class='ipsList_inline'>
											{{if member.canWarn( $member )}}
												<li>
													<a href='{$addWarningUrl}' id='elWarnUserButton' data-ipsDialog data-ipsDialog-title="{lang="warn_member" sprintf="$member->name"}" class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="warn_member" sprintf="$member->name"}'>{lang="warn_user"}</a>
												</li>
											{{endif}}
											{{if \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer') and $member->member_id != \IPS\Member::loggedIn()->member_id}}
												<li>
													{{if $member->members_bitoptions['bw_is_spammer']}}
														<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=0" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="spam_unflag"}' data-confirm data-confirmSubMessage="{lang="spam_unflag_confirm"}">{lang="spam_unflag"}</a>
													{{else}}
														<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=1" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="spam_flag"}' data-confirm>{lang="spam_flag"}</a>
													{{endif}}
												</li>
											{{endif}}
										</ul>
									{{endif}}
								</div>
							</div>
							{{if count( $member->warnings( 1 ) )}}
								<a href='{url="app=core&module=system&controller=warnings&id={$member->member_id}" seoTemplate="warn_list" seoTitle="$member->members_seo_name"}' data-action="showRecentWarnings" title='{lang="see_recent_warnings"}'>
									<p class='ipsType_reset ipsPad_half ipsType_right ipsType_small ipsType_light ipsAreaBackground_light'>
										{lang="see_recent_warnings"} <i class='fa fa-caret-down'></i>
									</p>
								</a>
								<div data-role="recentWarnings" class='ipsPad_half ipsHide'>
									<ol class='ipsDataList'>
										{{foreach $member->warnings( 2 ) as $warning}}
											<li class="ipsDataItem" id='elWarningOverview_{$warning->id}'>
												<div class='ipsDataItem_generic ipsDataItem_size1 ipsType_center'>
													<a href="{url="app=core&module=system&controller=warnings&id={$member->member_id}&w={$warning->id}" seoTemplate="warn_view" seoTitle="$member->members_seo_name"}" data-ipsDialog data-ipsDialog-size='narrow' class="ipsType_blendLinks" data-ipsTooltip title='{lang="wan_action_points" pluralize="$warning->points"}'>
														<span class="ipsPoints">{$warning->points}</span>
													</a>
												</div>
												<div class='ipsDataItem_main'>
													<a href="{url="app=core&module=system&controller=warnings&id={$member->member_id}&w={$warning->id}" seoTemplate="warn_view" seoTitle="$member->members_seo_name"}" data-ipsDialog data-ipsDialog-showFrom='#elWarningOverview_{$warning->id}' data-ipsDialog-size='narrow' class="ipsType_blendLinks" title=''>
														<h4 class="ipsDataItem_title">{lang="core_warn_reason_{$warning->reason}"}</h4>
														<p class='ipsDataItem_meta ipsType_light'>
															{lang="byline" sprintf="\IPS\Member::load( $warning->moderator )->name"}{datetime="$warning->date"}
														</p>
													</a>
												</div>
											</li>
										{{endforeach}}
									</ol>
									<br>
									<p class='ipsType_reset ipsType_center ipsType_small'>
										<a href='{url="app=core&module=system&controller=warnings&id={$member->member_id}" seoTemplate="warn_list" seoTitle="$member->members_seo_name"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="see_all_warnings"}' data-ipsDialog data-ipsDialog-remoteVerify='false' data-ipsDialog-remoteSubmit='false' data-ipsDialog-title="{lang="members_warnings" sprintf="$member->name"}">{lang="see_all_c"}</a>
									</p>
								</div>
							{{endif}}
						</div>
					{{elseif \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer')}}
						{{if $member->members_bitoptions['bw_is_spammer']}}
							<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=0" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall ipsButton_fullWidth' title='{lang="spam_unflag"}' data-confirm data-confirmSubMessage="{lang="spam_unflag_confirm"}">{lang="spam_unflag"}</a>
						{{else}}
							<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=1" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall ipsButton_fullWidth' title='{lang="spam_flag"}' data-confirm>{lang="spam_flag"}</a>
						{{endif}}
					{{endif}}

					{{if count( $followers ) || \IPS\Member::loggedIn()->member_id === $member->member_id}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom' id='elFollowers' data-feedID='member-{$member->member_id}' data-controller='core.front.profile.followers'>
						  <h2 class="followers-header"><span>My followers</span></h2>
                          {template="followers" group="profile" app="core" params="$member, $followers"}
						</div>
	 				{{endif}}

					<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
						<h2 class='ipsType_sectionHead ipsType_reset'>{lang='profile_about' sprintf='$member->name'}</h2>
						{{if $member->group['g_icon'] }}
							<div class='ipsType_center ipsPad_half'><img src='{$member->group['g_icon']}' alt=''></div>
						{{endif}}
						<ul class='ipsDataList ipsDataList_reducedSpacing cProfileFields'>
							{{if $member->isOnline() AND $member->location}}
								<li class="ipsDataItem">
									<span class="ipsDataItem_generic ipsDataItem_size3 ipsType_break"><strong>{lang="online_users_location_lang"}</strong></span>
									<span class="ipsDataItem_main">{$member->location()|raw}</span>
								</li>
							{{endif}}
							{{if $member->member_title || $member->rank['title'] || $member->rank['image']}}
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="profile_rank"}</strong></span>
									<div class='ipsDataItem_generic'>
										{{if $member->member_title}}
											{$member->member_title}
											<br>
										{{elseif $member->rank['title']}}
											{$member->rank['title']}
											<br>
										{{endif}}
										{$member->rank['image']|raw}
									</div>
								</li>
							{{endif}}
							{{if $member->birthday}}
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="bday"}</strong></span>
									<span class='ipsDataItem_generic'>{$member->birthday}</span>
								</li>
							{{endif}}
						</ul>
					</div>
					{{foreach $sidebarFields as $group => $fields}}
						{{if count( $fields )}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
							{{if $group != 'core_pfieldgroups_0'}}
                                <h2 class='ipsType_sectionHead ipsType_reset'>{lang="$group"}</h2>
                            {{endif}}
							<ul class='ipsDataList ipsDataList_reducedSpacing cProfileFields'>
								{{foreach $fields as $field => $value}}
									<li class='ipsDataItem'>
										<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="$field"}</strong></span>
										<span class='ipsDataItem_generic'>{$value|raw}</span>
									</li>
								{{endforeach}}
							</ul>
						</div>
						{{endif}}
					{{endforeach}}
					{{if \IPS\Member::loggedIn()->modPermission('can_see_emails')}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
							<h2 class='ipsType_sectionHead ipsType_reset'>{lang="profile_contact"}</h2>
							<ul class='ipsDataList ipsDataList_reducedSpacing'>
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3'><strong>{lang="profile_email"}</strong></span>
									<span class='ipsDataItem_generic'><a href='mailto:{$member->email}' title='{lang="email_this_user"}'>{wordbreak="$member->email"}</a></span>
								</li>
							</ul>
						</div>
					{{endif}}
					{{if !empty( $visitors ) || \IPS\Member::loggedIn()->member_id == $member->member_id}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom' data-controller='core.front.profile.toggleBlock'>
							{template="recentVisitorsBlock" group="profile" params="$member, $visitors"}
						</div>
					{{endif}}
				</div>

			</div>
			<section class='ipsColumn ipsColumn_fluid'>
				<div class='ipsBox'>
					<!-- Single status -->
					{{if \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) && \IPS\Request::i()->status && \IPS\Request::i()->type == 'status' && settings.profile_comments and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
	 					<section data-controller='core.front.profile.toggleBlock' class='ipsPad'>
							{template="statuses" group="profile" app="core" params="$statuses, $statusCount, $form, $member"}
						</section>
					{{else}}
					<!-- Full profile content -->
						<div class='ipsTabs ipsTabs_small ipsClearfix' id='elProfileTabs' data-ipsTabBar data-ipsTabBar-stretch data-ipsTabBar-contentArea='#elProfileTabs_content'>
							<a href='#elProfileTabs' data-action='expandTabs'><i class='fa fa-caret-down'></i></a>
							<ul role="tablist">
								<li role="presentation">
									<a href='{$member->url()->setQueryString( 'tab', 'activity' )}' id='elActivity' class='ipsTabs_item ipsType_center {{if ( !\IPS\Request::i()->tab && !\IPS\Request::i()->status ) || \IPS\Request::i()->tab == 'activity'}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="users_activity_feed"}</a>
								</li>
								{{if settings.profile_comments AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
				 					<li role="presentation">
										<a href='{$member->url()->setQueryString( 'tab', 'statuses' )}' id='elUpdates' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'statuses' || \IPS\Request::i()->status}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="status_feed"}</a>
									</li>
								{{endif}}
								{{foreach $mainFields as $group => $fields}}
									{{foreach $fields as $field => $value}}
										{{if $value}}
											<li role="presentation">
												<a href='{$member->url()->setQueryString( 'tab', 'field_' . $field )}' id='elField{$field}' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'field_' . $field}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="$field"}</a>
											</li>
										{{endif}}
									{{endforeach}}
								{{endforeach}}
								{{foreach $nodes as $type => $table}}
									{{if $_table = (string) $table and $table->pages}}
										<li role="presentation">
											<a href='{$member->url()->setQueryString( 'tab', 'node_' . $type )}' id='elNode{$type}' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'node_' . $type}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="profile_{$type}"}</a>
										</li>
									{{endif}}
								{{endforeach}}			 				
							</ul>
						</div>
						<div id='elProfileTabs_content' class='ipsTabs_panels'>
							<div id="ipsTabs_elProfileTabs_elActivity_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
								{{if count( $latestActivity )}}
									<h2 class='ipsType_pageTitle ipsSpacer_top'>{lang="users_activity_feed_title" sprintf="$member->name"}</h2>

									<ol class='ipsDataList ipsDataList_large ipsSpacer_top cSearchActivity'>
										{{foreach $latestActivity as $activity}}
											{{$activityClass = get_class( $activity );}}
											{{$activityParts = explode( '\\', $activityClass );}}
											{{$application = $activityParts[1];}}

											<li class='ipsDataItem'>
												<div class='ipsDataItem_icon ipsType_center ipsPos_top ipsResponsive_hidePhone'>
													{template="userPhoto" group="global" app="core" params="$activity->author(), 'tiny'"}
													<br><br>
													<i class="fa fa-{$activity::$icon} ipsType_large ipsType_light" title="{lang="$activity::$title"}" data-ipsTooltip></i>
												</div>
												<div class='ipsDataItem_main'>
													<p class='ipsType_reset ipsType_break ipsContained ipsType_normal'>
														<strong>
															{{if $activity instanceof \IPS\Content\Comment or $activity instanceof \IPS\Content\Review}}
					  											{{$item = $activity->item();}}
					  											{lang="user_own_activity_comment" sprintf="$member->name" htmlsprintf="'<a href=\'' . $activity->url() . '\'>' . $activity->indefiniteArticle() . '</a>'"}: <a href='{$item->url()}'>{$item->mapped('title')}</a>
					  										{{else}}
					  											{lang="user_own_activity_item" sprintf="$member->name, $activity->indefiniteArticle()"} <a href='{$activity->container()->url()}'>{$activity->container()->_title}</a>
					  										{{endif}}
					  									</strong>
					  									&nbsp;&nbsp;
					  									<span class='ipsType_light ipsType_noBreak'>{datetime="$activity->mapped('date')"}</span>
					  								</p>
													{template="snippet" group="global" app="$application" params="$activity"}
												</div>
											</li>
										{{endforeach}}
									</ol>
								{{else}}
									<div class='ipsPad ipsType_center ipsType_large ipsType_light'>
										{lang="no_recent_activity" sprintf="$member->name"}
									</div>
								{{endif}}
							</div>

							{{foreach $mainFields as $group => $fields}}
								{{foreach $fields as $field => $value}}
									{{if $value}}
										<div id="ipsTabs_elProfileTabs_elField{$field}_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
											<h2 class='ipsType_pageTitle ipsSpacer_top'>{lang="$field"}</h2>
											<div class='ipsType_richText ipsType_normal ipsSpacer_top' data-controller='core.front.core.lightboxedImages'>
												{$value|raw}
											</div>
										</div>
									{{endif}}
								{{endforeach}}
							{{endforeach}}

							{{foreach $nodes as $type => $table}}
								{{if $_table = (string) $table and $table->pages}}
									<div id="ipsTabs_elProfileTabs_elNode{$type}_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
										{$_table|raw}
									</div>
								{{endif}}
							{{endforeach}}

			 				{{if settings.profile_comments AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
			 					<div id="ipsTabs_elProfileTabs_elUpdates_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
				 					<section data-controller='core.front.profile.toggleBlock'>
										{template="statuses" group="profile" app="core" params="$statuses, $statusCount, $form, $member"}
									</section>
								</div>
							{{endif}}
						</div>
					{{endif}}
				</div>
			</section>
		</div>
{{if !\IPS\Request::i()->isAjax()}}
	</div>
</div>
{{endif}}
    [5] => profile
    [6] => $member, $visitors, $sidebarFields, $mainFields, $statuses, $statusCount, $form, $nodes, $addWarningUrl, $followers=array(), $latestActivity
    [7] => 0
    [8] => 0
    [9] => 1
    [10] => 1
    [11] => 0
    [12] => 4213688f69cc22f254a6a550d0a2637f
    [13] => 1430921787
)

 | File                                                                       | Function                                                                      | Line No.          |
 |----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------|
 | /system/Db/Db.php                                                          | [IPS\Db\_Exception].__construct                                               | 389               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Db/Db.php                                                          | [IPS\_Db].preparedQuery                                                       | 604               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Theme/Theme.php                                                    | [IPS\_Db].insert                                                              | 2560              |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /applications/core/modules/admin/customization/themes.php                  | [IPS\_Theme].saveTemplate                                                     | 2201              |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 |                                                                            | [IPS\core\modules\admin\customization\_themes].saveTemplate                   |                   |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Dispatcher/Controller.php                                          | [].call_user_func                                                             | 85                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Node/Controller.php                                                | [IPS\Dispatcher\_Controller].execute                                          | 63                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Dispatcher/Dispatcher.php                                          | [IPS\Node\_Controller].execute                                                | 129               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /admin/index.php                                                           | [IPS\_Dispatcher].run                                                         | 13                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
------------------------------------------------------------------------
Wed, 06 May 2015 14:16:34 +0000 (Severity: 0)
178.76.195.226 - http://pixel.dev.grapheme.ru/en/forum/admin/?adsess=qsqj3aesjpi0s0t36osktkahc5&app=core&module=customization&controller=themes&id=1&do=saveTemplate
Duplicate entry '4213688f69cc22f254a6a550d0a2637f' for key 'template_unique_key'
INSERT INTO `ipb_core_theme_templates` ( `template_set_id`, `template_group`, `template_location`, `template_app`, `template_content`, `template_name`, `template_data`, `template_added_to`, `template_user_added`, `template_user_edited`, `template_removable`, `template_version`, `template_unique_key`, `template_updated` ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
Array
(
    [0] => 1
    [1] => profile
    [2] => front
    [3] => core
    [4] => {{if !\IPS\Request::i()->isAjax()}}
<!-- When altering this template be sure to also check for similar in the hovercard -->
<div data-controller='core.front.profile.main'>
	{template="profileHeader" app="core" location="front" group="profile" params="$member, false"}
	<br>

	<div data-role="profileContent">
{{endif}}
		<div class='ipsColumns ipsColumns_collapseTablet' data-controller="core.front.profile.body">
			<div class='ipsColumn ipsColumn_fixed ipsColumn_veryWide' id='elProfileInfoColumn'>
				<div class='ipsAreaBackground_light ipsPad'>
					{{if settings.reputation_enabled and settings.reputation_show_profile}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom'>
							{{if member.group['gbw_view_reps']}}
								<a href="{url="app=core&module=members&controller=profile&id={$member->member_id}&do=reputation" seoTemplate="profile_reputation" seoTitle="$member->members_seo_name"}" data-action="repLog" title="{lang="members_reputation" sprintf="$member->name"}">
							{{endif}}
								<div class='cProfileRepScore ipsPad_half {{if $member->pp_reputation_points > 1}}cProfileRepScore_positive{{elseif $member->pp_reputation_points < 0}}cProfileRepScore_negative{{else}}cProfileRepScore_neutral{{endif}}'>
									<h2 class='ipsType_minorHeading'>{lang="profile_reputation"}</h2>
									<span class='cProfileRepScore_points'>{$member->pp_reputation_points}</span>
									{{if $member->reputation()}}
										<span class='cProfileRepScore_title'>{$member->reputation()}</span>
									{{endif}}
									{{if $member->reputationImage()}}
										<div class='ipsAreaBackground_reset ipsAreaBackground_rounded ipsPad_half ipsType_center'>
											<img src='{$member->reputationImage()}' alt=''>
										</div>
									{{endif}}
								</div>
							{{if member.group['gbw_view_reps']}}
								<p class='ipsType_reset ipsPad_half ipsType_right ipsType_light ipsType_small'>
									{lang="replog_show_activity"} <i class='fa fa-caret-right'></i>
								</p>
							</a>
							{{endif}}
						</div>
					{{endif}}
					
					{{if \IPS\Settings::i()->warn_on AND ( \IPS\Member::loggedIn()->modPermission('mod_see_warn') or ( \IPS\Settings::i()->warn_show_own and \IPS\Member::loggedIn()->member_id == $member->member_id ) )}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom'>
							<div id='elWarningInfo' class='ipsPad {{if $member->mod_posts || $member->restrict_post || $member->temp_ban}}ipsAreaBackground_negative{{endif}} ipsClearfix'>
								<i class='ipsPos_left {{if $member->warn_level > 0 || $member->mod_posts || $member->restrict_post || $member->temp_ban}}fa fa-exclamation-triangle{{else}}fa fa-circle-o ipsType_light{{endif}}'></i>
								<div>
									<h2 class='ipsType_sectionHead'>{lang="member_warn_level" pluralize="$member->warn_level"}</h2>
									<br>
									{{if !$member->mod_posts && !$member->restrict_post && !$member->temp_ban}}
										<span>{lang="no_restrictions_applied"}</span>
										<br>
									{{else}}
										<span>{lang="restrictions_applied"}</span>
										<ul>
											{{if $member->mod_posts}}
												<li data-ipsTooltip title="{{if $member->mod_posts == -1}}{lang="moderation_modq_perm"}{{else}}{lang="moderation_modq_temp" sprintf="\IPS\DateTime::ts( $member->mod_posts )"}{{endif}}">
													{lang="moderation_modq"}
												</li>
											{{endif}}
											{{if $member->restrict_post}}
												<li data-ipsTooltip title="{{if $member->restrict_post == -1}}{lang="moderation_nopost_perm"}{{else}}{lang="moderation_nopost_temp" sprintf="\IPS\DateTime::ts( $member->restrict_post )"}{{endif}}">
													{lang="moderation_nopost"}
												</li>
											{{endif}}
											{{if $member->temp_ban}}
												<li data-ipsTooltip title="{{if $member->temp_ban == -1}}{lang="moderation_banned_perm"}{{else}}{lang="moderation_banned_temp" sprintf="\IPS\DateTime::ts( $member->temp_ban )"}{{endif}}">
													{lang="moderation_banned"}
												</li>
											{{endif}}
										</ul>
									{{endif}}
									{{if ( member.canWarn( $member ) || \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer') ) and $member->member_id != \IPS\Member::loggedIn()->member_id }}
										<br>
										<ul class='ipsList_inline'>
											{{if member.canWarn( $member )}}
												<li>
													<a href='{$addWarningUrl}' id='elWarnUserButton' data-ipsDialog data-ipsDialog-title="{lang="warn_member" sprintf="$member->name"}" class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="warn_member" sprintf="$member->name"}'>{lang="warn_user"}</a>
												</li>
											{{endif}}
											{{if \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer') and $member->member_id != \IPS\Member::loggedIn()->member_id}}
												<li>
													{{if $member->members_bitoptions['bw_is_spammer']}}
														<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=0" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="spam_unflag"}' data-confirm data-confirmSubMessage="{lang="spam_unflag_confirm"}">{lang="spam_unflag"}</a>
													{{else}}
														<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=1" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="spam_flag"}' data-confirm>{lang="spam_flag"}</a>
													{{endif}}
												</li>
											{{endif}}
										</ul>
									{{endif}}
								</div>
							</div>
							{{if count( $member->warnings( 1 ) )}}
								<a href='{url="app=core&module=system&controller=warnings&id={$member->member_id}" seoTemplate="warn_list" seoTitle="$member->members_seo_name"}' data-action="showRecentWarnings" title='{lang="see_recent_warnings"}'>
									<p class='ipsType_reset ipsPad_half ipsType_right ipsType_small ipsType_light ipsAreaBackground_light'>
										{lang="see_recent_warnings"} <i class='fa fa-caret-down'></i>
									</p>
								</a>
								<div data-role="recentWarnings" class='ipsPad_half ipsHide'>
									<ol class='ipsDataList'>
										{{foreach $member->warnings( 2 ) as $warning}}
											<li class="ipsDataItem" id='elWarningOverview_{$warning->id}'>
												<div class='ipsDataItem_generic ipsDataItem_size1 ipsType_center'>
													<a href="{url="app=core&module=system&controller=warnings&id={$member->member_id}&w={$warning->id}" seoTemplate="warn_view" seoTitle="$member->members_seo_name"}" data-ipsDialog data-ipsDialog-size='narrow' class="ipsType_blendLinks" data-ipsTooltip title='{lang="wan_action_points" pluralize="$warning->points"}'>
														<span class="ipsPoints">{$warning->points}</span>
													</a>
												</div>
												<div class='ipsDataItem_main'>
													<a href="{url="app=core&module=system&controller=warnings&id={$member->member_id}&w={$warning->id}" seoTemplate="warn_view" seoTitle="$member->members_seo_name"}" data-ipsDialog data-ipsDialog-showFrom='#elWarningOverview_{$warning->id}' data-ipsDialog-size='narrow' class="ipsType_blendLinks" title=''>
														<h4 class="ipsDataItem_title">{lang="core_warn_reason_{$warning->reason}"}</h4>
														<p class='ipsDataItem_meta ipsType_light'>
															{lang="byline" sprintf="\IPS\Member::load( $warning->moderator )->name"}{datetime="$warning->date"}
														</p>
													</a>
												</div>
											</li>
										{{endforeach}}
									</ol>
									<br>
									<p class='ipsType_reset ipsType_center ipsType_small'>
										<a href='{url="app=core&module=system&controller=warnings&id={$member->member_id}" seoTemplate="warn_list" seoTitle="$member->members_seo_name"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="see_all_warnings"}' data-ipsDialog data-ipsDialog-remoteVerify='false' data-ipsDialog-remoteSubmit='false' data-ipsDialog-title="{lang="members_warnings" sprintf="$member->name"}">{lang="see_all_c"}</a>
									</p>
								</div>
							{{endif}}
						</div>
					{{elseif \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer')}}
						{{if $member->members_bitoptions['bw_is_spammer']}}
							<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=0" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall ipsButton_fullWidth' title='{lang="spam_unflag"}' data-confirm data-confirmSubMessage="{lang="spam_unflag_confirm"}">{lang="spam_unflag"}</a>
						{{else}}
							<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=1" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall ipsButton_fullWidth' title='{lang="spam_flag"}' data-confirm>{lang="spam_flag"}</a>
						{{endif}}
					{{endif}}

					{{if count( $followers ) || \IPS\Member::loggedIn()->member_id === $member->member_id}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom' id='elFollowers' data-feedID='member-{$member->member_id}' data-controller='core.front.profile.followers'>
						  <h2 class="followers-header"><span>My followers</span></h2>
                          {template="followers" group="profile" app="core" params="$member, $followers"}
						</div>
	 				{{endif}}

					<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
						<h2 class='ipsType_sectionHead ipsType_reset'>{lang='profile_about' sprintf='$member->name'}</h2>
						{{if $member->group['g_icon'] }}
							<div class='ipsType_center ipsPad_half'><img src='{$member->group['g_icon']}' alt=''></div>
						{{endif}}
						<ul class='ipsDataList ipsDataList_reducedSpacing cProfileFields'>
							{{if $member->isOnline() AND $member->location}}
								<li class="ipsDataItem">
									<span class="ipsDataItem_generic ipsDataItem_size3 ipsType_break"><strong>{lang="online_users_location_lang"}</strong></span>
									<span class="ipsDataItem_main">{$member->location()|raw}</span>
								</li>
							{{endif}}
							{{if $member->member_title || $member->rank['title'] || $member->rank['image']}}
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="profile_rank"}</strong></span>
									<div class='ipsDataItem_generic'>
										{{if $member->member_title}}
											{$member->member_title}
											<br>
										{{elseif $member->rank['title']}}
											{$member->rank['title']}
											<br>
										{{endif}}
										{$member->rank['image']|raw}
									</div>
								</li>
							{{endif}}
							{{if $member->birthday}}
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="bday"}</strong></span>
									<span class='ipsDataItem_generic'>{$member->birthday}</span>
								</li>
							{{endif}}
						</ul>
					</div>
					{{foreach $sidebarFields as $group => $fields}}
						{{if count( $fields )}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
							{{if $group != 'core_pfieldgroups_0'}}
                                <h2 class='ipsType_sectionHead ipsType_reset'>{lang="$group"}</h2>
                            {{endif}}
							<ul class='ipsDataList ipsDataList_reducedSpacing cProfileFields'>
								{{foreach $fields as $field => $value}}
									<li class='ipsDataItem'>
										<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="$field"}</strong></span>
										<span class='ipsDataItem_generic'>{$value|raw}</span>
									</li>
								{{endforeach}}
							</ul>
						</div>
						{{endif}}
					{{endforeach}}
					{{if \IPS\Member::loggedIn()->modPermission('can_see_emails')}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
							<h2 class='ipsType_sectionHead ipsType_reset'>{lang="profile_contact"}</h2>
							<ul class='ipsDataList ipsDataList_reducedSpacing'>
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3'><strong>{lang="profile_email"}</strong></span>
									<span class='ipsDataItem_generic'><a href='mailto:{$member->email}' title='{lang="email_this_user"}'>{wordbreak="$member->email"}</a></span>
								</li>
							</ul>
						</div>
					{{endif}}
					{{if !empty( $visitors ) || \IPS\Member::loggedIn()->member_id == $member->member_id}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom' data-controller='core.front.profile.toggleBlock'>
							{template="recentVisitorsBlock" group="profile" params="$member, $visitors"}
						</div>
					{{endif}}
				</div>

			</div>
			<section class='ipsColumn ipsColumn_fluid'>
				<div class='ipsBox'>
					<!-- Single status -->
					{{if \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) && \IPS\Request::i()->status && \IPS\Request::i()->type == 'status' && settings.profile_comments and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
	 					<section data-controller='core.front.profile.toggleBlock' class='ipsPad'>
							{template="statuses" group="profile" app="core" params="$statuses, $statusCount, $form, $member"}
						</section>
					{{else}}
					<!-- Full profile content -->
						<div class='ipsTabs ipsTabs_small ipsClearfix' id='elProfileTabs' data-ipsTabBar data-ipsTabBar-stretch data-ipsTabBar-contentArea='#elProfileTabs_content'>
							<a href='#elProfileTabs' data-action='expandTabs'><i class='fa fa-caret-down'></i></a>
							<ul role="tablist">
								<li role="presentation">
									<a href='{$member->url()->setQueryString( 'tab', 'activity' )}' id='elActivity' class='ipsTabs_item ipsType_center {{if ( !\IPS\Request::i()->tab && !\IPS\Request::i()->status ) || \IPS\Request::i()->tab == 'activity'}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="users_activity_feed"}</a>
								</li>
								{{if settings.profile_comments AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
				 					<li role="presentation">
										<a href='{$member->url()->setQueryString( 'tab', 'statuses' )}' id='elUpdates' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'statuses' || \IPS\Request::i()->status}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="status_feed"}</a>
									</li>
								{{endif}}
								{{foreach $mainFields as $group => $fields}}
									{{foreach $fields as $field => $value}}
										{{if $value}}
											<li role="presentation">
												<a href='{$member->url()->setQueryString( 'tab', 'field_' . $field )}' id='elField{$field}' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'field_' . $field}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="$field"}</a>
											</li>
										{{endif}}
									{{endforeach}}
								{{endforeach}}
								{{foreach $nodes as $type => $table}}
									{{if $_table = (string) $table and $table->pages}}
										<li role="presentation">
											<a href='{$member->url()->setQueryString( 'tab', 'node_' . $type )}' id='elNode{$type}' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'node_' . $type}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="profile_{$type}"}</a>
										</li>
									{{endif}}
								{{endforeach}}			 				
							</ul>
						</div>
						<div id='elProfileTabs_content' class='ipsTabs_panels'>
							<div id="ipsTabs_elProfileTabs_elActivity_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
								{{if count( $latestActivity )}}
									<h2 class='ipsType_pageTitle ipsSpacer_top'>{lang="users_activity_feed_title" sprintf="$member->name"}</h2>

									<ol class='ipsDataList ipsDataList_large ipsSpacer_top cSearchActivity'>
										{{foreach $latestActivity as $activity}}
											{{$activityClass = get_class( $activity );}}
											{{$activityParts = explode( '\\', $activityClass );}}
											{{$application = $activityParts[1];}}

											<li class='ipsDataItem'>
												<div class='ipsDataItem_icon ipsType_center ipsPos_top ipsResponsive_hidePhone'>
													{template="userPhoto" group="global" app="core" params="$activity->author(), 'tiny'"}
													<br><br>
													<i class="fa fa-{$activity::$icon} ipsType_large ipsType_light" title="{lang="$activity::$title"}" data-ipsTooltip></i>
												</div>
												<div class='ipsDataItem_main'>
													<p class='ipsType_reset ipsType_break ipsContained ipsType_normal'>
														<strong>
															{{if $activity instanceof \IPS\Content\Comment or $activity instanceof \IPS\Content\Review}}
					  											{{$item = $activity->item();}}
					  											{lang="user_own_activity_comment" sprintf="$member->name" htmlsprintf="'<a href=\'' . $activity->url() . '\'>' . $activity->indefiniteArticle() . '</a>'"}: <a href='{$item->url()}'>{$item->mapped('title')}</a>
					  										{{else}}
					  											{lang="user_own_activity_item" sprintf="$member->name, $activity->indefiniteArticle()"} <a href='{$activity->container()->url()}'>{$activity->container()->_title}</a>
					  										{{endif}}
					  									</strong>
					  									&nbsp;&nbsp;
					  									<span class='ipsType_light ipsType_noBreak'>{datetime="$activity->mapped('date')"}</span>
					  								</p>
													{template="snippet" group="global" app="$application" params="$activity"}
												</div>
											</li>
										{{endforeach}}
									</ol>
								{{else}}
									<div class='ipsPad ipsType_center ipsType_large ipsType_light'>
										{lang="no_recent_activity" sprintf="$member->name"}
									</div>
								{{endif}}
							</div>

							{{foreach $mainFields as $group => $fields}}
								{{foreach $fields as $field => $value}}
									{{if $value}}
										<div id="ipsTabs_elProfileTabs_elField{$field}_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
											<h2 class='ipsType_pageTitle ipsSpacer_top'>{lang="$field"}</h2>
											<div class='ipsType_richText ipsType_normal ipsSpacer_top' data-controller='core.front.core.lightboxedImages'>
												{$value|raw}
											</div>
										</div>
									{{endif}}
								{{endforeach}}
							{{endforeach}}

							{{foreach $nodes as $type => $table}}
								{{if $_table = (string) $table and $table->pages}}
									<div id="ipsTabs_elProfileTabs_elNode{$type}_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
										{$_table|raw}
									</div>
								{{endif}}
							{{endforeach}}

			 				{{if settings.profile_comments AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
			 					<div id="ipsTabs_elProfileTabs_elUpdates_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
				 					<section data-controller='core.front.profile.toggleBlock'>
										{template="statuses" group="profile" app="core" params="$statuses, $statusCount, $form, $member"}
									</section>
								</div>
							{{endif}}
						</div>
					{{endif}}
				</div>
			</section>
		</div>
{{if !\IPS\Request::i()->isAjax()}}
	</div>
</div>
{{endif}}
    [5] => profile
    [6] => $member, $visitors, $sidebarFields, $mainFields, $statuses, $statusCount, $form, $nodes, $addWarningUrl, $followers=array(), $latestActivity
    [7] => 0
    [8] => 0
    [9] => 1
    [10] => 1
    [11] => 0
    [12] => 4213688f69cc22f254a6a550d0a2637f
    [13] => 1430921794
)

 | File                                                                       | Function                                                                      | Line No.          |
 |----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------|
 | /system/Db/Db.php                                                          | [IPS\Db\_Exception].__construct                                               | 389               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Db/Db.php                                                          | [IPS\_Db].preparedQuery                                                       | 604               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Theme/Theme.php                                                    | [IPS\_Db].insert                                                              | 2560              |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /applications/core/modules/admin/customization/themes.php                  | [IPS\_Theme].saveTemplate                                                     | 2201              |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 |                                                                            | [IPS\core\modules\admin\customization\_themes].saveTemplate                   |                   |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Dispatcher/Controller.php                                          | [].call_user_func                                                             | 85                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Node/Controller.php                                                | [IPS\Dispatcher\_Controller].execute                                          | 63                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Dispatcher/Dispatcher.php                                          | [IPS\Node\_Controller].execute                                                | 129               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /admin/index.php                                                           | [IPS\_Dispatcher].run                                                         | 13                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
------------------------------------------------------------------------
Wed, 06 May 2015 14:17:18 +0000 (Severity: 0)
178.76.195.226 - http://pixel.dev.grapheme.ru/en/forum/admin/?adsess=qsqj3aesjpi0s0t36osktkahc5&app=core&module=customization&controller=themes&id=1&do=saveTemplate
Duplicate entry '4213688f69cc22f254a6a550d0a2637f' for key 'template_unique_key'
INSERT INTO `ipb_core_theme_templates` ( `template_set_id`, `template_group`, `template_location`, `template_app`, `template_content`, `template_name`, `template_data`, `template_added_to`, `template_user_added`, `template_user_edited`, `template_removable`, `template_version`, `template_unique_key`, `template_updated` ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
Array
(
    [0] => 1
    [1] => profile
    [2] => front
    [3] => core
    [4] => {{if !\IPS\Request::i()->isAjax()}}
<!-- When altering this template be sure to also check for similar in the hovercard -->
<div data-controller='core.front.profile.main'>
	{template="profileHeader" app="core" location="front" group="profile" params="$member, false"}
	<br>

	<div data-role="profileContent">
{{endif}}
		<div class='ipsColumns ipsColumns_collapseTablet' data-controller="core.front.profile.body">
			<div class='ipsColumn ipsColumn_fixed ipsColumn_veryWide' id='elProfileInfoColumn'>
				<div class='ipsAreaBackground_light ipsPad'>
					{{if settings.reputation_enabled and settings.reputation_show_profile}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom'>
							{{if member.group['gbw_view_reps']}}
								<a href="{url="app=core&module=members&controller=profile&id={$member->member_id}&do=reputation" seoTemplate="profile_reputation" seoTitle="$member->members_seo_name"}" data-action="repLog" title="{lang="members_reputation" sprintf="$member->name"}">
							{{endif}}
								<div class='cProfileRepScore ipsPad_half {{if $member->pp_reputation_points > 1}}cProfileRepScore_positive{{elseif $member->pp_reputation_points < 0}}cProfileRepScore_negative{{else}}cProfileRepScore_neutral{{endif}}'>
									<h2 class='ipsType_minorHeading'>{lang="profile_reputation"}</h2>
									<span class='cProfileRepScore_points'>{$member->pp_reputation_points}</span>
									{{if $member->reputation()}}
										<span class='cProfileRepScore_title'>{$member->reputation()}</span>
									{{endif}}
									{{if $member->reputationImage()}}
										<div class='ipsAreaBackground_reset ipsAreaBackground_rounded ipsPad_half ipsType_center'>
											<img src='{$member->reputationImage()}' alt=''>
										</div>
									{{endif}}
								</div>
							{{if member.group['gbw_view_reps']}}
								<p class='ipsType_reset ipsPad_half ipsType_right ipsType_light ipsType_small'>
									{lang="replog_show_activity"} <i class='fa fa-caret-right'></i>
								</p>
							</a>
							{{endif}}
						</div>
					{{endif}}
					
					{{if \IPS\Settings::i()->warn_on AND ( \IPS\Member::loggedIn()->modPermission('mod_see_warn') or ( \IPS\Settings::i()->warn_show_own and \IPS\Member::loggedIn()->member_id == $member->member_id ) )}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom'>
							<div id='elWarningInfo' class='ipsPad {{if $member->mod_posts || $member->restrict_post || $member->temp_ban}}ipsAreaBackground_negative{{endif}} ipsClearfix'>
								<i class='ipsPos_left {{if $member->warn_level > 0 || $member->mod_posts || $member->restrict_post || $member->temp_ban}}fa fa-exclamation-triangle{{else}}fa fa-circle-o ipsType_light{{endif}}'></i>
								<div>
									<h2 class='ipsType_sectionHead'>{lang="member_warn_level" pluralize="$member->warn_level"}</h2>
									<br>
									{{if !$member->mod_posts && !$member->restrict_post && !$member->temp_ban}}
										<span>{lang="no_restrictions_applied"}</span>
										<br>
									{{else}}
										<span>{lang="restrictions_applied"}</span>
										<ul>
											{{if $member->mod_posts}}
												<li data-ipsTooltip title="{{if $member->mod_posts == -1}}{lang="moderation_modq_perm"}{{else}}{lang="moderation_modq_temp" sprintf="\IPS\DateTime::ts( $member->mod_posts )"}{{endif}}">
													{lang="moderation_modq"}
												</li>
											{{endif}}
											{{if $member->restrict_post}}
												<li data-ipsTooltip title="{{if $member->restrict_post == -1}}{lang="moderation_nopost_perm"}{{else}}{lang="moderation_nopost_temp" sprintf="\IPS\DateTime::ts( $member->restrict_post )"}{{endif}}">
													{lang="moderation_nopost"}
												</li>
											{{endif}}
											{{if $member->temp_ban}}
												<li data-ipsTooltip title="{{if $member->temp_ban == -1}}{lang="moderation_banned_perm"}{{else}}{lang="moderation_banned_temp" sprintf="\IPS\DateTime::ts( $member->temp_ban )"}{{endif}}">
													{lang="moderation_banned"}
												</li>
											{{endif}}
										</ul>
									{{endif}}
									{{if ( member.canWarn( $member ) || \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer') ) and $member->member_id != \IPS\Member::loggedIn()->member_id }}
										<br>
										<ul class='ipsList_inline'>
											{{if member.canWarn( $member )}}
												<li>
													<a href='{$addWarningUrl}' id='elWarnUserButton' data-ipsDialog data-ipsDialog-title="{lang="warn_member" sprintf="$member->name"}" class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="warn_member" sprintf="$member->name"}'>{lang="warn_user"}</a>
												</li>
											{{endif}}
											{{if \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer') and $member->member_id != \IPS\Member::loggedIn()->member_id}}
												<li>
													{{if $member->members_bitoptions['bw_is_spammer']}}
														<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=0" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="spam_unflag"}' data-confirm data-confirmSubMessage="{lang="spam_unflag_confirm"}">{lang="spam_unflag"}</a>
													{{else}}
														<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=1" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="spam_flag"}' data-confirm>{lang="spam_flag"}</a>
													{{endif}}
												</li>
											{{endif}}
										</ul>
									{{endif}}
								</div>
							</div>
							{{if count( $member->warnings( 1 ) )}}
								<a href='{url="app=core&module=system&controller=warnings&id={$member->member_id}" seoTemplate="warn_list" seoTitle="$member->members_seo_name"}' data-action="showRecentWarnings" title='{lang="see_recent_warnings"}'>
									<p class='ipsType_reset ipsPad_half ipsType_right ipsType_small ipsType_light ipsAreaBackground_light'>
										{lang="see_recent_warnings"} <i class='fa fa-caret-down'></i>
									</p>
								</a>
								<div data-role="recentWarnings" class='ipsPad_half ipsHide'>
									<ol class='ipsDataList'>
										{{foreach $member->warnings( 2 ) as $warning}}
											<li class="ipsDataItem" id='elWarningOverview_{$warning->id}'>
												<div class='ipsDataItem_generic ipsDataItem_size1 ipsType_center'>
													<a href="{url="app=core&module=system&controller=warnings&id={$member->member_id}&w={$warning->id}" seoTemplate="warn_view" seoTitle="$member->members_seo_name"}" data-ipsDialog data-ipsDialog-size='narrow' class="ipsType_blendLinks" data-ipsTooltip title='{lang="wan_action_points" pluralize="$warning->points"}'>
														<span class="ipsPoints">{$warning->points}</span>
													</a>
												</div>
												<div class='ipsDataItem_main'>
													<a href="{url="app=core&module=system&controller=warnings&id={$member->member_id}&w={$warning->id}" seoTemplate="warn_view" seoTitle="$member->members_seo_name"}" data-ipsDialog data-ipsDialog-showFrom='#elWarningOverview_{$warning->id}' data-ipsDialog-size='narrow' class="ipsType_blendLinks" title=''>
														<h4 class="ipsDataItem_title">{lang="core_warn_reason_{$warning->reason}"}</h4>
														<p class='ipsDataItem_meta ipsType_light'>
															{lang="byline" sprintf="\IPS\Member::load( $warning->moderator )->name"}{datetime="$warning->date"}
														</p>
													</a>
												</div>
											</li>
										{{endforeach}}
									</ol>
									<br>
									<p class='ipsType_reset ipsType_center ipsType_small'>
										<a href='{url="app=core&module=system&controller=warnings&id={$member->member_id}" seoTemplate="warn_list" seoTitle="$member->members_seo_name"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="see_all_warnings"}' data-ipsDialog data-ipsDialog-remoteVerify='false' data-ipsDialog-remoteSubmit='false' data-ipsDialog-title="{lang="members_warnings" sprintf="$member->name"}">{lang="see_all_c"}</a>
									</p>
								</div>
							{{endif}}
						</div>
					{{elseif \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer')}}
						{{if $member->members_bitoptions['bw_is_spammer']}}
							<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=0" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall ipsButton_fullWidth' title='{lang="spam_unflag"}' data-confirm data-confirmSubMessage="{lang="spam_unflag_confirm"}">{lang="spam_unflag"}</a>
						{{else}}
							<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=1" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall ipsButton_fullWidth' title='{lang="spam_flag"}' data-confirm>{lang="spam_flag"}</a>
						{{endif}}
					{{endif}}

					{{if count( $followers ) || \IPS\Member::loggedIn()->member_id === $member->member_id}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom' id='elFollowers' data-feedID='member-{$member->member_id}' data-controller='core.front.profile.followers'>
						  <h2 class="followers-header"><span>My followers</span></h2>
                          {template="followers" group="profile" app="core" params="$member, $followers"}
						</div>
	 				{{endif}}

					<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
						<h2 class='ipsType_sectionHead ipsType_reset'>{lang='profile_about' sprintf='$member->name'}</h2>
						{{if $member->group['g_icon'] }}
							<div class='ipsType_center ipsPad_half'><img src='{$member->group['g_icon']}' alt=''></div>
						{{endif}}
						<ul class='ipsDataList ipsDataList_reducedSpacing cProfileFields'>
							{{if $member->isOnline() AND $member->location}}
								<li class="ipsDataItem">
									<span class="ipsDataItem_generic ipsDataItem_size3 ipsType_break"><strong>{lang="online_users_location_lang"}</strong></span>
									<span class="ipsDataItem_main">{$member->location()|raw}</span>
								</li>
							{{endif}}
							{{if $member->member_title || $member->rank['title'] || $member->rank['image']}}
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="profile_rank"}</strong></span>
									<div class='ipsDataItem_generic'>
										{{if $member->member_title}}
											{$member->member_title}
											<br>
										{{elseif $member->rank['title']}}
											{$member->rank['title']}
											<br>
										{{endif}}
										{$member->rank['image']|raw}
									</div>
								</li>
							{{endif}}
							{{if $member->birthday}}
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="bday"}</strong></span>
									<span class='ipsDataItem_generic'>{$member->birthday}</span>
								</li>
							{{endif}}
						</ul>
					</div>
					{{foreach $sidebarFields as $group => $fields}}
						{{if count( $fields )}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
							{{if $group != 'core_pfieldgroups_0'}}
                                <h2 class='ipsType_sectionHead ipsType_reset'>{lang="$group"}</h2>
                            {{endif}}
							<ul class='ipsDataList ipsDataList_reducedSpacing cProfileFields'>
								{{foreach $fields as $field => $value}}
									<li class='ipsDataItem'>
										<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="$field"}</strong></span>
										<span class='ipsDataItem_generic'>{$value|raw}</span>
									</li>
								{{endforeach}}
							</ul>
						</div>
						{{endif}}
					{{endforeach}}
					{{if \IPS\Member::loggedIn()->modPermission('can_see_emails')}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
							<h2 class='ipsType_sectionHead ipsType_reset'>{lang="profile_contact"}</h2>
							<ul class='ipsDataList ipsDataList_reducedSpacing'>
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3'><strong>{lang="profile_email"}</strong></span>
									<span class='ipsDataItem_generic'><a href='mailto:{$member->email}' title='{lang="email_this_user"}'>{wordbreak="$member->email"}</a></span>
								</li>
							</ul>
						</div>
					{{endif}}
					{{if !empty( $visitors ) || \IPS\Member::loggedIn()->member_id == $member->member_id}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom' data-controller='core.front.profile.toggleBlock'>
							{template="recentVisitorsBlock" group="profile" params="$member, $visitors"}
						</div>
					{{endif}}
				</div>

			</div>
			<section class='ipsColumn ipsColumn_fluid'>
				<div class='ipsBox'>
					<!-- Single status -->
					{{if \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) && \IPS\Request::i()->status && \IPS\Request::i()->type == 'status' && settings.profile_comments and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
	 					<section data-controller='core.front.profile.toggleBlock' class='ipsPad'>
							{template="statuses" group="profile" app="core" params="$statuses, $statusCount, $form, $member"}
						</section>
					{{else}}
					<!-- Full profile content -->
						<div class='ipsTabs ipsTabs_small ipsClearfix' id='elProfileTabs' data-ipsTabBar data-ipsTabBar-stretch data-ipsTabBar-contentArea='#elProfileTabs_content'>
							<a href='#elProfileTabs' data-action='expandTabs'><i class='fa fa-caret-down'></i></a>
							<ul role="tablist">
								<li role="presentation">
									<a href='{$member->url()->setQueryString( 'tab', 'activity' )}' id='elActivity' class='ipsTabs_item ipsType_center {{if ( !\IPS\Request::i()->tab && !\IPS\Request::i()->status ) || \IPS\Request::i()->tab == 'activity'}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="users_activity_feed"}</a>
								</li>
								{{if settings.profile_comments AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
				 					<li role="presentation">
										<a href='{$member->url()->setQueryString( 'tab', 'statuses' )}' id='elUpdates' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'statuses' || \IPS\Request::i()->status}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="status_feed"}</a>
									</li>
								{{endif}}
								{{foreach $mainFields as $group => $fields}}
									{{foreach $fields as $field => $value}}
										{{if $value}}
											<li role="presentation">
												<a href='{$member->url()->setQueryString( 'tab', 'field_' . $field )}' id='elField{$field}' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'field_' . $field}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="$field"}</a>
											</li>
										{{endif}}
									{{endforeach}}
								{{endforeach}}
								{{foreach $nodes as $type => $table}}
									{{if $_table = (string) $table and $table->pages}}
										<li role="presentation">
											<a href='{$member->url()->setQueryString( 'tab', 'node_' . $type )}' id='elNode{$type}' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'node_' . $type}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="profile_{$type}"}</a>
										</li>
									{{endif}}
								{{endforeach}}			 				
							</ul>
						</div>
						<div id='elProfileTabs_content' class='ipsTabs_panels'>
							<div id="ipsTabs_elProfileTabs_elActivity_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
								{{if count( $latestActivity )}}
									<h2 class='ipsType_pageTitle ipsSpacer_top'>{lang="users_activity_feed_title" sprintf="$member->name"}</h2>

									<ol class='ipsDataList ipsDataList_large ipsSpacer_top cSearchActivity'>
										{{foreach $latestActivity as $activity}}
											{{$activityClass = get_class( $activity );}}
											{{$activityParts = explode( '\\', $activityClass );}}
											{{$application = $activityParts[1];}}

											<li class='ipsDataItem'>
												<div class='ipsDataItem_icon ipsType_center ipsPos_top ipsResponsive_hidePhone'>
													{template="userPhoto" group="global" app="core" params="$activity->author(), 'tiny'"}
													<br><br>
													<i class="fa fa-{$activity::$icon} ipsType_large ipsType_light" title="{lang="$activity::$title"}" data-ipsTooltip></i>
												</div>
												<div class='ipsDataItem_main'>
													<p class='ipsType_reset ipsType_break ipsContained ipsType_normal'>
														<strong>
															{{if $activity instanceof \IPS\Content\Comment or $activity instanceof \IPS\Content\Review}}
					  											{{$item = $activity->item();}}
					  											{lang="user_own_activity_comment" sprintf="$member->name" htmlsprintf="'<a href=\'' . $activity->url() . '\'>' . $activity->indefiniteArticle() . '</a>'"}: <a href='{$item->url()}'>{$item->mapped('title')}</a>
					  										{{else}}
					  											{lang="user_own_activity_item" sprintf="$member->name, $activity->indefiniteArticle()"} <a href='{$activity->container()->url()}'>{$activity->container()->_title}</a>
					  										{{endif}}
					  									</strong>
					  									&nbsp;&nbsp;
					  									<span class='ipsType_light ipsType_noBreak'>{datetime="$activity->mapped('date')"}</span>
					  								</p>
													{template="snippet" group="global" app="$application" params="$activity"}
												</div>
											</li>
										{{endforeach}}
									</ol>
								{{else}}
									<div class='ipsPad ipsType_center ipsType_large ipsType_light'>
										{lang="no_recent_activity" sprintf="$member->name"}
									</div>
								{{endif}}
							</div>

							{{foreach $mainFields as $group => $fields}}
								{{foreach $fields as $field => $value}}
									{{if $value}}
										<div id="ipsTabs_elProfileTabs_elField{$field}_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
											<h2 class='ipsType_pageTitle ipsSpacer_top'>{lang="$field"}</h2>
											<div class='ipsType_richText ipsType_normal ipsSpacer_top' data-controller='core.front.core.lightboxedImages'>
												{$value|raw}
											</div>
										</div>
									{{endif}}
								{{endforeach}}
							{{endforeach}}

							{{foreach $nodes as $type => $table}}
								{{if $_table = (string) $table and $table->pages}}
									<div id="ipsTabs_elProfileTabs_elNode{$type}_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
										{$_table|raw}
									</div>
								{{endif}}
							{{endforeach}}

			 				{{if settings.profile_comments AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
			 					<div id="ipsTabs_elProfileTabs_elUpdates_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
				 					<section data-controller='core.front.profile.toggleBlock'>
										{template="statuses" group="profile" app="core" params="$statuses, $statusCount, $form, $member"}
									</section>
								</div>
							{{endif}}
						</div>
					{{endif}}
				</div>
			</section>
		</div>
{{if !\IPS\Request::i()->isAjax()}}
	</div>
</div>
{{endif}}
    [5] => profile
    [6] => $member, $visitors, $sidebarFields, $mainFields, $statuses, $statusCount, $form, $nodes, $addWarningUrl, $followers=array(), $latestActivity
    [7] => 0
    [8] => 0
    [9] => 1
    [10] => 1
    [11] => 0
    [12] => 4213688f69cc22f254a6a550d0a2637f
    [13] => 1430921838
)

 | File                                                                       | Function                                                                      | Line No.          |
 |----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------|
 | /system/Db/Db.php                                                          | [IPS\Db\_Exception].__construct                                               | 389               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Db/Db.php                                                          | [IPS\_Db].preparedQuery                                                       | 604               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Theme/Theme.php                                                    | [IPS\_Db].insert                                                              | 2560              |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /applications/core/modules/admin/customization/themes.php                  | [IPS\_Theme].saveTemplate                                                     | 2201              |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 |                                                                            | [IPS\core\modules\admin\customization\_themes].saveTemplate                   |                   |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Dispatcher/Controller.php                                          | [].call_user_func                                                             | 85                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Node/Controller.php                                                | [IPS\Dispatcher\_Controller].execute                                          | 63                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Dispatcher/Dispatcher.php                                          | [IPS\Node\_Controller].execute                                                | 129               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /admin/index.php                                                           | [IPS\_Dispatcher].run                                                         | 13                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
------------------------------------------------------------------------
Wed, 06 May 2015 14:21:55 +0000 (Severity: 0)
178.76.195.226 - http://pixel.dev.grapheme.ru/en/forum/admin/?adsess=qsqj3aesjpi0s0t36osktkahc5&app=core&module=customization&controller=themes&id=1&do=saveTemplate
Duplicate entry '4213688f69cc22f254a6a550d0a2637f' for key 'template_unique_key'
INSERT INTO `ipb_core_theme_templates` ( `template_set_id`, `template_group`, `template_location`, `template_app`, `template_content`, `template_name`, `template_data`, `template_added_to`, `template_user_added`, `template_user_edited`, `template_removable`, `template_version`, `template_unique_key`, `template_updated` ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
Array
(
    [0] => 1
    [1] => profile
    [2] => front
    [3] => core
    [4] => {{if !\IPS\Request::i()->isAjax()}}
<!-- When altering this template be sure to also check for similar in the hovercard -->
<div data-controller='core.front.profile.main'>
	{template="profileHeader" app="core" location="front" group="profile" params="$member, false"}
	<br>

	<div data-role="profileContent">
{{endif}}
		<div class='ipsColumns ipsColumns_collapseTablet' data-controller="core.front.profile.body">
			<div class='ipsColumn ipsColumn_fixed ipsColumn_veryWide' id='elProfileInfoColumn'>
				<div class='ipsAreaBackground_light ipsPad'>
					{{if settings.reputation_enabled and settings.reputation_show_profile}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom'>
							{{if member.group['gbw_view_reps']}}
								<a href="{url="app=core&module=members&controller=profile&id={$member->member_id}&do=reputation" seoTemplate="profile_reputation" seoTitle="$member->members_seo_name"}" data-action="repLog" title="{lang="members_reputation" sprintf="$member->name"}">
							{{endif}}
								<div class='cProfileRepScore ipsPad_half {{if $member->pp_reputation_points > 1}}cProfileRepScore_positive{{elseif $member->pp_reputation_points < 0}}cProfileRepScore_negative{{else}}cProfileRepScore_neutral{{endif}}'>
									<h2 class='ipsType_minorHeading'>{lang="profile_reputation"}</h2>
									<span class='cProfileRepScore_points'>{$member->pp_reputation_points}</span>
									{{if $member->reputation()}}
										<span class='cProfileRepScore_title'>{$member->reputation()}</span>
									{{endif}}
									{{if $member->reputationImage()}}
										<div class='ipsAreaBackground_reset ipsAreaBackground_rounded ipsPad_half ipsType_center'>
											<img src='{$member->reputationImage()}' alt=''>
										</div>
									{{endif}}
								</div>
							{{if member.group['gbw_view_reps']}}
								<p class='ipsType_reset ipsPad_half ipsType_right ipsType_light ipsType_small'>
									{lang="replog_show_activity"} <i class='fa fa-caret-right'></i>
								</p>
							</a>
							{{endif}}
						</div>
					{{endif}}
					
					{{if \IPS\Settings::i()->warn_on AND ( \IPS\Member::loggedIn()->modPermission('mod_see_warn') or ( \IPS\Settings::i()->warn_show_own and \IPS\Member::loggedIn()->member_id == $member->member_id ) )}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom'>
							<div id='elWarningInfo' class='ipsPad {{if $member->mod_posts || $member->restrict_post || $member->temp_ban}}ipsAreaBackground_negative{{endif}} ipsClearfix'>
								<i class='ipsPos_left {{if $member->warn_level > 0 || $member->mod_posts || $member->restrict_post || $member->temp_ban}}fa fa-exclamation-triangle{{else}}fa fa-circle-o ipsType_light{{endif}}'></i>
								<div>
									<h2 class='ipsType_sectionHead'>{lang="member_warn_level" pluralize="$member->warn_level"}</h2>
									<br>
									{{if !$member->mod_posts && !$member->restrict_post && !$member->temp_ban}}
										<span>{lang="no_restrictions_applied"}</span>
										<br>
									{{else}}
										<span>{lang="restrictions_applied"}</span>
										<ul>
											{{if $member->mod_posts}}
												<li data-ipsTooltip title="{{if $member->mod_posts == -1}}{lang="moderation_modq_perm"}{{else}}{lang="moderation_modq_temp" sprintf="\IPS\DateTime::ts( $member->mod_posts )"}{{endif}}">
													{lang="moderation_modq"}
												</li>
											{{endif}}
											{{if $member->restrict_post}}
												<li data-ipsTooltip title="{{if $member->restrict_post == -1}}{lang="moderation_nopost_perm"}{{else}}{lang="moderation_nopost_temp" sprintf="\IPS\DateTime::ts( $member->restrict_post )"}{{endif}}">
													{lang="moderation_nopost"}
												</li>
											{{endif}}
											{{if $member->temp_ban}}
												<li data-ipsTooltip title="{{if $member->temp_ban == -1}}{lang="moderation_banned_perm"}{{else}}{lang="moderation_banned_temp" sprintf="\IPS\DateTime::ts( $member->temp_ban )"}{{endif}}">
													{lang="moderation_banned"}
												</li>
											{{endif}}
										</ul>
									{{endif}}
									{{if ( member.canWarn( $member ) || \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer') ) and $member->member_id != \IPS\Member::loggedIn()->member_id }}
										<br>
										<ul class='ipsList_inline'>
											{{if member.canWarn( $member )}}
												<li>
													<a href='{$addWarningUrl}' id='elWarnUserButton' data-ipsDialog data-ipsDialog-title="{lang="warn_member" sprintf="$member->name"}" class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="warn_member" sprintf="$member->name"}'>{lang="warn_user"}</a>
												</li>
											{{endif}}
											{{if \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer') and $member->member_id != \IPS\Member::loggedIn()->member_id}}
												<li>
													{{if $member->members_bitoptions['bw_is_spammer']}}
														<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=0" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="spam_unflag"}' data-confirm data-confirmSubMessage="{lang="spam_unflag_confirm"}">{lang="spam_unflag"}</a>
													{{else}}
														<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=1" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="spam_flag"}' data-confirm>{lang="spam_flag"}</a>
													{{endif}}
												</li>
											{{endif}}
										</ul>
									{{endif}}
								</div>
							</div>
							{{if count( $member->warnings( 1 ) )}}
								<a href='{url="app=core&module=system&controller=warnings&id={$member->member_id}" seoTemplate="warn_list" seoTitle="$member->members_seo_name"}' data-action="showRecentWarnings" title='{lang="see_recent_warnings"}'>
									<p class='ipsType_reset ipsPad_half ipsType_right ipsType_small ipsType_light ipsAreaBackground_light'>
										{lang="see_recent_warnings"} <i class='fa fa-caret-down'></i>
									</p>
								</a>
								<div data-role="recentWarnings" class='ipsPad_half ipsHide'>
									<ol class='ipsDataList'>
										{{foreach $member->warnings( 2 ) as $warning}}
											<li class="ipsDataItem" id='elWarningOverview_{$warning->id}'>
												<div class='ipsDataItem_generic ipsDataItem_size1 ipsType_center'>
													<a href="{url="app=core&module=system&controller=warnings&id={$member->member_id}&w={$warning->id}" seoTemplate="warn_view" seoTitle="$member->members_seo_name"}" data-ipsDialog data-ipsDialog-size='narrow' class="ipsType_blendLinks" data-ipsTooltip title='{lang="wan_action_points" pluralize="$warning->points"}'>
														<span class="ipsPoints">{$warning->points}</span>
													</a>
												</div>
												<div class='ipsDataItem_main'>
													<a href="{url="app=core&module=system&controller=warnings&id={$member->member_id}&w={$warning->id}" seoTemplate="warn_view" seoTitle="$member->members_seo_name"}" data-ipsDialog data-ipsDialog-showFrom='#elWarningOverview_{$warning->id}' data-ipsDialog-size='narrow' class="ipsType_blendLinks" title=''>
														<h4 class="ipsDataItem_title">{lang="core_warn_reason_{$warning->reason}"}</h4>
														<p class='ipsDataItem_meta ipsType_light'>
															{lang="byline" sprintf="\IPS\Member::load( $warning->moderator )->name"}{datetime="$warning->date"}
														</p>
													</a>
												</div>
											</li>
										{{endforeach}}
									</ol>
									<br>
									<p class='ipsType_reset ipsType_center ipsType_small'>
										<a href='{url="app=core&module=system&controller=warnings&id={$member->member_id}" seoTemplate="warn_list" seoTitle="$member->members_seo_name"}' class='ipsButton ipsButton_light ipsButton_verySmall' title='{lang="see_all_warnings"}' data-ipsDialog data-ipsDialog-remoteVerify='false' data-ipsDialog-remoteSubmit='false' data-ipsDialog-title="{lang="members_warnings" sprintf="$member->name"}">{lang="see_all_c"}</a>
									</p>
								</div>
							{{endif}}
						</div>
					{{elseif \IPS\Member::loggedIn()->modPermission('can_flag_as_spammer')}}
						{{if $member->members_bitoptions['bw_is_spammer']}}
							<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=0" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall ipsButton_fullWidth' title='{lang="spam_unflag"}' data-confirm data-confirmSubMessage="{lang="spam_unflag_confirm"}">{lang="spam_unflag"}</a>
						{{else}}
							<a href='{url="app=core&module=system&controller=moderation&id={$member->member_id}&s=1" seoTemplate="flag_as_spammer" seoTitle="$member->members_seo_name" csrf="true"}' class='ipsButton ipsButton_light ipsButton_verySmall ipsButton_fullWidth' title='{lang="spam_flag"}' data-confirm>{lang="spam_flag"}</a>
						{{endif}}
					{{endif}}

					{{if count( $followers ) || \IPS\Member::loggedIn()->member_id === $member->member_id}}
						<div class='cProfileSidebarBlock ipsBox ipsSpacer_bottom' id='elFollowers' data-feedID='member-{$member->member_id}' data-controller='core.front.profile.followers'>
						  <h2 class="followers-header"><span>My followers</span></h2>
                          {template="followers" group="profile" app="core" params="$member, $followers"}
						</div>
	 				{{endif}}

					<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
						<h2 class='ipsType_sectionHead ipsType_reset'>{lang='profile_about' sprintf='$member->name'}</h2>
						{{if $member->group['g_icon'] }}
							<div class='ipsType_center ipsPad_half'><img src='{$member->group['g_icon']}' alt=''></div>
						{{endif}}
						<ul class='ipsDataList ipsDataList_reducedSpacing cProfileFields'>
							{{if $member->isOnline() AND $member->location}}
								<li class="ipsDataItem">
									<span class="ipsDataItem_generic ipsDataItem_size3 ipsType_break"><strong>{lang="online_users_location_lang"}</strong></span>
									<span class="ipsDataItem_main">{$member->location()|raw}</span>
								</li>
							{{endif}}
							{{if $member->member_title || $member->rank['title'] || $member->rank['image']}}
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="profile_rank"}</strong></span>
									<div class='ipsDataItem_generic'>
										{{if $member->member_title}}
											{$member->member_title}
											<br>
										{{elseif $member->rank['title']}}
											{$member->rank['title']}
											<br>
										{{endif}}
										{$member->rank['image']|raw}
									</div>
								</li>
							{{endif}}
							{{if $member->birthday}}
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="bday"}</strong></span>
									<span class='ipsDataItem_generic'>{$member->birthday}</span>
								</li>
							{{endif}}
						</ul>
					</div>
					{{foreach $sidebarFields as $group => $fields}}
						{{if count( $fields )}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
							{{if $group != 'core_pfieldgroups_0'}}
                                <h2 class='ipsType_sectionHead ipsType_reset'>{lang="$group"}</h2>
                            {{endif}}
							<ul class='ipsDataList ipsDataList_reducedSpacing cProfileFields'>
								{{foreach $fields as $field => $value}}
									<li class='ipsDataItem'>
										<span class='ipsDataItem_generic ipsDataItem_size3 ipsType_break'><strong>{lang="$field"}</strong></span>
										<span class='ipsDataItem_generic'>{$value|raw}</span>
									</li>
								{{endforeach}}
							</ul>
						</div>
						{{endif}}
					{{endforeach}}
					{{if \IPS\Member::loggedIn()->modPermission('can_see_emails')}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom'>
							<h2 class='ipsType_sectionHead ipsType_reset'>{lang="profile_contact"}</h2>
							<ul class='ipsDataList ipsDataList_reducedSpacing'>
								<li class='ipsDataItem'>
									<span class='ipsDataItem_generic ipsDataItem_size3'><strong>{lang="profile_email"}</strong></span>
									<span class='ipsDataItem_generic'><a href='mailto:{$member->email}' title='{lang="email_this_user"}'>{wordbreak="$member->email"}</a></span>
								</li>
							</ul>
						</div>
					{{endif}}
					{{if !empty( $visitors ) || \IPS\Member::loggedIn()->member_id == $member->member_id}}
						<div class='cProfileSidebarBlock ipsPad ipsBox ipsSpacer_bottom' data-controller='core.front.profile.toggleBlock'>
							{template="recentVisitorsBlock" group="profile" params="$member, $visitors"}
						</div>
					{{endif}}
				</div>

			</div>
			<section class='ipsColumn ipsColumn_fluid'>
				<div class='ipsBox'>
					<!-- Single status -->
					{{if \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) && \IPS\Request::i()->status && \IPS\Request::i()->type == 'status' && settings.profile_comments and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
	 					<section data-controller='core.front.profile.toggleBlock' class='ipsPad'>
							{template="statuses" group="profile" app="core" params="$statuses, $statusCount, $form, $member"}
						</section>
					{{else}}
					<!-- Full profile content -->
						<div class='ipsTabs ipsTabs_small ipsClearfix' id='elProfileTabs' data-ipsTabBar data-ipsTabBar-stretch data-ipsTabBar-contentArea='#elProfileTabs_content'>
							<a href='#elProfileTabs' data-action='expandTabs'><i class='fa fa-caret-down'></i></a>
							<ul role="tablist">
								<li role="presentation">
									<a href='{$member->url()->setQueryString( 'tab', 'activity' )}' id='elActivity' class='ipsTabs_item ipsType_center {{if ( !\IPS\Request::i()->tab && !\IPS\Request::i()->status ) || \IPS\Request::i()->tab == 'activity'}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="users_activity_feed"}</a>
								</li>
								{{if settings.profile_comments AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
				 					<li role="presentation">
										<a href='{$member->url()->setQueryString( 'tab', 'statuses' )}' id='elUpdates' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'statuses' || \IPS\Request::i()->status}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="status_feed"}</a>
									</li>
								{{endif}}
								{{foreach $mainFields as $group => $fields}}
									{{foreach $fields as $field => $value}}
										{{if $value}}
											<li role="presentation">
												<a href='{$member->url()->setQueryString( 'tab', 'field_' . $field )}' id='elField{$field}' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'field_' . $field}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="$field"}</a>
											</li>
										{{endif}}
									{{endforeach}}
								{{endforeach}}
								{{foreach $nodes as $type => $table}}
									{{if $_table = (string) $table and $table->pages}}
										<li role="presentation">
											<a href='{$member->url()->setQueryString( 'tab', 'node_' . $type )}' id='elNode{$type}' class='ipsTabs_item ipsType_center {{if \IPS\Request::i()->tab == 'node_' . $type}}ipsTabs_activeItem{{endif}}' aria-selected='false'>{lang="profile_{$type}"}</a>
										</li>
									{{endif}}
								{{endforeach}}			 				
							</ul>
						</div>
						<div id='elProfileTabs_content' class='ipsTabs_panels'>
							<div id="ipsTabs_elProfileTabs_elActivity_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
								{{if count( $latestActivity )}}
									<h2 class='ipsType_pageTitle ipsSpacer_top'>{lang="users_activity_feed_title" sprintf="$member->name"}</h2>

									<ol class='ipsDataList ipsDataList_large ipsSpacer_top cSearchActivity'>
										{{foreach $latestActivity as $activity}}
											{{$activityClass = get_class( $activity );}}
											{{$activityParts = explode( '\\', $activityClass );}}
											{{$application = $activityParts[1];}}

											<li class='ipsDataItem'>
												<div class='ipsDataItem_icon ipsType_center ipsPos_top ipsResponsive_hidePhone'>
													{template="userPhoto" group="global" app="core" params="$activity->author(), 'tiny'"}
													<br><br>
													<i class="fa fa-{$activity::$icon} ipsType_large ipsType_light" title="{lang="$activity::$title"}" data-ipsTooltip></i>
												</div>
												<div class='ipsDataItem_main'>
													<p class='ipsType_reset ipsType_break ipsContained ipsType_normal'>
														<strong>
															{{if $activity instanceof \IPS\Content\Comment or $activity instanceof \IPS\Content\Review}}
					  											{{$item = $activity->item();}}
					  											{lang="user_own_activity_comment" sprintf="$member->name" htmlsprintf="'<a href=\'' . $activity->url() . '\'>' . $activity->indefiniteArticle() . '</a>'"}: <a href='{$item->url()}'>{$item->mapped('title')}</a>
					  										{{else}}
					  											{lang="user_own_activity_item" sprintf="$member->name, $activity->indefiniteArticle()"} <a href='{$activity->container()->url()}'>{$activity->container()->_title}</a>
					  										{{endif}}
					  									</strong>
					  									&nbsp;&nbsp;
					  									<span class='ipsType_light ipsType_noBreak'>{datetime="$activity->mapped('date')"}</span>
					  								</p>
													{template="snippet" group="global" app="$application" params="$activity"}
												</div>
											</li>
										{{endforeach}}
									</ol>
								{{else}}
									<div class='ipsPad ipsType_center ipsType_large ipsType_light'>
										{lang="no_recent_activity" sprintf="$member->name"}
									</div>
								{{endif}}
							</div>

							{{foreach $mainFields as $group => $fields}}
								{{foreach $fields as $field => $value}}
									{{if $value}}
										<div id="ipsTabs_elProfileTabs_elField{$field}_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
											<h2 class='ipsType_pageTitle ipsSpacer_top'>{lang="$field"}</h2>
											<div class='ipsType_richText ipsType_normal ipsSpacer_top' data-controller='core.front.core.lightboxedImages'>
												{$value|raw}
											</div>
										</div>
									{{endif}}
								{{endforeach}}
							{{endforeach}}

							{{foreach $nodes as $type => $table}}
								{{if $_table = (string) $table and $table->pages}}
									<div id="ipsTabs_elProfileTabs_elNode{$type}_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
										{$_table|raw}
									</div>
								{{endif}}
							{{endforeach}}

			 				{{if settings.profile_comments AND \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'core', 'status' ) ) and ( $member->pp_setting_count_comments or \IPS\Member::loggedIn()->member_id == $member->member_id )}}
			 					<div id="ipsTabs_elProfileTabs_elUpdates_panel" class='ipsTabs_panel ipsAreaBackground_reset ipsPad'>
				 					<section data-controller='core.front.profile.toggleBlock'>
										{template="statuses" group="profile" app="core" params="$statuses, $statusCount, $form, $member"}
									</section>
								</div>
							{{endif}}
						</div>
					{{endif}}
				</div>
			</section>
		</div>
{{if !\IPS\Request::i()->isAjax()}}
	</div>
</div>
{{endif}}
    [5] => profile
    [6] => $member, $visitors, $sidebarFields, $mainFields, $statuses, $statusCount, $form, $nodes, $addWarningUrl, $followers=array(), $latestActivity
    [7] => 0
    [8] => 0
    [9] => 1
    [10] => 1
    [11] => 0
    [12] => 4213688f69cc22f254a6a550d0a2637f
    [13] => 1430922115
)

 | File                                                                       | Function                                                                      | Line No.          |
 |----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------|
 | /system/Db/Db.php                                                          | [IPS\Db\_Exception].__construct                                               | 389               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Db/Db.php                                                          | [IPS\_Db].preparedQuery                                                       | 604               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Theme/Theme.php                                                    | [IPS\_Db].insert                                                              | 2560              |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /applications/core/modules/admin/customization/themes.php                  | [IPS\_Theme].saveTemplate                                                     | 2201              |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 |                                                                            | [IPS\core\modules\admin\customization\_themes].saveTemplate                   |                   |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Dispatcher/Controller.php                                          | [].call_user_func                                                             | 85                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Node/Controller.php                                                | [IPS\Dispatcher\_Controller].execute                                          | 63                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /system/Dispatcher/Dispatcher.php                                          | [IPS\Node\_Controller].execute                                                | 129               |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
 | /admin/index.php                                                           | [IPS\_Dispatcher].run                                                         | 13                |
 '----------------------------------------------------------------------------+-------------------------------------------------------------------------------+-------------------'
------------------------------------------------------------------------
