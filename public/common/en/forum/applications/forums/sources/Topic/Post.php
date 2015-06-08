<?php
/**
 * @brief		Post Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @subpackage	Board
 * @since		8 Jan 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\forums\Topic;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Post Model
 */
class _Post extends \IPS\Content\Comment implements \IPS\Content\ReportCenter, \IPS\Content\EditHistory, \IPS\Content\Hideable, \IPS\Content\Reputation, \IPS\Content\Shareable, \IPS\Content\Searchable
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] ID Database Column
	 */
	public static $databaseColumnId = 'pid';
	
	/**
	 * @brief	[Content\Comment]	Item Class
	 */
	public static $itemClass = 'IPS\forums\Topic';
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'forums_posts';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = '';
	
	/**
	 * @brief	Application
	 */
	public static $application = 'forums';

	/**
	 * @brief	Title
	 */
	public static $title = 'post';
	
	/**
	 * @brief	Database Column Map
	 */
	public static $databaseColumnMap = array(
		'item'				=> 'topic_id',
		'author'			=> 'author_id',
		'author_name'		=> 'author_name',
		'content'			=> 'post',
		'date'				=> 'post_date',
		'ip_address'		=> 'ip_address',
		'edit_time'			=> 'edit_time',
		'edit_show'			=> 'append_edit',
		'edit_member_name'	=> 'edit_name',
		'edit_reason'		=> 'post_edit_reason',
		'hidden'			=> 'queued',
		'first'				=> 'new_topic'
	);
	
	/**
	 * @brief	Icon
	 */
	public static $icon = 'comment';
	
	/**
	 * @brief	Reputation Type
	 */
	public static $reputationType = 'pid';
	
	/**
	 * @brief	[Content\Comment]	Comment Template
	 */
	public static $commentTemplate = array( array( 'topics', 'forums', 'front' ), 'postContainer' );
	
	/**
	 * @brief	[Content]	Key for hide reasons
	 */
	public static $hideLogKey = 'post';
	
	/**
	 * @brief	Bitwise values for post_bwoptions field
	 */
	public static $bitOptions = array(
		'post_bwoptions' => array(
			'post_bwoptions' => array(
				'best_answer'	=> 1
			)
		)
	);
	
	/**
	 * Join profile fields when loading comments?
	 */
	public static $joinProfileFields = TRUE;

	/**
	 * Create comment
	 *
	 * @param	\IPS\Content\Item		$item		The content item just created
	 * @param	string					$comment	The comment
	 * @param	bool					$first		Is the first comment?
	 * @param	string					$guestName	If author is a guest, the name to use
	 * @param	bool|NULL				$incrementPostCount	Increment post count? If NULL, will use static::incrementPostCount()
	 * @param	\IPS\Member|NULL		$member				The author of this comment. If NULL, uses currently logged in member.
	 * @param	\IPS\DateTime|NULL		$time				The time
	 * @return	static
	 */
	public static function create( $item, $comment, $first=FALSE, $guestName=NULL, $incrementPostCount=NULL, $member=NULL, \IPS\DateTime $time=NULL )
	{
		$comment = call_user_func_array( 'parent::create', func_get_args() );
		
		if ( !$comment->hidden() and $popularNowSettings = json_decode( \IPS\Settings::i()->forums_popular_now, TRUE ) and $popularNowSettings['posts'] and $popularNowSettings['minutes'] )
		{
			$popularNowInterval = new \DateInterval( 'PT' . $popularNowSettings['minutes'] . 'M' );
			
			$comments = $item->comments( NULL, NULL, 'date', 'desc', NULL, FALSE, \IPS\DateTime::create()->sub( $popularNowInterval ) );
			if ( count( $comments ) >= $popularNowSettings['posts'] )
			{
				$commentToBasePopularNowTimeOff = array_slice( $comments, ( $popularNowSettings['posts'] - 1 ), 1 );
				$commentToBasePopularNowTimeOff = array_pop( $commentToBasePopularNowTimeOff );
				
				$item->popular_time = \IPS\DateTime::ts( $commentToBasePopularNowTimeOff->post_date )->add( $popularNowInterval )->getTimestamp();
				$item->save();
			}
			elseif ( $item->popular_time !== NULL )
			{
				$item->popular_time = NULL;
				$item->save();
			}
		}
		
		return $comment;
	}
	
	/**
	 * Should posting this increment the poster's post count?
	 *
	 * @param	\IPS\Node\Model|NULL	$container	Container
	 * @return	void
	 * @see		\IPS\Topic\Post::incrementPostCount()
	 */
	public static function incrementPostCount( \IPS\Node\Model $container = NULL )
	{
		return $container and $container->inc_postcount;
	}
	
	/**
	 * Post count for member
	 *
	 * @param	\IPS\Member	$member	The memner
	 * @return	int
	 */
	public static function memberPostCount( \IPS\Member $member )
	{
		return \IPS\Db::i()->select( 'COUNT(*)', 'forums_posts', array(
			'author_id=? AND forum_id IN(?)',
			$member->member_id,
			\IPS\Db::i()->select( 'id', 'forums_forums', 'inc_postcount=1' )
		) )->join( 'forums_topics', 'tid=topic_id' )->first() ;
	}
	
	/**
	 * Get items with permisison check
	 *
	 * @param	array		$where				Where clause
	 * @param	string		$order				MySQL ORDER BY clause (NULL to order by date)
	 * @param	int|array	$limit				Limit clause
	 * @param	string		$permissionKey		A key which has a value in the permission map (either of the container or of this class) matching a column ID in core_permission_index
	 * @param	bool|NULL	$includeHiddenItems	Include hidden files? Boolean or NULL to detect if currently logged member has permission
	 * @param	int			$queryFlags			Select bitwise flags
	 * @param	\IPS\Member	$member				The member (NULL to use currently logged in member)
	 * @param	bool		$joinContainer		If true, will join container data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinComments		If true, will join comment data (set to TRUE if your $where clause depends on this data)
	 * @param	bool		$joinReviews		If true, will join review data (set to TRUE if your $where clause depends on this data)
	 * @return	\IPS\Patterns\ActiveRecordIterator|int
	 */
	public static function getItemsWithPermission( $where=array(), $order=NULL, $limit=10, $permissionKey='read', $includeHiddenItems=NULL, $queryFlags=0, \IPS\Member $member=NULL, $joinContainer=FALSE, $joinComments=FALSE, $joinReviews=FALSE, $countOnly=FALSE, $joins=NULL )
	{
		$where = \IPS\forums\Topic::getItemsWithPermissionWhere( $where, $permissionKey, $member, $joinContainer );
		return parent::getItemsWithPermission( $where, $order, $limit, $permissionKey, $includeHiddenItems, $queryFlags, $member, $joinContainer, $joinComments, $joinReviews, $countOnly, $joins );
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @return	callable
	 */
	public function searchResultHtml()
	{
		if ( $this->item()->container()->password and !\IPS\Member::loggedIn()->inGroup( explode( ',', $this->item()->container()->password_override ) ) and !( isset( \IPS\Request::i()->cookie[ 'ipbforumpass_' . $this->item()->container()->id ] ) and md5( $this->item()->container()->password ) === \IPS\Request::i()->cookie[ 'ipbforumpass_' . $this->item()->container()->id ] ) )
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'forums' )->searchNoPermission( $this, \IPS\Member::loggedIn()->language()->addToStack('no_perm_post_password'), $this->item()->container()->url()->setQueryString( 'topic', $this->item()->tid ) );
		}
		elseif ( $this->item()->container()->min_posts_view and $this->item()->container()->min_posts_view > \IPS\Member::loggedIn()->member_posts )
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'forums' )->searchNoPermission( $this, \IPS\Member::loggedIn()->language()->addToStack( 'no_perm_post_min_posts', FALSE, array( 'pluralize' => array( $this->item()->container()->min_posts_view ) ) ) );
		}
		else
		{
			return \IPS\Theme::i()->getTemplate( 'search', 'core', 'front' )->contentComment( $this );
		}
	}
	
	/**
	 * Get HTML for search result display
	 *
	 * @return	callable
	 */
	public function activityStreamHtml()
	{
		if ( $this->item()->container()->password and !\IPS\Member::loggedIn()->inGroup( explode( ',', $this->item()->container()->password_override ) ) and !( isset( \IPS\Request::i()->cookie[ 'ipbforumpass_' . $this->item()->container()->id ] ) and md5( $this->item()->container()->password ) === \IPS\Request::i()->cookie[ 'ipbforumpass_' . $this->item()->container()->id ] ) )
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'forums' )->searchNoPermission( $this, \IPS\Member::loggedIn()->language()->addToStack('no_perm_post_password'), $this->item()->container()->url()->setQueryString( 'topic', $this->item()->tid ) );
		}
		elseif ( $this->item()->container()->min_posts_view and $this->item()->container()->min_posts_view > \IPS\Member::loggedIn()->member_posts )
		{
			return \IPS\Theme::i()->getTemplate( 'global', 'forums' )->searchNoPermission( $this, \IPS\Member::loggedIn()->language()->addToStack( 'no_perm_post_min_posts', FALSE, array( 'pluralize' => array( $this->item()->container()->min_posts_view ) ) ) );
		}
		else
		{
			return \IPS\Theme::i()->getTemplate( 'system', 'core', 'front' )->activityStreamResult( $this );
		}
	}
	
	/* !Questions & Answers */
	
	/**
	 * Can the user rate answers?
	 *
	 * @param	int					$rating		1 for positive, -1 for negative, 0 for either
	 * @param	\IPS\Member|NULL	$member		The member (NULL for currently logged in member)
	 * @return	bool
	 * @throws	\InvalidArgumentException
	 */
	public function canVote( $rating=0, $member=NULL )
	{
		/* Is $rating valid */
		if ( !in_array( $rating, array( -1, 0, 1 ) ) )
		{
			throw new \InvalidArgumentException;
		}
		
		/* Downvoting disabled? */
		if ( $rating === -1 and !\IPS\Settings::i()->forums_answers_downvote )
		{
			return FALSE;
		}
		
		/* Guests can't vote */
		$member = $member ?: \IPS\Member::loggedIn();
		if ( !$member->member_id )
		{
			return FALSE;
		}
		
		/* Can't vote your own answers */
		if ( $member == $this->author() )
		{
			return FALSE;
		}
		
		/* Check the forum settings */
		if ( $this->item()->container()->qa_rate_answers !== NULL and $this->item()->container()->qa_rate_answers != '*' and !$member->inGroup( explode( ',', $this->item()->container()->qa_rate_answers ) ) )
		{
			return FALSE;
		}
		
		/* Have we already voted? */
		if ( $rating !== 0 or !\IPS\Settings::i()->forums_answers_downvote )
		{
			$ratings = $this->item()->answerVotes( $member );
			if ( isset( $ratings[ $this->pid ] ) and $ratings[ $this->pid ] === $rating )
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}

    /**
     * Delete Post
     *
     * @return	void
     */
    public function delete()
    {
        /* Reset best answer if relevant */
        if ( $this->item()->topic_answered_pid == $this->pid )
        {
            $this->item()->topic_answered_pid = FALSE;
            $this->item()->save();
        }

        parent::delete();
    }
    
    /**
	 * Indefinite Article
	 *
	 * @param	\IPS\Lang|NULL	$language	The language to use, or NULL for the language of the currently logged in member
	 * @return	string
	 */
	public function indefiniteArticle( \IPS\Lang $lang = NULL )
	{
		if ( $this->item()->isQuestion() )
		{
			$lang = $lang ?: \IPS\Member::loggedIn()->language();
			return $lang->get( '__indefart_answer', FALSE );
		}
		else
		{
			return parent::indefiniteArticle( $lang );
		}
	}

	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		/* Set forum theme if it has been overridden */
		$this->item()->container()->setTheme();

		return parent::html();
	}
}