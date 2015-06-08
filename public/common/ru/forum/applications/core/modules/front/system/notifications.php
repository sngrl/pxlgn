<?php
/**
 * @brief		Notification Settings Controller
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		27 Aug 2013
 * @version		SVN_VERSION_NUMBER
 */
 
namespace IPS\core\modules\front\system;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Notification Settings Controller
 */
class _notifications extends \IPS\Dispatcher\Controller
{
	/**
	 * Execute
	 */
	protected function _checkLoggedIn()
	{
		if ( !\IPS\Member::loggedIn()->member_id )
		{
			\IPS\Output::i()->error( 'no_module_permission_guest', '2C154/2', 403, '' );
		}
	}
	
	/**
	 * View Notifications
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$this->_checkLoggedIn();

		/* Init table */
		$urlObject	= \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications', 'front', 'notifications' );
		$table = new \IPS\Notification\Table( $urlObject );
		$table->setMember( \IPS\Member::loggedIn() );		
		
		$notifications = $table->getRows();
	
		\IPS\Db::i()->update( 'core_notifications', array( 'read_time' => time() ), array( 'member=?', \IPS\Member::loggedIn()->member_id ) );
		\IPS\Member::loggedIn()->recountNotifications();
		
		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( array( 'data' => \IPS\Theme::i()->getTemplate( 'system' )->notificationsAjax( $notifications ) ) );
		}
		elseif ( isset( \IPS\Request::i()->format ) and \IPS\Request::i()->format === 'rss' )
		{
			$document = \IPS\Xml\Rss::newDocument( $urlObject, \IPS\Member::loggedIn()->language()->get('notifications'), sprintf( \IPS\Member::loggedIn()->language()->get( 'notifications_rss_desc' ), \IPS\Member::loggedIn()->name, \IPS\Settings::i()->board_name ) );
			
			if ( count( $table->getRows() ) )
			{
				foreach ( $table->getRows() as $notification )
				{
					\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $notification['data']['title'] );

					if( isset( $notification['data']['content'] ) )
					{
						\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $notification['data']['content'] );
					}

					$document->addItem( $notification['data']['title'], $notification['data']['url'], isset( $notification['data']['content'] ) ? $notification['data']['content'] : NULL, $notification['notification']->updated_time, $notification['notification']->id );
				}
			}
			
			/* @note application/rss+xml is not a registered IANA mime-type so we need to stick with text/xml for RSS */
			\IPS\Output::i()->sendOutput( $document->asXML(), 200, 'text/xml' );
		}
		else
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('notifications');
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Output::i()->title );
			\IPS\Output::i()->output = (string) $table;
		}
	}
	
	/**
	 * Options
	 *
	 * @return	void
	 */
	protected function options()
	{
		$this->_checkLoggedIn();

		/* Build form */
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\Checkbox( 'allow_admin_mails', \IPS\Member::loggedIn()->allow_admin_mails ) );

		$_autoTrack	= array();
		if( \IPS\Member::loggedIn()->auto_follow['content'] )
		{
			$_autoTrack[]	= 'content';
		}
		if( \IPS\Member::loggedIn()->auto_follow['comments'] )
		{
			$_autoTrack[]	= 'comments';
		}
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'auto_track', $_autoTrack, FALSE, array( 'options' => array( 'content' => 'auto_track_content', 'comments' => 'auto_track_comments' ), 'multiple' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'auto_track_type', \IPS\Member::loggedIn()->auto_follow['method'] ?: 'immediate', FALSE, array( 'options' => array(
			'immediate'	=> \IPS\Member::loggedIn()->language()->addToStack('follow_type_immediate'),
			'daily'		=> \IPS\Member::loggedIn()->language()->addToStack('follow_type_daily'),
			'weekly'	=> \IPS\Member::loggedIn()->language()->addToStack('follow_type_weekly')
		) ), NULL, NULL, NULL, 'auto_track_type' ) );
		$form->add( new \IPS\Helpers\Form\Checkbox( 'show_pm_popup', \IPS\Member::loggedIn()->members_bitoptions['show_pm_popup'] ) );
		$form->addMatrix( 'notifications', \IPS\Notification::buildMatrix( \IPS\Member::loggedIn() ) );
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			\IPS\Member::loggedIn()->allow_admin_mails = $values['allow_admin_mails'];
			\IPS\Member::loggedIn()->auto_track = json_encode( array(
				'content'	=> ( is_array( $values['auto_track'] ) AND in_array( 'content', $values['auto_track'] ) ) ? 1 : 0,
				'comments'	=> ( is_array( $values['auto_track'] ) AND in_array( 'comments', $values['auto_track'] ) ) ? 1 : 0,
				'method'	=> $values['auto_track_type']
			)	);
			\IPS\Member::loggedIn()->members_bitoptions['show_pm_popup'] = $values['show_pm_popup'];
			if ( isset( $values['notifications']['report_center'] ) and !$values['notifications']['report_center']['member_notifications_inline'] )
			{
				\IPS\Member::loggedIn()->members_bitoptions['no_report_count'] = TRUE;
			}
			else
			{
				\IPS\Member::loggedIn()->members_bitoptions['no_report_count'] = FALSE;
			}			
			\IPS\Notification::saveMatrix( \IPS\Member::loggedIn(), $values );
			\IPS\Member::loggedIn()->save();
			\IPS\Output::i()->inlineMessage = \IPS\Member::loggedIn()->language()->addToStack('saved');
		}
		
		/* Display */
		\IPS\Output::i()->output = $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'system' ), 'notificationsSettings' ) );
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('notification_options');
		\IPS\Output::i()->breadcrumb[] = array( \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications', 'front', 'notifications' ), \IPS\Member::loggedIn()->language()->addToStack('notifications') );
		\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('options') );
	}
	
	/**
	 * Follow Something
	 *
	 * @return	void
	 */
	protected function follow()
	{
		$this->_checkLoggedIn();

		/* Get class */
		$class = NULL;
		foreach ( \IPS\Application::load( \IPS\Request::i()->follow_app )->extensions( 'core', 'ContentRouter' ) as $ext )
		{
			foreach ( $ext->classes as $classname )
			{
				if ( $classname == 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . ucfirst( \IPS\Request::i()->follow_area ) )
				{
					$class = $classname;
					break;
				}
				if ( isset( $classname::$containerNodeClass ) and $classname::$containerNodeClass == 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . ucfirst( \IPS\Request::i()->follow_area ) )
				{
					$class = $classname::$containerNodeClass;
					break;
				}
				if( isset( $classname::$containerFollowClasses ) )
				{
					foreach( $classname::$containerFollowClasses as $followClass )
					{
						if( $followClass == 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . ucfirst( \IPS\Request::i()->follow_area ) )
						{
							$class = $followClass;
							break;
						}
					}
				}
			}
		}
		
		if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'member' )
		{
			/* You can't follow yourself */
			if( \IPS\Request::i()->follow_id == \IPS\Member::loggedIn()->member_id )
			{
				\IPS\Output::i()->error( 'cant_follow_self', '3C154/7', 403, '' );
			}
			
			/* Following disabled */
			$member = \IPS\Member::load( \IPS\Request::i()->follow_id );

			if( !$member->member_id )
			{
				\IPS\Output::i()->error( 'cant_follow_member', '3C154/9', 403, '' );
			}

			if( $member->members_bitoptions['pp_setting_moderate_followers'] and !\IPS\Member::loggedIn()->following( 'core', 'member', $member->member_id ) )
			{
				\IPS\Output::i()->error( 'cant_follow_member', '3C154/8', 403, '' );
			}
				
			$class = 'IPS\\Member';
		}
		
		if ( !$class )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/3', 404, '' );
		}
		
		/* Get thing */
		$thing = NULL;
		try
		{
			if ( in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				$thing = $class::loadAndCheckPerms( \IPS\Request::i()->follow_id );
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'follow_thing', FALSE, array( 'sprintf' => array( $thing->_title ) ) );

				/* Set navigation */
				try
				{
					foreach ( $thing->parents() as $parent )
					{
						\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
					}
					\IPS\Output::i()->breadcrumb[] = array( NULL, $thing->_title );
				}
				catch ( \Exception $e ) { }
			}
			else if ( $class != "IPS\Member" )
			{
				$thing = $class::loadAndCheckPerms( \IPS\Request::i()->follow_id );
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'follow_thing', FALSE, array( 'sprintf' => array( $thing->mapped('title') ) ) );

				/* Set navigation */
				$container = NULL;
				try
				{
					$container = $thing->container();
					foreach ( $container->parents() as $parent )
					{
						\IPS\Output::i()->breadcrumb[] = array( $parent->url(), $parent->_title );
					}
					\IPS\Output::i()->breadcrumb[] = array( $container->url(), $container->_title );
				}
				catch ( \Exception $e ) { }
				
				/* Set meta tags */
				\IPS\Output::i()->breadcrumb[] = array( NULL, $thing->mapped('title') );
			}
			else 
			{
				$thing = $class::load( \IPS\Request::i()->follow_id );
				\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('follow_thing', FALSE, array( 'sprintf' => array( $thing->name ) ) );

				/* Set navigation */
				\IPS\Output::i()->breadcrumb[] = array( NULL, $thing->name );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/4', 404, '' );
		}
		
		/* Do we follow it? */
		try
		{
			$current = \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_app=? AND follow_area=? AND follow_rel_id=? AND follow_member_id=?', \IPS\Request::i()->follow_app, \IPS\Request::i()->follow_area, \IPS\Request::i()->follow_id, \IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \UnderflowException $e )
		{
			$current = FALSE;
		}
				
		/* How do we receive notifications? */
		$type = in_array( 'IPS\Content\Item', class_parents( $class ) ) ? 'new_comment' : 'new_content';
		$notificationConfiguration = \IPS\Member::loggedIn()->notificationsConfiguration();
		$notificationConfiguration = isset( $notificationConfiguration[ $type ] ) ? $notificationConfiguration[ $type ] : array();
		$lang = 'follow_type_immediate';
		if ( in_array( 'email', $notificationConfiguration ) and in_array( 'inline', $notificationConfiguration ) )
		{
			$lang = 'follow_type_immediate_inline_email';
		}
		elseif ( in_array( 'email', $notificationConfiguration ) )
		{
			$lang = 'follow_type_immediate_email';
		}
		
		if ( $class == "IPS\Member" )
		{
			\IPS\Member::loggedIn()->language()->words[ $lang ] = \IPS\Member::loggedIn()->language()->addToStack( $lang . '_member', FALSE, array( 'sprintf' => array( $thing->name ) ) );
		}
		
		if ( empty( $notificationConfiguration ) )
		{
			\IPS\Member::loggedIn()->language()->words[ $lang . '_desc' ] = \IPS\Member::loggedIn()->language()->addToStack( 'follow_type_immediate_none', FALSE ) . ' <a href="' .  \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options&type=' . $type, 'front', 'notifications_options' ) . '">' . \IPS\Member::loggedIn()->language()->addToStack( 'notification_options', FALSE ) . '</a>';
		}
		else
		{
			\IPS\Member::loggedIn()->language()->words[ $lang . '_desc' ] = '<a href="' .  \IPS\Http\Url::internal( 'app=core&module=system&controller=notifications&do=options&type=' . $type, 'front', 'notifications_options' ) . '">' . \IPS\Member::loggedIn()->language()->addToStack( 'follow_type_immediate_change', FALSE ) . '</a>';
		}
			
		/* Build form */
		$form = new \IPS\Helpers\Form( 'follow', ( $current ) ? 'update_follow' : 'follow', NULL, array(
			'data-followApp' 	=> \IPS\Request::i()->follow_app,
			'data-followArea' 	=> \IPS\Request::i()->follow_area,
			'data-followID' 	=> \IPS\Request::i()->follow_id
		) );

		$form->class = 'ipsForm_vertical';
		
		$options = array();
		$options['immediate'] = $lang;
		
		if ( $class != "IPS\Member" )
		{
			$options['daily']	= \IPS\Member::loggedIn()->language()->addToStack('follow_type_daily');
			$options['weekly']	= \IPS\Member::loggedIn()->language()->addToStack('follow_type_weekly');
			$options['none']	= \IPS\Member::loggedIn()->language()->addToStack('follow_type_no_notification');
		}
		
		if ( count( $options ) > 1 )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'follow_type', $current ? $current['follow_notify_freq'] : NULL, TRUE, array(
				'options'	=> $options,
				'disabled'	=> empty( $notificationConfiguration ) ? array( 'immediate' ) : array()
			) ) );
		}
		else
		{	
			foreach ( $options as $k => $v )
			{
				$form->hiddenValues[ $k ] = $v;
				$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack( $v ) . '<br>' . \IPS\Member::loggedIn()->language()->addToStack( $lang  . '_desc' ), '', FALSE );
			}
		}
		$form->add( new \IPS\Helpers\Form\Checkbox( 'follow_public', $current ? !$current['follow_is_anon'] : TRUE, FALSE, array(
			'label' => ( $class != "IPS\Member" ) ? \IPS\Member::loggedIn()->language()->addToStack( 'follow_public' ) : \IPS\Member::loggedIn()->language()->addToStack('follow_public_member', FALSE, array( 'sprintf' => array( $thing->name ) ) )
		) ) );
		if ( $current )
		{
			$form->addButton( 'unfollow', 'link', \IPS\Http\Url::internal( "app=core&module=system&section=notifications&do=unfollow&id={$current['follow_id']}&follow_app={$current['follow_app']}&follow_area={$current['follow_area']}" )->csrf(), 'ipsButton ipsButton_negative ipsPos_right', array('data-action' => 'unfollow') );
		}
		
		/* Handle submissions */
		if ( $values = $form->values() )
		{
			/* Insert */
			$save = array(
				'follow_id'			=> md5( \IPS\Request::i()->follow_app . ';' . \IPS\Request::i()->follow_area . ';' . \IPS\Request::i()->follow_id . ';' .  \IPS\Member::loggedIn()->member_id ),
				'follow_app'			=> \IPS\Request::i()->follow_app,
				'follow_area'			=> \IPS\Request::i()->follow_area,
				'follow_rel_id'		=> \IPS\Request::i()->follow_id,
				'follow_member_id'	=> \IPS\Member::loggedIn()->member_id,
				'follow_is_anon'		=> !$values['follow_public'],
				'follow_added'		=> time(),
				'follow_notify_do'	=> ( isset( $values['follow_type'] ) AND $values['follow_type'] == 'none' ) ? 0 : 1,
				'follow_notify_meta'	=> '',
				'follow_notify_freq'	=> ( $class == "IPS\Member" ) ? 'immediate' : $values['follow_type'],
				'follow_notify_sent'	=> 0,
				'follow_visible'		=> 1
			);
			if ( $current )
			{
				\IPS\Db::i()->update( 'core_follow', $save, array( 'follow_id=?', $current['follow_id'] ) );
			}
			else
			{
				\IPS\Db::i()->insert( 'core_follow', $save );
			}
			
			/* Send notification if following member */
			if( $class == "IPS\Member"  )
			{
				$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'member_follow', \IPS\Member::loggedIn(), array( \IPS\Member::loggedIn() ) );
				$notification->recipients->attach( $thing );
				$notification->send();
			}
			
			/* Boink */
			if ( \IPS\Request::i()->isAjax() )
			{
				\IPS\Output::i()->json( 'ok' );
			}
			else
			{
				\IPS\Output::i()->redirect( $thing->url() );
			}
		}

		/* Display */
		$output = $form->customTemplate( array( call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), array( 'system', 'core' ) ), 'followForm' ) );

		/* @see http://community.invisionpower.com/4bugtrack/follow-button-adds-meta-title-and-link-tags-r3209/ */
		if( \IPS\Request::i()->isAjax() ){
			\IPS\Output::i()->sendOutput( $output, 200, 'text/html', \IPS\Output::i()->httpHeaders );	
		} else {
			\IPS\Output::i()->output = $output;
		}		
	}
	
	/**
	 * Unfollow
	 *
	 * @return	void
	 */
	protected function unfollow()
	{
		$this->_checkLoggedIn();

		\IPS\Session::i()->csrfCheck();
		
		try
		{
			$follow = \IPS\Db::i()->select( '*', 'core_follow', array( 'follow_id=? AND follow_member_id=?', \IPS\Request::i()->id, \IPS\Member::loggedIn()->member_id ) )->first();
		}
		catch ( \OutOfRangeException $e ) {}
		
		\IPS\Db::i()->delete( 'core_follow', array( 'follow_id=? AND follow_member_id=?', \IPS\Request::i()->id, \IPS\Member::loggedIn()->member_id ) );

		/* Get class */
		$class = NULL;
		foreach ( \IPS\Application::load( \IPS\Request::i()->follow_app )->extensions( 'core', 'ContentRouter' ) as $ext )
		{
			foreach ( $ext->classes as $classname )
			{
				if ( $classname == 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . ucfirst( \IPS\Request::i()->follow_area ) )
				{
					$class = $classname;
					break;
				}
				if ( isset( $classname::$containerNodeClass ) and $classname::$containerNodeClass == 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . ucfirst( \IPS\Request::i()->follow_area ) )
				{
					$class = $classname::$containerNodeClass;
					break;
				}
				if( isset( $classname::$containerFollowClasses ) )
				{
					foreach( $classname::$containerFollowClasses as $followClass )
					{
						if( $followClass == 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . ucfirst( \IPS\Request::i()->follow_area ) )
						{
							$class = $followClass;
							break;
						}
					}
				}
			}
		}
		
		if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'member' )
		{
			$class = 'IPS\\Member';
		}

		/* Get thing */
		$thing = NULL;

		try
		{
			if ( $class != "IPS\Member" )
			{
				$thing = $class::loadAndCheckPerms( $follow['follow_rel_id'] );
			}
			else if( !in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				$thing = $class::load( $follow['follow_rel_id'] );
			}
		}
		catch ( \OutOfRangeException $e )
		{
		}

		if ( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json( 'ok' );
		}
		else
		{
			\IPS\Output::i()->redirect( isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : \IPS\Settings::i()->base_url );
		}
	}
	
	/**
	 * Show Followers
	 *
	 * @return	void
	 */
	protected function followers()
	{
		$perPage = 50;
		$thisPage = isset( \IPS\Request::i()->followerPage ) ? \IPS\Request::i()->followerPage : 1;
				
		/* Get class */
		if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'member' )
		{
			$class = 'IPS\\Member';
		}
		else
		{
			$class = 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . ucfirst( \IPS\Request::i()->follow_area );
		}
		
		if ( !class_exists( $class ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/5', 404, '' );
		}
		
		/* Get thing */
		$thing = NULL;
		$anonymous = 0;
		try
		{
			if ( in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				$classname = $class::$contentItemClass;
				$containerClass = $class;
				$thing = $containerClass::loadAndCheckPerms( \IPS\Request::i()->follow_id );
				$followers = $classname::containerFollowers( $thing, $classname::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( ( $thisPage - 1 ) * $perPage, $perPage ), 'name' );
				$anonymous = $classname::containerFollowers( $thing, $classname::FOLLOW_ANONYMOUS )->count( TRUE );
				$title = $thing->_title;
			}
			else if ( $class != "IPS\Member" )
			{
				$thing = $class::loadAndCheckPerms( \IPS\Request::i()->follow_id );
				$followers = $thing->followers( $class::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( ( $thisPage - 1 ) * $perPage, $perPage ), 'name' );
				$anonymous = $thing->followers( $class::FOLLOW_ANONYMOUS )->count( TRUE );
				$title = $thing->name;
			}
			else
			{
				$thing = $class::load( \IPS\Request::i()->follow_id );
				$followers = $thing->followers( $class::FOLLOW_PUBLIC, array( 'none', 'immediate', 'daily', 'weekly' ), NULL, array( ( $thisPage - 1 ) * $perPage, $perPage ), 'name' );
				$anonymous = $thing->followers( $class::FOLLOW_ANONYMOUS )->count( TRUE );
				$title = $thing->mapped('title');
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/6', 404, '' );
		}
				
		/* Display */
		if ( \IPS\Request::i()->isAjax() and isset( \IPS\Request::i()->_infScroll ) )
		{
			\IPS\Output::i()->sendOutput(  \IPS\Theme::i()->getTemplate( 'system' )->followersRows( $followers ) );
		}
		else
		{
			$url = \IPS\Http\Url::internal( "app=core&module=system&section=notifications&do=followers&follow_app=". \IPS\Request::i()->follow_app ."&follow_area=". \IPS\Request::i()->follow_area ."&follow_id=" . \IPS\Request::i()->follow_id . "&_infScroll=1" );
			$pagination = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $url, ceil( $followers->count( TRUE ) / $perPage ), $thisPage, $perPage, FALSE, 'followerPage' );
			
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('item_followers', FALSE, array( 'sprintf' => array( $title ) ) );
			\IPS\Output::i()->breadcrumb[] = array( $thing->url(), $title );
			\IPS\Output::i()->breadcrumb[] = array( NULL, \IPS\Member::loggedIn()->language()->addToStack('who_follows_this') );
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'system' )->followers( $url, $pagination, $followers, $anonymous );
		}
	}

	/**
	 * Follow button
	 *
	 * @return	void
	 */
	protected function button()
	{
		/* Get class */
		if( \IPS\Request::i()->follow_app == 'core' and \IPS\Request::i()->follow_area == 'member' )
		{
			$class = 'IPS\\Member';
		}
		else
		{
			$class = 'IPS\\' . \IPS\Request::i()->follow_app . '\\' . ucfirst( \IPS\Request::i()->follow_area );
		}
		if ( !class_exists( $class ) )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/5', 404, '' );
		}
		
		/* Get thing */
		$thing = NULL;
		try
		{
			if ( in_array( 'IPS\Node\Model', class_parents( $class ) ) )
			{
				$classname = $class::$contentItemClass;
				$containerClass = $class;
				$thing = $containerClass::loadAndCheckPerms( \IPS\Request::i()->follow_id );
				$count = $classname::containerFollowerCount( $thing );
			}
			else if ( $class != "IPS\Member" )
			{
				$thing = $class::loadAndCheckPerms( \IPS\Request::i()->follow_id );
				$count = $thing->followers()->count( TRUE );
			}
			else
			{
				$thing = $class::load( \IPS\Request::i()->follow_id );
				$count = $thing->followers()->count( TRUE );
			}
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2C154/6', 404, '' );
		}

		if ( \IPS\Request::i()->follow_area == 'member' )
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'profile' )->memberFollowButton( \IPS\Request::i()->follow_app, \IPS\Request::i()->follow_area, \IPS\Request::i()->follow_id, $count );
		}
		else
		{
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->followButton( \IPS\Request::i()->follow_app, \IPS\Request::i()->follow_area, \IPS\Request::i()->follow_id, $count );
		}
	}
}