<?php
/**
 * @brief		Sphinx Search Index
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
 * Sphinx Search Index
 */
class _Index extends \IPS\Content\Search\Index
{
	/**
	 * @brief	The index table
	 */
	protected $table;
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
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
	}
	
	/**
	 * Index an item
	 *
	 * @param	\IPS\Content\Searchable	$object	Item to add
	 * @return	void
	 */
	public function index( \IPS\Content\Searchable $object )
	{
		$indexData = $this->indexData( $object );
		if ( $indexData )
		{
			$data = array_merge( array( 'id' => static::_getId( $object ), 'index_class_id' => static::_getClassId( get_class( $object ) ) ), $indexData );
			$data['index_permissions'] = explode( ',', str_replace( '*', 0, $data['index_permissions'] ) );
	
			if( !$data['index_permissions'] )
			{
				$this->removeFromSearchIndex( $object );
			}
			else
			{
				$result = \IPS\Db::i('sphinx')->query( "REPLACE INTO {$this->table} ( " . implode( ', ', array_keys( $data ) ) . ' ) VALUES ( ' . implode( ', ', array_map( array( $this, '_escapeValue' ), $data ) ) . ' )' );
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
		\IPS\Db::i('sphinx')->query( "DELETE FROM {$this->table} WHERE id=" . static::_getId( $object ) );
		
		$class = get_class( $object );
		$idColumn = $class::$databaseColumnId;
		if ( isset( $class::$commentClass ) )
		{
			$commentClass = $class::$commentClass;
			
			foreach ( \IPS\Db::i()->select( $commentClass::$databasePrefix . $commentClass::$databaseColumnId, $commentClass::$databaseTable, array( $commentClass::$databasePrefix . $commentClass::$databaseColumnMap['item'] . '=?', $object->$idColumn ) ) as $commentid )
			{
				\IPS\Db::i('sphinx')->query( "DELETE FROM {$this->table} WHERE id=" . static::_getId( $commentid, $commentClass ) );
			}
		}
		
		if ( isset( $class::$reviewClass ) )
		{
			$reviewClass = $class::$reviewClass;
			
			foreach ( \IPS\Db::i()->select( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnId, $reviewClass::$databaseTable, array( $reviewClass::$databasePrefix . $reviewClass::$databaseColumnMap['item'] . '=?', $object->$idColumn ) ) as $reviewid )
			{
				\IPS\Db::i('sphinx')->query( "DELETE FROM {$this->table} WHERE id=" . static::_getId( $reviewid, $reviewClass ) );
			}
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
		$where = array( 'index_class_id=' . static::_getClassId( $class ) );
		if ( $containerId !== NULL )
		{
			$where[] = "index_container_id={$containerId}";
		}
		
		$where = implode( ' AND ', $where );
		
		\IPS\Db::i('sphinx')->query( "DELETE FROM {$this->table} WHERE {$where}" );
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
		$where = array( 'index_class_id=' . static::_getClassId( $class ) );
		if ( $containerId !== NULL )
		{
			$where[] = "index_container_id={$containerId}";
		}
		if ( $itemId !== NULL )
		{
			$where[] = "index_item_id={$itemId}";
		}

		$update = array();
		if ( $newPermissions !== NULL )
		{
			$update['index_permissions'] = explode( ',', str_replace( '*', 0, $newPermissions ) );
		}
		if ( $newHiddenStatus !== NULL )
		{
			if ( $newHiddenStatus === 2 )
			{
				$where[] = 'index_hidden=0';
			}
			else
			{
				$where[] = 'index_hidden=2';
			}
			
			$update['index_hidden'] = $newHiddenStatus;
		}
		if ( $newContainer )
		{
			$update['index_container_id'] = $newContainer;
		}
		
		$set = array();
		foreach ( $update as $k => $v )
		{
			$set[] = "{$k}={$this->_escapeValue($v)}";
		}
		$set = implode( ', ', $set );
		
		$where = implode( ' AND ', $where );
				
		try
		{
			\IPS\Db::i('sphinx')->query( "UPDATE {$this->table} SET {$set} WHERE {$where}" );
		}
		catch ( \Exception $e )
		{
			echo '<pre>';
			print_r( $e );
			exit;
		}
	}
	
	/**
	 * Prune search index
	 *
	 * @param	\IPS\DateTime|NULL	$cutoff	The date to delete index records from, or NULL to delete all
	 * @return	void
	 */
	public function prune( \IPS\DateTime $cutoff = NULL )
	{
		if ( $cutoff === NULL )
		{
			\IPS\Db::i('sphinx')->query( "TRUNCATE RTINDEX {$this->table}" );
		}
		else
		{
			throw new \UnexpectedValueException;
		}
	}
	
	/**
	 * Get an ID for an object
	 *
	 * @param	\IPS\Content\Searchable|int	$object	Item to get ID for
	 * @param	string|NULL					$class 	If $object is an ID rather than an object, provide the class
	 * @return	int
	 */
	public static function _getId( $object, $class = NULL )
	{		
		if ( is_object( $object ) )
		{
			$class = get_class( $object );
			$idColumn = $class::$databaseColumnId;
			$id = $object->$idColumn;
		}
		else
		{
			$id = $object;
		}
		
		return ( static::_getClassId( $class ) * 100000000 ) + $id;
	}
	
	/**
	 * Get an ID for an class
	 *
	 * @param	string	$class 	The class
	 * @return	int
	 */
	public static function _getClassId( $class )
	{
		if ( isset( \IPS\Data\Store::i()->sphinxIdMap ) )
		{
			$map = \IPS\Data\Store::i()->sphinxIdMap;
			if ( !in_array( $class, $map ) )
			{
				$map[] = $class;
				\IPS\Data\Store::i()->sphinxIdMap = $map;
			}
		}
		else
		{
			$map = array( 1 => $class );
			\IPS\Data\Store::i()->sphinxIdMap = $map;
		}
		
		return intval( array_search( $class, $map ) );
	}
	
	/**
	 * Escape a value for the query (SphinxQL doesn't support prepared statements)
	 *
	 * @param	mixed	$val	The value
	 * @return	mixed
	 */
	protected function _escapeValue( $val )
	{
		if ( is_numeric( $val ) )
		{
			return $val;
		}
		elseif ( is_array( $val ) )
		{
			return '(' . implode( ',', array_map( array( $this, '_escapeValue' ), $val ) ) . ')';
		}
		else
		{
			return ( '\'' . \IPS\Db::i( 'sphinx' )->real_escape_string( $val ) . '\'' );
		}
	}
}