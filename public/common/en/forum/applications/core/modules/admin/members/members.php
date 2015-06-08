<?php
/**
 * @brief		Manage Members
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		21 Mar 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\modules\admin\members;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Manage Members
 */
class _members extends \IPS\Dispatcher\Controller
{
	/**
	 * Manage Members
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Create the table */
		$table = new \IPS\Helpers\Table\Db( 'core_members', \IPS\Http\Url::internal( 'app=core&module=members&controller=members' ), array( array( 'name<>? AND email<>?', '', '' ) ) );
		$table->langPrefix = 'members_';
				
		/* Columns we need */
		$table->include = array( 'photo', 'name', 'email', 'joined', 'group_name', 'ip_address' );
		$table->mainColumn = 'name';
		$table->noSort	= array( 'photo' );
		
		/* Default sort options */
		$table->sortBy = $table->sortBy ?: 'joined';
		$table->sortDirection = $table->sortDirection ?: 'desc';
		
		/* Groups for advanced filter (need to do it this way because array_merge renumbers the result */
		$groups     = array( '' => 'any_group' );
		$joinFields = array( 'core_members.member_id as member_id' );
		
		foreach ( \IPS\Member\Group::groups() as $k => $v )
		{
			$groups[ $k ] = $v->name;
		}

		/* Profile fields */
		foreach ( \IPS\core\ProfileFields\Field::fields( array(), \IPS\core\ProfileFields\STAFF ) as $group => $fields )
		{
			/* Header */
			\IPS\Member::loggedIn()->language()->words[ "members_core_pfieldgroups_{$group}" ] = \IPS\Member::loggedIn()->language()->addToStack( "core_pfieldgroups_{$group}", FALSE );

			$fieldsToAdd	= array();

			/* Fields */
			foreach ( $fields as $id => $field )
			{
				/* Alias the lang keys */
				$realLangKey = "core_pfield_{$id}";
				$fakeLangKey = "members_field_{$id}";
				\IPS\Member::loggedIn()->language()->words[ $fakeLangKey ] = \IPS\Member::loggedIn()->language()->addToStack( $realLangKey, FALSE );

				/* Work out the object type so we can show the appropriate field */
				$type = get_class( $field );
				$helper = NULL;

				switch ( $type )
				{
					case 'IPS\Helpers\Form\Text':
					case 'IPS\Helpers\Form\Tel':
					case 'IPS\Helpers\Form\Editor':
					case 'IPS\Helpers\Form\Email':
					case 'IPS\Helpers\Form\TextArea':
					case 'IPS\Helpers\Form\Url':
						$helper = \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT;
						break;
					case 'IPS\Helpers\Form\Date':
						$helper = \IPS\Helpers\Table\SEARCH_DATE_RANGE;
						break;
					case 'IPS\Helpers\Form\Number':
						$helper = \IPS\Helpers\Table\SEARCH_NUMERIC;
						break;
					case 'IPS\Helpers\Form\Select':
					case 'IPS\Helpers\Form\Radio':
						$options = array( '' => "");
						if( count( $field->options['options'] ) )
						{
							foreach ($field->options['options'] as $option)
							{
								$options[$option] = $option;
							}
						}

						$helper = array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => $options ) );
						break;
				}

				if ( $helper )
				{
					$fieldsToAdd[ "field_{$id}" ] = $helper;
				}

				/* Set fields we need for the table joins below */
				$joinFields[] = "field_{$id}";
			}
		
		/* Joins */
		$table->joins = array(
			array(
				'select' => 'v.vid, v.coppa_user, v.lost_pass, v.new_reg, v.email_chg, v.user_verified, v.spam_flag',
				'from' => array( 'core_validating', 'v' ),
				'where' => 'v.member_id=core_members.member_id AND v.lost_pass != 1' ),
			array(
				'select' => implode( ',', $joinFields ),
				'from' => array( 'core_pfields_content', 'p' ),
				'where' => 'p.member_id=core_members.member_id' ),
			array(
				'select' => 'row_id',
				'from' => array( 'core_admin_permission_rows', 'a' ),
				'where' => "a.row_id=core_members.member_id AND a.row_id_type='member'" ),
			array(
				'select' => 'g_access_cp',
				'from' => array( 'core_groups', 'g' ),
				'where' => "g.g_id=core_members.member_group_id" ),
		);
		
		/* Search */
		$table->quickSearch = 'name';
		$table->advancedSearch = array(
			'name'				=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'member_id'			=> array( \IPS\Helpers\Table\SEARCH_NUMERIC, array(), function( $v ){
				switch ( $v[0] )
				{
					case 'gt':
						return array( "core_members.member_id>?", (float) $v[1] );
					case 'lt':
						return array( "core_members.member_id<?", (float) $v[1] );
					case 'eq':
						return array( "core_members.member_id=?", (float) $v[1] );
				}
			} ),
			'email'				=> \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT,
			'ip_address'		=> array( \IPS\Helpers\Table\SEARCH_CONTAINS_TEXT, array(), function( $val )
			{
				return array( "core_members.ip_address LIKE ?", '%' . $val . '%' );
			} ),
			'member_group_id'	=> array( \IPS\Helpers\Table\SEARCH_SELECT, array( 'options' => $groups ), function( $val )
			{
				return array( 'member_group_id=? OR FIND_IN_SET( ?, mgroup_others )', $val, $val );
			} ),
			'joined'			=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'member_last_post'			=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'last_visit'		=> \IPS\Helpers\Table\SEARCH_DATE_RANGE,
			'member_posts'				=> \IPS\Helpers\Table\SEARCH_NUMERIC,
			);
		
			if( count( $fieldsToAdd ) )
			{
				$table->advancedSearch[ "core_pfieldgroups_{$group}" ] = \IPS\Helpers\Table\HEADER;

				$table->advancedSearch	= array_merge( $table->advancedSearch, $fieldsToAdd );
			}
		}
						
		/* Filters */
		$table->filters = array(
			'members_filter_banned'			=> 'temp_ban<>0',
			'members_filter_locked'			=> 'failed_login_count>=' . (int) \IPS\Settings::i()->ipb_bruteforce_attempts,
			'members_filter_spam'			=> \IPS\Db::i()->bitwiseWhere( \IPS\Member::$bitOptions['members_bitoptions'], 'bw_is_spammer' ),
			'members_filter_validating'		=> '( v.lost_pass=0 AND v.vid IS NOT NULL )',
			'members_filter_administrators'	=> '( a.row_id IS NOT NULL OR g.g_access_cp=1 )'
		);
		
		/* Custom parsers */
		$table->parsers = array(
			'email'				=> function( $val, $row )
			{
				if ( $row['vid'] )
				{
					return \IPS\Theme::i()->getTemplate( 'members', 'core', 'admin' )->memberEmailCell( \IPS\Theme::i()->getTemplate( 'members', 'core', 'admin' )->memberValidatingCell( $val, \IPS\Member::constructFromData( $row )->validatingDescription( $row ) ) );
				}
				else
				{
					return \IPS\Theme::i()->getTemplate( 'members', 'core', 'admin' )->memberEmailCell( htmlentities( $val, \IPS\HTMLENTITIES, 'UTF-8', FALSE ) );
				}				
			},
			'photo'				=> function( $val, $row )
			{
				return \IPS\Theme::i()->getTemplate( 'global', 'core' )->userPhoto( \IPS\Member::constructFromData( $row ), 'mini' );
			},
			'joined'			=> function( $val, $row )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			},
			'group_name'	=> function( $val, $row )
			{
				$secondary = \IPS\Member::constructFromData( $row )->groups;
				
				foreach( $secondary as $k => $v )
				{
					if( $v == $row['member_group_id'] or $v == 0 )
					{
						unset( $secondary[ $k ] );
						continue;
					}
					
					$secondary[ $k ] = \IPS\Member\Group::load( $v );
				}

				return \IPS\Theme::i()->getTemplate( 'members', 'core', 'admin' )->groupCell( \IPS\Member\Group::load( $row['member_group_id'] ), $secondary );
			},
			'ip_address'	=> function( $val, $row )
			{
				if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_ip' ) )
				{
					return "<a href='" . \IPS\Http\Url::internal( "app=core&module=members&controller=ip&ip={$val}" ) . "'>{$val}</a>";
				}
				return $val;
			},
			'member_last_post' => function( $val, $row )
			{
				return ( $val ) ? \IPS\DateTime::ts( $val )->localeDate() : \IPS\Member::loggedIn()->language()->addToStack( 'never' );
			},
			'last_visit' => function( $val, $row )
			{
				return ( $val ) ? \IPS\DateTime::ts( $val )->localeDate() : \IPS\Member::loggedIn()->language()->addToStack( 'never' );
			},
			'name' => function( $val, $row )
			{
				return "<a href='" . \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=edit&id=' ) . $row['member_id'] . "'>" . htmlentities( $val, \IPS\HTMLENTITIES, 'UTF-8', FALSE ) . "</a>";
			},
		);
		
		/* Specify the buttons */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_add' ) )
		{
			$table->rootButtons = array(
				'add'	=> array(
					'icon'		=> 'plus',
					'title'		=> 'members_add',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=add' ),
					'data'		=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('members_add') )
				)
			);
		}
		$table->rowButtons = function( $row )
		{
			$member = \IPS\Member::constructFromData( $row );
			
			$return = array();
			
			if ( isset( $row['vid'] ) and $row['vid'] and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_validating' ) )
			{
				$return['approve'] = array(
					'icon'		=> 'check-circle',
					'title'		=> 'approve',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=approve&id=' ) . $member->member_id,
					'id'		=> "{$member->member_id}-approve",
					'data'		=> array(
						'bubble' 		=> '',
					)
				);
				$return['ban'] = array(
					'icon'		=> 'times',
					'title'		=> 'ban',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=ban&id=' ) . $member->member_id . '&permban=1',
					'id'		=> "{$member->member_id}-ban",
					'data'		=> array(
						'bubble'		=> '',
					)
				);
				
				if ( !$row['user_verified'] )
				{
					$return['resend_email'] = array(
						'icon'		=> 'envelope-o',
						'title'		=> 'resend_validation_email',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=resendEmail&id=' ) . $member->member_id,
						'data' 		=> array( 'doajax' => '' ),
						'id'		=> "{$member->member_id}-resend",
					);
				}
			}
			
			if ( $row['failed_login_count'] >= (int) \IPS\Settings::i()->ipb_bruteforce_attempts and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_locked' ) )
			{
				$return['unlock'] = array(
					'icon'		=> 'unlock',
					'title'		=> 'unlock',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=unlock&id=' ) . $member->member_id,
					'data'		=> array( 'bubble' => '' )
				);
			}
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit' ) and ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit_admin' ) or !$member->isAdmin() ) )
			{
				$return['edit'] = array(
					'icon'		=> 'pencil',
					'title'		=> 'edit',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=edit&id=' ) . $member->member_id,
					'hotkey'	=> 'e'
				);
				
				if ( $member->member_id != \IPS\Member::loggedIn()->member_id )
				{
					$return['flag'] = array(
						'icon'		=> 'flag',
						'title'		=> 'spam_flag',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=spam&id=' ) . $member->member_id . '&status=1',
						'hidden'	=> $member->members_bitoptions['bw_is_spammer'],
						'id'		=> "{$member->member_id}-flag",
						'data'		=> array(
							'bubble'		=> '',
						)
					);
					$return['unflag'] = array(
						'icon'		=> 'flag-o',
						'title'		=> 'spam_unflag',
						'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=spam&id=' ) . $member->member_id . '&status=0',
						'hidden'	=> !$member->members_bitoptions['bw_is_spammer'],
						'id'		=> "{$member->member_id}-unflag",
						'data'		=> array(
							'bubble'		=> '',
						)
					);
				}
			}
						
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete' ) and ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete_admin' ) or !$member->isAdmin() ) and $member->member_id != \IPS\Member::loggedIn()->member_id )
			{
				$return['delete'] = array(
					'icon'		=> 'times-circle',
					'title'		=> 'delete',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=delete&id=' ) . $member->member_id,
					'data'		=> array( 'delete' => '' ),
				);
			}
			
			return $return;
		};
				
		/* Display */
		if( \IPS\Request::i()->advanced_search_submitted OR \IPS\Request::i()->quicksearch )
		{
			$link = ' <a href="' . \IPS\Http\Url::internal( 'app=core&module=members&controller=members' ) . '">' . \IPS\Member::loggedIn()->language()->addToStack('member_view_full_list') . '</a>';
			
			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete' ) OR \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit' ) )
			{
				$query = http_build_query( array(
					'members_name'				=> \IPS\Request::i()->quicksearch ?: NULL,
					'members_member_id'			=> \IPS\Request::i()->members_member_id,
					'members_email'				=> \IPS\Request::i()->members_email,
					'members_ip_address'		=> \IPS\Request::i()->members_ip_address,
					'members_member_group_id'	=> \IPS\Request::i()->members_member_group_id,
					'members_joined'			=> \IPS\Request::i()->members_joined,
					'members_last_post'			=> \IPS\Request::i()->members_last_post,
					'members_last_visit'		=> \IPS\Request::i()->members_last_visit,
					'members_posts'				=> \IPS\Request::i()->members_posts,
				) );
				
				if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete' ) )
				{
					$link .= ' &mdash; <a href="' . \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=massManage&action=prune&{$query}" ) . '">' . \IPS\Member::loggedIn()->language()->addToStack( 'member_search_prune' ) . '</a>';
				}
				
				if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit' ) )
				{
					$link .= ' &mdash; <a href="' . \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=massManage&action=move&{$query}" ) . '">' . \IPS\Member::loggedIn()->language()->addToStack( 'member_search_move' ) . '</a>';
				}
			}
			
			$table->extraHtml = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( \IPS\Member::loggedIn()->language()->addToStack( 'member_search_results' ) . $link, 'info', NULL, FALSE );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('members');
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'members', (string) $table );
	}

	/**
	 * Prune members
	 *
	 * @return	void
	 */
	public function massManage()
	{
		switch( \IPS\Request::i()->action )
		{
			case 'prune':
				\IPS\Dispatcher::i()->checkAcpPermission( 'member_delete' );
			break;
			
			case 'move':
				\IPS\Dispatcher::i()->checkAcpPermission( 'member_edit' );
			break;
		}
		
		$where = array();
		
		if ( \IPS\Request::i()->members_name )
		{
			$where[] = array( "name LIKE CONCAT( '%', ?, '%' )", \IPS\Request::i()->members_name );
		}
		
		if ( \IPS\Request::i()->members_member_id )
		{
			$where[] = array( 'member_id=?', (int) \IPS\Request::i()->members_member_id );
		}
		
		if ( \IPS\Request::i()->members_email )
		{
			$where[] = array( "email LIKE CONCAT( '%', ?, '%' )", \IPS\Request::i()->members_email );
		}
		
		if ( \IPS\Request::i()->members_ip_address )
		{
			$where[] = array( 'ip_address=?', (string) \IPS\Request::i()->members_ip_address );
		}
		
		if ( \IPS\Request::i()->members_member_group_id )
		{
			$adminGroups = array();
			foreach( \IPS\Member\Group::groups( TRUE, FALSE ) AS $k => $group )
			{
				if ( $group->g_access_cp )
				{
					$adminGroups[] = $group->g_id;
				}
			}
			
			/* We do a generic permissions check here, then later on when the process is actually running, we check each individual one to make sure we don't do something we shouldn't do */
			if
			(
				(
					(
						\IPS\Request::i()->action === 'prune' AND \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete_admin' )
					)
					OR
					(
						\IPS\Request::i()->action === 'move' AND
						(
							\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit_admin' ) OR
							\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin1' ) OR
							\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin2' )
						)
					)
				)
				OR
				!in_array( \IPS\Request::i()->members_member_group_id, $adminGroups )
			)
			{
				$where[] = array( 'member_group_id=?', (int) \IPS\Request::i()->members_member_group_id );
			}
		}
		
		if ( \IPS\Request::i()->members_joined['start'] AND \IPS\Request::i()->members_joined['end'] )
		{
			$start	= new \IPS\DateTime( \IPS\Request::i()->members_joined['start'] );
			$end	= new \IPS\DateTime( \IPS\Request::i()->members_joined['end'] );
			$where[] = array( 'joined BETWEEN ? AND ?', $start->getTimestamp(), $end->getTimestamp() );
		}
		
		if ( \IPS\Request::i()->members_last_post['start'] AND \IPS\Request::i()->members_last_post['end'] )
		{
			$start	= new \IPS\DateTime( \IPS\Request::i()->members_last_post['start'] );
			$end	= new \IPS\DateTime( \IPS\Request::i()->members_last_post['end'] );
			$where[] = array( 'member_last_post BETWEEN ? AND ?', $start->getTimestamp(), $end->getTimestamp() );
		}
		
		if ( \IPS\Request::i()->members_last_visit['start'] AND isset( \IPS\Request::i()->members_last_visit['end'] ) )
		{
			$start	= new \IPS\DateTime( \IPS\Request::i()->members_last_visit['start'] );
			$end	= new \IPS\DateTime( \IPS\Request::i()->members_last_visit['end'] );
			$where[] = array( 'last_visit BETWEEN ? AND ?', $start->getTimestamp(), $end->getTimestamp() );
		}
		
		if ( ( isset( \IPS\Request::i()->members_posts[0] ) AND \IPS\Request::i()->members_posts[0] != 'any' ) AND isset( \IPS\Request::i()->members_posts[1] ) )
		{
			switch( \IPS\Request::i()->members_posts[0] )
			{
				case 'gt':
					$operator = '>';
				break;
				
				case 'lt':
					$operator = '<';
				break;
				
				case 'eq':
					$operator = '=';
				break;
			}
			$where[] = array( 'posts'.$operator.'?', (int) \IPS\Request::i()->members_posts[1] );
		}
		
		if ( !count( $where ) )
		{
			if ( \IPS\Request::i()->action === 'prune' )
			{
				\IPS\Output::i()->error( 'member_prune_no_results', '2C114/E', 404, '' );
			}
			else
			{
				\IPS\Output::i()->error( 'member_move_no_results', '2C114/G', 404, '' );
			}
		}
		
		/* Unset any previous session data */
		$_SESSION['members_manage_where']	= $where;
		$_SESSION['members_manage_action']	= \IPS\Request::i()->action;
		
		if ( \IPS\Request::i()->action === 'prune' )
		{
			$count = \IPS\Db::i()->select( 'COUNT(*) AS count', 'core_members', $where )->first();
			\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'members' )->confirmMassAction( $count, 'prune' );
			\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'member_prune_confirm' );
		}
		else
		{
			$form = new \IPS\Helpers\Form;
			$form->add( new \IPS\Helpers\Form\Select( 'move_to_group', NULL, TRUE, array( 'options'	=> \IPS\Member\Group::groups( TRUE, FALSE ), 'parse' => 'normal' ) ) );
			
			if ( $values = $form->values() )
			{
				$group = \IPS\Member\Group::load( $values['move_to_group'] );
				
				if ( $group->g_access_cp AND !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin2' ) )
				{
					\IPS\Output::i()->error( 'member_move_admin_group', '2C114/H', 403, '' );
				}
				
				$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_members', $_SESSION['members_manage_where'] )->first();
				$_SESSION['members_manage_group']	= $group->g_id;
				\IPS\Output::i()->output			= \IPS\Theme::i()->getTemplate( 'members' )->confirmMassAction( $count, 'move', $group );
				\IPS\Output::i()->title				= \IPS\Member::loggedIn()->language()->addToStack( 'member_move_confirm' );
			}
			else
			{
				\IPS\Output::i()->output	= $form;
				\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack( 'member_search_move' );
			}
		}
	}
	
	/**
	 * Move Members
	 *
	 * @return	void
	 */
	public function doMove()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_edit' );
		
		$cycle = 1;
		$group = $_SESSION['members_manage_group'];
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'moving_members' );
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=doMove" ),
		function( $data ) use ( $cycle, $group )
		{
			$newGroup = \IPS\Member\Group::load( $group );
			if ( !is_array( $data ) )
			{
				$data	= array( 'done' => 0, 'total' => 0, 'group' => $newGroup->g_id );
			}
			
			$select	= \IPS\Db::i()->select( '*', 'core_members', $_SESSION['members_manage_where'], 'member_id ASC', array( 0, $cycle ), NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
			
			if ( $data['total'] == 0 )
			{
				$data['total'] = $select->count( TRUE );
			}
			
			if ( !$select->count() )
			{
				return NULL;
			}
			
			foreach( $select AS $row )
			{
				try
				{
					$member = \IPS\Member::constructFromData( $row );
					
					/* Let's leave our own account alone */
					if ( $member->member_id == \IPS\Member::loggedIn()->member_id )
					{
						throw new \OutOfBoundsException;
					}
					
					/* Is this member an admin? */
					if ( $member->isAdmin() AND ( !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_edit_admin' ) OR !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_move_admin1' ) ) )
					{
						throw new \OutOfBoundsException;
					}
					
					/* Member is already in this group? */
					if ( $member->inGroup( $newGroup ) )
					{
						$extraGroups	= array_filter( explode( ',', $member->mgroup_others ) );
						
						$this->mgroup_others = implode( ',', array_diff( $extraGroups, array( $newGroup->g_id ) ) );
					}
					
					$member->member_group_id = $newGroup->g_id;
					$member->save();
				}
				catch( \Exception $e ) { }
			}
			
			$data['done'] += $cycle;
			
			return array( $data, \IPS\Member::loggedIn()->language()->addToStack( 'moving_members' ), ( $data['done'] / $data['total'] ) * 100 );
		}, function () use ( $group )
		{
			$newGroup = \IPS\Member\Group::load( $group );
			$_SESSION['members_manage_where']	= NULL;
			$_SESSION['members_manage_action']	= NULL;
			$_SESSION['members_manage_group']	= NULL;
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=members&advanced_search_submitted=1&csrfKey=' . \IPS\Session::i()->csrfKey . '&members_member_group_id=' . $newGroup->g_id ) );
		} );
	}

	/**
	 * Prune members
	 *
	 * @return	void
	 */
	public function doPrune()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_delete' );
		
		$cycle = 1;
		
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'pruning_members' );
		\IPS\Output::i()->output = new \IPS\Helpers\MultipleRedirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=doPrune" ),
		function ( $data ) use ( $cycle )
		{
			$select	= \IPS\Db::i()->select( '*', 'core_members', $_SESSION['members_manage_where'], 'member_id ASC', array( 0, $cycle ), NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
			$total	= $select->count( TRUE );
			
			if ( !$select->count() )
			{
				return NULL;
			}

			if( !is_array( $data ) )
			{
				$data	= array( 'total' => $total, 'done' => 0 );
			}

			foreach( $select AS $row )
			{
				try
				{
					$member = \IPS\Member::constructFromData( $row );
					
					if ( $member->member_id == \IPS\Member::loggedIn()->member_id OR ( $member->isAdmin() AND !\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete_admin' ) ) )
					{
						throw new \OutOfBoundsException;
					}
					
					$member->delete();
				}
				catch( \Exception $e ) {}
			}
			
			$data['done'] += $cycle;
			
			return array( $data, \IPS\Member::loggedIn()->language()->addToStack('pruning_members'), ( $data['done'] / $data['total'] ) * 100 );
		}, function()
		{
			$_SESSION['members_manage_where']	= NULL;
			$_SESSION['members_manage_action']	= NULL;
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members" ), 'completed' );
		} );
	}

	/**
	 * Add Member
	 *
	 * @return	void
	 */
	public function add()
	{
		/* Check permissions */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_add' );
		
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Text( 'username', NULL, TRUE, array( 'accountUsername' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\Password( 'password', NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Email( 'email_address', NULL, TRUE, array( 'maxLength' => 150, 'accountEmail' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'group', \IPS\Settings::i()->member_group, TRUE, array( 'options' => \IPS\Member\Group::groups( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_add_admin' ), FALSE ), 'parse' => 'normal' ) ) );
		$form->add( new \IPS\Helpers\Form\Select( 'secondary_groups', array(), FALSE, array( 'options' => \IPS\Member\Group::groups( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_add_admin' ), FALSE ), 'multiple' => TRUE, 'parse' => 'normal' ) ) );
		foreach ( \IPS\Lang::languages() as $lang )
		{
			$languages[ $lang->id ] = $lang->title;
		}
		$form->add( new \IPS\Helpers\Form\Select( 'language', NULL, TRUE, array( 'options' => $languages ) ) );
		
		$themes = array( 0 => 'skin_none' );
		foreach( \IPS\Theme::themes() as $theme )
		{
			$themes[ $theme->id ] = $theme->_title;
		}
		
		$form->add( new \IPS\Helpers\Form\Select( 'skin', NULL, TRUE, array( 'options' => $themes ) ) );
		$form->add( new \IPS\Helpers\Form\YesNo( 'member_add_confirmemail', TRUE ) );
		
		if( \IPS\Settings::i()->use_coppa )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'member_add_coppa_user', FALSE, FALSE, array(), NULL, NULL, NULL, 'member_add_coppa_user' ) );
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$member = new \IPS\Member;
			$member->name				= $values['username'];
			$member->email				= $values['email_address'];
			$member->member_group_id	= $values['group'];
			$member->mgroup_others		= implode( ',', $values['secondary_groups'] );
			$member->language			= $values['language'];
			$member->skin				= $values['skin'];
			$member->members_pass_salt	= $member->generateSalt();
			$member->members_pass_hash	= $member->encryptedPassword( $values['password'] );
			
			if( \IPS\Settings::i()->use_coppa )
			{
				$member->members_bitoptions['coppa_user'] = ( $values['member_add_coppa_user'] ) ?: FALSE;
			}
			
			$member->save();
			
			/* Reset statistics */
			\IPS\Widget::deleteCaches( 'stats', 'core' );
			
			\IPS\Session::i()->log( 'acplog__members_created', array( $member->name => FALSE ) );
				
			if ( $values['member_add_confirmemail'] )
			{
				\IPS\Email::buildFromTemplate( 'core', 'admin_reg', array( $member, $values['password'] ) )->send( $member );
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=edit&id=' . $member->member_id ), 'saved' );
		}
		
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->outputTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core' ), 'blankTemplate' );
		}
		\IPS\Output::i()->title	= \IPS\Member::loggedIn()->language()->addToStack( 'members_add' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'members_add', $form, FALSE );
	}
	
	/**
	 * Edit Member
	 *
	 * @return	void
	 */
	public function edit()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_edit' );
		
		/* Load Member */
		$member = \IPS\Member::load( \IPS\Request::i()->id );

		if ( !$member->member_id )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/1', 404, '' );
		}
		if ( $member->isAdmin() )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'member_edit_admin' );
		}

		/* Get extensions */
		$extensions = \IPS\Application::allExtensions( 'core', 'MemberForm', FALSE, 'core', 'BasicInformation' );
		
		/* Build form */
		\IPS\Output::i()->sidebar['actions'] = array();
		$form = new \IPS\Helpers\Form;
		foreach ( $extensions as $k => $class )
		{
			$form->addtab( 'member__' . $k );
			$class->process( $form, $member );
			
			if ( method_exists( $class, 'actionButtons' ) )
			{
				\IPS\Output::i()->sidebar['actions'] = array_merge( \IPS\Output::i()->sidebar['actions'], $class->actionButtons( $member ) );
			}
		}
		
		/* Delete button */
		if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete' ) and ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_delete_admin' ) or !$member->isAdmin() ) and $member->member_id != \IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->sidebar['actions']['delete'] = array(
				'title'		=> 'delete',
				'icon'		=> 'times-circle',
				'link'		=> \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=delete&id={$member->member_id}" ),
				'data'		=> array( 'delete' => '' )
			);
		}
		
		/* Approve */
		if ( $member->members_bitoptions['validating'] and \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_validating' ) )
		{
			\IPS\Output::i()->sidebar['actions']['approve'] = array(
					'title'		=> 'approve',
					'icon'		=> 'check-circle',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=approve&id=' ) . $member->member_id,
					'id'		=> "{$member->member_id}-approve",
					'data'		=> array( 'bubble'	=> '' )
			);
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			foreach ( $extensions as $class )
			{
				$class->save( $values, $member );
			}
			
			$member->save();
			
			/* Edited member, so clear widget caches (stats, widgets that contain photos, names and so on) */
			\IPS\Widget::deleteCaches();
			
			\IPS\Session::i()->log( 'acplog__members_edited', array( $member->name => FALSE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=members&do=edit&id=' . $member->member_id ), 'saved' );
		}
		
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'members/member.css', 'core', 'admin' ) );

		\IPS\Output::i()->title		= $member->name;
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate('members')->editMember(
			$member,
			$form,
			( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_photo' ) and ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'member_photo_admin' ) or !$member->isAdmin() ) ),
			\IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'members', 'membertools_ip' )
		);
	}
	
	/**
	 * Change Password
	 *
	 * @return	void
	 */
	protected function password()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_edit' );
		
		/* Load Member */
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
			if ( $member->isAdmin() )
			{
				\IPS\Dispatcher::i()->checkAcpPermission( 'member_edit_admin' );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/2', 404, '' );
		}
		
		/* Show Form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Password( 'password', '', TRUE, array( 'confirm' => 'password_confirm' ) ) );
		$form->add( new \IPS\Helpers\Form\Password( 'password_confirm', '', TRUE ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			foreach ( \IPS\Login::handlers( TRUE ) as $handler )
			{
				try
				{
					$handler->changePassword( $member, $values['password'] );
				}
				catch( \BadMethodCallException $e ){}
			}
			
			\IPS\Session::i()->log( 'acplog__members_edited', array( $member->name => FALSE ) );
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'saved' );
		}
		
		/* Display */
		\IPS\Output::i()->outputTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core' ), 'blankTemplate' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( 'password', $form, FALSE );
	}
	
	/**
	 * Change Photo
	 *
	 * @return	void
	 */
	public function photo()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_photo' );
		
		/* Load member */
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
			if ( $member->isAdmin() )
			{
				\IPS\Dispatcher::i()->checkAcpPermission( 'member_photo_admin' );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/3', 404, '' );
		}
		
		/* What options do we have? */
		$options = array( 'custom' => 'member_photo_upload', 'url' => 'member_photo_url' );
		$toggles = array( 'custom' => array( 'member_photo_upload' ), 'url' => array( 'member_photo_url' ) );
		$extra = array();
		if ( \IPS\Settings::i()->allow_gravatars )
		{
			$options['gravatar'] = 'member_photo_gravatar';
			$extra[] = new \IPS\Helpers\Form\Email( 'photo_gravatar_email', $member->pp_gravatar, FALSE, array( 'maxLength' => 255, 'placeholder' => $member->email ), NULL, NULL, NULL, 'member_photo_gravatar' );
			$toggles['gravatar'] = array( 'member_photo_gravatar' );
		}
		foreach ( \IPS\core\ProfileSync\ProfileSyncAbstract::services() as $key => $class )
		{
			$obj = new $class( $member );
			if ( $obj->connected() )
			{
				$langKey = 'profilesync__' . $key;
				$options[ 'sync-' . $key ] = \IPS\Member::loggedIn()->language()->addToStack('member_photo_sync', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $langKey ) ) ) );
			}
		}
		$options['none'] = 'member_photo_none';
		
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Radio( 'pp_photo_type', $member->pp_photo_type, TRUE, array( 'options' => $options, 'toggles' => $toggles ) ) );
		$customVal = NULL;
		if ( $member->pp_photo_type === 'custom' )
		{
			$customVal = \IPS\File::get( 'core_Profile', $member->pp_main_photo );
		}
		$photoVars = explode( ':', $member->group['g_photo_max_vars'] );
		$form->add( new \IPS\Helpers\Form\Upload( 'member_photo_upload', $customVal, FALSE, array( 'image' => array( 'maxWidth' => $photoVars[1], 'maxHeight' => $photoVars[2] ), 'storageExtension' => 'core_Profile' ), NULL, NULL, NULL, 'member_photo_upload' ) );
		$form->add( new \IPS\Helpers\Form\Url( 'member_photo_url', NULL, FALSE, array( 'file' => 'core_Profile', 'allowedMimes' => 'image/*' ), NULL, NULL, NULL, 'member_photo_url' ) );
		foreach ( $extra as $element )
		{
			$form->add( $element );
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			$member->pp_photo_type = $values['pp_photo_type'] === 'none' ? NULL : $values['pp_photo_type'];
						
			/* Save main photo */
			switch ( $values['pp_photo_type'] )
			{
				case 'custom':
					if ( $values['member_photo_upload'] )
					{
						$member->pp_photo_type  = 'custom';
						$member->pp_main_photo  = NULL;
						$member->pp_main_photo  = (string) $values['member_photo_upload'];
						$member->pp_thumb_photo = (string) $values['member_photo_upload']->thumbnail( 'core_Profile', 200, 200, TRUE );
					}
					break;
					
				case 'url':
					$member->pp_photo_type = 'custom';
					$member->pp_main_photo = NULL;
					$member->pp_main_photo = (string) $values['member_photo_url'];
					break;
				
				case 'none':
					$member->pp_main_photo								= NULL;
					$member->members_bitoptions['bw_disable_gravatar']	= 1;
					break;
				
				default:
					if ( mb_substr( $values['pp_photo_type'], 0, 5 ) === 'sync-' )
					{
						$class = 'IPS\core\ProfileSync\\' . mb_substr( $values['pp_photo_type'], 5 );
						$obj = new $class( $member );
						$obj->save( array( 'profilesync_photo' => TRUE ) );
					}
			}
			
			/* Edited member, so clear widget caches (stats, widgets that contain photos, names and so on) */
			\IPS\Widget::deleteCaches();
									
			/* Save */
			$member->save();
			\IPS\Session::i()->log( 'acplog__members_edited', array( $member->name => FALSE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'saved' );
		}
		
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->outputTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core' ), 'blankTemplate' );
		}
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( 'photo', $form, FALSE );
	}
	
	/**
	 * Find IP Addresses
	 *
	 * @return	void
	 */
	public function ip()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'membertools_ip' );
		
		/* Load Member */
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/5', 404, '' );
		}
		
		/* Init Table */
		$table = new \IPS\Helpers\Table\Custom( $member->ipAddresses(), \IPS\Http\Url::internal( "app=core&module=members&controller=members&id={$member->member_id}" ) );
		$table->langPrefix = 'members_iptable_';
		$table->mainColumn = 'ip';
		$table->sortBy = $table->sortBy ?: 'last';
		
		/* Parsers */
		$table->parsers = array(
			'first'			=> function( $val )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			},
			'last'			=> function( $val )
			{
				return \IPS\DateTime::ts( $val )->localeDate();
			},
		);
		
		/* Buttons */
		$table->rowButtons = function( $row )
		{
			return array(
				'view'	=> array(
					'icon'		=> 'search',
					'title'		=> 'see_uses',
					'link'		=> \IPS\Http\Url::internal( 'app=core&module=members&controller=ip&ip=' ) . $row['ip'],
				),
			);
		};
		
		/* Display */
		\IPS\Output::i()->title			= $member->name;
		\IPS\Output::i()->breadcrumb[]	= array( "app=core&module=members&controller=members&do=edit&id={$member->member_id}", $member->name );
		\IPS\Output::i()->output		= \IPS\Theme::i()->getTemplate('global')->block( 'menu__core_members_ip', (string) $table );
	}
	
	/**
	 * Photo Resize
	 *
	 * @return	void
	 */
	public function photoResize()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_photo' );
		
		/* Load member */
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/3', 404, '' );
		}
		if ( $member->isAdmin() )
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'member_photo_admin' );
		}
		
		/* Get photo */
		$image = \IPS\File::get( 'core_Profile', $member->pp_main_photo );
	
		/* Build Form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\WidthHeight( 'member_photo_resize', NULL, TRUE, array( 'image' => $image ) ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Create new file */
			$original = \IPS\File::get( 'core_Profile', $member->pp_main_photo );
			$image = \IPS\Image::create( $original->contents() );
			$image->resize( $values['member_photo_resize'][0], $values['member_photo_resize'][1] );
			
			/* Save the new */
			$member->pp_main_photo = \IPS\File::create( 'core_Profile', $original->filename, (string) $image );
			$member->pp_thumb_photo = (string) $member->pp_main_photo->thumbnail( 'core_Profile', 200, 200, TRUE );
			$member->save();
			
			/* Delete the original */
			$original->delete();
						
			/* Log and redirect */
			\IPS\Session::i()->log( 'acplog__members_edited', array( $member->name => FALSE ) );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'saved' );
		}
		
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->outputTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core' ), 'blankTemplate' );
		}
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( 'member_photo_resize', $form, FALSE );
	}
	
	/**
	 * Unlock
	 *
	 * @return	void
	 */
	public function unlock()
	{
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/9', 404, '' );
		}
		$member->failed_logins = array();
		$member->save();
		
		\IPS\Session::i()->log( 'acplog__members_unlocked', array( $member->name => FALSE ) );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'saved' );
		}
	}
	
	/**
	 * View Display Name History
	 *
	 * @return	void
	 */
	public function viewDnameHistory()
	{
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/D', 404, '' );
		}
		
		$table = new \IPS\Helpers\Table\Db( 'core_dnames_change', \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=viewDnameHistory&id={$member->member_id}" ), array( 'dname_member_id=?', (int) $member->member_id ) );
		$table->include = array( 'dname_previous', 'dname_current', 'dname_date', 'dname_ip_address' );
		$table->noSort	= array( 'dname_previous', 'dname_current', 'dname_date', 'dname_ip_address' );
		$table->parsers	= array(
			'dname_date'	=> function( $value )
			{
				return \IPS\DateTime::ts( $value )->localeDate();
			},
		);
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'members' )->viewDnameHistory( (string) $table, $member );
	}
	
	/**
	 * Clear Display Name History
	 *
	 * @return	void
	 */
	public function clearDnameHistory()
	{
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
		}
		catch( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/C', 404, '' );
		}
		
		\IPS\Db::i()->delete( 'core_dnames_change', array( 'dname_member_id=?', (int) $member->member_id ) );
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'dname_history_cleared' );
		}
	}
	
	/**
	 * Flag as spammer
	 *
	 * @return	void
	 */
	public function spam()
	{
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
			
			if ( $member->member_id == \IPS\Member::loggedIn()->member_id )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/8', 404, '' );
		}
				
		if ( \IPS\Request::i()->status )
		{
			$member->flagAsSpammer();
			\IPS\Session::i()->log( 'modlog__spammer_flagged', array( $member->name => FALSE ) );
		}
		else
		{
			$member->unflagAsSpammer();
			\IPS\Session::i()->log( 'modlog__spammer_flagged', array( $member->name => FALSE ) );
		}
				
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), ( \IPS\Request::i()->status ? 'account_flagged' : 'account_unflagged' ) );
		}
	}
	
	/**
	 * Approve
	 *
	 * @return	void
	 */
	public function approve()
	{
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/A', 404, '' );
		}
		
		$member->validate();
		
		/* Log */
		\IPS\Session::i()->log( 'acplog__members_approved', array( $member->name => FALSE ) );

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'OK' );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'account_approved' );
		}
	}
	
	/**
	 * Resend Validation Email
	 *
	 * @return	void
	 */
	public function resendEmail()
	{
		/* Load Member */
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/B', 404, '' );
		}
		
		/* Send */
		foreach ( \IPS\Db::i()->select( '*', 'core_validating', array( 'member_id=?', $member->member_id ) ) as $row )
		{
			if ( !$row['user_verified'] )
			{
				/* Lost Pass */
				if ( $row['lost_pass'] )
				{
					\IPS\Email::buildFromTemplate( 'core', 'lost_password_init', array( $member, $row['vid'] ) )->send( $member );
				}
				/* New Reg */
				elseif ( $row['new_reg'] )
				{
					\IPS\Email::buildFromTemplate( 'core', 'registration_validate', array( $member, $row['vid'] ) )->send( $member );
				}
				/* Email Change */
				elseif ( $row['email_chg'] )
				{
					\IPS\Email::buildFromTemplate( 'core', 'email_change', array( $member, $row['vid'] ) )->send( $member );
				}
			}
		}
		
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( \IPS\Member::loggedIn()->language()->get('validation_email_resent') );
		}
		else
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'validation_email_resent' );
		}
	}
	
	/**
	 * Merge
	 *
	 * @return	void
	 */
	public function merge()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'members_merge' );
		
		/* Load first member */
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/6', 404, '' );
		}
		
		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Member( 'member_merge', NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Select( 'member_merge_keep', 1, TRUE, array( 'options' => array( 1 => \IPS\Member::loggedIn()->language()->addToStack( 'member_merge_keep_1', FALSE, array( 'sprintf' => array( $member->name ) ) ), 2 => 'member_merge_keep_2' ) ) ) );
		
		/* Merge */
		if ( $values = $form->values() )
		{
			/* Which account are we keeping */
			if ( $values['member_merge_keep'] == 1 )
			{
				$accountToKeep		= $member;
				$accountToDelete	= $values['member_merge'];
			}
			else
			{
				$accountToDelete	= $member;
				$accountToKeep		= $values['member_merge'];
			}
			
			/* Do it */
			$accountToKeep->merge( $accountToDelete );
						
			/* Delete the account */
			$accountToDelete->delete();
			
			/* Log */
			\IPS\Session::i()->log( 'acplog__members_merge', array( $accountToKeep->name => FALSE, $accountToDelete->name ) );
			
			/* Boink */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$accountToKeep->member_id}" ), 'saved' );
		}
		
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->outputTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core' ), 'blankTemplate' );
		}
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( 'merge', $form, FALSE );
	}
	
	/**
	 * Ban
	 *
	 * @return	void
	 */
	public function ban()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_ban' );
		
		/* Load member */
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
			if ( $member->isAdmin() )
			{
				\IPS\Dispatcher::i()->checkAcpPermission( 'member_ban_admin' );
			}
			if ( $member->member_id == \IPS\Member::loggedIn()->member_id )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/7', 404, '' );
		}
		
		/* Just do it? */
		if ( \IPS\Request::i()->permban )
		{
			$member->temp_ban = -1;
			$member->save();

			/* Login handler callback */
			foreach ( \IPS\Login::handlers( TRUE ) as $k => $handler )
			{
				try
				{
					$handler->banAccount( $member, TRUE );
				}
				catch( \Exception $e ){}
			}

			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'OK' );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'account_approved' );
			}
		}
		else
		{
			/* Get existing banned IPs */
			$bannedIps = iterator_to_array( \IPS\Db::i()->select( 'ban_content', 'core_banfilters', array( 'ban_type=?', 'ip' ) )->setKeyField( 'ban_content' ) );
			
			/* Build form */
			$form = new \IPS\Helpers\Form;
			$form->add( new \IPS\Helpers\Form\Date( 'member_ban_until', $member->temp_ban, FALSE, array(
				'time'				=> TRUE,
				'unlimited'			=> -1,
				'unlimitedLang'		=> 'permanently',
				'unlimitedToggles'	=> $member->temp_ban == -1 ? array() : array( 'member_ban_group', 'member_ban_ips' ),
				'unlimitedToggleOn'	=> FALSE,
			), NULL, NULL, NULL, 'member_ban_until' ) );
			
			if ( $member->temp_ban === 0 )
			{
				$form->add( new \IPS\Helpers\Form\Select( 'member_ban_group', $member->member_group_id, FALSE, array( 'options' => \IPS\Member\Group::groups( FALSE, FALSE ), 'parse' => 'normal' ), NULL, NULL, NULL, 'member_ban_group' ) );
				$memberIps = array_keys( $member->ipAddresses() );
				$form->add( new \IPS\Helpers\Form\Select( 'member_ban_ips', array_intersect( $memberIps, $bannedIps ), FALSE, array( 'options' => $memberIps, 'multiple' => TRUE ), NULL, NULL, NULL, 'member_ban_ips' ) );
			}
			
			/* Ban */
			if ( $values = $form->values() )
			{
				$_existingValue	= $member->temp_ban;

				if ( $values['member_ban_until'] === -1 )
				{
					$member->temp_ban = -1;
				}
				elseif ( !$values['member_ban_until'] )
				{
					$member->temp_ban = 0;
				}
				else
				{
					$member->temp_ban = $values['member_ban_until']->getTimestamp();
				}
				
				if ( isset( $values['member_ban_group'] ) )
				{
					$member->member_group_id = $values['member_ban_group'];
				}
				if ( isset( $values['member_ban_ips'] ) )
				{
					foreach ( $memberIps as $ip )
					{
						if ( in_array( $ip, $values['member_ban_ips'] ) and !in_array( $ip, $bannedIps ) )
						{
							\IPS\DB::i()->insert( 'core_banfilters', array( 'ban_type' => 'ip', 'ban_content' => $ip, 'ban_date' => time(), 'ban_reason' => $member->name ) );
						}
						elseif ( !in_array( $ip, $values['member_ban_ips'] ) and in_array( $ip, $bannedIps ) )
						{
							\IPS\Db::i()->delete( 'core_banfilters', array( 'ban_content=? AND ban_type=?', $ip, 'ip' ) );
						}
					}

					unset( \IPS\Data\Store::i()->bannedIpAddresses );
				}
				
				$member->save();

				/* Login handler callback */
				if( ( $_existingValue == 0 AND $member->temp_ban == -1 ) OR ( $_existingValue == -1 AND $member->temp_ban == 0 ) )
				{
					foreach ( \IPS\Login::handlers( TRUE ) as $k => $handler )
					{
						try
						{
							$handler->banAccount( $member, ( $member->temp_ban == -1 ) ? TRUE : FALSE );
						}
						catch( \Exception $e ){}
					}
				}

				\IPS\Session::i()->log( 'acplog__members_edited', array( $member->name => FALSE ) );
				
				\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'saved' );
			}
			
			/* Display */
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->outputTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core' ), 'blankTemplate' );
			}
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( 'ban', $form, FALSE );
		}
	}
	
	/**
	 * Login as member
	 *
	 * @return	void
	 */
	public function login()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_login' );
		
		/* Load Member and Admin*/
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		$admin = \IPS\Member::loggedIn();
		
		/* Generate a hash and store it in \IPS\Data\Store */
		$key = md5( uniqid() );
		\IPS\Data\Store::i()->admin_login_as_user = $key;
		
		/* Log It */
		\IPS\Session::i()->log( 'acplog__members_loginas', array( $member->name => FALSE ) );
		
		/* Redirect to front controller to update session */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login&do=loginas&admin={$admin->member_id}&id={$member->member_id}&key={$key}", 'front' ) );
	}
	
	/**
	 * Delete Content
	 *
	 * @return	void
	 */
	public function deleteContent()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'membertools_delete' );
		
		/* Load Member */
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		
		/* Build form */
		$form = new \IPS\Helpers\Form('delete_content', 'delete');
		$form->add( new \IPS\Helpers\Form\Radio( 'hide_or_delete_content', NULL, TRUE, array( 'options' => array( 'hide' => 'hide', 'delete' => 'delete' ) ) ) );
		if ( $values = $form->values() )
		{
			$member->hideOrDeleteAllContent( $values['hide_or_delete_content'] );
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'deleted' );
		}
		
		/* Display */
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->outputTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core' ), 'blankTemplate' );
		}
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( 'deletecontent', $form, FALSE );
	}
	
	/**
	 * Delete Guest Content
	 *
	 * @return	void
	 */
	public function deleteGuestContent()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'membertools_delete' );
		
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Text( 'guest_name_to_delete', NULL, TRUE ) );
		if ( $values = $form->values() )
		{
			$classes = array();
			foreach ( \IPS\Content::routedClasses( FALSE, FALSE, TRUE ) as $class )
			{
				if ( isset( $class::$databaseColumnMap['author'] ) and isset( $class::$databaseColumnMap['author_name'] ) )
				{
					\IPS\Task::queue( 'core', 'MemberContent', array( 'member_id' => 0, 'name' => $values['guest_name_to_delete'], 'class' => $class, 'action' => 'delete' ) );
				}
			}
			
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=membersettings&controller=spam&searchResult=guest_captcha" ), 'deleted' );
		}
		
		\IPS\Output::i()->output = $form;
	}
	
	/**
	 * Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_delete' );
		
		/* Load member */
		try
		{
			$member = \IPS\Member::load( \IPS\Request::i()->id );
			if ( $member->isAdmin() )
			{
				\IPS\Dispatcher::i()->checkAcpPermission( 'member_delete_admin' );
			}

			if( $member->member_id == \IPS\Member::loggedIn()->member_id )
			{
				throw new \OutOfRangeException;
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C114/7', 404, '' );
		}
		
		/* Delete */
		\IPS\Session::i()->log( 'acplog__members_deleted', array( $member->name => FALSE ) );
		$member->delete();
		
		/* Boink */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members" ), 'saved' );
	}
	
	/**
	 * Admin Details
	 *
	 * @return	void
	 */
	public function adminDetails()
	{
		$details = array(
					'username'		=> \IPS\Member::loggedIn()->name,
					'email_address'	=> \IPS\Member::loggedIn()->email,
					'password'		=> \IPS\Member::loggedIn()->language()->addToStack('password_hidden'),
				);
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('change_details');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'members', 'core' )->adminDetails( $details );
	}
	
	/**
	 * Admin Password
	 *
	 * @return	void

	 */
	protected function adminPassword()
	{
		$form = new \IPS\Helpers\Form( 'form' );
		$form->add( new \IPS\Helpers\Form\Password( 'current_password', '', TRUE, array( 'validateFor' => \IPS\Member::loggedIn() ) ) );
		$form->add( new \IPS\Helpers\Form\Password( 'new_password', '', TRUE ) );
		$form->add( new \IPS\Helpers\Form\Password( 'confirm_new_password', '', TRUE, array( 'confirm' => 'new_password' ) ) );
		
		if ( $values = $form->values() )
		{
			/* Save it */
			foreach ( \IPS\Login::handlers( TRUE ) as $handler )
			{
				try
				{
					$handler->changePassword( \IPS\Member::loggedIn(), $values['new_password'] );
				}
				catch( \BadMethodCallException $e ) {}
			}
			
			/* Log */
			\IPS\Session::i()->log( 'acplogs__admin_pass_updated' );
			
			/* Redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=adminDetails" ), 'saved' );
		}

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( \IPS\Member::loggedIn()->language()->addToStack('change_password'), $form, FALSE );
	}
	
	/**
	 * Admin Email
	 *
	 * @return	void
	 */
	public function adminEmail()
	{
		$form = new \IPS\Helpers\Form( 'form' );
		$form->add( new \IPS\Helpers\Form\Email( 'email_address', NULL, TRUE, array() ) );
		
		if ( $values = $form->values() )
		{
			/* Save it */
			if ( $values['email_address'] != \IPS\Member::loggedIn()->email )
			{
				foreach ( \IPS\Login::handlers( TRUE ) as $handler )
				{
					try
					{
						$handler->changeEmail( \IPS\Member::loggedIn(), \IPS\Member::loggedIn()->email, $values['email_address'] );
					}
					catch( \BadMethodCallException $e ) {}
				}
			}
			
			/* Log */
			\IPS\Session::i()->log( 'acplogs__admin_email_updated' );
			 	
			/* Redirect */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=adminDetails" ), 'saved' );
		}
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate('global')->block( \IPS\Member::loggedIn()->language()->addToStack('change_email'), $form, FALSE );
	}
	
	/**
	 * Recount Content Item Count
	 *
	 * @return	void
	 */
	public function recountContent()
	{
		if ( !\IPS\Request::i()->prompt )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=reset&do=posts' ) );
		}
		
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_recount_content' );
		
		/* Load Member */
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		
		/* Rebuild */
		$member->recountContent();
		
		/* redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'saved' );
	}
	
	/**
	 * Recount Reputation Count
	 *
	 * @return	void
	 */
	public function recountReputation()
	{
		if ( !\IPS\Request::i()->prompt )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=members&controller=reset&do=rep' ) );
		}
		
		/* Check permission */
		\IPS\Dispatcher::i()->checkAcpPermission( 'member_recount_content' );
		
		/* Load Member */
		$member = \IPS\Member::load( \IPS\Request::i()->id );
		
		/* Rebuild */
		$member->recountReputation();
		
		/* redirect */
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=members&controller=members&do=edit&id={$member->member_id}" ), 'saved' );
	}
}