<?php
/**
 * @brief		Database Storage Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		07 May 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Data\Store;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Database Storage Class
 */
class _Database extends \IPS\Data\Store
{
	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		return TRUE;
	}

	/**
	 * @brief	Always needed Store keys
	 */
	public $initLoad = array( 'cacheKeys', 'settings', 'storageConfigurations', 'themes', 'languages', 'groups', 'applications', 'modules', 'widgets', 'furl', 'javascript_map', 'metaTags', 'bannedIpAddresses', 'license_data' );
		
	/**
	 * @brief	Have we done the intitial load?
	 */
	protected $doneInitLoad = FALSE;
		
	/**
	 * @brief	Cache
	 */
	protected static $cache = array();
	
	/**
	 * Constructor
	 * Gets stores which are always needed to save individual queries
	 *
	 * @return	void
	 */
	public function __construct()
	{		
		if ( \IPS\Session\Front::loggedIn() )
		{
			$this->initLoad[] = 'administrators';
			$this->initLoad[] = 'moderators';
			$this->initLoad[] = 'emoticons';
		}
		else
		{
			$this->initLoad[] = 'loginHandlers';
		}
	}
	
	/**
	 * Load mutiple
	 * Used so if it is known that several are going to be needed, they can all be loaded into memory at the same time
	 *
	 * @param	array	$keys	Keys
	 * @return	void
	 */
	public function loadIntoMemory( array $keys )
	{
		foreach ( \IPS\Db::i()->select( '*', 'core_store', \IPS\Db::i()->in( 'store_key', $keys ) ) as $row )
		{
			static::$cache[ $row['store_key'] ] = $row['store_value'];
		}
	}

	/**
	 * Abstract Method: Get
	 *
	 * @param	string	$key	Key
	 * @return	string	Value from the datastore
	 */
	public function get( $key )
	{
		if ( !$this->doneInitLoad and in_array( $key, $this->initLoad ) )
		{
			$this->loadIntoMemory( $this->initLoad );
			$this->doneInitLoad = TRUE;
		}
		
		if ( !isset( static::$cache[ $key ] ) )
		{			
			static::$cache[ $key ] = \IPS\Db::i()->select( 'store_value', 'core_store', array( 'store_key=?', $key ) )->first();
		}
		
		return static::$cache[ $key ];
	}
	
	/**
	 * Abstract Method: Set
	 *
	 * @param	string	$key	Key
	 * @param	string	$value	Value
	 * @return	bool
	 */
	public function set( $key, $value )
	{
		\IPS\Db::i()->replace( 'core_store', array(
			'store_key'		=> $key,
			'store_value'	=> $value
		) );
		return TRUE;
	}
	
	/**
	 * Abstract Method: Exists?
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	public function exists( $key )
	{
		if ( isset( static::$cache[ $key ] ) )
		{
			return TRUE;
		}
		else
		{
			try
			{
				$this->get( $key );
				return TRUE;
			}
			catch ( \UnderflowException $e )
			{
				return FALSE;
			}
		}
	}
	
	/**
	 * Abstract Method: Delete
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	public function delete( $key )
	{
		\IPS\Db::i()->delete( 'core_store', array( 'store_key=?', $key ) );
		return TRUE;
	}
	
	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll( $exclude=NULL )
	{
		$where = array();
		if( $exclude !== NULL )
		{
			$where[] = array( 'store_key != ?', $exclude );
		}
		\IPS\Db::i()->delete( 'core_store', $where );
	}
}