<?php
/**
 * @brief		APC Caching Class
 * @author		<a href='http://www.invisionpower.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) 2001 - SVN_YYYY Invision Power Services, Inc.
 * @license		http://www.invisionpower.com/legal/standards/
 * @package		IPS Social Suite
 * @since		17 Sept 2013
 * @version		SVN_VERSION_NUMBER
 */

namespace IPS\Data\Cache;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * APC Storage Class
 */
class _Apc extends \IPS\Data\Cache
{
	/**
	 * Server supports this method?
	 *
	 * @return	bool
	 */
	public static function supported()
	{
		return function_exists('apc_fetch');
	}
	
	/**
	 * Abstract Method: Get
	 *
	 * @param	string	$key	Key
	 * @return	string	Value from the _datastore
	 */
	protected function get( $key )
	{
		if( isset( $this->cache[ $key ] ) )
		{
			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Get {$key} from APC (already loaded)", 'cache' );
			return $this->cache[ $key ];
		}

		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Get {$key} from APC", 'cache' );
		
		$this->cache[ $key ]	= apc_fetch( \IPS\SUITE_UNIQUE_KEY . '_' . $key );
		return $this->cache[ $key ];
	}
	
	/**
	 * Abstract Method: Set
	 *
	 * @param	string	$key	Key
	 * @param	string	$value	Value
	 * @return	bool
	 */
	protected function set( $key, $value )
	{
		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Set {$key} in APC", 'cache' );
		return (bool) apc_store( \IPS\SUITE_UNIQUE_KEY . '_' . $key, $value );
	}
	
	/**
	 * Abstract Method: Exists?
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function exists( $key )
	{
		if( isset( $this->cache[ $key ] ) )
		{
			\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Check exists {$key} from APC (already loaded)", 'cache' );
			return TRUE;
		}

		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Check exists {$key} from APC", 'cache' );

		$result = apc_fetch( $key, $exists );
		if ( $exists )
		{
			$this->cache[ $key ] = $result;
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	/**
	 * Abstract Method: Delete
	 *
	 * @param	string	$key	Key
	 * @return	bool
	 */
	protected function delete( $key )
	{
		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Delete {$key} from APC", 'cache' );
		return (bool) apc_delete( \IPS\SUITE_UNIQUE_KEY . '_' . $key );
	}
	
	/**
	 * Abstract Method: Clear All Caches
	 *
	 * @return	void
	 */
	public function clearAll()
	{
		\IPS\Log::i( LOG_DEBUG )->setIpAddress( $_SERVER['REMOTE_ADDR'] )->write( "Flush all caches from APC", 'cache' );
		apc_clear_cache();
	}
}