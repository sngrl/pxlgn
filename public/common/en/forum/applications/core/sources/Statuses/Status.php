<?php
/**
 * @brief		Status Update Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		10 Feb 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\Statuses;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Status Update Model
 */
class _Status extends \IPS\Content\Item implements \IPS\Content\ReportCenter, \IPS\Content\Reputation, \IPS\Content\Lockable, \IPS\Content\Hideable
{
	/* !\IPS\Patterns\ActiveRecord */
	
	/**
	 * @brief	Database Table
	 */
	public static $databaseTable = 'core_member_status_updates';
	
	/**
	 * @brief	Database Prefix
	 */
	public static $databasePrefix = 'status_';
	
	/**
	 * @brief	[Content\Comment]	Icon
	 */
	public static $icon = 'comment-o';
	
	/**
	 * @brief	Number of comments per page
	 */
	public static $commentsPerPage = 3;

	/**
	 * @brief	Number of comments per page when requesting previous replies (ajax)
	 */
	public static $commentsPerPageAjax = 25;

	/**
	 * @brief	Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		foreach( new \IPS\Patterns\ActiveRecordIterator( \IPS\Db::i()->select( '*', 'core_member_status_replies', array( 'reply_status_id=?', $this->id ) ), 'IPS\core\Statuses\Reply' ) AS $reply )
		{
			$reply->delete();
		}
		
		parent::delete();
	}
	
	/* !\IPS\Content\Item */

	/**
	 * @brief	Title
	 */
	public static $title = 'member_status';
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'id';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'date'			=> 'date',
		'author'		=> 'author_id',
		'num_comments'	=> 'replies',
		'locked'		=> 'is_locked',
		'approved'		=> 'approved',
		'ip_address'	=> 'author_ip',
		'content'		=> 'content',
		'title'			=> 'content',
	);
	
	/**
	 * @brief	Application
	 */
	public static $application = 'core';
	
	/**
	 * @brief	Module
	 */
	public static $module = 'members';
	
	/**
	 * @brief	Language prefix for forms
	 */
	public static $formLangPrefix = 'status_';
	
	/**
	 * @brief	Comment Class
	 */
	public static $commentClass = 'IPS\core\Statuses\Reply';
	
	/**
	 * @brief	[Content\Item]	First "comment" is part of the item?
	 */
	public static $firstCommentRequired = FALSE;
	
	/**
	 * @brief	Reputation Type
	 */
	public static $reputationType = 'status_id';
	
		/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'status_status';
	
	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return FALSE;
	}
	
	/**
	 * Get Title
	 *
	 * @return	string
	 */
	public function get_title()
	{
		return strip_tags( $this->mapped('content') );
	}
	
	/**
	 * Get mapped value
	 *
	 * @param	string	$key	date,content,ip_address,first
	 * @return	mixed
	 */
	public function mapped( $key )
	{
		if ( $key === 'title' )
		{
			return $this->title;
		}
		
		return parent::mapped( $key );
	}

	/**
	 * Can a given member create a status update?
	 *
	 * @param \IPS\Member $member
	 * @return bool
	 */
	public static function canCreateFromCreateMenu( \IPS\Member $member = null)
	{
		if ( !$member )
		{
			$member = \IPS\Member::loggedIn();
		}

		/* Can we access the module? */
		if ( !parent::canCreate( $member, NULL, FALSE ) )
		{
			return FALSE;
		}

		/* We have to be logged in */
		if ( !$member->member_id )
		{
			return FALSE;
		}

		if ( !$member->pp_setting_count_comments or !\IPS\Settings::i()->profile_comments )
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Can a given member create this type of content?
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @param	bool		$showError	If TRUE, rather than returning a boolean value, will display an error
	 * @return	bool
	 */
	public static function canCreate( \IPS\Member $member, \IPS\Node\Model $container=NULL, $showError=FALSE )
	{
		$profileOwner = isset( \IPS\Request::i()->id ) ? \IPS\Member::load( \IPS\Request::i()->id ) : \IPS\Member::loggedIn();
		
		/* Can we access the module? */
		if ( !parent::canCreate( $member, $container, $showError ) )
		{			
			return FALSE;
		}
		
		/* We have to be logged in */
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		/* Is the user being ignored */
		if ( $profileOwner->isIgnoring( $member, 'messages' ) )
		{
			return FALSE;
		}

		if ( !$profileOwner->pp_setting_count_comments and $member->member_id != \IPS\Request::i()->id )
		{	
			return FALSE;
		}
		
		return TRUE;
	}

	/**
	 * Get elements for add/edit form
	 *
	 * @param	\IPS\Content\Item|NULL	$item				The current item if editing or NULL if creating
	 * @param	\IPS\Node\Model|NULL	$container			Container (e.g. forum), if appropriate
	 * @param	bool 					$fromCreateMenu		false to deactivate the minimize feature
	 * @return	array
	 */
	public static function formElements( $item=NULL, \IPS\Node\Model $container=NULL , $fromCreateMenu=FALSE)
	{
		$formElements = parent::formElements( $item, $container );

		if ( $fromCreateMenu )
		{
			$minimize = NULL;
		}
		else
		{
			$member = isset( \IPS\Request::i()->id ) ? \IPS\Member::load( \IPS\Request::i()->id ) : \IPS\Member::loggedIn();

			$minimize = ( $member->member_id != \IPS\Member::loggedIn()->member_id ) ?
				\IPS\Member::loggedIn()->language()->addToStack( static::$formLangPrefix . '_update_placeholder_other', FALSE, array( 'sprintf' => array( $member->name ) ) ) :
				static::$formLangPrefix . '_update_placeholder';
		}

		$formElements['status_content'] = new \IPS\Helpers\Form\Editor( static::$formLangPrefix . 'content' . ( $fromCreateMenu ? '_ajax' : '' ), ( $item ) ? $item->content : NULL, TRUE, array(
				'app'			=> static::$application,
				'key'			=> 'Members',
				'autoSaveKey' 	=> 'status',
				'minimize'		=> $minimize,
			), '\IPS\Helpers\Form::floodCheck' );
				
		return $formElements;
	}
	
	/**
	 * Create from form
	 *
	 * @param	array					$values				Values from form
	 * @param	\IPS\Node\Model|NULL	$container			Container (e.g. forum), if appropriate
	 * @param	bool					$sendNotification	TRUE to automatically send new content notifications (useful for items that may be uploaded in bulk)
	 * @return	\IPS\Content\Item
	 */
	public static function createFromForm( $values, \IPS\Node\Model $container = NULL, $sendNotification = TRUE )
	{
		/* Create */
		$status = parent::createFromForm( $values, $container, $sendNotification );
		\IPS\File::claimAttachments( 'status', $status->id );
		
		/* Sync */
		if ( $syncSettings = json_decode( \IPS\Member::loggedIn()->profilesync, TRUE ) )
		{
			foreach ( $syncSettings as $provider => $settings )
			{
				if ( isset( $settings['status'] ) and $settings['status'] === 'export' )
				{
					$class= 'IPS\core\ProfileSync\\' . ucfirst( $provider );
					$sync = new $class( \IPS\Member::loggedIn() );
					if ( method_exists( $sync, 'exportStatus' ) )
					{
						try
						{
							$sync->exportStatus( $status );
						}
						catch ( \Exception $e ) { }
					}
				}
			}
		}
		
		/* Return */
		return $status;
	}
	
	/**
	 * Process create/edit form
	 *
	 * @param	array				$values	Values from form
	 * @return	void
	 */
	public function processForm( $values )
	{
		parent::processForm( $values );
	
		$this->member_id = isset( \IPS\Request::i()->id ) ? \IPS\Request::i()->id : \IPS\Member::loggedIn()->member_id;
		
		if ( !$this->_new )
		{
			$oldContent = $this->content;
		}
		$this->content	= $values['status_content'];
		if ( !$this->_new )
		{
			$this->sendAfterEditNotifications( $oldContent );
		}		
	}

	/**
	 * @brief	Cached URLs
	 */
	protected $_url	= array();

	/**
	 * Get URL
	 *
	 * @param	string|NULL		$action		Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action=NULL )
	{
		$_key	= md5( $action );

		if( !isset( $this->_url[ $_key ] ) )
		{
			$member = \IPS\Member::load( $this->member_id );
			$this->_url[ $_key ] = \IPS\Http\Url::internal( "app=core&module=members&controller=profile&id={$member->member_id}&status={$this->id}&type=status", 'front', 'profile', array( $member->members_seo_name ) );
		
			if ( $action )
			{
				if ( $action == 'edit' )
				{
					$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( 'do', 'editStatus' );
				}
				else
				{
					$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( array( 'do' => $action, 'type' => 'status' ) );
				}

				if ( $action == 'moderate' AND \IPS\Request::i()->controller == 'feed' )
				{
					$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( '_fromFeed', 1 );
				}
			}
		}
	
		return $this->_url[ $_key ];
	}
	
	/**
	 * Send notifications
	 *
	 * @return	void
	 */
	public function sendNotifications()
	{
		parent::sendNotifications();

		/* Notify when somebody comments on my profile */
		if( $this->author()->member_id != $this->member_id )	
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'profile_comment', $this, array( $this ) );
			$member = \IPS\Member::load( $this->member_id );
			$notification->recipients->attach( $member );
			
			$notification->send();
		}

		/* Notify when a follower posts a status update */
		if ( $this->author()->member_id == $this->member_id )
		{
			$notification	= new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_status', $this, array( $this ) );
			$followers		= \IPS\Member::load( $this->member_id )->followers( 3, array( 'immediate' ), $this->mapped('date'), NULL );

			if( is_array( $followers ) )
			{
				foreach( $followers AS $follower )
				{
					$notification->recipients->attach( \IPS\Member::load( $follower['follow_member_id'] ) );
				}
			}
			
			$notification->send();
		}
	}
	
	/**
	 * Should new items be moderated?
	 *
	 * @param	\IPS\Member		$member		The member posting
	 * @param	\IPS\Node\Model	$container	The container
	 * @return	bool
	 */
	public static function moderateNewItems( \IPS\Member $member, \IPS\Node\Model $container = NULL )
	{
		if ( $member->moderateNewContent() or \IPS\Settings::i()->profile_comment_approval )
		{
			return TRUE;
		}

		return parent::moderateNewItems( $member, $container );
	}
	
	/**
	 * Should new comments be moderated?
	 *
	 * @param	\IPS\Member	$member	The member posting
	 * @return	bool
	 */
	public function moderateNewComments( \IPS\Member $member )
	{
		return ( $member->moderateNewContent() or \IPS\Settings::i()->profile_comment_approval );
	}
	
	/**
	 * Can delete?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canDelete( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
	
		/* Profile owner should always be able to delete */
		if ( $member->member_id == $this->member_id )
		{
			return TRUE;
		}
		
		return parent::canDelete( $member );
	}
	
	/**
	 * Get comments
	 *
	 * @param	int|NULL			$limit					The number to get (NULL to use static::$commentsPerPage)
	 * @param	int|NULL			$offset					The number to start at (NULL to examine \IPS\Request::i()->page)
	 * @param	string				$order					The column to order by
	 * @param	string				$orderDirection			"asc" or "desc"
	 * @param	\IPS\Member|NULL	$member					If specified, will only get comments by that member
	 * @param	bool|NULL			$includeHiddenComments	Include hidden comments or not? NULL to base of currently logged in member's permissions
	 * @param	\IPS\DateTime|NULL	$cutoff					If an \IPS\DateTime object is provided, only comments posted AFTER that date will be included
	 * @param	mixed				$extraWhereClause	Additional where clause(s) (see \IPS\Db::build for details)
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public function comments( $limit=NULL, $offset=NULL, $order='date', $orderDirection=NULL, $member=NULL, $includeHiddenComments=NULL, $cutoff=NULL, $extraWhereClause=NULL )
	{
		if( ( \IPS\Request::i()->page OR $offset ) AND !$limit )
		{
			/* Prevent negative offsets */
			$page = ( isset( \IPS\Request::i()->page ) ) ? intval( \IPS\Request::i()->page ) : 1;

			if( $page < 1 )
			{
				$page	= 1;
			}

			$limit	= static::$commentsPerPageAjax;
			$offset	= $offset ?: ( $page - 1 ) * static::$commentsPerPage;
		}

		/* Unless specifically defined via API call, we need to order comments in a different direction than normal for the interface. */
		$sort = FALSE;
		if ( is_null( $orderDirection ) )
		{
			$sort			= TRUE;
			$orderDirection	= 'desc';
		}
		
		$return = parent::comments( $limit, $offset, $order, $orderDirection, $member, $includeHiddenComments, $cutoff );

		if ( $sort )
		{
			/* Reverse the order of the returned comments, so they display correctly when loaded (ex: ID 89, 90, 91, rather than 91, 90, 89) */
			ksort( $return );
		}

		return $return;
	}
	
	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'profile', 'core' ), 'statusContentRows' );
	}

	/**
	 * Get number of comments to show per page
	 *
	 * @return int
	 */
	public static function getCommentsPerPage()
	{
		return static::$commentsPerPage;
	}
}