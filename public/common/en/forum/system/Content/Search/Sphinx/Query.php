<?php
/**
 * @brief		Sphinx Search Query
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		26 Aug 2014
 * @version		SVN_VERSION_NUMBER
*/

namespace IPS\Content\Search\Sphinx;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Sphinx Search Query
 */
class _Query extends \IPS\Content\Search\Query
{
	const SUPPORTS_JOIN_FILTERS = FALSE;
	
	/**
	 * @brief	The index table
	 */
	protected $table;
		
	/**
     * @brief       The WHERE clause
     */
    protected $where = array();
    
    /**
     * @brief       The WHERE clause for hidden/unhidden
     */
    protected $hiddenClause = NULL;
    
    /**
     * @brief       The offset
     */
    protected $offset = 0;
	
	/**
	 * Constructor
	 *
	 * @param	\IPS\Member	$member	The member performing the search
	 * @return	void
	 */
	public function __construct( \IPS\Member $member )
	{		
		\IPS\Db::i( 'sphinx', array(
			'sql_host'		=> \IPS\Settings::i()->search_sphinx_server,
			'sql_user'		=> NULL,
			'sql_pass'		=> NULL,
			'sql_database'	=> NULL,
			'sql_port'		=> (int) \IPS\Settings::i()->search_sphinx_port,
			'sql_socket'	=> NULL,
			'sql_utf8mb4'	=> \IPS\Settings::i()->sql_utf8mb4
		) );
		
		$this->table = \IPS\Settings::i()->sphinx_prefix . 'ips';
		
		parent::__construct( $member );
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
	public function filterByContent( $class, $containers = NULL, $minimumComments = NULL, $minimumReviews = NULL, $minimumViews = NULL )
	{				
		/* Classes */
		$classes = array( \IPS\Content\Search\Sphinx\Index::_getClassId( $class ) );
		if ( isset( $class::$commentClass ) )
		{
			$classes[] = \IPS\Content\Search\Sphinx\Index::_getClassId( $class::$commentClass );
		}
		if ( isset( $class::$reviewClass ) )
		{
			$classes[] = \IPS\Content\Search\Sphinx\Index::_getClassId( $class::$reviewClass );
		}
		$this->where[] = 'index_class_id IN(' . implode( ',', $classes ) . ')';
		
		/* Containers */
		if ( $containers !== NULL )
		{
			$this->where[] = 'index_container_id IN(' . implode( ',', array_map( 'intval', $containers ) ) . ' )';
		}
		
		/* We don't support anything else */
		if ( $minimumComments !== NULL or $minimumReviews !== NULL or $minimumViews !== NULL )
		{
			throw new \UnexpectedValueException;
		}
		
		return $this;
	}
	
	/**
	 * Filter by content type to exclude
	 *
	 * @param	string		$$classes 		The types of content to search (not including all comment/review classes)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByExcludeContent( array $classes )
	{
		$this->where[] = 'index_class NOT IN('. implode( ',', array_map( array( 'IPS\Content\Search\Sphinx\Index', '_getClassId' ), $classes ) ) . ')';
		return $this;
	}
	
	/**
	 * Filter by content item
	 *
	 * @param	\IPS\Content\Item	$item		The item
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByItem( \IPS\Content\Item $item )
	{
		$class = get_class( $item );
		
		$classes = array();
		if ( isset( $class::$commentClass ) )
		{
			$classes[] = \IPS\Content\Search\Sphinx\Index::_getClassId( $class::$commentClass );
		}
		if ( isset( $class::$reviewClass ) )
		{
			$classes[] = \IPS\Content\Search\Sphinx\Index::_getClassId( $class::$reviewClass );
		}
		$this->where[] = 'index_class_id IN(' . implode( ',', $classes ) . ')';
		
		$idColumn = $item::$databaseColumnId;
		$this->where[] = "index_item_id={$item->$idColumn}";
		
		return $this;
	}
	
	/**
	 * Filter by author
	 *
	 * @param	\IPS\Member	$author		The author
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByAuthor( \IPS\Member $author )
	{
		$this->where[] = "index_author={$author->member_id}";
		return $this;
	}
		
	/**
	 * Filter by start date
	 *
	 * @param	\IPS\DateTime|NULL	$start		The start date (only results AFTER this date will be returned)
	 * @param	\IPS\DateTime|NULL	$end		The end date (only results BEFORE this date will be returned)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByCreateDate( \IPS\DateTime $start = NULL, \IPS\DateTime $end = NULL )
	{
		if ( $start )
		{
			$this->where[] = 'index_date_created>' . $start->getTimestamp();
		}
		if ( $end )
		{
			$this->where[] = 'index_date_created<' . $end->getTimestamp();
		}
		return $this;
	}
	
	/**
	 * Filter by last updated date
	 *
	 * @param	\IPS\DateTime|NULL	$start		The start date (only results AFTER this date will be returned)
	 * @param	\IPS\DateTime|NULL	$end		The end date (only results BEFORE this date will be returned)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function filterByLastUpdatedDate( \IPS\DateTime $start = NULL, \IPS\DateTime $end = NULL )
	{
		if ( $start )
		{
			$this->where[] = 'index_date_updated>' . $start->getTimestamp();
		}
		if ( $end )
		{
			$this->where[] = 'index_date_updated<' . $end->getTimestamp();
		}
		return $this;
	}
	
	/**
	 * Set hidden status
	 *
	 * @param	int|array	$statuses	The statuses (array of HIDDEN_ constants)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function setHiddenFilter( $statuses )
	{
		if ( is_null( $statuses ) )
		{
			$this->hiddenClause = NULL;
		}
		if ( is_array( $statuses ) )
		{
			$this->hiddenClause = 'index_hidden IN(' . implode( ',', array_map( 'intval', $statuses ) ) . ' )';
		}
		else
		{
			$this->hiddenClause = 'index_hidden=' . intval( $statuses );
		}
				
		return $this;
	}
	
	/**
	 * Set page
	 *
	 * @param	int		$page	The page number
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function setPage( $page )
	{
		$this->offset = ( $page - 1 ) * $this->resultsToGet;
		
		return $this;
	}
	
	/**
	 * Set order
	 *
	 * @param	int		$order	Order (see ORDER_ constants)
	 * @return	\IPS\Content\Search\Query	(for daisy chaining)
	 */
	public function setOrder( $order )
	{
		switch ( $order )
		{
			case static::ORDER_NEWEST_UPDATED:
				$this->order = 'index_date_updated DESC';
				break;
			
			case static::ORDER_NEWEST_CREATED:
				$this->order = 'index_date_created DESC';
				break;
		}
		
		return $this;
	}
	
	/**
	 * Search
	 *
	 * @param	string|null	$term	The term to search for
	 * @param	array|null	$tags	The tags to search for
	 * @param	int			$method	\IPS\Content\Search\Index::i()->TERM_OR_TAGS or \IPS\Content\Search\Index::i()->TERM_AND_TAGS
	 * @return	\IPS\Content\Search\Results
	 */
	public function search( $term = NULL, $tags = NULL, $method = 1 )
	{
		/* Build the match term */
		if ( $term !== NULL )
		{
			$match[] = "( @(index_title,index_content) {$this->escape( $term )} )";
		}
		if ( $tags !== NULL )
		{
			$match[] = "( @index_tags " . implode( ' | ', array_map( array( $this, 'escape' ), $tags ) ) . ' )';
		}
		if ( !empty( $match ) )
		{
			array_unshift( $this->where, 'MATCH( \'' . implode( ( $method === static::TERM_OR_TAGS ? ' | ' : ' ' ), $match ) . '\' )' );
		}
		
		/* Only get stuff we have permission for */
		$this->where[] = 'index_permissions IN(0,' . implode( ',', $this->permissionArray() ) . ')';
		
		/* Add the hidden clause */
		if ( $this->hiddenClause )
		{
			$this->where[] = $this->hiddenClause;
		}
		
		/* Search */
		return new \IPS\Content\Search\Sphinx\Results( "SELECT * FROM {$this->table} WHERE " . implode( ' AND ', $this->where ) . ( $this->order ? "ORDER BY {$this->order}" : '' ) . " LIMIT {$this->offset}, {$this->resultsToGet}" );
	}
	
	/**
	 * Escape string for SphinxQL query
	 *
	 * @param	string	$term	Search term
	 * @return	string
	 */
	protected function escape( $term )
	{
		return str_replace( array( '\\', '(',')','|','-','!','@','~','"','&', '/', '^', '$', '=' ), array('\\\\', '\(','\)','\|','\-','\!','\@','\~','\"','\&','\/', '\^', '\$', '\=' ), $term );
	}
}