<?php
/**
 * @brief		MySQL Search Results
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		21 Aug 2014
 * @version		SVN_VERSION_NUMBER
*/

namespace IPS\Content\Search\Mysql;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * MySQL Search Results
 */
class _Results extends \IteratorIterator implements \Countable
{
	/**
	 * Get current
	 *
	 * @return	\IPS\Patterns\ActiveRecord
	 */
	public function current()
	{
		$row = parent::current();
		return call_user_func( array( $row['index_class'], 'load' ), $row['index_object_id'] );
	}
	
	/**
	 * @brief	Count
	 */
	protected $count;
	
	/**
	 * @brief	Count
	 */
	protected $countAllRows;
	
	/**
	 * Get count
	 *
	 * @param	bool	$allRows	If TRUE, will get the number of rows ignoring the limit
	 * @return	int
	 */
	public function count( $allRows = FALSE )
	{
		if ( $allRows )
		{		
			if ( $this->countAllRows === NULL )
			{
				$this->countAllRows = (int) $this->getInnerIterator()->count( TRUE );
			}
			return $this->countAllRows;
		}
		else
		{
			if ( $this->count === NULL )
			{
				$this->count = (int) $this->getInnerIterator()->count( FALSE );
			}
			return $this->count;
		}
	}
}