<?php
/**
 * @brief		Content Comment Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		8 Jul 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Content;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Content Comment Model
 */
abstract class _Comment extends \IPS\Content
{
	/**
	 * @brief	[Content\Comment]	Comment Template
	 */
	public static $commentTemplate = array( array( 'global', 'core', 'front' ), 'commentContainer' );
	
	/**
	 * @brief	[Content\Comment]	Form Template
	 */
	public static $formTemplate = array( array( 'forms', 'core', 'front' ), 'commentTemplate' );
	
	/**
	 * @brief	[Content\Comment]	The ignore type
	 */
	public static $ignoreType = 'topics';
	
	/**
	 * @brief	[Content\Item]	Sharelink HTML
	 */
	protected $sharelinks = array();
	
	/**
	 * Create comment
	 *
	 * @param	\IPS\Content\Item		$item				The content item just created
	 * @param	string					$comment			The comment
	 * @param	bool					$first				Is the first comment?
	 * @param	string					$guestName			If author is a guest, the name to use
	 * @param	bool|NULL				$incrementPostCount	Increment post count? If NULL, will use static::incrementPostCount()
	 * @param	\IPS\Member|NULL		$member				The author of this comment. If NULL, uses currently logged in member.
	 * @param	\IPS\DateTime|NULL		$time				The time
	 * @return	static
	 */
	public static function create( $item, $comment, $first=FALSE, $guestName=NULL, $incrementPostCount=NULL, $member=NULL, \IPS\DateTime $time=NULL )
	{
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}

		/* Create the object */
		$obj = new static;
		foreach ( array( 'item', 'date', 'author', 'author_name', 'content', 'ip_address', 'first', 'approved', 'hidden' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$val = NULL;
				switch ( $k )
				{
					case 'item':
						$idColumn = $item::$databaseColumnId;
						$val = $item->$idColumn;
						break;
					
					case 'date':
						$val = ( $time ) ? $time->getTimestamp() : time();
						break;
					
					case 'author':
						$val = (int) $member->member_id;
						break;
						
					case 'author_name':
						$val = ( $member->member_id ) ? $member->name : ( $guestName ?: '' );
						break;
						
					case 'content':
						$val = $comment;
						break;
						
					case 'ip_address':
						$val = \IPS\Request::i()->ipAddress();
						break;
					
					case 'first':
						$val = $first;
						break;
						
					case 'approved':
						if ( in_array( 'IPS\Content\Review', class_parents( get_called_class() ) ) )
						{
							$val = $item->moderateNewReviews( $member ) ? 0 : 1;
						}
						else
						{
							$val = $item->moderateNewComments( $member ) ? 0 : 1;
						}

                        /* If this is the first post within an item, don't mark it hidden, otherwise the count of unapproved comments/items will include both the comment and the item when really only the item is hidden */
						if ( $first )
						{
							$val = TRUE;
						}
						break;
					
					case 'hidden':
						if ( in_array( 'IPS\Content\Review', class_parents( get_called_class() ) ) )
						{
							$val = $item->moderateNewReviews( $member ) ? 1 : 0;
						}
						else
						{
							$val = $item->moderateNewComments( $member ) ? 1 : 0;
						}
						
						/* If this is the first post within an item, don't mark it hidden, otherwise the count of unapproved comments/items will include both the comment and the item when really only the item is hidden */
						if ( $first )
						{
							$val = FALSE;
						}

						if ( ! $first and $item->approvedButHidden() )
						{
							$val = 2;
						}

						break;
				}
				
				foreach ( is_array( static::$databaseColumnMap[ $k ] ) ? static::$databaseColumnMap[ $k ] : array( static::$databaseColumnMap[ $k ] ) as $column )
				{
					$obj->$column = $val;
				}
			}
		}
		$obj->save();
		
		/* Increment post count */
		try
		{
			if ( !$obj->hidden() and ( $incrementPostCount === TRUE or ( $incrementPostCount === NULL and static::incrementPostCount( $item->container() ) ) ) )
			{
				$obj->author()->member_posts++;
			}
		}
		catch( \BadMethodCallException $e ) { }
		
		/* Update the container */
		if ( !$obj->hidden() AND !$item->hidden() )
		{
			try
			{
				$container = $item->container();
				if ( $container->_comments !== NULL )
				{
					$container->setLastComment( $obj );
					$container->save();
				}
			}
			catch ( \BadMethodCallException $e ) { }
		}

		/* Update member's last post */
		if( $obj->author()->member_id )
		{
			$obj->author()->member_last_post = time();
			$obj->author()->save();
		}
		
		/* Send notifications */
		if ( !$obj->hidden() and ( !$first or !$item::$firstCommentRequired ) )
		{
			$obj->sendNotifications();
		}
		else if( $obj->hidden() === 1 )
		{
			$obj->sendUnapprovedNotification();
		}
		
		/* Update item */
		$obj->postCreate();

		/* Add to search index */
		if ( $obj instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $obj );
		}

		/* Return */
		return $obj;
	}
	
	/**
	 * Join profile fields when loading comments?
	 */
	public static $joinProfileFields = FALSE;
	
	/**
	 * Joins (when loading comments)
	 *
	 * @param	\IPS\Content\Item	$item			The item
	 * @return	array
	 */
	public static function joins( \IPS\Content\Item $item )
	{
		$return = array();
		
		/* Author */
		$authorColumn = static::$databasePrefix . static::$databaseColumnMap['author'];
		$return['author'] = array(
			'select'	=> 'author.*',
			'from'		=> array( 'core_members', 'author' ),
			'where'		=> array( 'author.member_id = ' . static::$databaseTable . '.' . $authorColumn )
		);
		
		/* Author profile fields */
		if ( static::$joinProfileFields and \IPS\core\ProfileFields\Field::fieldsForContentView() )
		{
			$return['author_pfields'] = array(
				'select'	=> 'author_pfields.*',
				'from'		=> array( 'core_pfields_content', 'author_pfields' ),
				'where'		=> array( 'author_pfields.member_id=author.member_id' )
			);
		}
				
		return $return;
	}
	
	/**
	 * Do stuff after creating (abstracted as comments and reviews need to do different things)
	 *
	 * @return	void
	 */
	public function postCreate()
	{
		$item = $this->item();

		if( isset( static::$databaseColumnMap['date'] ) )
		{
			if( is_array( static::$databaseColumnMap['date'] ) )
			{
				$postDateColumn = static::$databaseColumnMap['date'][0];
			}
			else
			{
				$postDateColumn = static::$databaseColumnMap['date'];
			}
		}

		if ( !$this->hidden() or $item->approvedButHidden() )
		{
			if ( isset( $item::$databaseColumnMap['last_comment'] ) )
			{
				$lastCommentField = $item::$databaseColumnMap['last_comment'];
				if ( is_array( $lastCommentField ) )
				{
					foreach ( $lastCommentField as $column )
					{
						$item->$column = ( isset( $postDateColumn ) ) ? $this->$postDateColumn : time();
					}
				}
				else
				{
					$item->$lastCommentField = ( isset( $postDateColumn ) ) ? $this->$postDateColumn : time();
				}
			}
			if ( isset( $item::$databaseColumnMap['last_comment_by'] ) )
			{
				$lastCommentByField = $item::$databaseColumnMap['last_comment_by'];
				$item->$lastCommentByField = (int) $this->author()->member_id;
			}
			if ( isset( $item::$databaseColumnMap['last_comment_name'] ) )
			{
				$lastCommentNameField = $item::$databaseColumnMap['last_comment_name'];
				$item->$lastCommentNameField = $this->mapped('author_name');
			}
			if ( isset( $item::$databaseColumnMap['num_comments'] ) )
			{
				$numCommentsField = $item::$databaseColumnMap['num_comments'];
				$item->$numCommentsField = (int) $item->$numCommentsField + 1;
			}
			
			$item->save();
			
			if ( !$item->hidden() and ! $item->approvedButHidden() and $item->containerWrapper() and $item->container()->_comments !== NULL )
			{
				$item->container()->_comments = ( $item->container()->_comments + 1 );
				$item->container()->setLastComment( $this );
				$item->container()->save();
			}
		}
		else
		{
			if ( !$item->approvedButHidden() )
			{
				if ( isset( $item::$databaseColumnMap['unapproved_comments'] ) )
				{
					$numCommentsField = $item::$databaseColumnMap['unapproved_comments'];
					$item->$numCommentsField++;
				}
			}

			$item->save();
			
			if ( $item->containerWrapper() AND !$item->approvedButHidden() AND $item->container()->_unapprovedComments !== NULL )
			{
				$item->container()->_unapprovedComments = $item->container()->_unapprovedComments + 1;
				$item->container()->save();
			}
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
			$itemClass	= static::$itemClass;
			$itemField	= static::$databaseColumnMap['item'];
			$idColumn	= static::$databaseColumnId;
					
			$this->_url[ $_key ] = $this->item()->url();

			if( $action )
			{
				$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( array( 'do' => $action . 'Comment', 'comment' => $this->$idColumn ) );
			}
			else
			{
				$where = array( array( static::$databasePrefix . static::$databaseColumnMap['item'] . '=? AND ' . static::$databasePrefix . static::$databaseColumnId . '<=?', $this->$itemField, $this->$idColumn ) );
				
				if ( static::commentWhere() !== NULL )
				{
					$where[] = static::commentWhere();
				}
				
				$commentPosition = \IPS\Db::i()->select( 'COUNT(*) AS position', static::$databaseTable, $where, static::$databasePrefix . static::$databaseColumnMap['date'] . ' asc' )->first();
				$page = ceil( $commentPosition / $itemClass::getCommentsPerPage() );
				if ( $page != 1 )
				{
					$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( 'page', $page );
				}
				
				$this->_url[ $_key ] = $this->_url[ $_key ]->setFragment( 'comment-' . $this->$idColumn );
			}
		}
	
		return $this->_url[ $_key ];
	}

	/**
	 * Get containing item
	 *
	 * @return	\IPS\Content\Item
	 */
	public function item()
	{
		$itemClass = static::$itemClass;
		return $itemClass::load( $this->mapped( 'item' ) );
	}
	
	/**
	 * Is first message?
	 *
	 * @return	bool
	 */
	public function isFirst()
	{
		if ( isset( static::$databaseColumnMap['first'] ) )
		{
			if ( $this->mapped('first') )
			{
				return TRUE;
			}
		}
		return FALSE;
	}
	
	/**
	 * Get permission index ID
	 *
	 * @return	int|NULL
	 */
	public function permId()
	{
		return $this->item()->permId();
	}
	
	/**
	 * Can view?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @return	bool
	 */
	public function canView( $member=NULL )
	{
		if( $member === NULL )
		{
			$member	= \IPS\Member::loggedIn();
		}
				
		if ( $this instanceof \IPS\Content\Hideable and $this->hidden() and !$this->item()->canViewHiddenComments( $member ) and ( $this->hidden() !== 1 or $this->author() !== $member ) )
		{
			return FALSE;
		}

		return $this->item()->canView( $member );
	}
	
	/**
	 * Can edit?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canEdit( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		$container = NULL;
		try
		{
			$container = $this->item()->container();
		}
		catch ( \BadMethodCallException $e ) { }
		
		if ( $member->member_id )
		{
			if ( static::modPermission( 'edit', $member, $container ) )
			{
				return TRUE;
			}
			elseif ( $member->member_id == $this->author()->member_id and $member->group['g_edit_posts'] )
			{
				if ( !$member->group['g_edit_cutoff'] )
				{
					return TRUE;
				}
				else
				{
					if( \IPS\DateTime::ts( $this->mapped('date') )->add( new \DateInterval( "PT{$member->group['g_edit_cutoff']}M" ) ) > \IPS\DateTime::create() )
					{
						return TRUE;
					}
				}
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Can hide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canHide( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		$container = NULL;
		try
		{
			$container = $this->item()->container();
		}
		catch ( \BadMethodCallException $e ) { }

		return ( !$this->isFirst() and ( static::modPermission( 'hide', $member, $container ) or ( $member->member_id == $this->author()->member_id and $member->group['gbw_soft_delete_own'] ) ) );
	}
	
	/**
	 * Can unhide?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return  boolean
	 */
	public function canUnhide( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();

		$container = NULL;
		try
		{
			$container = $this->item()->container();
		}
		catch ( \BadMethodCallException $e ) { }

		$hiddenByItem = FALSE;
		if ( isset( static::$databaseColumnMap['hidden'] ) )
		{
			$column = static::$databaseColumnMap['hidden'];
			$hiddenByItem = (boolean) ( $this->$column === 2 );
		}

		return ( !$this->isFirst() and ! $hiddenByItem and static::modPermission( 'unhide', $member, $container ) );
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

		$container = NULL;
		try
		{
			$container = $this->item()->container();
		}
		catch ( \BadMethodCallException $e ) { }

		return ( !$this->isFirst() and ( static::modPermission( 'delete', $member, $container ) or ( $member->member_id and $member->member_id == $this->author()->member_id and $member->group['g_delete_own_posts'] ) ) );
	}
	
	/**
	 * Can split this comment off?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canSplit( $member=NULL )
	{
		$itemClass = static::$itemClass;

		if ( $itemClass::$firstCommentRequired )
		{
			$container = NULL;
			try
			{
				$container = $this->item()->container();
			}
			catch ( \BadMethodCallException $e ) { }

			if ( !$this->isFirst() )
			{
				$member = $member ?: \IPS\Member::loggedIn();
				return static::modPermission( 'split_merge', $member, $container );
			}
		}
		return FALSE;
	}
	
	/**
	 * Search Index Permissions
	 *
	 * @return	string	Comma-delimited values or '*'
	 * 	@li			Number indicates a group
	 *	@li			Number prepended by "m" indicates a member
	 *	@li			Number prepended by "s" indicates a social group
	 */
	public function searchIndexPermissions()
	{
		try
		{
			return $this->item()->searchIndexPermissions();
		}
		catch ( \BadMethodCallException $e )
		{
			return '*';
		}
	}
	
	/**
	 * Should this comment be ignored?
	 *
	 * @param	\IPS\Member|null	$member	The member to check for - NULL for currently logged in member
	 * @return	bool
	 */
	public function isIgnored( $member=NULL )
	{
		if ( $member === NULL )
		{
			$member = \IPS\Member::loggedIn();
		}
				
		return $member->isIgnoring( $this->author(), static::$ignoreType );
	}
	
	/**
	 * Get date line
	 *
	 * @return	string
	 */
	public function dateLine()
	{
		if( $this->mapped('first') )
		{
			return \IPS\Member::loggedIn()->language()->addToStack( static::$formLangPrefix . 'date_started', FALSE, array( 'htmlsprintf' => array( \IPS\DateTime::ts( $this->mapped('date') )->html( FALSE ) ) ) );
		}
		else
		{
			return \IPS\Member::loggedIn()->language()->addToStack( static::$formLangPrefix . 'date_replied', FALSE, array( 'htmlsprintf' => array( \IPS\DateTime::ts( $this->mapped('date') )->html( FALSE ) ) ) );
		}
	}
	
	/**
	 * Get edit line
	 *
	 * @return	string|NULL
	 */
	public function editLine()
	{
		if ( $this instanceof \IPS\Content\EditHistory and $this->mapped('edit_time') and ( $this->mapped('edit_show') or \IPS\Member::loggedIn()->modPermission('can_view_editlog') ) )
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'core' )->commentEditLine( $this, ( isset( static::$databaseColumnMap['edit_reason'] ) and $this->mapped('edit_reason') ) );
		}
		return NULL;
	}
	
	/**
	 * Get edit history
	 *
	 * @param	bool	$staff		Set true for moderators who have permission to view the full log which will show edits not made by the autor and private edits
	 * @return	\IPS\Db\Select
	 */
	public function editHistory( $staff=FALSE )
	{
		$idColumn = static::$databaseColumnId;
		$where = array( array( 'class=? AND comment_id=?', get_called_class(), $this->$idColumn ) );
		if ( !$staff )
		{
			$where[] = array( 'member=? AND public=1', $this->author()->member_id );
		}
		return \IPS\Db::i()->select( '*', 'core_edit_history', $where, 'time DESC' );
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		$template = static::$commentTemplate[1];
		return call_user_func_array( array( \IPS\Theme::i(), 'getTemplate' ), static::$commentTemplate[0] )->$template( $this->item(), $this );
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @return	callable
	 */
	public function searchResultHtml()
	{
		return \IPS\Theme::i()->getTemplate( 'search', 'core', 'front' )->contentComment( $this );
	}
	
	/**
	 * Users to receieve immediate notifications
	 *
	 * @param	int|array			$limit	LIMIT clause
	 * @param	string|NULL			$extra		Additional data
	 * @return \IPS\Db\Select
	 */
	public function notificationRecipients( $limit=array( 0, 25 ), $extra=NULL )
	{
		$memberFollowers = $this->author()->followers( 3, array( 'immediate' ), $this->mapped('date'), NULL, NULL, NULL );
		
		if( count( $memberFollowers ) )
		{
			$unions	= array( 
				$this->item()->followers( static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS, array( 'immediate' ), $this->mapped('date'), NULL, NULL, 0 ),
				$memberFollowers
			);
		
			return \IPS\Db::i()->union( $unions, 'follow_added', $limit, 'follow_member_id', FALSE, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
		}
		else
		{
			return $this->item()->followers( static::FOLLOW_PUBLIC + static::FOLLOW_ANONYMOUS, array( 'immediate' ), $this->mapped('date'), $limit, 'follow_added', \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS );
		}		
	}
	
	/**
	 * Create Notification
	 *
	 * @param	string|NULL		$extra		Additional data
	 * @return	\IPS\Notification
	 */
	protected function createNotification( $extra=NULL )
	{
		return new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_comment', $this->item(), array( $this ) );
	}
	
	/**
	 * Syncing to run when hiding
	 *
	 * @return	void
	 */
	public function onHide()
	{
		$item = $this->item();
		
		try
		{
			if ( $item->container()->_comments !== NULL )
			{
				$item->container()->_comments = $item->container()->_comments - 1;
				$item->container()->setLastComment();
				$item->container()->save();
			}
		} catch ( \BadMethodCallException $e ) {}
		
		if ( isset( $item::$databaseColumnMap['num_comments'] ) )
		{
			$column = $item::$databaseColumnMap['num_comments'];

			if ( $item->$column > 0 )
			{
				$item->$column = $item->mapped( 'num_comments' ) - 1;
			}
		}
		
		/* Remove any notifications */
		$idColumn = static::$databaseColumnId;
		\IPS\Db::i()->delete( 'core_notifications', array( 'item_sub_class=? AND item_sub_id=?', (string) get_called_class(), (int) $this->$idColumn ) );
		
		$item->resyncLastComment();
		$item->save();
	}
	
	/**
	 * Syncing to run when unhiding
	 *
	 * @param	bool	$approving	If true, is being approved for the first time
	 * @return	void
	 */
	public function onUnhide( $approving )
	{
		$item = $this->item();

		if ( $approving )
		{
			if ( isset( $item::$databaseColumnMap['unapproved_comments'] ) )
			{
				$column = $item::$databaseColumnMap['unapproved_comments'];

				if ( $item->$column > 0 )
				{
					$item->$column = $item->mapped( 'unapproved_comments' ) - 1;
				}
			}
			
			try 
			{
				if ( $item->container()->_unapprovedComments !== NULL )
				{
					if ( $item->container()->_unapprovedComments > 0 )
					{
						$item->container()->_unapprovedComments = $item->container()->_unapprovedComments - 1;
						$item->container()->save();
					}
				}
			} catch ( \BadMethodCallException $e ) {}

			try
			{
				if ( static::incrementPostCount( $item->container() ) )
				{
					$this->author()->member_posts++;
					$this->author()->save();
				}
			}
			catch( \BadMethodCallException $e ) { }
			
		}

		if ( isset( $item::$databaseColumnMap['num_comments'] ) )
		{
			$column = $item::$databaseColumnMap['num_comments'];
			$item->$column = $item->mapped('num_comments') + 1;
		}
		
		try
		{
			if ( $item->container()->_comments !== NULL )
			{
				$item->container()->_comments = $item->container()->_comments + 1;
				$item->container()->setLastComment();
				$item->container()->save();
			}
		} catch ( \BadMethodCallException $e ) {}
		
		$item->resyncLastComment();
		$item->save();
	}
	
	/**
	 * Move Comment to another item
	 *
	 * @param	\IPS\Content\Item	$item The item to move this comment too.
	 * @return	void
	 */
	public function move( \IPS\Content\Item $item )
	{
		$oldItem = $this->item();
		
		$idColumn = $item::$databaseColumnId;
		$itemColumn = static::$databaseColumnMap['item'];
		$this->$itemColumn = $item->$idColumn;
		$this->save();
		
		$oldItem->rebuildFirstAndLastCommentData();
		$item->rebuildFirstAndLastCommentData();

		/* Add to search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
	}
	
	/**
	 * Get container
	 *
	 * @return	\IPS\Node\Model
	 * @note	Certain functionality requires a valid container but some areas do not use this functionality (e.g. messenger)
	 * @note	Some functionality refers to calls to the container when managing comments (e.g. deleting a comment and decrementing content counts). In this instance, load the parent items container.
	 * @throws	\OutOfRangeException|\BadMethodCallException
	 */
	public function container()
	{
		$container = NULL;
		
		try
		{
			$container = $this->item()->container();
		}
		catch( \BadMethodCallException $e ) {}
		
		return $container;
	}
			
	/**
	 * Delete Comment
	 *
	 * @return	void
	 */
	public function delete()
	{
		/* Init */
		$idColumn = static::$databaseColumnId;
		$itemClass = static::$itemClass;
		$itemIdColumn = $itemClass::$databaseColumnId;
		
		/* Unclaim attachments */
		\IPS\File::unclaimAttachments( $itemClass::$application . '_' . ucfirst( $itemClass::$module ), $this->item()->$itemIdColumn, $this->$idColumn );
		
		/* Reduce the number of comment/reviews count on the item but only if the item is unapproved or visible 
		 * - hidden as opposed to unapproved items do not get included in either of the unapproved_comments/num_comments columns */
		if( $this->hidden() !== -1 ) 
		{
			$columnName = ( $this->hidden() === 1 ) ? 'unapproved_comments' : 'num_comments';
			if ( in_array( 'IPS\Content\Review', class_parents( get_called_class() ) ) )
			{
				$columnName = ( $this->hidden() === 1 ) ? 'unapproved_reviews' : 'num_reviews';
			}
			if ( isset( $itemClass::$databaseColumnMap[$columnName] ) )
			{
				$column = $itemClass::$databaseColumnMap[$columnName];

				if ( $this->item()->$column > 0 )
				{
					$this->item()->$column--;
					$this->item()->save();
				}
			}
		}
		
		/* Delete any notifications telling people about this */
		$memberIds	= array();

		foreach( \IPS\DB::i()->select( 'member', 'core_notifications', array( 'item_sub_class=? AND item_sub_id=?', (string) get_called_class(), (int) $this->$idColumn ) ) as $member )
		{
			$memberIds[ $member ]	= $member;
		}

		\IPS\Db::i()->delete( 'core_notifications', array( 'item_sub_class=? AND item_sub_id=?', (string) get_called_class(), (int) $this->$idColumn ) );

		foreach( $memberIds as $member )
		{
			\IPS\Member::load( $member )->recountNotifications();
		}
		
		/* Actually delete */
		parent::delete();
		
		/* Remove from search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->removeFromSearchIndex( $this );
		}
		
		/* Update last comment/review data for container and item */
		try
		{
			if ( in_array( 'IPS\Content\Review', class_parents( get_called_class() ) ) )
			{
				if ( $this->item()->container()->_reviews !== NULL )
				{
					$this->item()->container()->_reviews = ( $this->item()->container()->_reviews - 1 );
					$this->item()->container()->setLastReview();
					$this->item()->resyncLastReview();
				}
				if ( $this->item()->container()->_unapprovedReviews !== NULL )
				{
					$this->item()->container()->_unapprovedReviews = ( $this->item()->container()->_unapprovedReviews > 0 ) ? ( $this->item()->container()->_unapprovedReviews - 1 ) : 0;
				}
				$this->item()->container()->save();
			}
			else if ( $this->item()->container() !== NULL )
			{
				if ( $this->item()->container()->_comments !== NULL )
				{
					if ( !$this->hidden() )
					{
						$this->item()->container()->_comments = ( $this->item()->container()->_comments > 0 ) ? ( $this->item()->container()->_comments - 1 ) : 0;
					}
					$this->item()->container()->setLastComment();
					$this->item()->resyncLastComment();
				}
				if ( $this->item()->container()->_unapprovedComments !== NULL and $this->hidden() === 1 )
				{
					$this->item()->container()->_unapprovedComments = ( $this->item()->container()->_unapprovedComments > 0 ) ? ( $this->item()->container()->_unapprovedComments - 1 ) : 0;
				}
				$this->item()->container()->save();
			}
		}
		catch ( \BadMethodCallException $e ) {}
	}
	
	/**
	 * Change Author
	 *
	 * @param	\IPS\Member	$newAuthor	The new author
	 * @return	void
	 */
	public function changeAuthor( \IPS\Member $newAuthor )
	{
		$oldAuthor = $this->author();
		
		/* Update the row */
		parent::changeAuthor( $newAuthor );
		
		/* Adjust post counts */
		if ( static::incrementPostCount( $this->item()->containerWrapper() ) )
		{
			$oldAuthor->member_posts--;
			$oldAuthor->save();
			
			$newAuthor->member_posts++;
			$newAuthor->save();
		}
		
		/* Last comment */
		$this->item()->resyncLastComment();
		$this->item()->resyncLastReview();
		if ( $container = $this->item()->containerWrapper() )
		{
			$container->setLastComment();
			$container->setLastReview();
			$container->save();
		}
	}
	
	/**
	 * Get template for content tables
	 *
	 * @return	callable
	 */
	public static function contentTableTemplate()
	{
		return array( \IPS\Theme::i()->getTemplate( 'tables', 'core', 'front' ), 'commentRows' );
	}
	
	/**
	 * Get content for header in content tables
	 *
	 * @return	callable
	 */
	public function contentTableHeader()
	{
		return \IPS\Theme::i()->getTemplate( 'global', static::$application )->commentTableHeader( $this, $this->item() );
	}

	/**
	 * Get comments based on some arbitrary parameters
	 *
	 * @param	array		$where					Where clause
	 * @param	string		$order					MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit					Limit clause
	 * @param	string|NULL	$permissionKey			A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index, or NULL to ignore permissions
	 * @param	bool|NULL	$includeHiddenComments	Include hidden comments? Boolean or NULL to detect if currently logged member has permission
	 * @param	int			$queryFlags			Select bitwise flags
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments		If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews		If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$countOnly			If true will return the count
	 * @param	array|null	$joins				Additional arbitrary joins for the query
	 * @return	array|NULL|\IPS\Content\Comment	If $limit is 1, will return \IPS\Content\Comment or NULL for no results. For any other number, will return an array.
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenComments=NULL, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL )
	{
		/* Get the item class - we need it later */
		$itemClass	= static::$itemClass;

		/* Work out the order */
		$order = $order ?: ( static::$databasePrefix . static::$databaseColumnMap['date'] . ' DESC' );
		
		/* Exclude hidden comments */
		$includeHiddenComments = ( $includeHiddenComments === NULL ) ? static::modPermission( 'view_hidden' ) : $includeHiddenComments;
		
		if ( in_array( 'IPS\Content\Hideable', class_implements( get_called_class() ) ) and !$includeHiddenComments )
		{
			if ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$where[] = array( static::$databasePrefix . static::$databaseColumnMap['approved'] . '=?', 1 );
			}
			elseif ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$where[] = array( static::$databasePrefix . static::$databaseColumnMap['hidden'] . '=?', 0 );
			}
		}

		/* Exclude hidden items */
		if ( in_array( 'IPS\Content\Hideable', class_implements( $itemClass ) ) and !$includeHiddenComments )
		{
			$authorCol = $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['author'];
			if ( isset( $itemClass::$databaseColumnMap['approved'] ) )
			{
				$col = $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['approved'];
				if ( \IPS\Member::loggedIn()->member_id )
				{
					$where[] = array( "( {$col}=1 OR ( {$col}=0 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id . " ) )" );
				}
				else
				{
					$where[] = array( "{$col}=1" );
				}
			}
			elseif ( isset( $itemClass::$databaseColumnMap['hidden'] ) )
			{
				$col = $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['hidden'];
				if ( \IPS\Member::loggedIn()->member_id )
				{
					$where[] = array( "( {$col}=0 OR ( {$col}=1 AND {$authorCol}=" . \IPS\Member::loggedIn()->member_id . " ) )" );
				}
				else
				{
					$where[] = array( "{$col}=0" );
				}
			}
		}
        else
        {
            /* Legacy items pending deletion in 3.x at time of upgrade may still exist */
            $col	= null;

            if ( isset( $itemClass::$databaseColumnMap['approved'] ) )
            {
                $col = $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['approved'];
            }
            else if( isset( $itemClass::$databaseColumnMap['hidden'] ) )
            {
                $col = $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['hidden'];
            }

            if( $col )
            {
            	$where[] = array( "{$col} < 2" );
            }
        }
        
		/* Build the select clause */
		if( $countOnly )
		{
			$select = \IPS\Db::i()->select( 'COUNT(*) as cnt', static::$databaseTable, $where, NULL, NULL, NULL, NULL, $queryFlags )
				->join( $itemClass::$databaseTable, array( static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnMap['item'] . "=" . $itemClass::$databaseTable . "." . $itemClass::$databasePrefix . $itemClass::$databaseColumnId ) );

			if ( count( $joins ) )
			{
				foreach( $joins as $join )
				{
					$select->join( $join['from'], ( isset( $join['where'] ) ? $join['where'] : null ), 'LEFT' );
				}
			}
			return $select->first();
		}

		$selectClause = static::$databaseTable . '.*';
		if ( count( $joins ) )
		{
			foreach( $joins as $join )
			{
				if ( isset( $join['select'] ) )
				{
					$selectClause .= ', ' . $join['select'];
				}
			}
		}
		
		if ( in_array( 'IPS\Content\Permissions', class_implements( $itemClass ) ) AND $permissionKey !== NULL )
		{
			$containerClass = $itemClass::$containerNodeClass;
			
			$where[] = array( '(' . \IPS\Db::i()->findInSet( 'perm_' . $containerClass::$permissionMap[ $permissionKey ], \IPS\Member::loggedIn()->groups ) . ' OR ' . 'perm_' . $containerClass::$permissionMap[ $permissionKey ] . '=? )', '*' );
			$selectClause .= ', ' . $itemClass::$databaseTable . '.*';

			$select = \IPS\Db::i()->select( $selectClause, static::$databaseTable, $where, $order, $limit, NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS )
				->join( $itemClass::$databaseTable, array( static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnMap['item'] . "=" . $itemClass::$databaseTable . "." . $itemClass::$databasePrefix . $itemClass::$databaseColumnId ) )
				->join( 'core_permission_index', array( "core_permission_index.app=? AND core_permission_index.perm_type=? AND core_permission_index.perm_type_id=" . $itemClass::$databaseTable . "." . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'], $containerClass::$permApp, $containerClass::$permType ) );
		}
		else
		{
			$select = \IPS\Db::i()->select( $selectClause, static::$databaseTable, $where, $order, $limit, NULL, NULL, \IPS\Db::SELECT_SQL_CALC_FOUND_ROWS )
				->join( $itemClass::$databaseTable, array( static::$databaseTable . "." . static::$databasePrefix . static::$databaseColumnMap['item'] . "=" . $itemClass::$databaseTable . "." . $itemClass::$databasePrefix . $itemClass::$databaseColumnId ) );
		}
		
		
		if ( $joinContainer )
		{
			$containerClass = $itemClass::$containerNodeClass;
			$select->join( $containerClass::$databaseTable, array( $itemClass::$databaseTable . '.' . $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=' . $containerClass::$databaseTable . '.' . $containerClass::$databasePrefix . $containerClass::$databaseColumnId ) );
		}
				
		if ( count( $joins ) )
		{
			foreach( $joins as $join )
			{
				$select->join( $join['from'], ( isset( $join['where'] ) ? $join['where'] : null ), 'LEFT' );
			}
		}
		
		/* Return */
		return new \IPS\Patterns\ActiveRecordIterator( $select, get_called_class() );
	}
	
	/**
	 * Warning Reference Key
	 *
	 * @return	string
	 */
	public function warningRef()
	{
		/* If the member cannot warn, return NULL so we're not adding ugly parameters to the profile URL unnecessarily */
		if ( !\IPS\Member::loggedIn()->modPermission('mod_can_warn') )
		{
			return NULL;
		}
		
		$itemClass = static::$itemClass;
		$idColumn = static::$databaseColumnId;
		return base64_encode( json_encode( array( 'app' => $itemClass::$application, 'module' => $itemClass::$module . '-comment' , 'id_1' => $this->mapped('item'), 'id_2' => $this->$idColumn ) ) );
	}
	
	/**
	 * Get attachment IDs
	 *
	 * @return	array
	 */
	public function attachmentIds()
	{
		$item = $this->item();
		$idColumn = $item::$databaseColumnId;
		$commentIdColumn = static::$databaseColumnId;
		return array( $this->item()->$idColumn, $this->$commentIdColumn ); 
	}
	
	/**
	 * @brief	Existing warning
	 */
	public $warning;
		
	/**
	 * Can Share
	 *
	 * @return	boolean
	 */
	public function canShare()
	{
		return ( \IPS\Member::loggedIn()->member_id and in_array( 'IPS\Content\Shareable', class_implements( get_called_class() ) ) );
	}
	
	/**
	 * Return sharelinks for this item
	 *
	 * @return array
	 */
	public function sharelinks()
	{
		$item = $this->item();
		if( !count( $this->sharelinks ) )
		{
			/* If the item is shareable, we need to initiate the share services */
			if ( $this instanceof Shareable )
			{
				$shareService	= 'IPS\core\ShareLinks\Service';

				foreach( $shareService::shareLinks() as $node )
				{
					if( $node->enabled and ( $node->groups === "*" or \IPS\Member::loggedIn()->inGroup( explode( ',', $node->groups ) ) ) )
					{
						if( file_exists( \IPS\ROOT_PATH . '/system/Content/ShareServices/' . ucwords( $node->key ) . '.php' ) )
						{
							$className	= "IPS\\Content\\ShareServices\\" . ucwords( $node->key );
							$idColumn	= static::$databaseColumnId;
							$url		= $this->url( 'find' )->setQueryString( 'comment', $this->$idColumn );
							$this->sharelinks[ $node->key ]	= new $className( $url, $item->mapped('title') );
						}
					}
				}
			}
		}

		return $this->sharelinks;
	}

	/**
	 * Addition where needed for fetching comments
	 *
	 * @return	array|NULL
	 */
	public static function commentWhere()
	{
		return NULL;
	}
}