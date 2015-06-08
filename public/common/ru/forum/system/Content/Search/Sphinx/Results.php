<?php
/**
 * @brief		Sphinx Search Results
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		21 Aug 2014
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
 * Sphinx Search Results
 */
class _Results implements \Iterator, \Countable
{
	/**
	 * @brief	Query
	 */
	protected $query;
	
	/**
	 * @brief	Result
	 */
	protected $result;
	
	/**
	 * @brief	Key
	 */
	protected $key;
	
	/**
	 * @brief	Current Row
	 */
	protected $row;
	
	/**
	 * @brief	Are we at the beginning?
	 */
	protected $rewound = FALSE;
	
	/**
	 * @brief	Count
	 */
	protected $count;
	
	/**
	 * @brief	Count
	 */
	protected $countAllRows;
	
	/**
	 * Constructor
	 *
	 * @param	string	$query	The query
	 * @return	void
	 */
	public function __construct( $query )
	{
		$this->query = $query;
	}
	
	/**
	 * Get current
	 *
	 * @return	\IPS\Patterns\ActiveRecord
	 */
	public function current()
	{
		$class = $this->row['index_class'];
		return $class::load( $this->row['index_object_id'] );
	}
	
	/**
	 * Get key
	 *
	 * @return	mixed
	 */
	public function key()
	{
		return $this->key;
	}
	
	/**
	 * Get key
	 *
	 * @return	mixed
	 */
	public function next()
	{
		$this->rewound = FALSE;
    	$this->row = $this->result->fetch_assoc();
    	$this->key++;
	}
	
	/**
	 * Run the query
	 *
	 * @return	void
	 */
	protected function runQuery()
	{		
		/* Run the query */
		$this->result = \IPS\Db::i('sphinx')->query( $this->query );
		
		/* Get counts */
		$this->count = $this->result->num_rows;
		$meta = \IPS\Db::i('sphinx')->query('SHOW META');
		while ( $row = $meta->fetch_assoc() )
		{
			if ( $row['Variable_name'] === 'total' )
			{
				$this->countAllRows = $row['Value'];
			}
			break;
		}
    	
    	/* Note that the query has just been ran */
    	$this->rewound = TRUE;
	}
	
	/**
	 * Get key
	 *
	 * @return	void
	 */
	public function rewind()
	{
		/* Run the query */
    	if ( !$this->rewound )
    	{
	    	$this->runQuery();
    	}
    	
    	/* Get the first result */
    	$this->key = -1;
    	$this->next();
	}
	
	/**
	 * Get key
	 *
	 * @return	bool
	 */
	public function valid()
	{
		return ( $this->row !== NULL );
	}
		
	/**
	 * Get count
	 *
	 * @param	bool	$allRows	If TRUE, will get the number of rows ignoring the limit
	 * @return	int
	 */
	public function count( $allRows = FALSE )
	{
		if ( !$this->result )
		{
			$this->runQuery();
		}
		
		if ( $allRows )
		{
			return $this->countAllRows;
		}
		else
		{
			return $this->count;
		}
	}
}