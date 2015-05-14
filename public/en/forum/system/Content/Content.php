<?php
/**
 * @brief		Abstract Content Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		3 Oct 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Content Model
 */
abstract class _Content extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[Content\Comment]	Database Column Map
	 */
	protected static $databaseColumnMap = array();
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = NULL;
	
	/**
	 * @brief	[Content\Comment]	Language prefix for forms
	 */
	public static $formLangPrefix = '';
	
	/**
	 * @brief	[Content\Item]	Include this content type in user profiles
	 */
	public static $includeInUserProfiles = TRUE;
	
	/**
	 * @brief	Include In Sitemap
	 */
	public static $includeInSitemap = TRUE;
	
	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return TRUE;
	}
	
	/**
	 * Post count for member
	 *
	 * @param	\IPS\Member	$member	The memner
	 * @return	int
	 */
	public static function memberPostCount( \IPS\Member $member )
	{
		return static::incrementPostCount() ? \IPS\Db::i()->select( 'COUNT(*)', static::$databaseTable, array( static::$databasePrefix . static::$databaseColumnMap['author'] . '=?', $member->member_id ) )->first() : 0;
	}
		
	/**
	 * Load and check permissions
	 *
	 * @return	static
	 * @throws	\OutOfRangeException
	 */
	public static function loadAndCheckPerms( $id )
	{
		$obj = static::load( $id );
		
		if ( !$obj->canView( \IPS\Member::loggedIn() ) )
		{
			throw new \OutOfRangeException;
		}

		return $obj;
	}
	
	/**
	 * Construct ActiveRecord from database row
	 *
	 * @param	array	$data							Row from database table
	 * @param	bool	$updateMultitonStoreIfExists	Replace current object in multiton store if it already exists there?
	 * @return	static
	 */
	public static function constructFromData( $data, $updateMultitonStoreIfExists = TRUE )
    {
	    if ( isset( $data[ static::$databaseTable ] ) and is_array( $data[ static::$databaseTable ] ) )
	    {		    
	        /* Add author data to multiton storeto prevent ->author() running another query later */
	        if ( isset( $data['author'] ) and is_array( $data['author'] ) )
	        {
	           	$author = \IPS\Member::constructFromData( $data['author'], FALSE );
	            
	            if ( isset( $data['author_pfields'] ) )
	            {
		            unset( $data['author_pfields']['member_id'] );
					$author->setProfileFieldValuesInMemory( $data['author_pfields'] );
	            }
	        }
	        
	        /* Load content */
	        $obj = parent::constructFromData( $data[ static::$databaseTable ], $updateMultitonStoreIfExists );
	        			
			/* Return */
			return $obj;
		}
		else
		{
			return parent::constructFromData( $data, $updateMultitonStoreIfExists );
		}
    }
    
    /**
	 * Get WHERE clause for Social Group considerations for getItemsWithPermission
	 *
	 * @param	string		$socialGroupColumn	The column which contains the social group ID
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @return	string
	 */
	public static function socialGroupGetItemsWithPermissionWhere( $socialGroupColumn, $member )
	{			
		$socialGroups = array();
		
		$member = $member ?: \IPS\Member::loggedIn();
		if ( $member->member_id )
		{
			$socialGroups = iterator_to_array( \IPS\Db::i()->select( 'group_id', 'core_sys_social_group_members', array( 'member_id=?', $member->member_id ) ) );
		}

		if ( count( $socialGroups ) )
		{
			return $socialGroupColumn . '=0 OR ( ' . \IPS\Db::i()->in( $socialGroupColumn, $socialGroups ) . ' )';
		}
		else
		{
			return $socialGroupColumn . '=0';
		}
	}
	
	/**
	 * Get mapped value
	 *
	 * @param	string	$key	date,content,ip_address,first
	 * @return	mixed
	 */
	public function mapped( $key )
	{
		if ( isset( static::$databaseColumnMap[ $key ] ) )
		{
			$field = static::$databaseColumnMap[ $key ];
			
			if ( is_array( $field ) )
			{
				$field = array_pop( $field );
			}
			
			return $this->$field;
		}
		return NULL;
	}
	
	/**
	 * Get author
	 *
	 * @return	\IPS\Member
	 */
	public function author()
	{
		if ( $this->mapped('author') or !isset( static::$databaseColumnMap['author_name'] ) or !$this->mapped('author_name') )
		{
			return \IPS\Member::load( $this->mapped('author') );
		}
		else
		{
			$guest = new \IPS\Member;
			$guest->name = $this->mapped('author_name');
			return $guest;
		}
	}
	
	/**
	 * Returns the content
	 *
	 * @return	string
	 */
	public function content()
	{
		return $this->mapped('content');
	}
	
	/**
	 * Text for use with data-ipsTruncate
	 * Returns the post with paragraphs turned into line breaks
	 *
	 * @param	bool	$oneLine	If TRUE, will use spaces instead of line breaks. Useful if using a single line display.
	 * @return	string
	 * @note	For now we are removing all HTML. If we decide to change this to remove specific tags in future, we can use \IPS\Text\Parser::removeElements( $this->content() )
	 */
	public function truncated( $oneLine=FALSE )
	{	
		$text = \IPS\Text\Parser::removeElements( $this->content(), array( 'blockquote' ) );
		$text = str_replace( array( '</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>' ), ( $oneLine ? ' ' : '<br>' ), $text );
		$text = strip_tags( str_replace( ">", "> ", $text ), '<br>' );

		return $text;
	}
	
	/**
	 * Delete Record
	 *
	 * @return	void
	 */
	public function delete()
	{
		if ( $this instanceof \IPS\Content\Reputation )
		{
			$idColumn = static::$databaseColumnId;
			\IPS\Db::i()->delete( 'core_reputation_index', array( 'app=? AND type=? AND type_id=?', static::$application, static::$reputationType, $this->$idColumn ) );
		}
		
		parent::delete();

		$this->expireWidgetCaches();
	}

	/**
	 * Is this a future entry?
	 *
	 * @return bool
	 */
	public function isFutureDate()
	{
		if ( $this instanceof \IPS\Content\FuturePublishing )
		{
			if ( isset( static::$databaseColumnMap['is_future_entry'] ) and isset( static::$databaseColumnMap['date'] ) )
			{
				$column = static::$databaseColumnMap['date'];
				if ( $this->$column > time() )
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	 * Return the tooltip blurb for future entries
	 *
	 * @return string
	 */
	public function futureDateBlurb()
	{
		$column = static::$databaseColumnMap['date'];
		$time   = \IPS\DateTime::ts( $this->$column );
		return  \IPS\Member::loggedIn()->language()->addToStack("content_future_date_blurb", FALSE, array( 'sprintf' => array( $time->localeDate(), $time->localeTime() ) ) );
	}
	
	/**
	 * Content is hidden?
	 *
	 * @return	int
	 * 	@li	-1 is hidden having been hidden by a moderator
	 * 	@li	0 is unhidden
	 *	@li	1 is hidden needing approval
	 * @note	The actual column may also contain 2 which means the item is hidden because the parent is hidden, but it is not hidden in itself. This method will return -1 in that case.
	 *
	 * @note    A piece of content (item and comment) can have an alias for hidden OR approved.
	 *          With hidden: 0=not hidden, 1=hidden (needs moderator approval), -1=hidden by moderator, 2=parent item is hidden
	 *          With approved: 1=not hidden, 0=hidden (needs moderator approval), -1=hidden by moderator
	 *
	 *          User posting has moderator approval set: When adding an unapproved ITEM (approved=0, hidden=1) you should *not* increment container()->_comments but you should update container()->_unapprovedItems
	 *          User posting has moderator approval set: When adding an unapproved COMMENT (approved=0, hidden=1) you should *not* increment item()->num_comments in item or container()->_comments but you should update item()->unapproved_comments and container()->_unapprovedComments
	 *
	 *          User post is hidden by moderator (approved=-1, hidden=0) you should decrement item()->num_comments and decrement container()->_comments but *not* increment item()->unapproved_comments or container()->_unapprovedComments
	 *          User item is hidden by a moderator (approved=-1, hidden=0) you should decrement container()->comments and subtract comment count from container()->_comments, but *not* increment container()->_unapprovedComments
	 *
	 *          Moderator hides item (approved=-1, hidden=-1) you should substract num_comments from container()->_comments. Comments inside item are flagged as approved=-1, hidden=2 but item()->num_comments should not be substracted from
	 *
	 *          Comments with a hidden value of 2 should increase item()->num_comments but not container()->_comments
	 * @throws	\RuntimeException
	 */
	public function hidden()
	{
		if ( $this instanceof \IPS\Content\Hideable )
		{
			if ( isset( static::$databaseColumnMap['hidden'] ) )
			{
				$column = static::$databaseColumnMap['hidden'];
				return ( $this->$column == 2 ) ? -1 : intval( $this->$column );
			}
			elseif ( isset( static::$databaseColumnMap['approved'] ) )
			{
				$column = static::$databaseColumnMap['approved'];
				return $this->$column == -1 ? intval( $this->$column ) : intval( !$this->$column );
			}
			else
			{
				throw new \RuntimeException;
			}
		}
		
		return 0;
	}

	/**
	 * Can see moderation tools
	 *
	 * @note	This is used generally to control if the user has permission to see multi-mod tools. Individual content items may have specific permissions
	 * @param	\IPS\Member|NULL	$member	The member to check for or NULL for the currently logged in member
	 * @param	\IPS\Node\Model|NULL		$container	The container
	 * @return	bool
	 */
	public static function canSeeMultiModTools( \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		return static::modPermission( 'edit', $member, $container ) or static::modPermission( 'hide', $member, $container ) or static::modPermission( 'unhide', $member, $container ) or static::modPermission( 'delete', $member, $container );
	}
	
	/**
	 * Check Moderator Permission
	 *
	 * @param	string						$type		'edit', 'hide', 'unhide', 'delete', etc.
	 * @param	\IPS\Member|NULL			$member		The member to check for or NULL for the currently logged in member
	 * @param	\IPS\Node\Model|NULL		$container	The container
	 * @return	bool
	 */
	public static function modPermission( $type, \IPS\Member $member = NULL, \IPS\Node\Model $container = NULL )
	{
		/* Compatibility checks */
		if ( ( $type == 'hide' or $type == 'unhide' ) and !in_array( 'IPS\Content\Hideable', class_implements( get_called_class() ) ) )
		{
			return FALSE;
		}
		if ( ( $type == 'pin' or $type == 'unpin' ) and !in_array( 'IPS\Content\Pinnable', class_implements( get_called_class() ) ) )
		{
			return FALSE;
		}
		if ( ( $type == 'feature' or $type == 'unfeature' ) and !in_array( 'IPS\Content\Featurable', class_implements( get_called_class() ) ) )
		{
			return FALSE;
		}
		if ( ( $type == 'future_publish' ) and !in_array( 'IPS\Content\FuturePublishing', class_implements( get_called_class() ) ) )
		{
			return FALSE;
		}
		
		/* Load Member */
		$member = $member ?: \IPS\Member::loggedIn();

		/* Global permission */
		if ( $member->modPermission( "can_{$type}_content" ) )
		{
			return TRUE;
		}
		/* Per-container permission */
		elseif ( $container )
		{
			$containerClass = get_class( $container );
			$title = static::$title;
			if
			(
				isset( $containerClass::$modPerm )
				and
				(
					$member->modPermission( $containerClass::$modPerm ) === 0
					or
					(
						is_array( $member->modPermission( $containerClass::$modPerm ) )
						and
						in_array( $container->_id, $member->modPermission( $containerClass::$modPerm ) )
					)
				)
				and
				$member->modPermission( "can_{$type}_{$title}" )
			)
			{
				return TRUE;
			}
		}
		
		/* Still here? return false */
		return FALSE;
	}
		
	/**
	 * Do Moderator Action
	 *
	 * @param	string				$action	The action
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string|NULL			$reason	Reason (for hides)
	 * @return	void
	 * @throws	\OutOfRangeException|\InvalidArgumentException|\RuntimeException
	 */
	public function modAction( $action, \IPS\Member $member = NULL, $reason = NULL )
	{
		if( $action === 'approve' )
		{
			$action	= 'unhide';
		}

		/* Check it's a valid action */
		if ( !in_array( $action, array( 'pin', 'unpin', 'feature', 'unfeature', 'hide', 'unhide', 'move', 'lock', 'unlock', 'delete', 'publish' ) ) )
		{
			throw new \InvalidArgumentException;
		}
		
		/* And that we can do it */
		if ( !call_user_func( array( $this, 'can' . ucfirst( $action ) ), $member ) )
		{
			throw new \OutOfRangeException;
		}
		
		/* Log */
		\IPS\Session::i()->modLog( 'modlog__action_' . $action, array( static::$title => TRUE, $this->url()->__toString() => FALSE, $this->mapped('title') ?: ( method_exists( $this, 'item' ) ? $this->item()->mapped('title') : NULL ) => FALSE ), ( $this instanceof \IPS\Content\Item ) ? $this : $this->item() );
		
		/* These ones just need a property setting */
		if ( in_array( $action, array( 'pin', 'unpin', 'feature', 'unfeature', 'lock', 'unlock' ) ) )
		{
			$val = TRUE;
			switch ( $action )
			{
				case 'unpin':
					$val = FALSE;
				case 'pin':
					$column = static::$databaseColumnMap['pinned'];
					break;
				
				case 'unfeature':
					$val = FALSE;
				case 'feature':
					$column = static::$databaseColumnMap['featured'];
					break;
				
				case 'unlock':
					$val = FALSE;
				case 'lock':
					if ( isset( static::$databaseColumnMap['locked'] ) )
					{
						$column = static::$databaseColumnMap['locked'];
					}
					else
					{
						$val = $val ? 'closed' : 'open';
						$column = static::$databaseColumnMap['status'];
					}
					break;
			}
			$this->$column = $val;
			$this->save();

			return;
		}
		
		/* Hide is a tiny bit more complicated */
		elseif ( $action === 'hide' )
		{
			$this->hide( $member, $reason );
			return;
		}
		elseif ( $action === 'unhide' )
		{
			$this->unhide( $member );
			return;
		}
		
		/* Delete is just a method */
		elseif ( $action === 'delete' )
		{
			$this->delete();
			return;
		}

		/* Publish is just a method */
		elseif ( $action === 'publish' )
		{
			$this->publish();
			return;
		}

		/* Move is just a method */
		elseif ( $action === 'move' )
		{
			$args	= func_get_args();			
			$this->move( $args[2][0], $args[2][1] );
			return;
		}
	}
	
	/**
	 * Hide
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @param	string				$reason	Reason
	 * @return	void
	 */
	public function hide( \IPS\Member $member = NULL, $reason = NULL )
	{
		if ( isset( static::$databaseColumnMap['hidden'] ) )
		{
			$column = static::$databaseColumnMap['hidden'];
		}
		elseif ( isset( static::$databaseColumnMap['approved'] ) )
		{
			$column = static::$databaseColumnMap['approved'];
		}
		else
		{
			throw new \RuntimeException;
		}

		/* Already hidden? */
		if( $this->$column == -1 )
		{
			return;
		}

		$this->$column = -1;
		$this->save();
		$this->onHide();
		
		if ( static::$hideLogKey )
		{
			$idColumn = static::$databaseColumnId;
			\IPS\Db::i()->delete( 'core_soft_delete_log', array( 'sdl_obj_id=? AND sdl_obj_key=?', $this->$idColumn, static::$hideLogKey ) );
			\IPS\Db::i()->insert( 'core_soft_delete_log', array(
				'sdl_obj_id'		=> $this->$idColumn,
				'sdl_obj_key'		=> static::$hideLogKey,
				'sdl_obj_member_id'	=> intval( $member ? $member->member_id : \IPS\Member::loggedIn()->member_id ),
				'sdl_obj_date'		=> time(),
				'sdl_obj_reason'	=> $reason,
				
			) );
		}
		
		if ( $this instanceof \IPS\Content\Tags )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 0 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}

        /* Update search index */
        if ( $this instanceof \IPS\Content\Searchable )
        {
            \IPS\Content\Search\Index::i()->index( $this );
        }

		$this->expireWidgetCaches();
	}
	
	/**
	 * Unhide
	 *
	 * @param	\IPS\Member|NULL	$member	The member doing the action (NULL for currently logged in member)
	 * @return	void
	 */
	public function unhide( $member=NULL )
	{
		/* If we're approving, we have to do extra stuff */
		$approving = FALSE;
		if ( $this->hidden() === 1 )
		{
			$approving = TRUE;
			if ( isset( static::$databaseColumnMap['approved_by'] ) )
			{
				$column = static::$databaseColumnMap['approved_by'];
				$this->$column = $member ? $member->member_id : \IPS\Member::loggedIn()->member_id;
			}
			if ( isset( static::$databaseColumnMap['approved_date'] ) )
			{
				$column = static::$databaseColumnMap['approved_date'];
				$this->$column = time();
			}
		}
		
		
		/* Now do the actual stuff */
		if ( isset( static::$databaseColumnMap['hidden'] ) )
		{
			$column = static::$databaseColumnMap['hidden'];

			/* Already approved? */
			if( $this->$column == 0 )
			{
				return;
			}

			$this->$column = 0;
		}
		elseif ( isset( static::$databaseColumnMap['approved'] ) )
		{
			$column = static::$databaseColumnMap['approved'];

			/* Already approved? */
			if( $this->$column == 1 )
			{
				return;
			}

			$this->$column = 1;
		}
		else
		{
			throw new \RuntimeException;
		}
		$this->save();
		$this->onUnhide( $approving );
		
		/* And update the tags perm cache */
		if ( $this instanceof \IPS\Content\Tags )
		{
			\IPS\Db::i()->update( 'core_tags_perms', array( 'tag_perm_visible' => 1 ), array( 'tag_perm_aai_lookup=?', $this->tagAAIKey() ) );
		}
		
		/* Update search index */
		if ( $this instanceof \IPS\Content\Searchable )
		{
			\IPS\Content\Search\Index::i()->index( $this );
		}
		
		/* Send notifications if necessary */
		if ( $approving )
		{
			$this->sendApprovedNotification();
		}
	}

	/**
	 * Send 

	/**
	 * Blurb for when/why/by whom this content was hidden
	 *
	 * @return	string
	 */
	public function hiddenBlurb()
	{
		if ( !( $this instanceof \IPS\Content\Hideable ) or !static::$hideLogKey )
		{
			throw new \BadMethodCallException;
		}
		
		try
		{
			$idColumn = static::$databaseColumnId;
			$log = \IPS\Db::i()->select( '*', 'core_soft_delete_log', array( 'sdl_obj_id=? AND sdl_obj_key=?', $this->$idColumn, static::$hideLogKey ) )->first();
			return \IPS\Member::loggedIn()->language()->addToStack('hidden_blurb', FALSE, array( 'sprintf' => array( \IPS\Member::load( $log['sdl_obj_member_id'] )->name, \IPS\DateTime::ts( $log['sdl_obj_date'] )->relative(), $log['sdl_obj_reason'] ?: \IPS\Member::loggedIn()->language()->addToStack('hidden_no_reason') ) ) );
		}
		catch ( \UnderflowException $e )
		{
			return \IPS\Member::loggedIn()->language()->addToStack('hidden');
		}
	}
		
	/**
	 * Can report?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	TRUE|string			TRUE or a language string for why not
	 * @note	This requires a few queries, so don't run a check in every template
	 */
	public function canReport( $member=NULL )
	{
		$member = $member ?: \IPS\Member::loggedIn();
		
		/* Is this type of comment reportabe? */
		if ( !( $this instanceof \IPS\Content\ReportCenter ) )
		{
			return 'generic_error';
		}
		
		/* Can the member report content? */
		if ( $member->group['gbw_no_report'] )
		{
			return 'no_module_permission';
		}
		
		/* Can they view this? */
		if ( !$this->canView() )
		{
			return 'no_module_permission';
		}

		/* Have they already subitted a report? */
		$idColumn = static::$databaseColumnId;
		$report = \IPS\Db::i()->select( 'id', 'core_rc_index', array( 'class=? AND content_id=?', get_called_class(), $this->$idColumn ) );
		if ( count( $report ) )
		{
			$report = \IPS\Db::i()->select( '*', 'core_rc_reports', array( 'rid=? AND report_by=?', $report->first(), $member->member_id ) );
			if ( count( $report ) )
			{
				return 'report_err_already_reported';
			}
		}
		
		return TRUE;
	}
	
	/**
	 * Report
	 *
	 * @param	string	$reportContent	Report content message from member
	 * @return	\\IPS\core\Reports\Report
	 * @throws	\UnexpectedValueException	If there is a permission error - you should only call this method after checking canReport
	 */
	public function report( $reportContent )
	{
		/* Permission check */
		if ( $this->canReport() !== TRUE )
		{
			throw new \UnexpectedValueException;
		}
		
		/* Find or create an index */
		$idColumn = static::$databaseColumnId;
		try
		{
			$index = \IPS\core\Reports\Report::load( $this->$idColumn, 'content_id', array( 'class=?', get_called_class() ) );
		}
		catch ( \OutOfRangeException $e )
		{
			$index = new \IPS\core\Reports\Report;
			$index->class = get_called_class();
			$index->content_id = $this->$idColumn;
			$index->perm_id = $this->permId();
			$index->first_report_by = (int) \IPS\Member::loggedIn()->member_id;
			$index->first_report_date = time();
			$index->author = $this->author()->member_id;
		}

		$index->status = 1;
		$index->save();

		/* Create a report */
		\IPS\Db::i()->insert( 'core_rc_reports', array(
			'rid'			=> $index->id,
			'report'		=> $reportContent,
			'report_by'		=> (int) \IPS\Member::loggedIn()->member_id,
			'date_reported'	=> time(),
			'ip_address'	=> \IPS\Request::i()->ipAddress()
		) );

		
		/* Rebuild */
		$index->rebuild();
		
		/* Send notification to mods */
		$moderators = array( 'm' => array(), 'g' => array() );
		foreach ( \IPS\Db::i()->select( '*', 'core_moderators' ) as $mod )
		{
			$canView = FALSE;
			if ( $mod['perms'] == '*' )
			{
				$canView = TRUE;
			}
			if ( $canView === FALSE )
			{
				$perms = json_decode( $mod['perms'], TRUE );
				
				if ( isset( $perms['can_view_reports'] ) AND $perms['can_view_reports'] === TRUE )
				{
					$canView = TRUE;
				}
			}
			if ( $canView === TRUE )
			{
				$moderators[ $mod['type'] ][] = $mod['id'];
			}
		}
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'report_center', $index, array( $index, $reportContent, $this ) );
		foreach ( \IPS\Db::i()->select( '*', 'core_members', ( count( $moderators['m'] ) ? \IPS\Db::i()->in( 'member_id', $moderators['m'] ) . ' OR ' : '' ) . \IPS\Db::i()->in( 'member_group_id', $moderators['g'] ) . ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $moderators['g'] ) ) as $member )
		{
			$notification->recipients->attach( \IPS\Member::constructFromData( $member ) );
		}
		$notification->send();
		
		/* Return */
		return $index;
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

		foreach ( array( 'author', 'author_name' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$col = static::$databaseColumnMap[ $k ];
				switch ( $k )
				{
					case 'author':
						$this->$col = $newAuthor->member_id ? $newAuthor->member_id : 0;
						break;
					
					case 'author_name':
						$this->$col = $newAuthor->member_id ? $newAuthor->name : '';
						break;
				}
			}
		}
		$this->save();

		\IPS\Session::i()->modLog( 'modlog__action_changeauthor', array( static::$title => TRUE, $this->url()->__toString() => FALSE, $this->mapped('title') ?: ( method_exists( $this, 'item' ) ? $this->item()->mapped('title') : NULL ) => FALSE ), ( $this instanceof \IPS\Content\Item ) ? $this : $this->item() );
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @return	callable
	 */
	public function activityStreamHtml()
	{
		return \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' )->activityStreamResult( $this );
	}
	
	/* !Reputation */
	
	/**
	 * Can give reputation?
	 *
	 * @note	This method is also ran to check if a member can "unrep"
	 * @param	int					$type	1 for positive, -1 for negative
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canGiveReputation( $type, \IPS\Member $member = NULL )
	{
		if ( \IPS\Member::loggedIn()->member_id and \IPS\Settings::i()->reputation_enabled and !in_array( $this->author()->member_group_id, explode( ',', \IPS\Settings::i()->reputation_protected_groups ) ) and $this instanceof \IPS\Content\Reputation )
		{		
			/* Can we rep ourselves? */
			$member = $member ?: \IPS\Member::loggedIn();
			if ( $this->author() == $member and !\IPS\Settings::i()->reputation_can_self_vote )
			{
				return FALSE;
			}
			
			/* Are we allowed to give reputation at all? - We still need to allow users to undo rep they have given */
			$limit = ( $type === 1 ) ? $member->group['g_rep_max_positive'] : $member->group['g_rep_max_negative'];
			if ( $limit == 0 and !$this->repGiven( $member ) )
			{
				return false;
			}
			
			/* Protected Groups */
			if ( in_array( $this->author()->member_group_id, explode( ',', \IPS\Settings::i()->reputation_protected_groups ) ) )
			{
				return FALSE;
			}
			
			/* Now work it out */
			switch ( \IPS\Settings::i()->reputation_point_types )
			{
				/* If we're using a positive system, we can rep positive only if we haven't repped yet, or negative only if we have */
				case 'like':
				case 'positive':
					return ( $this->repGiven( $member ) === 1 ) ? ( $type === -1 ) : ( $type === 1 );
				
				/* If we're using a negative system, we can rep negative only if we haven't repped yet, or positive only if we have */
				case 'negative':
					return $this->repGiven( $member ) ? ( $type === 1 ) : ( $type === -1 );
				
				/* If we're using a positive and negative system - it's more complicated... */
				case 'both':
					switch ( $this->repGiven( $member ) )
					{
						/* If we haven't repped yet, we can do either */
						case 0:
							return TRUE;
						
						/* If we've given positive rep already, we can only do negative now */
						case 1:
							return ( $type === -1 );
						
						/* If we've given negative rep already, we can only do positive now */
						case -1:
							return ( $type === 1 );
					}
			}
		}
		
		return FALSE;
	}

	/**
	 * Give reputation
	 *
	 * @param	int					$type	1 for positive, -1 for negative
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	void
	 * @throws	\DomainException|\BadMethodCallException
	 */
	public function giveReputation( $type, \IPS\Member $member=NULL )
	{
		if ( !( $this instanceof \IPS\Content\Reputation ) )
		{
			throw new \BadMethodCallException;
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
				
		if ( !$this->canGiveReputation( $type, $member ) )
		{
			throw new \DomainException( 'cannot_rep_user' );
		}
		
		$idColumn = static::$databaseColumnId;
		\IPS\Db::i()->delete( 'core_reputation_index', array( 'app=? AND type=? AND member_id=? AND type_id=?', static::$application, static::$reputationType, $member->member_id, $this->$idColumn ) );
				
		if ( $this->repGiven( $member ) )
		{
			if ( $this->reputation !== NULL )
			{
				unset( $this->reputation[ $member->member_id ] );
			}
		}
		else
		{
			$limit = ( $type === 1 ) ? $member->group['g_rep_max_positive'] : $member->group['g_rep_max_negative'];
			if ( $limit !== 999 )
			{
				$count = \IPS\Db::i()->select( 'COUNT(*)', 'core_reputation_index', array( 'member_id=? AND rep_date>?', $member->member_id, \IPS\DateTime::create()->sub( new \DateInterval( 'P1D' ) )->getTimestamp() ) )->first();
				if ( $count >= $limit )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('rep_daily_exceeded', FALSE, array( 'sprintf' => array( $limit ) ) ) );
				}
			}
			
			\IPS\Db::i()->insert( 'core_reputation_index', array(
				'member_id'			=> $member->member_id,
				'app'				=> static::$application,
				'type'				=> static::$reputationType,
				'type_id'			=> $this->$idColumn,
				'rep_date'			=> time(),
				'rep_rating'		=> $type,
				'member_received'	=> (int) $this->author()->member_id
			) );
			
			/* Send Notification */
			if ( $type === 1 AND $this->author()->member_id AND $this->author() != \IPS\Member::loggedIn() )
			{
				$notification = new \IPS\Notification( \IPS\Application::load('core'), 'new_likes', $this, array( $this, \IPS\Member::loggedIn() ) );
				$notification->recipients->attach( $this->author() );
				$notification->send();
			}
			
			if ( $this->reputation !== NULL )
			{
				$this->reputation[ $member->member_id ] = $type;
			}
		}
		
		if( $this->author()->member_id )
		{
			$this->author()->pp_reputation_points += $type;
			$this->author()->save();
		}
	}
	
	/**
	 * @brief	Reputation Cache
	 */
	public $reputation = NULL;
	
	/**
	 * Get reputation count
	 *
	 * @return	int
	 * @throws	\BadMethodCallException
	 */
	public function reputation()
	{
		if ( !( $this instanceof \IPS\Content\Reputation ) )
		{
			throw new \BadMethodCallException;
		}
				
		if ( $this->reputation === NULL )
		{
			$this->reputation = iterator_to_array( \IPS\Db::i()->select( 'member_id, rep_rating', 'core_reputation_index', $this->getReputationWhereClause() )->setKeyField( 'member_id' )->setValueField( 'rep_rating' ) );
		}
				
		return array_sum( $this->reputation );
	}
	
	/**
	 * Get reputation where clause
	 *
	 * @return	array
	 * @throws	\BadMethodCallException
	 */
	public function getReputationWhereClause()
	{
		if ( !( $this instanceof \IPS\Content\Reputation ) )
		{
			throw new \BadMethodCallException;
		}
	
		$idColumn = static::$databaseColumnId;
		$where = array( array( 'app=? AND type=? AND type_id=?', static::$application, static::$reputationType, $this->$idColumn ) );
			
		switch( \IPS\Settings::i()->reputation_point_types )
		{
			case 'positive':
			case 'like':
				$where[] = array( 'rep_rating=?', "1" );
				break;					
			case 'negative':
				$where[] = array( 'rep_rating=?', "-1" );
				break;
		}

		return $where;
	}

	/**
	 * @brief	Cached like blurb
	 */
	public $likeBlurb	= NULL;

	/**
	 * Get "like" blurb (You and X others like this)
	 *
	 * @return	string
	 */
	public function likeBlurb()
	{
		if( $this->likeBlurb !== NULL )
		{
			return $this->likeBlurb;
		}

		$reputation = $this->reputation();
		$people = array();
		
		/* Nobody */
		if ( !$reputation )
		{
			return '';
		}
		
		/* You */
		if ( $this->repGiven() === 1 )
		{
			$reputation--;
			if ( $reputation )
			{
				$people[] = \IPS\Member::loggedIn()->language()->addToStack('like_blurb_you_and_others');
			}
			else
			{
				$this->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack('like_blurb_just_you');
				return $this->likeBlurb;
			}
		}

		if( \IPS\Member::loggedIn()->group['gbw_view_reps'] )
		{
			/* 3 random names */
			$idColumn = static::$databaseColumnId;
			foreach ( \IPS\Db::i()->select( '*', 'core_reputation_index', array( 'app=? AND type=? AND type_id=? AND member_id!=?', static::$application, static::$reputationType, $this->$idColumn, ( \IPS\Member::loggedIn()->member_id ) ?: 0 ), 'RAND()', 3 ) as $rep )
			{
				$people[] = \IPS\Theme::i()->getTemplate( 'global', 'core' )->userLink( \IPS\Member::load( $rep['member_id'] ) );
				$reputation--;
			}
		}
						
		/* X others */
		$count = count( $people ) + $reputation;
		if ( $reputation )
		{
			$others = \IPS\Member::loggedIn()->language()->addToStack( empty( $people ) ? 'like_blurb_generic' : 'like_blurb_others', FALSE, array( 'pluralize' => array( $reputation ) ) );
			if ( \IPS\Member::loggedIn()->group['gbw_view_reps'] )
			{
				$others = \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reputationOthers( $this, $others );
			}			
			$people[] = $others;
		}

		$this->likeBlurb = \IPS\Member::loggedIn()->language()->addToStack( 'like_blurb', FALSE, array( 'pluralize' => array( $count ), 'htmlsprintf' => array( \IPS\Member::loggedIn()->language()->formatList( $people ) ) ) );
		return $this->likeBlurb;
	}
	
	/**
	 * Has reputation been given by a particular member?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	int	1 = Positive rep given. -1 = Negative rep given. 0 = No rep given
	 * @throws	\BadMethodCallException
	 */
	public function repGiven( \IPS\Member $member = NULL )
	{
		if ( !( $this instanceof \IPS\Content\Reputation ) )
		{
			throw new \BadMethodCallException;
		}
		
		if ( $this->reputation === NULL )
		{
			$this->reputation();
		}
		
		$member = $member ?: \IPS\Member::loggedIn();
		return array_key_exists( $member->member_id, $this->reputation ) ? intval( $this->reputation[ $member->member_id ] ) : 0;
	}
	
	/**
	 * Get table showing who has given reputation
	 *
	 * @return	\IPS\Helpers\Table\Db
	 * @throws	\DomainException
	 * @throws	\BadMethodCallException
	 */
	public function reputationTable()
	{
		if ( !( $this instanceof \IPS\Content\Reputation ) )
		{
			throw new \BadMethodCallException;
		}
		
		if ( !\IPS\Member::loggedIn()->group['gbw_view_reps'] )
		{
			throw new \DomainException;
		}
		
		$idColumn = static::$databaseColumnId;
		
		$table = new \IPS\Helpers\Table\Db( 'core_reputation_index', $this->url('showRep'), $this->getReputationWhereClause() );
		$table->sortBy			= 'rep_date';
		$table->sortDirection	= 'desc';
		$table->tableTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'reputationLogTable' );
		$table->rowsTemplate = array( \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' ), 'reputationLog' );
		
		return $table;
	}
	
	/* !Follow */
	
	const FOLLOW_PUBLIC = 1;
	const FOLLOW_ANONYMOUS = 2;
		
	const NOTIFICATIONS_PER_BATCH = 100;
	
	/**
	 * Send notifications
	 *
	 * @return	void
	 */
	public function sendNotifications()
	{		
		/* Send quote and mention notifications */
		$sentTo = $this->sendQuoteAndMentionNotifications();
		
		/* How many followers? */
		$idColumn = $this::$databaseColumnId;
		try
		{
			$count = $this->notificationRecipients()->count( TRUE );
		}
		catch ( \BadMethodCallException $e )
		{
			return;
		}
		
		/* Queue if there's lots, or just send them */
		if ( $count > static::NOTIFICATIONS_PER_BATCH )
		{
			\IPS\Task::queue( 'core', 'Follow', array( 'class' => get_class( $this ), 'item' => $this->$idColumn, 'exclude' => array_keys( $sentTo ) ), 1 );
		}
		else
		{
			$this->sendNotificationsBatch( 0, $sentTo );
		}
	}
	
	/**
	 * Send notifications batch
	 *
	 * @param	int				$offset		Current offset
	 * @param	array			$exclude	IDs to exclude
	 * @param	string|NULL		$extra		Additional data
	 * @return	int|null		New offset or NULL if complete
	 */
	public function sendNotificationsBatch( $offset=0, $exclude=array(), $extra=NULL )
	{
		$followIds = array();
		$followers = $this->notificationRecipients( array( $offset, static::NOTIFICATIONS_PER_BATCH ), $extra );
		
		/* Send notification */
		$notification = $this->createNotification( $extra );
		$notification->unsubscribeType = 'follow';
		foreach ( $followers as $follower )
		{
			$member = \IPS\Member::load( $follower['follow_member_id'] );
			if ( $member != $this->author() and $this->canView( $member ) and !in_array( $follower['follow_member_id'], $exclude ) )
			{
				$followIds[] = $follower['follow_id'];
				$notification->recipients->attach( $member, $follower );
			}
		}
		$notification->send();
		
		/* Log that we sent it */
		if( count( $followIds ) )
		{
			\IPS\Db::i()->update( 'core_follow', array( 'follow_notify_sent' => time() ), \IPS\Db::i()->in( 'follow_id', $followIds ) );
		}
		
		/* Update the queue */
		$newOffset = $offset + static::NOTIFICATIONS_PER_BATCH;
		if ( $newOffset > $followers->count( TRUE ) )
		{
			return NULL;
		}
		return $newOffset;
	}
	
	/**
	 * Send Approved Notification
	 *
	 * @return	void
	 */
	public function sendApprovedNotification()
	{
		$this->sendNotifications();
	}
	
	/**
	 * Send Unapproved Notification
	 *
	 * @return	void
	 */
	public function sendUnapprovedNotification()
	{
		$moderators = array( 'g' => array(), 'm' => array() );
		foreach( \IPS\Db::i()->select( '*', 'core_moderators' ) AS $mod )
		{
			$canView = FALSE;
			if ( $mod['perms'] == '*' )
			{
				$canView = TRUE;
			}
			if ( $canView === FALSE )
			{
				$perms = json_decode( $mod['perms'], TRUE );
				
				if ( isset( $mod['perms']['can_view_hidden_content'] ) AND $mod['perms']['can_view_hidden_content'] )
				{
					$canView = TRUE;
				}
			}
			if ( $canView === TRUE )
			{
				$moderators[ $mod['type'] ][] = $mod['id'];
			}
		}
		
		$notification = new \IPS\Notification( \IPS\Application::load('core'), 'unapproved_content', $this, array( $this, $this->author() ) );
		foreach ( \IPS\Db::i()->select( '*', 'core_members', ( count( $moderators['m'] ) ? \IPS\Db::i()->in( 'member_id', $moderators['m'] ) . ' OR ' : '' ) . \IPS\Db::i()->in( 'member_group_id', $moderators['g'] ) . ' OR ' . \IPS\Db::i()->findInSet( 'mgroup_others', $moderators['g'] ) ) as $member )
		{
            /* We don't need to notify the author of the content */
            if( $this->author()->member_id != $member['member_id'] )
            {
                $notification->recipients->attach(\IPS\Member::constructFromData($member));
            }
		}
		$notification->send();
	}
	
	/**
	 * Send the notifications after the content has been edited (for any new quotes or mentiones)
	 *
	 * @param	string	$oldContent	The content before the edit
	 * @return	void
	 */
	public function sendAfterEditNotifications( $oldContent )
	{				
		$existingData = static::_getQuoteAndMentionIdsFromContent( $oldContent );
		$this->sendQuoteAndMentionNotifications( array_unique( array_merge( $existingData['quotes'], $existingData['mentions'] ) ) );
	}
		
	/**
	 * Send quote and mention notifications
	 *
	 * @param	array	$exclude		An array of member IDs *not* to send notifications to
	 * @return	array	Member IDs sent to
	 */
	protected function sendQuoteAndMentionNotifications( $exclude=array() )
	{
		return $this->_sendQuoteAndMentionNotifications( static::_getQuoteAndMentionIdsFromContent( $this->content() ), $exclude );
	}
	
	/**
	 * Send quote and mention notifications from data
	 *
	 * @param	array	array( 'quotes' => array( ... member IDs ... ), 'mentions' => array( ... member IDs ... ) )
	 * @param	array	$exclude		An array of member IDs *not* to send notifications to
	 * @return	array	Member IDs sent to
	 */
	protected function _sendQuoteAndMentionNotifications( $data, $exclude=array() )
	{
		/* Init */
		$sentTo = array();
		
		/* Quotes */
		$data['quotes'] = array_filter( $data['quotes'], function( $v ) use ( $exclude )
		{
			return !in_array( $v, $exclude );
		} );
		if ( !empty( $data['quotes'] ) )
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'quote', ( $this instanceof \IPS\Content\Item ) ? $this : $this->item(), array( $this ), array( $this->author()->member_id ) );
			foreach ( $data['quotes'] as $quote )
			{
				$member = \IPS\Member::load( $quote );
				if ( $member != $this->author() and $this->canView( $member ) )
				{
					$notification->recipients->attach( $member );
				}
			}
			$sentTo = array_merge( $notification->send(), $sentTo );
		}
		
		/* Mentions */
		$data['mentions'] = array_filter( $data['mentions'], function( $v ) use ( $exclude )
		{
			return !in_array( $v, $exclude );
		} );
		if ( !empty( $data['mentions'] ) )
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'core' ), 'mention', ( $this instanceof \IPS\Content\Item ) ? $this : $this->item(), array( $this ), array( $this->author()->member_id ) );
			foreach ( $data['mentions'] as $mention )
			{
				$member = \IPS\Member::load( $mention );
				if ( $member != $this->author() and $this->canView( $member ) )
				{
					$notification->recipients->attach( $member );
				}
			}
			$sentTo = array_merge( $notification->send(), $sentTo );
		}
	
		/* Return */
		return $sentTo;
	}
	
	/**
	 * Get quote and mention notifications
	 *
	 * @param	string	$content	The content
	 * @return	array	array( 'quotes' => array( ... member IDs ... ), 'mentions' => array( ... member IDs ... ) )
	 */
	protected static function _getQuoteAndMentionIdsFromContent( $content )
	{
		$return = array( 'quotes' => array(), 'mentions' => array() );
		
		$document = new \DOMDocument;
		libxml_use_internal_errors(TRUE);
		if ( @$document->loadHTML( '<div>' . $content . '</div>' ) !== FALSE )
		{
			/* Quotes */
			foreach( $document->getElementsByTagName('blockquote') as $quote )
			{
				if ( $quote->getAttribute('data-ipsquote-userid') and (int) $quote->getAttribute('data-ipsquote-userid') > 0 )
				{
					$return['quotes'][] = $quote->getAttribute('data-ipsquote-userid');
				}
			}
			
			/* Mentions */
			foreach( $document->getElementsByTagName('a') as $link )
			{
				if ( $link->getAttribute('data-mentionid') )
				{
					$path = explode( '/', $link->getNodePath() );
					if ( !in_array( 'blockquote', $path ) )
					{
						$return['mentions'][] = $link->getAttribute('data-mentionid');
					}
				}
			}
		}
		
		return $return;
	}
	
	/**
	 * Expire appropriate widget caches automatically
	 *
	 * @return void
	 */
	public function expireWidgetCaches()
	{
		\IPS\Widget::deleteCaches( NULL, static::$application );
	}

	/**
	 * Fetch classes from content router
	 *
	 * @param	bool|\IPS\Member	$member		Check member access
	 * @param	bool				$enabled	Check application is enabled
	 * @param	bool				$archived	Include any supported archive classes
	 * @param	bool				$onlyItems	Only include item classes
	 * @return	array
	 */
	public static function routedClasses( $member=FALSE, $enabled=FALSE, $archived=FALSE, $onlyItems=FALSE )
	{
		$classes	= array();

		foreach ( \IPS\Application::allExtensions( 'core', 'ContentRouter', $member, NULL, NULL, TRUE, $enabled ) as $router )
		{
			foreach ( $router->classes as $class )
			{
				$classes[]	= $class;

				if( $onlyItems )
				{
					continue;
				}

				if ( isset( $class::$commentClass ) )
				{
					$classes[]	= $class::$commentClass;
				}

				if ( isset( $class::$reviewClass ) )
				{
					$classes[]	= $class::$reviewClass;
				}

				if( $archived === TRUE AND isset( $class::$archiveClass ) )
				{
					$classes[]	= $class::$archiveClass;
				}
			}
		}

		return $classes;
	}

	/**
	 * Return any custom multimod actions this content item supports
	 *
	 * @return	array
	 */
	public function customMultimodActions()
	{
		return array();
	}

	/**
	 * Return any available custom multimod actions this content item class supports
	 *
	 * @note	Return in format of EITHER
	 *	@li	array( array( 'action' => ..., 'icon' => ..., 'label' => ... ), ... )
	 *	@li	array( array( 'grouplabel' => ..., 'icon' => ..., 'groupaction' => ..., 'action' => array( array( 'action' => ..., 'label' => ... ), ... ) ) )
	 * @note	For an example, look at \IPS\core\Announcements\Announcement
	 * @return	array
	 */
	public static function availableCustomMultimodActions()
	{
		return array();
	}

	/**
	 * Get HTML for search result display
	 *
	 * @return	callable
	 */
	public function approvalQueueHtml( $ref=NULL, $container, $title )
	{
		return \IPS\Theme::i()->getTemplate( 'modcp', 'core', 'front' )->approvalQueueItem( $this, $ref, $container, $title );
	}

	/**
	 * Indefinite Article
	 *
	 * @param	\IPS\Lang|NULL	$language	The language to use, or NULL for the language of the currently logged in member
	 * @return	string
	 */
	public function indefiniteArticle( \IPS\Lang $lang = NULL )
	{
		$lang = $lang ?: \IPS\Member::loggedIn()->language();
		return $lang->addToStack( '__indefart_' . static::$title, FALSE );
	}
}