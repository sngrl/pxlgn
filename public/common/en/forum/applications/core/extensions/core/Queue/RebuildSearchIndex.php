<?php
/**
 * @brief		Background Task: Rebuild Search Index
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		14 Aug 2014
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\core\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Background Task: Rebuild Search Index
 */
class _RebuildSearchIndex
{
	/**
	 * @brief Number of content items to index per cycle
	 */
	public $index	= 500;
	
	/**
	 * Build query
	 *
	 * @param	array	$data
	 * @return	array	array( 'where' => xxx, 'joins' => array() )
	 */
	protected function _buildQuery( $data )
	{
		$classname = $data['class'];
		
		$where = array();
		$joins = array();
		
		if ( isset( $data['container'] ) )
		{
			if ( in_array( 'IPS\Content\Comment', class_parents( $classname ) ) )
			{
				$itemClass = $classname::$itemClass;
				$where[] = array( $itemClass::$databasePrefix . $itemClass::$databaseColumnMap['container'] . '=' . $data['container'] );
				$joins[ $itemClass::$databaseTable ] = $classname::$databasePrefix . $classname::$databaseColumnMap['item'] . '=' . $itemClass::$databasePrefix . $itemClass::$databaseColumnId;
			}
			else
			{
				$where[] = array( $classname::$databasePrefix . $classname::$databaseColumnMap['container'] . '=' . $data['container'] );
			}
		}
		
		if( \IPS\Settings::i()->search_index_timeframe )
		{
			$where[] = array( $classname::$databasePrefix . $classname::$databaseColumnMap['date'] . '> ?', \IPS\DateTime::ts( time() - ( 86400 * \IPS\Settings::i()->search_index_timeframe ) )->getTimestamp() );
		}
		
		return array( 'where' => $where, 'joins' => $joins );
	}

	/**
	 * Parse data before queuing
	 *
	 * @param	array	$data
	 * @return	array
	 */
	public function preQueueData( $data )
	{
		$classname = $data['class'];
		
		\IPS\Log::i( LOG_DEBUG )->write( "Getting preQueueData for " . $classname, 'rebuildSearchIndex' );
		
		$queryData = $this->_buildQuery( $data );
		try
		{			
			$select = \IPS\Db::i()->select( 'count(*)', $classname::$databaseTable, $queryData['where'] );
			foreach ( $queryData['joins'] as $table => $on )
			{
				$select->join( $table, $on );
			}
			$data['count'] = $select->first();
		}
		catch( \Exception $ex )
		{
			throw new \OutOfRangeException;
		}
		
		if( $data['count'] == 0 )
		{
			return null;
		}
		
		return $data;
	}

	/**
	 * Run Background Task
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	int|null				New offset or NULL if complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function run( $data, $offset )
	{
		$classname = $data['class'];
		if ( !class_exists( $classname ) )
		{
			throw new \OutOfRangeException;
		}
		
		$indexed = 0;
		
		\IPS\Log::i( LOG_DEBUG )->write( "Running " . $classname . ", with an offset of " . $offset, 'rebuildSearchIndex' );
		
		$queryData = $this->_buildQuery( $data );		
		$dateColumn = $classname::$databaseColumnMap['date'];
		$select = \IPS\Db::i()->select( '*', $classname::$databaseTable, $queryData['where'], $classname::$databasePrefix . $dateColumn . ' DESC', array( $offset, $this->index ) );
		foreach ( $queryData['joins'] as $table => $on )
		{
			$select->join( $table, $on );
		}
		$iterator = new \IPS\Patterns\ActiveRecordIterator( $select, $classname );
		
		foreach( $iterator as $item )
		{
			try
			{
				if ( !$item->isFutureDate() )
				{
					\IPS\Content\Search\Index::i()->index($item);
				}
			}
			catch ( \Exception $e ) { }

			$indexed++;
		}

		return ( $indexed == $this->index ) ? ( $offset + $this->index ) : null;
	}
	
	/**
	 * Get Progress
	 *
	 * @param	mixed					$data	Data as it was passed to \IPS\Task::queue()
	 * @param	int						$offset	Offset
	 * @return	array( 'text' => 'Doing something...', 'complete' => 50 )	Text explaning task and percentage complete
	 * @throws	\OutOfRangeException	Indicates offset doesn't exist and thus task is complete
	 */
	public function getProgress( $data, $offset )
	{
        $class = $data['class'];
        if ( !class_exists( $class ) )
		{
			throw new \OutOfRangeException;
		}
		
		return array( 'text' => \IPS\Member::loggedIn()->language()->addToStack('reindexing_stuff', FALSE, array( 'sprintf' => array( \IPS\Member::loggedIn()->language()->addToStack( $class::$title . '_pl', FALSE, array( 'strtolower' => TRUE ) ) ) ) ), 'complete' => $data['count'] ? ( round( 100 / $data['count'] * $offset, 2 ) ) : 100 );
	}	
}