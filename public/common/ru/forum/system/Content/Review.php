<?php
/**
 * @brief		Content Review Model
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		4 Nov 2013
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
 * Content Review Model
 */
abstract class _Review extends \IPS\Content\Comment
{
	/**
	 * @brief	[Content\Comment]	Comment Template
	 */
	public static $commentTemplate = array( array( 'global', 'core', 'front' ), 'reviewContainer' );
	
	/**
	 * Create first comment (created with content item)
	 *
	 * @param	\IPS\Content\Item		$item		The content item just created
	 * @param	string					$comment	The comment
	 * @param	bool					$first		Is the first comment?
	 * @param	int						$rating		The rating (1-5)
	 * @param	string					$guestName	If author is a guest, the name to use
	 * @param	\IPS\Member|NULL		$member		The author of this comment. If NULL, uses currently logged in member.
	 * @param	\IPS\DateTime|NULL		$time				The time
	 * @return	static
	 * @throws	\InvalidArgumentException
	 */
	public static function create( $item, $comment, $first=FALSE, $rating=NULL, $guestName=NULL, $member=NULL, \IPS\DateTime $time=NULL )
	{
		if ( !is_int( $rating ) or $rating < 1 or $rating > intval( \IPS\Settings::i()->reviews_rating_out_of ) )
		{
			throw new \InvalidArgumentException;
		}

		$obj = parent::create( $item, $comment, $first, $guestName, $member, $time );
		
		foreach ( array( 'rating', 'votes_data' ) as $k )
		{
			if ( isset( static::$databaseColumnMap[ $k ] ) )
			{
				$val = NULL;
				switch ( $k )
				{
					case 'rating':
						$val = $rating;
						break;
					
					case 'votes_data':
						$val = json_encode( array() );
						break;
				}
				
				foreach ( is_array( static::$databaseColumnMap[ $k ] ) ? static::$databaseColumnMap[ $k ] : array( static::$databaseColumnMap[ $k ] ) as $column )
				{
					$obj->$column = $val;
				}
			}
		}

		if( $obj->author()->member_id )
		{
			$obj->author()->member_last_post = time();
			$obj->author()->save();
		}

		$obj->save();

		/* Have to do this AFTER rating is set */
		$itemClass = static::$itemClass;
		$ratingField = $itemClass::$databaseColumnMap['rating'];
		
		$obj->item()->$ratingField = $obj->item()->averageReviewRating() ?: 0;
		$obj->item()->save();
		
		return $obj;
	}
	
	/**
	 * Do stuff after creating (abstracted as comments and reviews need to do different things)
	 *
	 * @return	void
	 */
	public function postCreate()
	{		
		$item = $this->item();
		if ( !$this->hidden() )
		{
			if ( isset( $item::$databaseColumnMap['last_review'] ) )
			{
				$lastReviewField = $item::$databaseColumnMap['last_review'];
				if ( is_array( $lastReviewField ) )
				{
					foreach ( $lastReviewField as $column )
					{
						$item->$column = time();
					}
				}
				else
				{
					$item->$lastReviewField = time();
				}
			}
			if ( isset( $item::$databaseColumnMap['last_review_by'] ) )
			{
				$lastReviewByField = $item::$databaseColumnMap['last_review_by'];
				$item->$lastReviewByField = $this->author()->member_id;
			}
			if ( isset( $item::$databaseColumnMap['num_reviews'] ) )
			{
				$numReviewsField = $item::$databaseColumnMap['num_reviews'];
				$item->$numReviewsField++;
			}
			
			if ( !$item->hidden() and $item->containerWrapper() and $item->container()->_reviews !== NULL )
			{
				$item->container()->_reviews = ( $item->container()->_reviews + 1 );
				$item->container()->setLastReview( $this );
				$item->container()->save();
			}
		}
		else
		{
			if ( isset( $item::$databaseColumnMap['unapproved_reviews'] ) )
			{
				$numReviewsField = $item::$databaseColumnMap['unapproved_reviews'];
				$item->$numReviewsField++;
			}
			if ( $item->containerWrapper() AND $item->container()->_unapprovedReviews !== NULL )
			{
				$item->container()->_unapprovedReviews = $item->container()->_unapprovedReviews + 1;
				$item->container()->save();
			}
		}
		
		$item->save();
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
			$itemClass = static::$itemClass;
			$itemField = static::$databaseColumnMap['item'];
			$idColumn	= static::$databaseColumnId;
					
			$this->_url[ $_key ] = $this->item()->url();

			if( $action )
			{
				$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( array( 'do' => $action . 'Review', 'review' => $this->$idColumn ) );
			}
			else
			{
				$commentPosition = \IPS\Db::i()->select( 'COUNT(*) AS position', static::$databaseTable, array( static::$databasePrefix . static::$databaseColumnMap['item'] . '=? AND ' . static::$databasePrefix . static::$databaseColumnId . '<=?', $this->$itemField, $this->$idColumn ), static::$databasePrefix . static::$databaseColumnMap['date'] . ' asc' )->first();
				$page = ceil( $commentPosition / $itemClass::$reviewsPerPage );
				if ( $page != 1 )
				{
					$this->_url[ $_key ] = $this->_url[ $_key ]->setQueryString( 'page', $page );
				}
				
				$this->_url[ $_key ] = $this->_url[ $_key ]->setFragment( 'review-' . $this->$idColumn );
			}
		}
	
		return $this->_url[ $_key ];
	}

	/**
	 * Get HTML for search result display
	 *
	 * @return	callable
	 */
	public function searchResultHtml()
	{
		return \IPS\Theme::i()->getTemplate( 'search', 'core', 'front' )->contentReview( $this );
	}
	
	/**
	 * Get line which says how many users found review helpful
	 *
	 * @return	string
	 */
	public function helpfulLine()
	{
		return \IPS\Theme::i()->getTemplate( 'global', 'core', 'front' )->reviewHelpful( $this->mapped('votes_helpful'), $this->mapped('votes_total') );
	}
	
	/**
	 * Syncing to run when hiding
	 *
	 * @return	void
	 */
	public function onHide()
	{
		$item = $this->item();
		if ( $item->container()->_reviews !== NULL )
		{
			$item->container()->_reviews = $item->container()->_reviews - 1;
			$item->container()->setLastReview();
			$item->container()->save();
		}
		if ( isset( $item::$databaseColumnMap['num_reviews'] ) )
		{
			$column = $item::$databaseColumnMap['num_reviews'];
			$item->$column = $item->mapped('num_reviews') - 1;
		}
		$item->resyncLastReview();
		
		$ratingField = $item::$databaseColumnMap['rating'];
		$item->$ratingField = $item->averageReviewRating() ?: 0; 
		
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
			if ( isset( $item::$databaseColumnMap['unapproved_reviews'] ) )
			{
				$column = $item::$databaseColumnMap['unapproved_reviews'];
				$item->$column = $item->mapped('unapproved_reviews') - 1;
			}
			if ( $item->container()->_unapprovedReviews !== NULL )
			{
				$item->container()->_unapprovedReviews = $item->container()->_unapprovedReviews - 1;
				$item->container()->setLastReview();
				$item->container()->save();
			}
		}

		if ( isset( $item::$databaseColumnMap['num_reviews'] ) )
		{
			$column = $item::$databaseColumnMap['num_reviews'];
			$item->$column = $item->mapped('num_reviews') + 1;
		}
		if ( $item->container()->_reviews !== NULL )
		{
			$item->container()->_reviews = $item->container()->_reviews + 1;
			$item->container()->setLastReview();
			$item->container()->save();
		}
		
		$ratingField = $item::$databaseColumnMap['rating'];
		$item->$ratingField = $item->averageReviewRating() ?: 0; 
		
		$item->resyncLastReview();
		$item->save();
	}
	
	/**
	 * Can split this comment off?
	 *
	 * @param	\IPS\Member|NULL	$member	The member to check for (NULL for currently logged in member)
	 * @return	bool
	 */
	public function canSplit( $member=NULL )
	{
		return FALSE;
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
		return base64_encode( json_encode( array( 'app' => $itemClass::$application, 'module' => $itemClass::$module . '-review' , 'id_1' => $this->mapped('item'), 'id_2' => $this->$idColumn ) ) );
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
		return array( $this->item()->$idColumn, $this->$commentIdColumn, 'review' ); 
	}
	
	/**
	 * Create Notification
	 *
	 * @param	string|NULL		$extra		Additional data
	 * @return	\IPS\Notification
	 */
	protected function createNotification( $extra=NULL )
	{
		return new \IPS\Notification( \IPS\Application::load( 'core' ), 'new_review', $this->item(), array( $this ) );
	}
	
	/**
	 * Delete Review
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();
		$itemClass = static::$itemClass;
		$ratingField = $itemClass::$databaseColumnMap['rating'];
		$this->item()->$ratingField = $this->item()->averageReviewRating() ?: 0; 
	}
}