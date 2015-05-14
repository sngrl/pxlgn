<?php
/**
 * @brief		MySQL Search Index
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
 * MySQL Search Index
 */
class _Index extends \IPS\Content\Search\Index
{
	/**
	 * Index an item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	void
	 */
	public function index( \IPS\Content\Searchable $object )
	{
		$indexData = $this->indexData( $object );
		if( $indexData )
		{
			if ( !$indexData['index_permissions'] )
			{
				$this->removeFromSearchIndex( $object );
			}
			else
			{
				\IPS\Db::i()->replace( 'core_search_index', $indexData );
			}
		}
	}
	
	/**
	 * Remove item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to remove
	 * @return	void
	 */
	public function removeFromSearchIndex( \IPS\Content\Searchable $object )
	{
		$class = get_class( $object );
		$idColumn = $class::$databaseColumnId;
		
		\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? AND index_object_id=?', $class, $object->$idColumn ) );
		
		if ( isset( $class::$commentClass ) )
		{
			$commentClass = $class::$commentClass;
			\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? AND index_item_id=?', $commentClass, $object->$idColumn ) );
		}
		
		if ( isset( $class::$reviewClass ) )
		{
			$reviewClass = $class::$reviewClass;
			\IPS\Db::i()->delete( 'core_search_index', array( 'index_class=? AND index_item_id=?', $reviewClass, $object->$idColumn ) );
		}
	}
	
	/**
	 * Removes all content for a classs
	 *
	 * @param	string		$class 			The class
	 * @param	int|NULL	$containerId	The container ID to update, or NULL
	 * @return	void
	 */
	public function removeClassFromSearchIndex( $class, $containerId=NULL )
	{
		$where = array( array( 'index_class=?', $class ) );
		if ( $containerId !== NULL )
		{
			$where[] = array( 'index_container_id=?', $containerId );
		}
		
		\IPS\Db::i()->delete( 'core_search_index', $where );
	}
	
	/**
	 * Mass Update (when permissions change, for example)
	 *
	 * @param	string				$class 				The class
	 * @param	int|NULL			$containerId		The container ID to update, or NULL
	 * @param	int|NULL			$itemId				The item ID to update, or NULL
	 * @param	string|NULL			$newPermissions		New permissions (if applicable)
	 * @param	int|NULL			$newHiddenStatus	New hidden status (if applicable) special value 2 can be used to indicate hidden only by parent
	 * @param	int|NULL			$newContainer		New container ID (if applicable)
	 * @return	void
	 */
	public function massUpdate( $class, $containerId = NULL, $itemId = NULL, $newPermissions = NULL, $newHiddenStatus = NULL, $newContainer = NULL )
	{
		$where = array( array( 'index_class=?', $class ) );
		if ( $containerId !== NULL )
		{
			$where[] = array( 'index_container_id=?', $containerId );
		}
		if ( $itemId !== NULL )
		{
			$where[] = array( 'index_item_id=?', $itemId );
		}

		$update = array();
		if ( $newPermissions !== NULL )
		{
			$update['index_permissions'] = $newPermissions;
		}
		if ( $newHiddenStatus !== NULL )
		{
			if ( $newHiddenStatus === 2 )
			{
				$where[] = array( 'index_hidden=0' );
			}
			else
			{
				$where[] = array( 'index_hidden=2' );
			}
			
			$update['index_hidden'] = $newHiddenStatus;
		}
		if ( $newContainer )
		{
			$update['index_container_id'] = $newContainer;
		}
		
		\IPS\Db::i()->update( 'core_search_index', $update, $where );
	}
	
	/**
	 * Prune search index
	 *
	 * @param	\IPS\DateTime|NULL	$cutoff	The date to delete index records from, or NULL to delete all
	 * @return	void
	 */
	public function prune( \IPS\DateTime $cutoff = NULL )
	{
		if ( $cutoff )
		{
			\IPS\DB::i()->delete( 'core_search_index', array( 'index_date_updated < ?', $cutoff->getTimestamp() ) );
		}
		else
		{
			\IPS\DB::i()->delete( 'core_search_index' );
		}
	}
}