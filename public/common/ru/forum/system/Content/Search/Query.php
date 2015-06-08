<?php
/**
 * @brief		Abstract Search Query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		21 Aug 2014
 * @version		SVN_VERSION_NUMBER
*/

namespace IPS\Content\Search;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Abstract Search Query
 */
abstract class _Query
{
	const TERM_OR_TAGS = 1;
	const TERM_AND_TAGS = 2;
	
	const HIDDEN_VISIBLE = 0;
	const HIDDEN_UNAPPROVED = 1;
	const HIDDEN_HIDDEN = -1;
	const HIDDEN_PARENT_HIDDEN = 2;
	
	const ORDER_NEWEST_UPDATED = 1;
	const ORDER_NEWEST_CREATED = 2;
	const ORDER_RELEVANCY = 3;
	
	const SUPPORTS_JOIN_FILTERS = TRUE;
		
	/**
	 * Create new query
	 *
	 * @param	\IPS\Member	$member	The member performing the search (NULL for currently logged in member)
	 * @return	\IPS\Content\Search
	 */
	public static function init( \IPS\Member $member = NULL )
	{
		$class = '\\IPS\\Content\\Search\\' . ucfirst( \IPS\Settings::i()->search_engine ) . '\\Query';
		return new $class( $member ?: \IPS\Member::loggedIn() );
	}
		
	/**
	 * @brief	Number of results to get
	 */
	public $resultsToGet = 25;
	
	/**
	 * @brief	The member performing the search
	 */
	protected $member;
			
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	The member performing the search
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{
		$this->member = $member;
		
		if ( !$member->modPermission('can_view_hidden_content') )
		{
			$this->setHiddenFilter( static::HIDDEN_VISIBLE );
		}
	}
	
	/**
	 * Exclude "first post" content items
	 * Useful for cases such as activity streams where you do not want both the topic and the first post to show
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function excludeFirstPostContentItems()
	{
		$classes = array();
		foreach ( \IPS\Content::routedClasses( TRUE, TRUE, FALSE, TRUE ) as $class )
		{
			if ( in_array( 'IPS\Content\Searchable', class_implements( $class ) ) and $class::$firstCommentRequired )
			{
				$classes[] = $class;
			}
		}
		
		if ( !empty( $classes ) )
		{
			return $this->filterByExcludeContent( $classes );
		}
		else
		{
			return $this;
		}
	}
	
	/**
	 * Exclude disabled applications
	 *
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function excludeDisabledApps()
	{
		$includeClasses = array();
		foreach ( \IPS\Content::routedClasses( TRUE, TRUE, FALSE, TRUE ) as $class )
		{
			if ( in_array( 'IPS\Content\Searchable', class_implements( $class ) ) )
			{
				$includeClasses[] = $class;
				
				if ( isset( $class::$commentClass ) )
				{
					$includeClasses[] = $class::$commentClass;
				}
				if ( isset( $class::$reviewClass ) )
				{
					$includeClasses[] = $class::$reviewClass;
				}
			}
		}
		$allClasses = array();
		foreach ( \IPS\Content::routedClasses( FALSE, FALSE, FALSE, TRUE ) as $class )
		{
			if ( in_array( 'IPS\Content\Searchable', class_implements( $class ) ) )
			{
				$allClasses[] = $class;
				
				if ( isset( $class::$commentClass ) )
				{
					$allClasses[] = $class::$commentClass;
				}
				if ( isset( $class::$reviewClass ) )
				{
					$allClasses[] = $class::$reviewClass;
				}
			}
		}

		$classes = array_diff( $allClasses, $includeClasses );
		
		if ( !empty( $classes ) )
		{
			return $this->filterByExcludeContent( $classes );
		}
		else
		{
			return $this;
		}
	}
	
    /**
	 * Filter by content type
	 *
	 * @param	string		$class 				The type of content to search (including all comment/review classes)
	 * @param	array|NULL	$containers			An array of container IDs to filter by, or NULL to not filter by containers
	 * @param	int|NULL	$minimumComments	The minimum number of comments
	 * @param	int|NULL	$minimumReviews		The minimum number of reviews
	 * @param	int|NULL	$minimumViews		The minimum number of views
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByContent( $class, $containers = NULL, $minimumComments = NULL, $minimumReviews = NULL, $minimumViews = NULL );
	
	/**
	 * Filter by content type to exclude
	 *
	 * @param	string		$$classes 		The types of content to search (not including all comment/review classes)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByExcludeContent( array $classes );
	
	/**
	 * Filter by content item
	 *
	 * @param	\IPS\Content\Item	$item		The item
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByItem( \IPS\Content\Item $item );
	
	/**
	 * Filter by author
	 *
	 * @param	\IPS\Member	$author		The author
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByAuthor( \IPS\Member $author );
	
	/**
	 * Filter by start date
	 *
	 * @param	\IPS\DateTime|NULL	$start		The start date (only results AFTER this date will be returned)
	 * @param	\IPS\DateTime|NULL	$end		The end date (only results BEFORE this date will be returned)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByCreateDate( \IPS\DateTime $start = NULL, \IPS\DateTime $end = NULL );
	
	/**
	 * Filter by last updated date
	 *
	 * @param	\IPS\DateTime|NULL	$start		The start date (only results AFTER this date will be returned)
	 * @param	\IPS\DateTime|NULL	$end		The end date (only results BEFORE this date will be returned)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function filterByLastUpdatedDate( \IPS\DateTime $start = NULL, \IPS\DateTime $end = NULL );
	
	/**
	 * Set hidden status
	 *
	 * @param	int|array|NULL	$statuses	The statuses (see HIDDEN_ constants) or NULL for any
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function setHiddenFilter( $statuses );
	
	/**
	 * Set limit
	 *
	 * @param	int		$limit	Number per page
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function setLimit( $limit )
	{
		$this->resultsToGet = $limit;
		return $this;
	}
	
	/**
	 * Set page
	 *
	 * @param	int		$page	The page number
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function setPage( $page );
	
	/**
	 * Set order
	 *
	 * @param	int		$order	Order (see ORDER_ constants)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	abstract public function setOrder( $order );
	
	/**
	 * Permission Array
	 *
	 * @return	array
	 */
	public function permissionArray()
	{
		return $this->member->permissionArray();
	}
	
	/**
	 * Search
	 *
	 * @param	string|null	$term	The term to search for
	 * @param	array|null	$tags	The tags to search for
	 * @param	int			$method	\IPS\Content\Search\Index::i()->TERM_OR_TAGS or \IPS\Content\Search\Index::i()->TERM_AND_TAGS
	 * @return	\Traversable
	 */
	abstract public function search( $term = NULL, $tags = NULL, $method = 1 );
}